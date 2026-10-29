<?php

namespace App\Http\Controllers;

use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\ValoracionInversion;
use App\Services\BitacoraService;
use App\Services\IngresoService;
use App\Services\InversionService;
use App\Services\TrmService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MOD-06 — RF-037: Registro de inversiones.
 *
 * Solo responde JSON: esta entrega no incluye interfaz Blade. El aislamiento de
 * datos (RNG-01) lo da el global scope DelUsuarioAutenticado de los modelos.
 */
class InversionController extends Controller
{
    /** RNG-10: Tamaños de página admitidos. */
    private const POR_PAGINA = [10, 20, 50, 100];

    /**
     * RF-037: Inversiones del Usuario, de la apertura más reciente a la más
     * antigua (RNG-10), filtrables por estado y tipo.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['nullable', Rule::in(Inversion::ESTADOS)],
            'tipo' => ['nullable', Rule::in(Inversion::TIPOS)],
        ], [
            'estado.in' => 'El estado no es válido.',
            'tipo.in' => 'El tipo de inversión no es válido.',
        ]);

        $porPagina = (int) $request->query('por_pagina', 20);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 20;
        }

        $inversiones = Inversion::query()
            ->when($validated['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($validated['tipo'] ?? null, fn ($query, $tipo) => $query->where('tipo', $tipo))
            ->orderByDesc('fecha_apertura')
            ->orderByDesc('creado_en')
            ->paginate($porPagina)
            ->withQueryString();

        $hoy = CarbonImmutable::today();

        return response()->json([
            'mensaje' => 'Inversiones consultadas.',
            'data' => [
                'inversiones' => collect($inversiones->items())->map(fn (Inversion $inversion) => InversionService::presentar($inversion, $hoy)),
                'meta' => [
                    'pagina_actual' => $inversiones->currentPage(),
                    'por_pagina' => $inversiones->perPage(),
                    'total' => $inversiones->total(),
                    'ultima_pagina' => $inversiones->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * RN-037.1 a RN-037.4: Registrar la inversión. En la misma transacción el
     * monto inicial queda como primer movimiento "Aporte" (RN-037.4) y como
     * valoración inicial (decisión 7, SC-001).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->reglas($request), $this->mensajes());

        $tipo = $validated['tipo'];
        $conTasa = in_array($tipo, Inversion::CON_TASA, true);
        $conUnidades = in_array($tipo, Inversion::CON_UNIDADES, true);
        $hoy = CarbonImmutable::today();
        $apertura = CarbonImmutable::parse($validated['fecha_apertura']);
        $montoInicial = (string) $validated['monto_inicial'];

        // RN-021.3 / CA-017.1: en USD sin tasa, la TRM de la fecha de apertura.
        $tasa = TrmService::tasaPara($validated['moneda'], $validated['tasa_cambio_usada'] ?? null, $apertura->toDateString());
        $montoCop = IngresoService::convertirACop($montoInicial, $tasa);

        if (! IngresoService::montoEnRango($montoCop)) {
            throw ValidationException::withMessages([
                'monto_inicial' => 'El monto convertido a pesos supera el máximo permitido de $ 999.999.999.999,99.',
            ]);
        }

        // RN-037.2: el vencimiento se calcula, pero el Usuario puede ajustarlo.
        $vencimiento = null;
        if ($conTasa) {
            $vencimiento = isset($validated['fecha_vencimiento'])
                ? CarbonImmutable::parse($validated['fecha_vencimiento'])
                : InversionService::fechaVencimiento($apertura, (int) $validated['plazo_dias']);
        }

        $inversion = DB::transaction(function () use ($validated, $conTasa, $conUnidades, $hoy, $apertura, $montoInicial, $tasa, $montoCop, $vencimiento) {
            $inversion = Inversion::create([
                'usuario_id' => Auth::id(),
                'nombre' => $validated['nombre'],
                'tipo' => $validated['tipo'],
                'entidad' => $validated['entidad'] ?? null,
                'moneda' => $validated['moneda'],
                'fecha_apertura' => $apertura->toDateString(),
                'monto_inicial' => $montoInicial,
                'nivel_riesgo' => $validated['nivel_riesgo'] ?? null,
                'tasa_ea' => $conTasa ? (string) $validated['tasa_ea'] : null,
                'plazo_dias' => $conTasa ? (int) $validated['plazo_dias'] : null,
                'periodicidad_intereses' => $conTasa ? $validated['periodicidad_intereses'] : null,
                'fecha_vencimiento' => $vencimiento?->toDateString(),
                'simbolo' => $conUnidades ? ($validated['simbolo'] ?? null) : null,
                'unidades_iniciales' => $conUnidades ? (string) $validated['unidades'] : null,
                'precio_unitario_compra' => $conUnidades ? (string) $validated['precio_unitario_compra'] : null,
                // RN-037.5: un CDT registrado después de su vencimiento ya está vencido.
                'estado' => InversionService::estadoSegunVencimiento($vencimiento, $hoy),
            ]);

            // RN-037.4: el monto inicial es el primer movimiento "Aporte".
            MovimientoInversion::create([
                'usuario_id' => Auth::id(),
                'inversion_id' => $inversion->id,
                'tipo' => 'APORTE',
                'fecha' => $apertura->toDateString(),
                'monto' => $montoInicial,
                'tasa_cambio_usada' => $tasa,
                'monto_cop' => $montoCop,
                'unidades' => $conUnidades ? (string) $validated['unidades'] : null,
                'nota' => 'Monto inicial (RN-037.4)',
                'es_inicial' => true,
            ]);

            // Decisión 7 (SC-001): sin valoraciones, el valor actual es el monto inicial.
            ValoracionInversion::create([
                'usuario_id' => Auth::id(),
                'inversion_id' => $inversion->id,
                'fecha' => $apertura->toDateString(),
                'valor' => $montoInicial,
                'precio_unitario' => $conUnidades ? (string) $validated['precio_unitario_compra'] : null,
                'unidades' => $conUnidades ? (string) $validated['unidades'] : null,
                'origen' => 'INICIAL',
            ]);

            BitacoraService::registrar(
                accion: 'CREAR_INVERSION',
                entidadAfectada: 'inversiones',
                entidadId: $inversion->id,
                valorNuevo: $inversion->fresh()->toArray(),
                justificacion: 'RF-037: registro de inversión con su aporte inicial (RN-037.4).',
                usuarioId: Auth::id()
            );

            return $inversion->fresh();
        });

        return response()->json([
            'mensaje' => 'Inversión registrada. El monto inicial quedó como primer aporte.',
            'data' => InversionService::presentar($inversion, $hoy),
        ], 201);
    }

    /**
     * RF-037: Detalle de la inversión con sus movimientos (RF-038).
     */
    public function show(string $id): JsonResponse
    {
        $inversion = $this->inversionPropia($id);

        $movimientos = MovimientoInversion::where('inversion_id', $inversion->id)
            ->orderByDesc('fecha')
            ->orderByDesc('creado_en')
            ->get();

        return response()->json([
            'mensaje' => 'Inversión consultada.',
            'data' => array_merge(InversionService::presentar($inversion, CarbonImmutable::today()), [
                'movimientos' => $movimientos,
            ]),
        ]);
    }

    /**
     * RF-037: Editar los datos descriptivos. Tipo, moneda, apertura y monto
     * inicial forman el aporte inicial (RN-037.4) y no se cambian. La fecha de
     * vencimiento sí: "se calcula, pero el usuario puede ajustarla" (RN-037.2).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $inversion = $this->inversionPropia($id);

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'min:3', 'max:60'],
            'entidad' => ['sometimes', 'nullable', 'string', 'max:80'],
            'nivel_riesgo' => ['sometimes', 'nullable', Rule::in(Inversion::NIVELES_RIESGO)],
            'simbolo' => ['sometimes', 'nullable', 'string', 'max:15'],
            'fecha_vencimiento' => ['sometimes', 'required', 'date', 'after:'.$inversion->fecha_apertura->toDateString()],
        ], $this->mensajes());

        if (array_key_exists('simbolo', $validated) && ! $inversion->tieneUnidades()) {
            throw ValidationException::withMessages([
                'simbolo' => 'El símbolo solo aplica a Acciones, ETF y Criptoactivos (RN-037.3).',
            ]);
        }

        if (array_key_exists('fecha_vencimiento', $validated) && ! $inversion->tieneTasa()) {
            throw ValidationException::withMessages([
                'fecha_vencimiento' => 'La fecha de vencimiento solo aplica a CDT y Bonos/TES (RN-037.2).',
            ]);
        }

        $anterior = $inversion->toArray();

        DB::transaction(function () use ($inversion, $validated, $anterior) {
            $inversion->fill($validated);

            // RN-037.5: al ajustar el vencimiento, el estado sigue a la nueva fecha.
            if (array_key_exists('fecha_vencimiento', $validated) && ! $inversion->estaLiquidada()) {
                $inversion->estado = InversionService::estadoSegunVencimiento(
                    CarbonImmutable::parse($validated['fecha_vencimiento']),
                    CarbonImmutable::today()
                );
            }

            $inversion->save();

            BitacoraService::registrar(
                accion: 'EDITAR_INVERSION',
                entidadAfectada: 'inversiones',
                entidadId: $inversion->id,
                valorAnterior: $anterior,
                valorNuevo: $inversion->fresh()->toArray(),
                justificacion: 'RF-037: edición de los datos de la inversión.',
                usuarioId: Auth::id()
            );
        });

        return response()->json([
            'mensaje' => 'Inversión actualizada.',
            'data' => InversionService::presentar($inversion->fresh(), CarbonImmutable::today()),
        ]);
    }

    /**
     * RNG-07: Eliminación lógica. Una inversión con rendimientos ya generó
     * ingresos (RN-038.4): no se elimina, se liquida.
     */
    public function destroy(string $id): JsonResponse
    {
        $inversion = $this->inversionPropia($id);

        $tieneRendimientos = MovimientoInversion::where('inversion_id', $inversion->id)
            ->where('tipo', 'RENDIMIENTO')
            ->exists();

        if ($tieneRendimientos) {
            throw ValidationException::withMessages([
                'inversion' => 'La inversión tiene rendimientos registrados como ingresos (RN-038.4): no se puede eliminar. Regístrele una "Liquidación total".',
            ]);
        }

        DB::transaction(function () use ($inversion) {
            $anterior = $inversion->toArray();
            $inversion->delete();

            BitacoraService::registrar(
                accion: 'ELIMINAR_INVERSION',
                entidadAfectada: 'inversiones',
                entidadId: $inversion->id,
                valorAnterior: $anterior,
                justificacion: 'RNG-07: eliminación lógica de la inversión.',
                usuarioId: Auth::id()
            );
        });

        return response()->json(['mensaje' => 'Inversión eliminada.']);
    }

    /**
     * RNG-01: El global scope ya devolvió 404 para recursos ajenos. Aquí solo se
     * distingue el caso que la regla manda registrar en bitácora (RF-019).
     */
    private function inversionPropia(string $id): Inversion
    {
        $inversion = Inversion::find($id);

        if ($inversion !== null) {
            return $inversion;
        }

        $esDeOtro = Inversion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereKey($id)
            ->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'inversiones',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }

    /**
     * RN-037.1 a RN-037.3: los campos de CDT y bonos, y los de acciones, ETF y
     * criptoactivos, son obligatorios solo para esos tipos (CA-037.2).
     */
    private function reglas(Request $request): array
    {
        $conTasa = fn () => in_array($request->input('tipo'), Inversion::CON_TASA, true);
        $conUnidades = fn () => in_array($request->input('tipo'), Inversion::CON_UNIDADES, true);

        return [
            'nombre' => ['required', 'string', 'min:3', 'max:60'],
            'tipo' => ['required', Rule::in(Inversion::TIPOS)],
            'entidad' => ['nullable', 'string', 'max:80'],
            'moneda' => ['required', Rule::in(Inversion::MONEDAS)],
            'fecha_apertura' => ['required', 'date', 'before_or_equal:today'],
            'monto_inicial' => ['required', 'numeric', 'gt:0', 'max:999999999999.99', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'nivel_riesgo' => ['nullable', Rule::in(Inversion::NIVELES_RIESGO)],
            'tasa_cambio_usada' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,14}(\.\d{1,4})?$/'],
            // RN-037.2
            'tasa_ea' => [Rule::requiredIf($conTasa), 'nullable', 'numeric', 'between:0,100', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'plazo_dias' => [Rule::requiredIf($conTasa), 'nullable', 'integer', 'between:1,3650'],
            'periodicidad_intereses' => [Rule::requiredIf($conTasa), 'nullable', Rule::in(Inversion::PERIODICIDADES)],
            'fecha_vencimiento' => ['nullable', 'date', 'after:fecha_apertura'],
            // RN-037.3
            'simbolo' => ['nullable', 'string', 'max:15'],
            'unidades' => [Rule::requiredIf($conUnidades), 'nullable', 'numeric', 'gt:0', 'regex:/^\d{1,16}(\.\d{1,8})?$/'],
            'precio_unitario_compra' => [Rule::requiredIf($conUnidades), 'nullable', 'numeric', 'gt:0', 'regex:/^\d{1,16}(\.\d{1,8})?$/'],
        ];
    }

    private function mensajes(): array
    {
        return [
            'nombre.required' => 'Indique el nombre de la inversión.',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max' => 'El nombre admite máximo 60 caracteres.',
            'tipo.required' => 'Indique el tipo de inversión.',
            'tipo.in' => 'El tipo de inversión no es válido.',
            'entidad.max' => 'La entidad o emisor admite máximo 80 caracteres.',
            'moneda.required' => 'Indique la moneda de la inversión.',
            'moneda.in' => 'La moneda debe ser COP o USD.',
            'fecha_apertura.required' => 'Indique la fecha de apertura.',
            'fecha_apertura.date' => 'La fecha de apertura no tiene un formato válido (AAAA-MM-DD).',
            'fecha_apertura.before_or_equal' => 'La fecha de apertura no puede ser posterior a hoy.',
            'monto_inicial.required' => 'Indique el monto inicial.',
            'monto_inicial.numeric' => 'El monto inicial debe ser un número.',
            'monto_inicial.gt' => 'El monto inicial debe ser mayor que cero.',
            'monto_inicial.max' => 'El monto inicial no puede superar $ 999.999.999.999,99.',
            'monto_inicial.regex' => 'El monto inicial admite hasta 12 dígitos enteros y 2 decimales.',
            'nivel_riesgo.in' => 'El nivel de riesgo debe ser Bajo, Medio o Alto.',
            'tasa_cambio_usada.gt' => 'La tasa de cambio debe ser mayor que cero.',
            'tasa_cambio_usada.regex' => 'La tasa de cambio admite hasta 4 decimales.',
            'tasa_ea.required' => 'Indique la tasa pactada (EA) del CDT o bono (RN-037.2).',
            'tasa_ea.between' => 'La tasa pactada debe estar entre 0 % y 100 %.',
            'tasa_ea.regex' => 'La tasa pactada admite hasta 4 decimales.',
            'plazo_dias.required' => 'Indique el plazo en días del CDT o bono (RN-037.2).',
            'plazo_dias.integer' => 'El plazo debe ser un número entero de días.',
            'plazo_dias.between' => 'El plazo debe estar entre 1 y 3.650 días.',
            'periodicidad_intereses.required' => 'Indique la periodicidad de pago de intereses (RN-037.2).',
            'periodicidad_intereses.in' => 'La periodicidad de pago de intereses no es válida.',
            'fecha_vencimiento.required' => 'Indique la fecha de vencimiento.',
            'fecha_vencimiento.date' => 'La fecha de vencimiento no tiene un formato válido (AAAA-MM-DD).',
            'fecha_vencimiento.after' => 'La fecha de vencimiento debe ser posterior a la de apertura.',
            'simbolo.max' => 'El símbolo admite máximo 15 caracteres.',
            'unidades.required' => 'Indique la cantidad de unidades: es obligatoria para Acciones, ETF y Criptoactivos (CA-037.2).',
            'unidades.numeric' => 'La cantidad de unidades debe ser un número.',
            'unidades.gt' => 'La cantidad de unidades debe ser mayor que cero.',
            'unidades.regex' => 'La cantidad de unidades admite hasta 8 decimales.',
            'precio_unitario_compra.required' => 'Indique el precio unitario de compra (RN-037.3).',
            'precio_unitario_compra.gt' => 'El precio unitario de compra debe ser mayor que cero.',
            'precio_unitario_compra.regex' => 'El precio unitario de compra admite hasta 8 decimales.',
        ];
    }
}
