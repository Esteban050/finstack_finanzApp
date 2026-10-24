<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\FuenteIngreso;
use App\Models\Ingreso;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Services\BitacoraService;
use App\Services\IngresoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * MOD-03 — Ingresos: RF-021 (registro), RF-023 (edición y eliminación) y
 * RF-024 (consulta y filtrado).
 *
 * El aislamiento de datos (RNG-01) lo garantiza el global scope
 * DelUsuarioAutenticado sobre el modelo Ingreso.
 */
class IngresoController extends Controller
{
    /** RNG-10: Tamaños de página admitidos. */
    private const POR_PAGINA = [10, 20, 50, 100];

    /**
     * RF-024: Listado de ingresos con filtros, ordenamiento y totales.
     */
    public function index(Request $request): View|JsonResponse
    {
        $porPagina = (int) $request->query('por_pagina', 20);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 20;
        }

        $base = $this->consultaFiltrada($request);

        // RN-024.3: los totales corresponden al conjunto filtrado completo, no
        // solo a la página visible, así que se agregan en una consulta aparte.
        //
        // RN-022.3: una ocurrencia PENDIENTE no entra en los cálculos hasta que
        // el usuario la confirme. Se totaliza solo lo CONFIRMADO y los pendientes
        // se informan por separado para que nada quede oculto.
        $totales = (clone $base)->confirmados()->selectRaw('
            COUNT(*) AS cantidad,
            COALESCE(SUM(monto_bruto_cop), 0) AS bruto,
            COALESCE(SUM(retencion_fuente_cop), 0) AS retencion,
            COALESCE(SUM(aportes_descontados_cop), 0) AS aportes,
            COALESCE(SUM(monto_neto_cop), 0) AS neto
        ')->first();

        $pendientes = (clone $base)->where('estado', 'PENDIENTE')->selectRaw('
            COUNT(*) AS cantidad,
            COALESCE(SUM(monto_bruto_cop), 0) AS bruto
        ')->first();

        // RNG-10: por defecto se ordenan por fecha, del más reciente al más antiguo.
        $ingresos = $base->with(['fuente.tipoFuente'])
            ->orderByDesc('fecha_recepcion')
            ->orderByDesc('creado_en')
            ->paginate($porPagina)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Ingresos consultados.',
                'data' => [
                    'ingresos' => $ingresos->items(),
                    'meta' => [
                        'pagina_actual' => $ingresos->currentPage(),
                        'por_pagina' => $ingresos->perPage(),
                        'total' => $ingresos->total(),
                        'ultima_pagina' => $ingresos->lastPage(),
                    ],
                    'totales' => $totales,
                    'pendientes' => $pendientes,
                ],
            ]);
        }

        return view('ingresos.index', [
            'ingresos' => $ingresos,
            'totales' => $totales,
            'pendientes' => $pendientes,
            'fuentes' => FuenteIngreso::orderBy('nombre')->get(),
            'tiposFuente' => $this->catalogoTiposFuente(),
            'clasificaciones' => FuenteIngreso::CLASIFICACIONES,
            'porPaginaOpciones' => self::POR_PAGINA,
            'porPagina' => $porPagina,
        ]);
    }

    /**
     * RF-021: Formulario de registro de ingreso.
     */
    public function create(): View
    {
        return view('ingresos.create', [
            // RN-020.3: una fuente inactiva no aparece al registrar ingresos nuevos.
            'fuentes' => FuenteIngreso::activas()->with('tipoFuente')->orderBy('nombre')->get(),
            'clasificaciones' => FuenteIngreso::CLASIFICACIONES,
        ]);
    }

    /**
     * RF-021: Registrar un ingreso asociado a una fuente.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate($this->reglas(), $this->mensajes());

        $fuente = $this->fuenteActivaPropia($validated['fuente_id']);
        $montos = IngresoService::prepararMontos($validated, $fuente);

        $ingreso = Ingreso::create(array_merge($montos, [
            'usuario_id' => Auth::id(),
            'fuente_id' => $fuente->id,
            'fecha_recepcion' => $validated['fecha_recepcion'],
            'origen' => 'MANUAL',
            'estado' => 'CONFIRMADO',
            'descripcion' => $validated['descripcion'] ?? null,
        ]));

        // monto_neto_cop es una columna generada por Postgres (RN-021.2): hay que
        // releerla para devolverla en la respuesta.
        $ingreso->refresh();

        BitacoraService::registrar(
            accion: 'CREAR_INGRESO',
            entidadAfectada: 'ingresos',
            entidadId: $ingreso->id,
            valorNuevo: $ingreso->toArray(),
            usuarioId: Auth::id()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Ingreso registrado exitosamente.',
                'data' => $ingreso->load('fuente'),
            ], 201);
        }

        return redirect()->route('ingresos.index')
            ->with('status', 'Ingreso registrado exitosamente.');
    }

    /**
     * RF-023: Formulario de edición. Con Accept: application/json devuelve el
     * registro, que es lo que consume la colección de Postman.
     */
    public function edit(Request $request, string $id): View|JsonResponse
    {
        $ingreso = $this->ingresoPropio($id);

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Ingreso consultado.',
                'data' => $ingreso->load('fuente.tipoFuente'),
            ]);
        }

        return view('ingresos.edit', [
            'ingreso' => $ingreso,
            'fuentes' => FuenteIngreso::activas()->with('tipoFuente')->orderBy('nombre')->get(),
            'clasificaciones' => FuenteIngreso::CLASIFICACIONES,
        ]);
    }

    /**
     * RF-023 RN-023.1: Se pueden editar todos los campos de RF-021, con las
     * mismas validaciones.
     */
    public function update(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $ingreso = $this->ingresoPropio($id);
        $validated = $request->validate($this->reglas(), $this->mensajes());

        $fuente = $this->fuenteActivaPropia($validated['fuente_id']);
        $montos = IngresoService::prepararMontos($validated, $fuente);

        $anterior = $ingreso->toArray();

        $ingreso->update(array_merge($montos, [
            'fuente_id' => $fuente->id,
            'fecha_recepcion' => $validated['fecha_recepcion'],
            'descripcion' => $validated['descripcion'] ?? null,
        ]));

        $ingreso->refresh();

        BitacoraService::registrar(
            accion: 'EDITAR_INGRESO',
            entidadAfectada: 'ingresos',
            entidadId: $ingreso->id,
            valorAnterior: $anterior,
            valorNuevo: $ingreso->toArray(),
            // RN-023.3: los cálculos guardados de seguridad social y renta del
            // periodo quedan desactualizados. MOD-05 y MOD-07 no existen todavía,
            // así que por ahora el efecto solo se deja registrado en bitácora.
            justificacion: 'RN-023.3: edición de ingreso; recalcular indicadores del periodo afectado.',
            usuarioId: Auth::id()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Ingreso actualizado.',
                'data' => $ingreso->load('fuente'),
            ]);
        }

        return redirect()->route('ingresos.index')
            ->with('status', 'Ingreso actualizado.');
    }

    /**
     * RF-023 RN-023.2 / RNG-07: La eliminación de registros financieros es lógica:
     * el registro deja de aparecer en consultas y cálculos, pero se conserva para
     * auditoría hasta que se suprima la cuenta (RF-010).
     */
    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $ingreso = $this->ingresoPropio($id);
        $anterior = $ingreso->toArray();

        $ingreso->delete();

        BitacoraService::registrar(
            accion: 'ELIMINAR_INGRESO',
            entidadAfectada: 'ingresos',
            entidadId: $id,
            valorAnterior: $anterior,
            justificacion: 'RN-023.3: eliminación de ingreso; recalcular indicadores del periodo afectado.',
            usuarioId: Auth::id()
        );

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => 'Ingreso eliminado.']);
        }

        return redirect()->route('ingresos.index')
            ->with('status', 'Ingreso eliminado.');
    }

    /**
     * RF-022 RN-022.3: Confirmar una ocurrencia generada en modo "Requiere
     * confirmación", para que entre en los cálculos.
     */
    public function confirmar(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $ingreso = $this->ingresoPropio($id);

        if (! $ingreso->estaPendiente()) {
            throw ValidationException::withMessages([
                'estado' => 'Este ingreso ya está confirmado.',
            ]);
        }

        $ingreso->update(['estado' => 'CONFIRMADO']);

        BitacoraService::registrar(
            accion: 'CONFIRMAR_INGRESO_RECURRENTE',
            entidadAfectada: 'ingresos',
            entidadId: $ingreso->id,
            valorNuevo: ['estado' => 'CONFIRMADO'],
            usuarioId: Auth::id()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Ingreso confirmado.',
                'data' => $ingreso->fresh(),
            ]);
        }

        return back()->with('status', 'Ingreso confirmado.');
    }

    /**
     * RF-024 RN-024.1: Filtros combinables sobre el listado de ingresos.
     */
    private function consultaFiltrada(Request $request): Builder
    {
        $filtros = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'fuente_id' => ['nullable', 'uuid'],
            'tipo_fuente_id' => ['nullable', 'uuid'],
            'clasificacion_tributaria' => ['nullable', Rule::in(FuenteIngreso::CLASIFICACIONES)],
            'moneda' => ['nullable', 'string', 'size:3'],
            'origen' => ['nullable', Rule::in(Ingreso::ORIGENES)],
            'estado' => ['nullable', Rule::in(Ingreso::ESTADOS)],
            'monto_min' => ['nullable', 'numeric', 'min:0'],
            'monto_max' => ['nullable', 'numeric', 'min:0'],
        ], [
            'fecha_desde.date' => 'La fecha inicial del filtro no tiene un formato válido.',
            'fecha_hasta.date' => 'La fecha final del filtro no tiene un formato válido.',
            'fecha_hasta.after_or_equal' => 'La fecha final del filtro no puede ser anterior a la inicial.',
            'fuente_id.uuid' => 'La fuente de ingreso del filtro no es válida.',
            'tipo_fuente_id.uuid' => 'El tipo de fuente del filtro no es válido.',
            'clasificacion_tributaria.in' => 'La clasificación tributaria del filtro debe ser una de las cinco cédulas del artículo 330 del Estatuto Tributario.',
            'moneda.string' => 'La moneda del filtro debe ser texto.',
            'moneda.size' => 'La moneda del filtro debe tener 3 letras, como COP, USD o EUR.',
            'origen.in' => 'El origen del filtro debe ser MANUAL, RECURRENTE o IMPORTADO.',
            'estado.in' => 'El estado del filtro debe ser CONFIRMADO o PENDIENTE.',
            'monto_min.numeric' => 'El monto mínimo del filtro debe ser un número.',
            'monto_min.min' => 'El monto mínimo del filtro no puede ser negativo.',
            'monto_max.numeric' => 'El monto máximo del filtro debe ser un número.',
            'monto_max.min' => 'El monto máximo del filtro no puede ser negativo.',
        ]);

        return Ingreso::query()
            ->when($filtros['fecha_desde'] ?? null, fn ($q, $v) => $q->where('fecha_recepcion', '>=', $v))
            ->when($filtros['fecha_hasta'] ?? null, fn ($q, $v) => $q->where('fecha_recepcion', '<=', $v))
            ->when($filtros['fuente_id'] ?? null, fn ($q, $v) => $q->where('fuente_id', $v))
            ->when($filtros['tipo_fuente_id'] ?? null, fn ($q, $v) => $q->whereHas(
                'fuente',
                fn ($f) => $f->where('tipo_fuente_id', $v)
            ))
            ->when($filtros['clasificacion_tributaria'] ?? null, fn ($q, $v) => $q->where('clasificacion_tributaria', $v))
            ->when($filtros['moneda'] ?? null, fn ($q, $v) => $q->where('moneda', strtoupper($v)))
            ->when($filtros['origen'] ?? null, fn ($q, $v) => $q->where('origen', $v))
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filtros['monto_min'] ?? null, fn ($q, $v) => $q->where('monto_bruto_cop', '>=', $v))
            ->when($filtros['monto_max'] ?? null, fn ($q, $v) => $q->where('monto_bruto_cop', '<=', $v));
    }

    /**
     * RN-021.1: Reglas de validación del ingreso.
     *
     * Los montos se validan con una expresión regular además de `numeric` para
     * descartar notación científica y excesos de decimales: así el valor llega a
     * bcmath como una cadena decimal plana, sin pasar por punto flotante (RNG-02),
     * y encaja exactamente en NUMERIC(18,2).
     */
    private function reglas(): array
    {
        return [
            'fuente_id' => ['required', 'uuid'],
            // CA-021.3: la fecha de recepción no puede ser posterior a la actual.
            'fecha_recepcion' => ['required', 'date', 'before_or_equal:today'],
            // RNG-09: mayor que 0 y menor o igual a 999.999.999.999,99.
            'monto_original' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'moneda' => ['required', 'string', 'size:3', 'alpha'],
            'tasa_cambio_usada' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,8}(\.\d{1,4})?$/'],
            'retencion_fuente' => ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'aportes_descontados' => ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            // RN-021.4: si no se envía, se hereda la clasificación de la fuente.
            'clasificacion_tributaria' => ['nullable', Rule::in(FuenteIngreso::CLASIFICACIONES)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * RN-021.1: Mensaje propio para cada regla. El proyecto no tiene archivos de
     * traducción, así que una regla sin mensaje acá sale al usuario como la clave
     * cruda (`validation.required`).
     */
    private function mensajes(): array
    {
        return [
            'fuente_id.required' => 'Seleccione la fuente de ingreso.',
            'fuente_id.uuid' => 'La fuente de ingreso seleccionada no es válida.',
            'fecha_recepcion.required' => 'Indique la fecha en que recibió el ingreso.',
            'fecha_recepcion.date' => 'La fecha de recepción no tiene un formato válido.',
            // CA-021.3: se rechaza con la sugerencia de crear un ingreso recurrente.
            'fecha_recepcion.before_or_equal' => 'La fecha de recepción no puede ser futura. Para un ingreso que aún no recibe, cree un ingreso recurrente o programado (RF-022).',
            'monto_original.required' => 'Indique el monto del ingreso.',
            'monto_original.numeric' => 'El monto del ingreso debe ser un número.',
            'monto_original.gt' => 'El monto del ingreso debe ser mayor que cero.',
            'monto_original.regex' => 'El monto admite hasta 12 dígitos enteros y 2 decimales, con punto como separador decimal.',
            'moneda.required' => 'Indique la moneda del ingreso.',
            'moneda.string' => 'La moneda debe ser texto.',
            'moneda.size' => 'La moneda debe tener 3 letras, como COP, USD o EUR.',
            'moneda.alpha' => 'La moneda solo admite letras, como COP, USD o EUR.',
            'tasa_cambio_usada.numeric' => 'La tasa de cambio debe ser un número.',
            'tasa_cambio_usada.gt' => 'La tasa de cambio debe ser mayor que cero.',
            'tasa_cambio_usada.regex' => 'La tasa de cambio admite hasta 8 dígitos enteros y 4 decimales.',
            'retencion_fuente.numeric' => 'La retención en la fuente debe ser un número.',
            'retencion_fuente.min' => 'La retención en la fuente no puede ser negativa.',
            'retencion_fuente.regex' => 'La retención admite hasta 12 dígitos enteros y 2 decimales.',
            'aportes_descontados.numeric' => 'Los aportes descontados deben ser un número.',
            'aportes_descontados.min' => 'Los aportes descontados no pueden ser negativos.',
            'aportes_descontados.regex' => 'Los aportes admiten hasta 12 dígitos enteros y 2 decimales.',
            'clasificacion_tributaria.in' => 'La clasificación tributaria debe ser una de las cinco cédulas del artículo 330 del Estatuto Tributario.',
            'descripcion.string' => 'La descripción debe ser texto.',
            'descripcion.max' => 'La descripción no puede superar los 255 caracteres.',
        ];
    }

    /**
     * RN-021.1 / RN-020.3: El ingreso se asocia a una fuente propia y activa.
     */
    private function fuenteActivaPropia(string $fuenteId): FuenteIngreso
    {
        $fuente = FuenteIngreso::activas()->find($fuenteId);

        if ($fuente === null) {
            throw ValidationException::withMessages([
                'fuente_id' => 'La fuente de ingreso no existe o está inactiva.',
            ]);
        }

        return $fuente;
    }

    private function catalogoTiposFuente()
    {
        return Categoria::where('tipo', 'INGRESO')
            ->where('es_predeterminada', true)
            ->whereNull('categoria_padre_id')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * RNG-01: El global scope ya devolvió 404 para recursos ajenos. Aquí solo se
     * distingue el caso que la regla manda registrar en bitácora (RF-019).
     */
    private function ingresoPropio(string $id): Ingreso
    {
        $ingreso = Ingreso::find($id);

        if ($ingreso !== null) {
            return $ingreso;
        }

        $esDeOtro = Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereKey($id)
            ->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'ingresos',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }
}
