<?php

namespace App\Http\Controllers;

use App\Models\FuenteIngreso;
use App\Models\Ingreso;
use App\Models\PlantillaRecurrencia;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Services\BitacoraService;
use App\Services\IngresoService;
use App\Services\RecurrenciaService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * RF-022 — Gestión de ingresos recurrentes (MOD-03).
 *
 * El Usuario define la plantilla; el Sistema genera cada ocurrencia en la fecha
 * programada mediante `scfp:generar-recurrencias` (RN-022.2).
 */
class IngresoRecurrenteController extends Controller
{
    /**
     * RF-022: Listado de plantillas de ingreso recurrente del Usuario.
     */
    public function index(Request $request): View|JsonResponse
    {
        $plantillas = PlantillaRecurrencia::deIngresos()
            ->orderByDesc('activa')
            ->orderBy('proxima_ejecucion')
            ->get();

        // RN-022.3 / CA-022.3: las ocurrencias pendientes de confirmar. El centro
        // de notificaciones (RF-058) no está en el alcance de la versión 1.0, así
        // que se exponen acá para que el usuario pueda confirmarlas o descartarlas.
        $pendientes = Ingreso::where('estado', 'PENDIENTE')
            ->whereNotNull('plantilla_recurrencia_id')
            ->with('fuente')
            ->orderBy('fecha_recepcion')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Ingresos recurrentes consultados.',
                'data' => [
                    'plantillas' => $plantillas,
                    'pendientes' => $pendientes,
                ],
            ]);
        }

        return view('ingresos.recurrentes.index', [
            'plantillas' => $plantillas,
            'pendientes' => $pendientes,
            'fuentes' => FuenteIngreso::activas()->with('tipoFuente')->orderBy('nombre')->get(),
            'clasificaciones' => FuenteIngreso::CLASIFICACIONES,
            'periodicidades' => PlantillaRecurrencia::PERIODICIDADES,
            'modosConfirmacion' => PlantillaRecurrencia::MODOS_CONFIRMACION,
        ]);
    }

    /**
     * RF-022 RN-022.1: Crear una plantilla de recurrencia de ingreso.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate($this->reglas(), $this->mensajes());
        $this->validarTope($validated);

        $fuente = $this->fuenteActivaPropia($validated['fuente_id']);
        $montos = IngresoService::prepararMontos($validated, $fuente);

        $fechaInicio = CarbonImmutable::parse($validated['fecha_inicio']);

        $plantilla = PlantillaRecurrencia::create([
            'usuario_id' => Auth::id(),
            'tipo_movimiento' => 'INGRESO',
            'periodicidad' => $validated['periodicidad'],
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $validated['fecha_fin'] ?? null,
            'max_ocurrencias' => $validated['max_ocurrencias'] ?? null,
            'ocurrencias_generadas' => 0,
            'modo_confirmacion' => $validated['modo_confirmacion'],
            // RN-028.2 (monto estimado) aplica a gastos recurrentes; RF-022 no lo
            // contempla para ingresos, así que la plantilla de ingreso nace en falso.
            'es_monto_estimado' => false,
            'datos_plantilla' => array_merge($montos, [
                'fuente_id' => $fuente->id,
                'descripcion' => $validated['descripcion'] ?? null,
            ]),
            // RN-022.2: la primera ocurrencia cae en la fecha de inicio.
            'proxima_ejecucion' => $fechaInicio->toDateString(),
            'activa' => true,
            'creado_en' => now(),
        ]);

        BitacoraService::registrar(
            accion: 'CREAR_INGRESO_RECURRENTE',
            entidadAfectada: 'plantillas_recurrencia',
            entidadId: $plantilla->id,
            valorNuevo: $plantilla->toArray(),
            usuarioId: Auth::id()
        );

        $mensaje = 'Ingreso recurrente creado. La primera ocurrencia se generará el '.$fechaInicio->format('d/m/Y').'.';

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => $mensaje,
                'data' => $plantilla,
            ], 201);
        }

        return redirect()->route('ingresos.recurrentes.index')->with('status', $mensaje);
    }

    /**
     * RF-022 RN-022.4: Editar la plantilla solo cambia las ocurrencias futuras;
     * las ya generadas no cambian.
     *
     * Una vez generada alguna ocurrencia, la fecha de inicio y la periodicidad
     * quedan congeladas: son el ancla con la que se calcula el calendario
     * (RN-022.2), y moverlas desplazaría o duplicaría las ocurrencias siguientes.
     */
    public function update(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $plantilla = $this->plantillaPropia($id);
        $yaGenero = $plantilla->ocurrencias_generadas > 0;

        $reglas = $this->reglas();
        if ($yaGenero) {
            unset($reglas['fecha_inicio'], $reglas['periodicidad']);

            // El ancla queda congelada, así que fecha_fin se compara contra la
            // fecha de inicio almacenada. Apuntar la regla al campo `fecha_inicio`
            // la volvería un no-op silencioso: ya no se valida y puede no venir
            // en la petición.
            $reglas['fecha_fin'] = [
                'nullable',
                'date',
                'after_or_equal:'.$plantilla->fecha_inicio->toDateString(),
            ];
        }

        $validated = $request->validate($reglas, $this->mensajes());
        $this->validarTope($validated);

        $fuente = $this->fuenteActivaPropia($validated['fuente_id']);
        $montos = IngresoService::prepararMontos($validated, $fuente);

        $anterior = $plantilla->toArray();

        $cambios = [
            'fecha_fin' => $validated['fecha_fin'] ?? null,
            'max_ocurrencias' => $validated['max_ocurrencias'] ?? null,
            'modo_confirmacion' => $validated['modo_confirmacion'],
            'datos_plantilla' => array_merge($montos, [
                'fuente_id' => $fuente->id,
                'descripcion' => $validated['descripcion'] ?? null,
            ]),
        ];

        if (! $yaGenero) {
            $fechaInicio = CarbonImmutable::parse($validated['fecha_inicio']);
            $cambios['fecha_inicio'] = $fechaInicio->toDateString();
            $cambios['periodicidad'] = $validated['periodicidad'];
            $cambios['proxima_ejecucion'] = $fechaInicio->toDateString();
        }

        $plantilla->update($cambios);

        BitacoraService::registrar(
            accion: 'EDITAR_INGRESO_RECURRENTE',
            entidadAfectada: 'plantillas_recurrencia',
            entidadId: $plantilla->id,
            valorAnterior: $anterior,
            valorNuevo: $plantilla->fresh()->toArray(),
            justificacion: 'RN-022.4: el cambio aplica solo a las ocurrencias futuras.',
            usuarioId: Auth::id()
        );

        $mensaje = $yaGenero
            ? 'Recurrencia actualizada. El cambio aplica solo a las ocurrencias futuras.'
            : 'Recurrencia actualizada.';

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => $mensaje,
                'data' => $plantilla->fresh(),
            ]);
        }

        return redirect()->route('ingresos.recurrentes.index')->with('status', $mensaje);
    }

    /**
     * RF-022 CA-022.2: Al pausar o terminar una recurrencia no se generan más
     * ocurrencias a partir de ese momento.
     */
    public function toggleActiva(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $plantilla = $this->plantillaPropia($id);
        $nuevoEstado = ! $plantilla->activa;

        // CA-022.2: al reanudar, el cursor se adelanta hasta la primera ocurrencia
        // que aún no ha llegado, para no generar de golpe las del periodo pausado.
        // Las saltadas consumen cupo de max_ocurrencias: el calendario sí avanzó.
        $cambios = ['activa' => $nuevoEstado];
        if ($nuevoEstado) {
            $indice = RecurrenciaService::adelantarCursor($plantilla);

            $cambios['ocurrencias_generadas'] = $indice;
            $cambios['proxima_ejecucion'] = RecurrenciaService::calcularOcurrencia(
                CarbonImmutable::parse($plantilla->fecha_inicio),
                $plantilla->periodicidad,
                $indice
            )->toDateString();
        }

        $plantilla->update($cambios);

        BitacoraService::registrar(
            accion: $nuevoEstado ? 'REANUDAR_INGRESO_RECURRENTE' : 'PAUSAR_INGRESO_RECURRENTE',
            entidadAfectada: 'plantillas_recurrencia',
            entidadId: $plantilla->id,
            valorNuevo: $cambios,
            usuarioId: Auth::id()
        );

        $mensaje = $nuevoEstado
            ? 'Recurrencia reanudada.'
            : 'Recurrencia pausada. No se generarán más ocurrencias.';

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => $mensaje,
                'data' => $plantilla->fresh(),
            ]);
        }

        return back()->with('status', $mensaje);
    }

    /**
     * RF-022: Eliminar la plantilla. Las ocurrencias ya generadas se conservan
     * como ingresos propios: la llave foránea queda en NULL (ON DELETE SET NULL),
     * porque son hechos económicos reales y no dependen de la plantilla.
     */
    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $plantilla = $this->plantillaPropia($id);
        $anterior = $plantilla->toArray();

        $plantilla->delete();

        BitacoraService::registrar(
            accion: 'ELIMINAR_INGRESO_RECURRENTE',
            entidadAfectada: 'plantillas_recurrencia',
            entidadId: $id,
            valorAnterior: $anterior,
            usuarioId: Auth::id()
        );

        $mensaje = 'Recurrencia eliminada. Las ocurrencias ya generadas se conservan como ingresos.';

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje]);
        }

        return redirect()->route('ingresos.recurrentes.index')->with('status', $mensaje);
    }

    /**
     * RN-022.1: La plantilla reúne los datos de periodicidad y los mismos datos
     * de RF-021, salvo la fecha de recepción, que la fija cada ocurrencia.
     */
    private function reglas(): array
    {
        return [
            'periodicidad' => ['required', Rule::in(PlantillaRecurrencia::PERIODICIDADES)],
            // La fecha de inicio sí puede ser futura: es el caso que CA-021.3
            // manda redirigir hacia acá.
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'max_ocurrencias' => ['nullable', 'integer', 'min:1', 'max:360'],
            'modo_confirmacion' => ['required', Rule::in(PlantillaRecurrencia::MODOS_CONFIRMACION)],

            'fuente_id' => ['required', 'uuid'],
            'monto_original' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'moneda' => ['required', 'string', 'size:3', 'alpha'],
            'tasa_cambio_usada' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,8}(\.\d{1,4})?$/'],
            'retencion_fuente' => ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'aportes_descontados' => ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'clasificacion_tributaria' => ['nullable', Rule::in(FuenteIngreso::CLASIFICACIONES)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * RN-022.1: Mensaje propio para cada regla. El proyecto no tiene archivos de
     * traducción, así que una regla sin mensaje acá sale al usuario como la clave
     * cruda (`validation.required`).
     */
    private function mensajes(): array
    {
        return [
            'periodicidad.required' => 'Seleccione cada cuánto se repite el ingreso.',
            'periodicidad.in' => 'La periodicidad seleccionada no es válida.',
            'fecha_inicio.required' => 'Indique la fecha en que inicia la recurrencia.',
            'fecha_inicio.date' => 'La fecha de inicio no tiene un formato válido.',
            'fecha_fin.date' => 'La fecha de fin no tiene un formato válido.',
            'fecha_fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'max_ocurrencias.integer' => 'El número de ocurrencias debe ser un número entero.',
            'max_ocurrencias.max' => 'El número de ocurrencias debe estar entre 1 y 360.',
            'max_ocurrencias.min' => 'El número de ocurrencias debe estar entre 1 y 360.',
            'modo_confirmacion.required' => 'Indique si las ocurrencias se registran automáticamente o requieren confirmación.',
            'modo_confirmacion.in' => 'El modo de confirmación debe ser AUTOMATICO o REQUIERE_CONFIRMACION.',
            'fuente_id.required' => 'Seleccione la fuente de ingreso.',
            'fuente_id.uuid' => 'La fuente de ingreso seleccionada no es válida.',
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
     * RN-022.1: El tope de la recurrencia se define por fecha de fin **o** por
     * número de ocurrencias. Admitir ambos a la vez dejaría ambiguo cuál manda.
     */
    private function validarTope(array $validated): void
    {
        if (($validated['fecha_fin'] ?? null) !== null && ($validated['max_ocurrencias'] ?? null) !== null) {
            throw ValidationException::withMessages([
                'max_ocurrencias' => 'Defina el tope con una fecha de fin o con un número de ocurrencias, no con ambos.',
            ]);
        }
    }

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

    /**
     * RNG-01: El global scope ya devolvió 404 para recursos ajenos. Aquí solo se
     * distingue el caso que la regla manda registrar en bitácora (RF-019).
     */
    private function plantillaPropia(string $id): PlantillaRecurrencia
    {
        $plantilla = PlantillaRecurrencia::deIngresos()->find($id);

        if ($plantilla !== null) {
            return $plantilla;
        }

        $esDeOtro = PlantillaRecurrencia::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereKey($id)
            ->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'plantillas_recurrencia',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }
}
