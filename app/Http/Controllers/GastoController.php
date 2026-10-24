<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Services\BitacoraService;
use App\Services\CategoriaGastoService;
use App\Services\GastoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * MOD-04 — Gastos: RF-025 (registro), RF-026 (clasificación multidimensional) y
 * RF-029 (edición, eliminación y consulta).
 *
 * El aislamiento de datos (RNG-01) lo garantiza el global scope
 * DelUsuarioAutenticado sobre el modelo Gasto.
 */
class GastoController extends Controller
{
    /** RNG-10: Tamaños de página admitidos. */
    private const POR_PAGINA = [10, 20, 50, 100];

    /** RN-029.3: "se pueden seleccionar hasta 100 gastos". */
    private const MAX_EDICION_MASIVA = 100;

    /**
     * RF-029: Listado de gastos con filtros, ordenamiento y totales.
     */
    public function index(Request $request): View|JsonResponse
    {
        $porPagina = (int) $request->query('por_pagina', 20);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 20;
        }

        $base = $this->consultaFiltrada($request);

        // CA-029.1 / RN-024.3: los totales corresponden al conjunto filtrado
        // completo, no solo a la página visible, así que se agregan aparte.
        //
        // CA-028.2: un gasto con monto estimado no entra en la ejecución
        // presupuestal hasta confirmarse. Se totaliza solo lo CONFIRMADO y los
        // pendientes se informan por separado para que nada quede oculto.
        $totales = (clone $base)->confirmados()->selectRaw("
            COUNT(*) AS cantidad,
            COALESCE(SUM(monto_cop), 0) AS monto,
            COALESCE(SUM(CASE WHEN medio_pago = 'TARJETA_CREDITO' THEN monto_cop ELSE 0 END), 0) AS tarjeta_credito,
            COALESCE(SUM(CASE WHEN relevancia_tributaria = 'POTENCIALMENTE_DEDUCIBLE' THEN monto_cop ELSE 0 END), 0) AS potencialmente_deducible
        ")->first();

        $pendientes = (clone $base)->where('estado', 'PENDIENTE')->selectRaw('
            COUNT(*) AS cantidad,
            COALESCE(SUM(monto_cop), 0) AS monto
        ')->first();

        // RNG-10: por defecto se ordenan por fecha, del más reciente al más antiguo.
        $gastos = $base->with(['categoria', 'subcategoria'])
            ->orderByDesc('fecha')
            ->orderByDesc('creado_en')
            ->paginate($porPagina)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Gastos consultados.',
                'data' => [
                    'gastos' => $gastos->items(),
                    'meta' => [
                        'pagina_actual' => $gastos->currentPage(),
                        'por_pagina' => $gastos->perPage(),
                        'total' => $gastos->total(),
                        'ultima_pagina' => $gastos->lastPage(),
                    ],
                    'totales' => $totales,
                    'pendientes' => $pendientes,
                ],
            ]);
        }

        return view('gastos.index', [
            'gastos' => $gastos,
            'totales' => $totales,
            'pendientes' => $pendientes,
            'categorias' => CategoriaGastoService::catalogo(Auth::id()),
            'etiquetasUsadas' => GastoService::etiquetasUsadas(),
            'dimensiones' => $this->dimensiones(),
            'porPaginaOpciones' => self::POR_PAGINA,
            'porPagina' => $porPagina,
        ]);
    }

    /**
     * RF-025: Formulario de registro de gasto.
     *
     * CA-026.1: el catálogo trae `naturaleza_sugerida` y `necesidad_sugerida` de
     * cada subcategoría, que son los valores que el formulario precarga al
     * elegirla (RN-026.2).
     */
    public function create(Request $request): View|JsonResponse
    {
        $datos = [
            // RN-027.2: una categoría inactiva no aparece al registrar gastos nuevos.
            'categorias' => CategoriaGastoService::catalogo(Auth::id(), soloActivas: true),
            'dimensiones' => $this->dimensiones(),
            // RN-026.1 (6): las etiquetas son reutilizables entre gastos.
            'etiquetasUsadas' => GastoService::etiquetasUsadas(),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Catálogo de clasificación consultado.',
                'data' => $datos,
            ]);
        }

        return view('gastos.create', $datos);
    }

    /**
     * RF-025: Registrar un gasto con su monto, fecha, clasificación (RF-026) y
     * medio de pago.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate($this->reglas(), $this->mensajes());

        $atributos = $this->prepararAtributos($validated);

        $gasto = Gasto::create(array_merge($atributos, [
            'usuario_id' => Auth::id(),
            'fecha' => $validated['fecha'],
            'origen' => 'MANUAL',
            'estado' => 'CONFIRMADO',
            'comercio_beneficiario' => $validated['comercio_beneficiario'] ?? null,
            'descripcion' => $validated['descripcion'] ?? null,
        ]));

        BitacoraService::registrar(
            accion: 'CREAR_GASTO',
            entidadAfectada: 'gastos',
            entidadId: $gasto->id,
            valorNuevo: $gasto->toArray(),
            // RN-025.3 / RN-025.4: el pago de cuota de crédito (RF-050), la
            // ejecución presupuestal (RF-056) y las alertas (RF-057) no están
            // especificados en la versión 1.0. El efecto queda registrado.
            justificacion: 'RN-025.4: actualizar la ejecución del presupuesto de la categoría y evaluar alertas.',
            usuarioId: Auth::id()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Gasto registrado exitosamente.',
                'data' => $gasto->load(['categoria', 'subcategoria']),
            ], 201);
        }

        return redirect()->route('gastos.index')
            ->with('status', 'Gasto registrado exitosamente.');
    }

    /**
     * RF-029: Formulario de edición. Con Accept: application/json devuelve el
     * registro, que es lo que consume la colección de Postman.
     */
    public function edit(Request $request, string $id): View|JsonResponse
    {
        $gasto = $this->gastoPropio($id);

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Gasto consultado.',
                'data' => $gasto->load(['categoria', 'subcategoria']),
            ]);
        }

        return view('gastos.edit', [
            'gasto' => $gasto,
            'categorias' => CategoriaGastoService::catalogo(Auth::id(), soloActivas: true),
            'dimensiones' => $this->dimensiones(),
            'etiquetasUsadas' => GastoService::etiquetasUsadas(),
        ]);
    }

    /**
     * RF-029 RN-029.1: Editar un gasto, con las mismas validaciones de RF-025 y
     * RF-026.
     */
    public function update(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $gasto = $this->gastoPropio($id);
        $validated = $request->validate($this->reglas(), $this->mensajes());

        $atributos = $this->prepararAtributos($validated);
        $anterior = $gasto->toArray();

        $gasto->update(array_merge($atributos, [
            'fecha' => $validated['fecha'],
            'comercio_beneficiario' => $validated['comercio_beneficiario'] ?? null,
            'descripcion' => $validated['descripcion'] ?? null,
        ]));

        BitacoraService::registrar(
            accion: 'EDITAR_GASTO',
            entidadAfectada: 'gastos',
            entidadId: $gasto->id,
            valorAnterior: $anterior,
            valorNuevo: $gasto->fresh()->toArray(),
            // RN-029.2: "Al editar o eliminar un gasto se recalcula la ejecución
            // del presupuesto del periodo afectado. Las alertas ya emitidas no se
            // borran, pero si el gasto deja de superar el umbral se muestran como
            // 'Resuelta'." RF-056 y RF-057 no están especificados: el efecto
            // queda registrado en bitácora.
            justificacion: 'RN-029.2: edición de gasto; recalcular la ejecución del presupuesto del periodo afectado.',
            usuarioId: Auth::id()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Gasto actualizado.',
                'data' => $gasto->fresh()->load(['categoria', 'subcategoria']),
            ]);
        }

        return redirect()->route('gastos.index')->with('status', 'Gasto actualizado.');
    }

    /**
     * RF-029 / RNG-07: La eliminación de registros financieros es lógica: el
     * registro deja de aparecer en consultas y cálculos, pero se conserva para
     * auditoría hasta que se suprima la cuenta (RF-010).
     */
    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $gasto = $this->gastoPropio($id);
        $anterior = $gasto->toArray();

        $gasto->delete();

        BitacoraService::registrar(
            accion: 'ELIMINAR_GASTO',
            entidadAfectada: 'gastos',
            entidadId: $id,
            valorAnterior: $anterior,
            justificacion: 'RN-029.2: eliminación de gasto; recalcular la ejecución del presupuesto del periodo afectado.',
            usuarioId: Auth::id()
        );

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => 'Gasto eliminado.']);
        }

        return redirect()->route('gastos.index')->with('status', 'Gasto eliminado.');
    }

    /**
     * RF-028 RN-028.2: "Para gastos de monto variable [...] la ocurrencia se
     * genera como 'Pendiente' con el monto estimado y el usuario debe confirmar
     * el monto real."
     *
     * Confirmar sin enviar monto deja el estimado como definitivo.
     */
    public function confirmar(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $gasto = $this->gastoPropio($id);

        if (! $gasto->estaPendiente()) {
            throw ValidationException::withMessages([
                'estado' => 'Este gasto ya está confirmado.',
            ]);
        }

        $validated = $request->validate([
            'monto_original' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'moneda' => ['nullable', 'string', 'size:3', 'alpha'],
            'tasa_cambio_usada' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,8}(\.\d{1,4})?$/'],
        ], $this->mensajes());

        $cambios = ['estado' => 'CONFIRMADO'];

        if (($validated['monto_original'] ?? null) !== null) {
            $cambios = array_merge($cambios, GastoService::prepararMontos([
                'moneda' => $validated['moneda'] ?? $gasto->moneda,
                'monto_original' => $validated['monto_original'],
                'tasa_cambio_usada' => $validated['tasa_cambio_usada'] ?? $gasto->tasa_cambio_usada,
            ]));
        }

        $anterior = $gasto->toArray();
        $gasto->update($cambios);

        BitacoraService::registrar(
            accion: 'CONFIRMAR_GASTO_RECURRENTE',
            entidadAfectada: 'gastos',
            entidadId: $gasto->id,
            valorAnterior: $anterior,
            valorNuevo: $cambios,
            justificacion: 'RN-028.2: confirmación del monto real de una ocurrencia con monto estimado.',
            usuarioId: Auth::id()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Gasto confirmado.',
                'data' => $gasto->fresh(),
            ]);
        }

        return back()->with('status', 'Gasto confirmado.');
    }

    /**
     * RF-029 RN-029.3: "Edición masiva: se pueden seleccionar hasta 100 gastos y
     * cambiarles la categoría, la naturaleza, la necesidad o las etiquetas en
     * una sola operación."
     */
    public function edicionMasiva(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_EDICION_MASIVA],
            'ids.*' => ['uuid'],
            'categoria_id' => ['nullable', 'uuid', 'required_with:subcategoria_id'],
            'subcategoria_id' => ['nullable', 'uuid', 'required_with:categoria_id'],
            'naturaleza' => ['nullable', Rule::in(Gasto::NATURALEZAS)],
            'necesidad' => ['nullable', Rule::in(Gasto::NECESIDADES)],
            'etiquetas' => ['nullable', 'array'],
        ], array_merge($this->mensajes(), [
            'ids.required' => 'Seleccione los gastos que quiere modificar.',
            'ids.array' => 'La selección de gastos debe enviarse como una lista.',
            'ids.min' => 'Seleccione al menos un gasto.',
            'ids.max' => 'La edición masiva admite como máximo '.self::MAX_EDICION_MASIVA.' gastos por operación.',
            'ids.*.uuid' => 'Alguno de los gastos seleccionados no es válido.',
            'categoria_id.required_with' => 'Para cambiar la clasificación indique la categoría y la subcategoría.',
            'subcategoria_id.required_with' => 'Para cambiar la clasificación indique la categoría y la subcategoría.',
        ]));

        $cambios = [];

        if (($validated['categoria_id'] ?? null) !== null) {
            // RN-026.1 (1): la jerarquía de dos niveles también se valida acá.
            GastoService::validarJerarquia(
                $validated['categoria_id'],
                $validated['subcategoria_id'],
                Auth::id()
            );

            $cambios['categoria_id'] = $validated['categoria_id'];
            $cambios['subcategoria_id'] = $validated['subcategoria_id'];
        }

        foreach (['naturaleza', 'necesidad'] as $campo) {
            if (($validated[$campo] ?? null) !== null) {
                $cambios[$campo] = $validated[$campo];
            }
        }

        if (array_key_exists('etiquetas', $validated)) {
            $cambios['etiquetas'] = GastoService::normalizarEtiquetas($validated['etiquetas']);
        }

        if ($cambios === []) {
            throw ValidationException::withMessages([
                'cambios' => 'Indique al menos un cambio: categoría y subcategoría, naturaleza, necesidad o etiquetas.',
            ]);
        }

        // RNG-01: el global scope restringe la selección a los gastos propios,
        // así que un id ajeno simplemente no entra en la operación.
        $modificados = DB::transaction(function () use ($validated, $cambios) {
            $gastos = Gasto::whereIn('id', $validated['ids'])->get();

            foreach ($gastos as $gasto) {
                $anterior = $gasto->toArray();
                $gasto->update($cambios);

                BitacoraService::registrar(
                    accion: 'EDITAR_GASTO_MASIVO',
                    entidadAfectada: 'gastos',
                    entidadId: $gasto->id,
                    valorAnterior: $anterior,
                    valorNuevo: $cambios,
                    justificacion: 'RN-029.3: edición masiva; recalcular la ejecución del presupuesto de los periodos afectados (RN-029.2).',
                    usuarioId: Auth::id()
                );
            }

            return $gastos->count();
        });

        $mensaje = "Se actualizaron {$modificados} gasto(s).";

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => $mensaje,
                'data' => [
                    'modificados' => $modificados,
                    'solicitados' => count($validated['ids']),
                    'cambios' => $cambios,
                ],
            ]);
        }

        return redirect()->route('gastos.index')->with('status', $mensaje);
    }

    /**
     * RF-025 RN-025.2: "Si el medio de pago es 'Tarjeta de crédito', el monto se
     * acumula en el indicador 'Consumos con tarjeta de crédito' del año
     * (RF-044). Todo gasto se acumula en 'Compras y consumos totales'."
     *
     * CA-025.2 se comprueba contra estos dos acumulados.
     */
    public function indicadores(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ], [
            'anio.integer' => 'El año debe ser un número entero.',
            'anio.min' => 'El año debe estar entre 2000 y 2100.',
            'anio.max' => 'El año debe estar entre 2000 y 2100.',
        ]);

        $anio = (int) ($validated['anio'] ?? now()->year);

        return response()->json([
            'mensaje' => 'Indicadores anuales de gasto consultados.',
            // RNG-08: la pantalla que muestre estos valores debe incluir el
            // aviso legal de cálculos.
            'aviso' => 'Valores estimados con fines informativos. No constituyen asesoría tributaria, contable ni financiera.',
            'data' => GastoService::indicadoresAnuales($anio),
        ]);
    }

    /**
     * RN-026.1 (6): Etiquetas ya usadas por el Usuario, para reutilizarlas.
     */
    public function etiquetas(Request $request): JsonResponse
    {
        return response()->json([
            'mensaje' => 'Etiquetas consultadas.',
            'data' => GastoService::etiquetasUsadas(),
        ]);
    }

    /**
     * RF-026 / RN-025.1: Convertir la petición validada en los atributos de las
     * seis dimensiones más los montos en pesos.
     *
     * @throws ValidationException
     */
    private function prepararAtributos(array $validated): array
    {
        $subcategoria = GastoService::validarJerarquia(
            $validated['categoria_id'],
            $validated['subcategoria_id'],
            Auth::id()
        );

        return array_merge(
            GastoService::prepararMontos($validated),
            GastoService::aplicarSugerencias($subcategoria, $validated),
            GastoService::validarRelevancia($validated),
            [
                'categoria_id' => $subcategoria->categoria_padre_id,
                'subcategoria_id' => $subcategoria->id,
                'medio_pago' => $validated['medio_pago'],
                'etiquetas' => GastoService::normalizarEtiquetas($validated['etiquetas'] ?? null),
            ]
        );
    }

    /**
     * RF-029 RN-029.1: "Filtros: rango de fechas, categoría, subcategoría,
     * naturaleza, necesidad, medio de pago, relevancia tributaria, etiqueta,
     * rango de montos y texto libre en descripción o comercio."
     *
     * CA-026.2: el listado se puede filtrar combinando cualquier dimensión, y
     * los totales corresponden exactamente al filtro.
     */
    private function consultaFiltrada(Request $request): Builder
    {
        $filtros = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'categoria_id' => ['nullable', 'uuid'],
            'subcategoria_id' => ['nullable', 'uuid'],
            'naturaleza' => ['nullable', Rule::in(Gasto::NATURALEZAS)],
            'necesidad' => ['nullable', Rule::in(Gasto::NECESIDADES)],
            'medio_pago' => ['nullable', Rule::in(Gasto::MEDIOS_PAGO)],
            'relevancia_tributaria' => ['nullable', Rule::in(Gasto::RELEVANCIAS)],
            'subtipo_deduccion' => ['nullable', Rule::in(Gasto::SUBTIPOS_DEDUCCION)],
            'etiqueta' => ['nullable', 'string', 'max:30'],
            'moneda' => ['nullable', 'string', 'size:3'],
            'origen' => ['nullable', Rule::in(Gasto::ORIGENES)],
            'estado' => ['nullable', Rule::in(Gasto::ESTADOS)],
            'monto_min' => ['nullable', 'numeric', 'min:0'],
            'monto_max' => ['nullable', 'numeric', 'min:0'],
            'texto' => ['nullable', 'string', 'max:255'],
        ], [
            'fecha_desde.date' => 'La fecha inicial del filtro no tiene un formato válido.',
            'fecha_hasta.date' => 'La fecha final del filtro no tiene un formato válido.',
            'fecha_hasta.after_or_equal' => 'La fecha final del filtro no puede ser anterior a la inicial.',
            'categoria_id.uuid' => 'La categoría del filtro no es válida.',
            'subcategoria_id.uuid' => 'La subcategoría del filtro no es válida.',
            'naturaleza.in' => 'La naturaleza del filtro debe ser FIJO o VARIABLE.',
            'necesidad.in' => 'La necesidad del filtro debe ser ESENCIAL o DISCRECIONAL.',
            'medio_pago.in' => 'El medio de pago del filtro no es válido.',
            'relevancia_tributaria.in' => 'La relevancia tributaria del filtro debe ser NO_DEDUCIBLE o POTENCIALMENTE_DEDUCIBLE.',
            'subtipo_deduccion.in' => 'El subtipo de deducción del filtro no es válido.',
            'etiqueta.max' => 'La etiqueta del filtro no puede superar los 30 caracteres.',
            'moneda.size' => 'La moneda del filtro debe tener 3 letras, como COP, USD o EUR.',
            'origen.in' => 'El origen del filtro debe ser MANUAL, RECURRENTE o IMPORTADO.',
            'estado.in' => 'El estado del filtro debe ser CONFIRMADO o PENDIENTE.',
            'monto_min.numeric' => 'El monto mínimo del filtro debe ser un número.',
            'monto_min.min' => 'El monto mínimo del filtro no puede ser negativo.',
            'monto_max.numeric' => 'El monto máximo del filtro debe ser un número.',
            'monto_max.min' => 'El monto máximo del filtro no puede ser negativo.',
            'texto.max' => 'El texto de búsqueda no puede superar los 255 caracteres.',
        ]);

        return Gasto::query()
            ->when($filtros['fecha_desde'] ?? null, fn ($q, $v) => $q->where('fecha', '>=', $v))
            ->when($filtros['fecha_hasta'] ?? null, fn ($q, $v) => $q->where('fecha', '<=', $v))
            ->when($filtros['categoria_id'] ?? null, fn ($q, $v) => $q->where('categoria_id', $v))
            ->when($filtros['subcategoria_id'] ?? null, fn ($q, $v) => $q->where('subcategoria_id', $v))
            ->when($filtros['naturaleza'] ?? null, fn ($q, $v) => $q->where('naturaleza', $v))
            ->when($filtros['necesidad'] ?? null, fn ($q, $v) => $q->where('necesidad', $v))
            ->when($filtros['medio_pago'] ?? null, fn ($q, $v) => $q->where('medio_pago', $v))
            ->when($filtros['relevancia_tributaria'] ?? null, fn ($q, $v) => $q->where('relevancia_tributaria', $v))
            ->when($filtros['subtipo_deduccion'] ?? null, fn ($q, $v) => $q->where('subtipo_deduccion', $v))
            // RN-026.1 (6): el filtro por etiqueta usa el índice GIN
            // idx_gastos_etiquetas_gin sobre el arreglo.
            ->when($filtros['etiqueta'] ?? null, fn ($q, $v) => $q->whereRaw(
                '? = ANY(etiquetas)',
                [mb_strtolower(trim($v))]
            ))
            ->when($filtros['moneda'] ?? null, fn ($q, $v) => $q->where('moneda', strtoupper($v)))
            ->when($filtros['origen'] ?? null, fn ($q, $v) => $q->where('origen', $v))
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filtros['monto_min'] ?? null, fn ($q, $v) => $q->where('monto_cop', '>=', $v))
            ->when($filtros['monto_max'] ?? null, fn ($q, $v) => $q->where('monto_cop', '<=', $v))
            // RN-029.1: texto libre en descripción o comercio.
            ->when($filtros['texto'] ?? null, fn ($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('descripcion', 'ILIKE', '%'.$v.'%')
                    ->orWhere('comercio_beneficiario', 'ILIKE', '%'.$v.'%');
            }));
    }

    /**
     * RN-025.1 / RN-026.1: Reglas de validación del gasto.
     *
     * Los montos se validan con una expresión regular además de `numeric` para
     * descartar notación científica y excesos de decimales: así el valor llega a
     * bcmath como una cadena decimal plana, sin pasar por punto flotante (RNG-02),
     * y encaja exactamente en NUMERIC(18,2).
     */
    private function reglas(): array
    {
        return [
            // RN-025.1: la fecha no puede ser posterior a la fecha actual.
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            // CA-025.1 / RNG-09: un gasto con monto 0 o negativo se rechaza.
            'monto_original' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'moneda' => ['required', 'string', 'size:3', 'alpha'],
            'tasa_cambio_usada' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,8}(\.\d{1,4})?$/'],
            // RN-026.1 (1): jerarquía de dos niveles, obligatoria.
            'categoria_id' => ['required', 'uuid'],
            'subcategoria_id' => ['required', 'uuid'],
            // RN-026.1 (4): medio de pago, obligatorio.
            'medio_pago' => ['required', Rule::in(Gasto::MEDIOS_PAGO)],
            // RN-026.1 (2) y (3): si no se envían, se toma la sugerencia de la
            // subcategoría (RN-026.2).
            'naturaleza' => ['nullable', Rule::in(Gasto::NATURALEZAS)],
            'necesidad' => ['nullable', Rule::in(Gasto::NECESIDADES)],
            // RN-026.1 (5): por defecto "No deducible".
            'relevancia_tributaria' => ['nullable', Rule::in(Gasto::RELEVANCIAS)],
            'subtipo_deduccion' => ['nullable', Rule::in(Gasto::SUBTIPOS_DEDUCCION)],
            // RN-026.1 (6): el detalle de longitud y cantidad lo valida
            // GastoService para poder nombrar el límite (CA-026.3).
            'etiquetas' => ['nullable', 'array'],
            'comercio_beneficiario' => ['nullable', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * RN-025.1: Mensaje propio para cada regla. El proyecto no tiene archivos de
     * traducción, así que una regla sin mensaje acá sale al usuario como la clave
     * cruda (`validation.required`).
     */
    private function mensajes(): array
    {
        return [
            'fecha.required' => 'Indique la fecha del gasto.',
            'fecha.date' => 'La fecha del gasto no tiene un formato válido.',
            'fecha.before_or_equal' => 'La fecha del gasto no puede ser futura. Para un gasto que aún no ocurre, cree un gasto recurrente (RF-028).',
            'monto_original.required' => 'Indique el monto del gasto.',
            'monto_original.numeric' => 'El monto del gasto debe ser un número.',
            'monto_original.gt' => 'El monto del gasto debe ser mayor que cero.',
            'monto_original.regex' => 'El monto admite hasta 12 dígitos enteros y 2 decimales, con punto como separador decimal.',
            'moneda.required' => 'Indique la moneda del gasto.',
            'moneda.string' => 'La moneda debe ser texto.',
            'moneda.size' => 'La moneda debe tener 3 letras, como COP, USD o EUR.',
            'moneda.alpha' => 'La moneda solo admite letras, como COP, USD o EUR.',
            'tasa_cambio_usada.numeric' => 'La tasa de cambio debe ser un número.',
            'tasa_cambio_usada.gt' => 'La tasa de cambio debe ser mayor que cero.',
            'tasa_cambio_usada.regex' => 'La tasa de cambio admite hasta 8 dígitos enteros y 4 decimales.',
            'categoria_id.required' => 'Seleccione la categoría del gasto.',
            'categoria_id.uuid' => 'La categoría seleccionada no es válida.',
            'subcategoria_id.required' => 'Seleccione la subcategoría del gasto.',
            'subcategoria_id.uuid' => 'La subcategoría seleccionada no es válida.',
            'medio_pago.required' => 'Seleccione el medio de pago.',
            'medio_pago.in' => 'El medio de pago debe ser EFECTIVO, TARJETA_DEBITO, TARJETA_CREDITO, TRANSFERENCIA, BILLETERA_DIGITAL u OTRO.',
            'naturaleza.in' => 'La naturaleza debe ser FIJO o VARIABLE.',
            'necesidad.in' => 'La necesidad debe ser ESENCIAL o DISCRECIONAL.',
            'relevancia_tributaria.in' => 'La relevancia tributaria debe ser NO_DEDUCIBLE o POTENCIALMENTE_DEDUCIBLE.',
            'subtipo_deduccion.in' => 'El subtipo de deducción debe ser intereses de vivienda, medicina prepagada, dependientes, aportes voluntarios a pensión o AFC, GMF u otros.',
            'etiquetas.array' => 'Las etiquetas deben enviarse como una lista.',
            'comercio_beneficiario.string' => 'El comercio o beneficiario debe ser texto.',
            'comercio_beneficiario.max' => 'El comercio o beneficiario no puede superar los 80 caracteres.',
            'descripcion.string' => 'La descripción debe ser texto.',
            'descripcion.max' => 'La descripción no puede superar los 255 caracteres.',
        ];
    }

    /**
     * RN-026.1: Valores admitidos en cada dimensión, para los selectores del
     * formulario y de los filtros.
     */
    private function dimensiones(): array
    {
        return [
            'naturalezas' => Gasto::NATURALEZAS,
            'necesidades' => Gasto::NECESIDADES,
            'medios_pago' => Gasto::MEDIOS_PAGO,
            'relevancias' => Gasto::RELEVANCIAS,
            'subtipos_deduccion' => Gasto::SUBTIPOS_DEDUCCION,
            'origenes' => Gasto::ORIGENES,
            'estados' => Gasto::ESTADOS,
            'max_etiquetas' => GastoService::MAX_ETIQUETAS,
        ];
    }

    /**
     * RNG-01: El global scope ya devolvió 404 para recursos ajenos. Aquí solo se
     * distingue el caso que la regla manda registrar en bitácora (RF-019).
     */
    private function gastoPropio(string $id): Gasto
    {
        $gasto = Gasto::find($id);

        if ($gasto !== null) {
            return $gasto;
        }

        $esDeOtro = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereKey($id)
            ->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'gastos',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }
}
