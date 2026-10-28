<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Models\PlantillaRecurrencia;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Services\BitacoraService;
use App\Services\CategoriaGastoService;
use App\Services\GastoService;
use App\Services\RecurrenciaService;
use App\Services\TrmService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * RF-028 — Gestión de gastos recurrentes (MOD-04).
 *
 * RN-028.1: "Se aplican las mismas reglas de periodicidad, generación,
 * confirmación y edición que en RF-022 (RN-022.1 a RN-022.4)", así que este
 * controlador es el espejo de IngresoRecurrenteController y comparte
 * `plantillas_recurrencia` y RecurrenciaService.
 *
 * Lo propio de los gastos es RN-028.2: el monto estimado.
 */
class GastoRecurrenteController extends Controller
{
    /** RN-028.3: "Tres días antes de cada ocurrencia, el Sistema envía un recordatorio". */
    private const DIAS_RECORDATORIO = 3;

    /**
     * RF-028: Listado de plantillas de gasto recurrente del Usuario.
     */
    public function index(Request $request): View|JsonResponse
    {
        $plantillas = PlantillaRecurrencia::deGastos()
            ->orderByDesc('activa')
            ->orderBy('proxima_ejecucion')
            ->get();

        // RN-022.3 / RN-028.2: ocurrencias pendientes de confirmar. El centro de
        // notificaciones (RF-058) no está en el alcance de la versión 1.0, así
        // que se exponen acá para poder confirmarlas.
        $pendientes = Gasto::where('estado', 'PENDIENTE')
            ->whereNotNull('plantilla_recurrencia_id')
            ->with(['categoria', 'subcategoria'])
            ->orderBy('fecha')
            ->get();

        // RN-028.3: el recordatorio se envía tres días antes de cada ocurrencia.
        // Sin RF-058 no hay a dónde enviarlo: las ocurrencias que caen dentro de
        // esa ventana se informan en la respuesta.
        $hoy = CarbonImmutable::today();
        $recordatorios = PlantillaRecurrencia::deGastos()
            ->where('activa', true)
            ->whereBetween('proxima_ejecucion', [
                $hoy->toDateString(),
                $hoy->addDays(self::DIAS_RECORDATORIO)->toDateString(),
            ])
            ->orderBy('proxima_ejecucion')
            ->get();

        $datos = [
            'plantillas' => $plantillas,
            'pendientes' => $pendientes,
            'recordatorios' => $recordatorios,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Gastos recurrentes consultados.',
                'data' => $datos,
            ]);
        }

        return view('gastos.recurrentes.index', array_merge($datos, [
            'categorias' => CategoriaGastoService::catalogo(Auth::id(), soloActivas: true),
            'periodicidades' => PlantillaRecurrencia::PERIODICIDADES,
            'modosConfirmacion' => PlantillaRecurrencia::MODOS_CONFIRMACION,
            'mediosPago' => Gasto::MEDIOS_PAGO,
            'dimensiones' => [
                'medios_pago' => Gasto::MEDIOS_PAGO,
            ],
        ]));
    }

    /**
     * RF-028 RN-028.1: Crear una plantilla de recurrencia de gasto.
     *
     * CA-028.1: una suscripción mensual de $ 39.900 el día 5 genera un gasto el
     * día 5 de cada mes con la clasificación configurada.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate($this->reglas(), $this->mensajes());
        $this->validarTope($validated);

        $fechaInicio = CarbonImmutable::parse($validated['fecha_inicio']);

        $plantilla = PlantillaRecurrencia::create([
            'usuario_id' => Auth::id(),
            'tipo_movimiento' => 'GASTO',
            'periodicidad' => $validated['periodicidad'],
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $validated['fecha_fin'] ?? null,
            'max_ocurrencias' => $validated['max_ocurrencias'] ?? null,
            'ocurrencias_generadas' => 0,
            'modo_confirmacion' => $validated['modo_confirmacion'],
            // RN-028.2: con monto estimado la ocurrencia nace PENDIENTE y el
            // usuario confirma el monto real (CA-028.2).
            'es_monto_estimado' => (bool) ($validated['es_monto_estimado'] ?? false),
            'datos_plantilla' => $this->datosPlantilla($validated, $fechaInicio->toDateString()),
            // RN-022.2: la primera ocurrencia cae en la fecha de inicio.
            'proxima_ejecucion' => $fechaInicio->toDateString(),
            'activa' => true,
            'creado_en' => now(),
        ]);

        BitacoraService::registrar(
            accion: 'CREAR_GASTO_RECURRENTE',
            entidadAfectada: 'plantillas_recurrencia',
            entidadId: $plantilla->id,
            valorNuevo: $plantilla->toArray(),
            usuarioId: Auth::id()
        );

        $mensaje = 'Gasto recurrente creado. La primera ocurrencia se generará el '.$fechaInicio->format('d/m/Y').'.';

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje, 'data' => $plantilla], 201);
        }

        return redirect()->route('gastos.recurrentes.index')->with('status', $mensaje);
    }

    /**
     * RF-028 RN-028.1 / RN-022.4: Editar la plantilla solo cambia las ocurrencias
     * futuras; las ya generadas no cambian.
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
            // fecha de inicio almacenada.
            $reglas['fecha_fin'] = [
                'nullable',
                'date',
                'after_or_equal:'.$plantilla->fecha_inicio->toDateString(),
            ];
        }

        $validated = $request->validate($reglas, $this->mensajes());
        $this->validarTope($validated);

        $anterior = $plantilla->toArray();

        $cambios = [
            'fecha_fin' => $validated['fecha_fin'] ?? null,
            'max_ocurrencias' => $validated['max_ocurrencias'] ?? null,
            'modo_confirmacion' => $validated['modo_confirmacion'],
            'es_monto_estimado' => (bool) ($validated['es_monto_estimado'] ?? false),
            // CA-017.1: la tasa se busca para la próxima ocurrencia, que es la
            // primera a la que aplica el cambio (RN-022.4).
            'datos_plantilla' => $this->datosPlantilla(
                $validated,
                $validated['fecha_inicio'] ?? $plantilla->proxima_ejecucion->toDateString()
            ),
        ];

        if (! $yaGenero) {
            $fechaInicio = CarbonImmutable::parse($validated['fecha_inicio']);
            $cambios['fecha_inicio'] = $fechaInicio->toDateString();
            $cambios['periodicidad'] = $validated['periodicidad'];
            $cambios['proxima_ejecucion'] = $fechaInicio->toDateString();
        }

        $plantilla->update($cambios);

        BitacoraService::registrar(
            accion: 'EDITAR_GASTO_RECURRENTE',
            entidadAfectada: 'plantillas_recurrencia',
            entidadId: $plantilla->id,
            valorAnterior: $anterior,
            valorNuevo: $plantilla->fresh()->toArray(),
            justificacion: 'RN-022.4 (vía RN-028.1): el cambio aplica solo a las ocurrencias futuras.',
            usuarioId: Auth::id()
        );

        $mensaje = $yaGenero
            ? 'Recurrencia actualizada. El cambio aplica solo a las ocurrencias futuras.'
            : 'Recurrencia actualizada.';

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje, 'data' => $plantilla->fresh()]);
        }

        return redirect()->route('gastos.recurrentes.index')->with('status', $mensaje);
    }

    /**
     * RF-028 RN-028.1 / CA-022.2: Al pausar o terminar una recurrencia no se
     * generan más ocurrencias a partir de ese momento.
     */
    public function toggleActiva(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $plantilla = $this->plantillaPropia($id);
        $nuevoEstado = ! $plantilla->activa;

        // CA-022.2: al reanudar, el cursor se adelanta hasta la primera
        // ocurrencia que aún no ha llegado, para no generar de golpe las del
        // periodo pausado. Las saltadas consumen cupo de max_ocurrencias.
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
            accion: $nuevoEstado ? 'REANUDAR_GASTO_RECURRENTE' : 'PAUSAR_GASTO_RECURRENTE',
            entidadAfectada: 'plantillas_recurrencia',
            entidadId: $plantilla->id,
            valorNuevo: $cambios,
            usuarioId: Auth::id()
        );

        $mensaje = $nuevoEstado
            ? 'Recurrencia reanudada.'
            : 'Recurrencia pausada. No se generarán más ocurrencias.';

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje, 'data' => $plantilla->fresh()]);
        }

        return back()->with('status', $mensaje);
    }

    /**
     * RF-028: Eliminar la plantilla. Las ocurrencias ya generadas se conservan
     * como gastos propios: la llave foránea queda en NULL (ON DELETE SET NULL),
     * porque son hechos económicos reales y no dependen de la plantilla.
     */
    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $plantilla = $this->plantillaPropia($id);
        $anterior = $plantilla->toArray();

        $plantilla->delete();

        BitacoraService::registrar(
            accion: 'ELIMINAR_GASTO_RECURRENTE',
            entidadAfectada: 'plantillas_recurrencia',
            entidadId: $id,
            valorAnterior: $anterior,
            usuarioId: Auth::id()
        );

        $mensaje = 'Recurrencia eliminada. Las ocurrencias ya generadas se conservan como gastos.';

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje]);
        }

        return redirect()->route('gastos.recurrentes.index')->with('status', $mensaje);
    }

    /**
     * RN-028.1 / RN-022.1: La plantilla reúne los datos de periodicidad y los
     * mismos datos de RF-025 y RF-026, salvo la fecha, que la fija cada
     * ocurrencia.
     */
    private function reglas(): array
    {
        return [
            'periodicidad' => ['required', Rule::in(PlantillaRecurrencia::PERIODICIDADES)],
            // La fecha de inicio sí puede ser futura: es el caso al que remite el
            // rechazo de una fecha futura en RF-025.
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'max_ocurrencias' => ['nullable', 'integer', 'min:1', 'max:360'],
            'modo_confirmacion' => ['required', Rule::in(PlantillaRecurrencia::MODOS_CONFIRMACION)],
            // RN-028.2: "el usuario puede marcar 'Monto estimado'".
            'es_monto_estimado' => ['nullable', 'boolean'],

            'monto_original' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'moneda' => ['required', 'string', 'size:3', 'alpha'],
            'tasa_cambio_usada' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,8}(\.\d{1,4})?$/'],
            'categoria_id' => ['required', 'uuid'],
            'subcategoria_id' => ['nullable', 'uuid'],
            'medio_pago' => ['required', Rule::in(Gasto::MEDIOS_PAGO)],
            'naturaleza' => ['nullable', Rule::in(Gasto::NATURALEZAS)],
            'necesidad' => ['nullable', Rule::in(Gasto::NECESIDADES)],
            'relevancia_tributaria' => ['nullable', Rule::in(Gasto::RELEVANCIAS)],
            'subtipo_deduccion' => ['nullable', Rule::in(Gasto::SUBTIPOS_DEDUCCION)],
            'etiquetas' => ['nullable', 'array'],
            'comercio_beneficiario' => ['nullable', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * RN-028.1: Mensaje propio para cada regla. El proyecto no tiene archivos de
     * traducción, así que una regla sin mensaje acá sale al usuario como la clave
     * cruda (`validation.required`).
     */
    private function mensajes(): array
    {
        return [
            'periodicidad.required' => 'Seleccione cada cuánto se repite el gasto.',
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
            'es_monto_estimado.boolean' => 'La marca de monto estimado debe ser verdadero o falso.',
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
     * RN-028.1 / RF-026: La clasificación con la que nacerá cada ocurrencia.
     * Se valida y se normaliza igual que en un gasto manual, para que la
     * ocurrencia generada por el comando programado no pueda violar RN-026.1.
     *
     * CA-017.1: si es en USD y no trae tasa, la plantilla queda marcada con
     * `tasa_automatica` y cada ocurrencia se convierte con la TRM de su fecha.
     */
    private function datosPlantilla(array $validated, string $fechaTasa): array
    {
        $subcategoria = null;
        $categoriaId = $validated['categoria_id'];
        $subcategoriaId = $validated['subcategoria_id'] ?? null;

        if (! empty($subcategoriaId)) {
            $subcategoria = GastoService::validarJerarquia(
                $categoriaId,
                $subcategoriaId,
                Auth::id()
            );
            $categoriaId = $subcategoria->categoria_padre_id;
            $sugerencias = GastoService::aplicarSugerencias($subcategoria, $validated);
        } else {
            $categoria = GastoService::categoriaUsable($categoriaId, Auth::id());
            if ($categoria === null) {
                throw ValidationException::withMessages([
                    'categoria_id' => 'La categoría no existe, está inactiva o no pertenece a tus categorías de gasto.',
                ]);
            }
            $sugerencias = [
                'naturaleza' => $validated['naturaleza'] ?? ($categoria->naturaleza_sugerida ?? 'VARIABLE'),
                'necesidad' => $validated['necesidad'] ?? ($categoria->necesidad_sugerida ?? 'DISCRECIONAL'),
            ];
        }

        return array_merge(
            GastoService::prepararMontos($validated, $fechaTasa),
            $sugerencias,
            GastoService::validarRelevancia($validated),
            [
                'categoria_id' => $categoriaId,
                'subcategoria_id' => $subcategoria?->id,
                'medio_pago' => $validated['medio_pago'],
                'etiquetas' => GastoService::normalizarEtiquetas($validated['etiquetas'] ?? null),
                'comercio_beneficiario' => $validated['comercio_beneficiario'] ?? null,
                'descripcion' => $validated['descripcion'] ?? null,
                'tasa_automatica' => TrmService::usaTrmAutomatica($validated),
            ]
        );
    }

    /**
     * RN-022.1 (vía RN-028.1): El tope de la recurrencia se define por fecha de
     * fin **o** por número de ocurrencias. Admitir ambos a la vez dejaría
     * ambiguo cuál manda.
     */
    private function validarTope(array $validated): void
    {
        if (($validated['fecha_fin'] ?? null) !== null && ($validated['max_ocurrencias'] ?? null) !== null) {
            throw ValidationException::withMessages([
                'max_ocurrencias' => 'Defina el tope con una fecha de fin o con un número de ocurrencias, no con ambos.',
            ]);
        }
    }

    /**
     * RNG-01: El global scope ya devolvió 404 para recursos ajenos. Aquí solo se
     * distingue el caso que la regla manda registrar en bitácora (RF-019).
     */
    private function plantillaPropia(string $id): PlantillaRecurrencia
    {
        $plantilla = PlantillaRecurrencia::deGastos()->find($id);

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
