<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
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
 * MOD-06 — RF-038: Movimientos de una inversión, base del cálculo de
 * rentabilidad (RF-040).
 *
 * Un rendimiento crea su ingreso de "Rentas de capital" en la misma
 * transacción (RN-038.4), y ese ingreso no se toca desde /ingresos
 * (IngresoController lo rechaza). Los movimientos no se editan: se eliminan y
 * se vuelven a registrar.
 *
 * Solo responde JSON: esta entrega no incluye interfaz Blade.
 */
class MovimientoInversionController extends Controller
{
    /** RNG-10: Tamaños de página admitidos. */
    private const POR_PAGINA = [10, 20, 50, 100];

    /**
     * RF-038: Movimientos de la inversión, del más reciente al más antiguo.
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $inversion = $this->inversionPropia($id);

        $porPagina = (int) $request->query('por_pagina', 20);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 20;
        }

        $movimientos = MovimientoInversion::where('inversion_id', $inversion->id)
            ->orderByDesc('fecha')
            ->orderByDesc('creado_en')
            ->paginate($porPagina)
            ->withQueryString();

        return response()->json([
            'mensaje' => 'Movimientos consultados.',
            'data' => [
                'movimientos' => $movimientos->items(),
                'meta' => [
                    'pagina_actual' => $movimientos->currentPage(),
                    'por_pagina' => $movimientos->perPage(),
                    'total' => $movimientos->total(),
                    'ultima_pagina' => $movimientos->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * RN-038.1 a RN-038.4: Registrar un movimiento.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $inversion = $this->inversionPropia($id);
        $validated = $request->validate($this->reglas($inversion), $this->mensajes());

        if ($inversion->estaLiquidada()) {
            throw ValidationException::withMessages([
                'inversion' => 'La inversión está liquidada: no admite movimientos nuevos (RN-038.3).',
            ]);
        }

        $tipo = $validated['tipo'];
        $monto = (string) $validated['monto'];
        $fecha = $validated['fecha'];

        // RNG-09: solo la liquidación total admite 0 (la inversión se perdió).
        if ($tipo !== 'LIQUIDACION_TOTAL' && bccomp($monto, '0', 2) !== 1) {
            throw ValidationException::withMessages([
                'monto' => 'El monto debe ser mayor que cero.',
            ]);
        }

        $retencion = $tipo === 'RENDIMIENTO' ? (string) ($validated['retencion'] ?? '0') : '0';
        $unidades = $this->unidadesDelMovimiento($inversion, $tipo, $validated);

        // RN-038.3: un retiro no puede superar el último valor de mercado registrado.
        if ($tipo === 'RETIRO_PARCIAL') {
            $valoracion = InversionService::valorActual($inversion);

            if ($valoracion !== null && bccomp($monto, (string) $valoracion->valor, 2) === 1) {
                throw ValidationException::withMessages([
                    'monto' => "El retiro no puede superar el último valor de mercado registrado ({$inversion->moneda} {$valoracion->valor} del {$valoracion->fecha->format('d/m/Y')}) (RN-038.3).",
                ]);
            }
        }

        // RN-038.3: la liquidación total es lo último que le pasa a la inversión.
        if ($tipo === 'LIQUIDACION_TOTAL') {
            $this->validarLiquidacionAlFinal($inversion, CarbonImmutable::parse($fecha));
        }

        // RN-021.3 / RN-041.4: el monto en COP con la tasa de la fecha del movimiento.
        $tasa = TrmService::tasaPara($inversion->moneda, $validated['tasa_cambio_usada'] ?? null, $fecha);
        $montoCop = IngresoService::convertirACop($monto, $tasa);

        [$movimiento, $movimientoRetencion, $ingreso] = DB::transaction(function () use ($inversion, $validated, $tipo, $monto, $fecha, $retencion, $unidades, $tasa, $montoCop) {
            $ingreso = $tipo === 'RENDIMIENTO'
                ? $this->registrarIngresoDelRendimiento($inversion, $monto, $retencion, $tasa, $fecha)
                : null;

            $movimiento = MovimientoInversion::create([
                'usuario_id' => Auth::id(),
                'inversion_id' => $inversion->id,
                'tipo' => $tipo,
                'fecha' => $fecha,
                'monto' => $monto,
                'tasa_cambio_usada' => $tasa,
                'monto_cop' => $montoCop,
                'unidades' => $unidades,
                'nota' => $validated['nota'] ?? null,
                'ingreso_id' => $ingreso?->id,
            ]);

            // RN-038.4: la retención informada queda también como movimiento,
            // atado al mismo ingreso (RN-040.1 la resta de la ganancia).
            $movimientoRetencion = null;
            if (bccomp($retencion, '0', 2) === 1) {
                $movimientoRetencion = MovimientoInversion::create([
                    'usuario_id' => Auth::id(),
                    'inversion_id' => $inversion->id,
                    'tipo' => 'RETENCION',
                    'fecha' => $fecha,
                    'monto' => $retencion,
                    'tasa_cambio_usada' => $tasa,
                    'monto_cop' => IngresoService::convertirACop($retencion, $tasa),
                    'nota' => 'Retención en la fuente del rendimiento (RN-038.4)',
                    'ingreso_id' => $ingreso->id,
                ]);
            }

            // RN-038.3: la liquidación total deja la inversión "Liquidada" con valor actual 0.
            if ($tipo === 'LIQUIDACION_TOTAL') {
                $anterior = $inversion->toArray();
                $inversion->update(['estado' => 'LIQUIDADA', 'liquidada_en' => $fecha]);

                ValoracionInversion::updateOrCreate(
                    ['inversion_id' => $inversion->id, 'fecha' => $fecha],
                    [
                        'usuario_id' => Auth::id(),
                        'valor' => '0',
                        'precio_unitario' => null,
                        'unidades' => null,
                        'origen' => 'LIQUIDACION',
                    ]
                );

                BitacoraService::registrar(
                    accion: 'LIQUIDAR_INVERSION',
                    entidadAfectada: 'inversiones',
                    entidadId: $inversion->id,
                    valorAnterior: $anterior,
                    valorNuevo: $inversion->fresh()->toArray(),
                    justificacion: 'RN-038.3: liquidación total; la inversión queda Liquidada con valor actual 0.',
                    usuarioId: Auth::id()
                );
            }

            BitacoraService::registrar(
                accion: 'REGISTRAR_MOVIMIENTO_INVERSION',
                entidadAfectada: 'movimientos_inversion',
                entidadId: $movimiento->id,
                valorNuevo: $movimiento->fresh()->toArray(),
                justificacion: "RF-038: movimiento {$tipo} de la inversión {$inversion->id}.",
                usuarioId: Auth::id()
            );

            return [$movimiento->fresh(), $movimientoRetencion?->fresh(), $ingreso?->fresh()];
        });

        return response()->json([
            'mensaje' => $ingreso === null
                ? 'Movimiento registrado.'
                : 'Movimiento registrado. Se creó su ingreso en "Rentas de capital".',
            'data' => [
                'movimiento' => $movimiento,
                'retencion' => $movimientoRetencion,
                'ingreso' => $ingreso,
                'inversion' => InversionService::presentar($inversion->fresh(), CarbonImmutable::today()),
            ],
        ], 201);
    }

    /**
     * RNG-07: Eliminación lógica de un movimiento.
     *
     * - El aporte inicial no se elimina (RN-037.4).
     * - Una inversión liquidada solo admite eliminar su liquidación, que la
     *   devuelve a Activa (o Vencida) y quita su valoración en 0.
     * - Un rendimiento se lleva su ingreso y su retención (RN-038.4); la
     *   retención atada a un ingreso no se elimina sola.
     */
    public function destroy(string $id, string $movimientoId): JsonResponse
    {
        $inversion = $this->inversionPropia($id);

        $movimiento = MovimientoInversion::where('inversion_id', $inversion->id)
            ->whereKey($movimientoId)
            ->first();

        if ($movimiento === null) {
            abort(404);
        }

        if ($movimiento->es_inicial) {
            throw ValidationException::withMessages([
                'movimiento' => 'El aporte inicial no se elimina: es el monto inicial de la inversión (RN-037.4).',
            ]);
        }

        if ($inversion->estaLiquidada() && $movimiento->tipo !== 'LIQUIDACION_TOTAL') {
            throw ValidationException::withMessages([
                'movimiento' => 'La inversión está liquidada: elimine primero la liquidación total.',
            ]);
        }

        if ($movimiento->tipo === 'RETENCION' && $movimiento->ingreso_id !== null) {
            throw ValidationException::withMessages([
                'movimiento' => 'Esta retención pertenece a un rendimiento: elimine el rendimiento y se eliminará con él.',
            ]);
        }

        DB::transaction(function () use ($inversion, $movimiento) {
            if ($movimiento->tipo === 'RENDIMIENTO' && $movimiento->ingreso_id !== null) {
                MovimientoInversion::where('ingreso_id', $movimiento->ingreso_id)
                    ->whereKeyNot($movimiento->id)
                    ->get()
                    ->each(fn (MovimientoInversion $retencion) => $retencion->delete());

                $ingreso = Ingreso::find($movimiento->ingreso_id);

                if ($ingreso !== null) {
                    $ingresoAnterior = $ingreso->toArray();
                    $ingreso->delete();

                    BitacoraService::registrar(
                        accion: 'ELIMINAR_INGRESO',
                        entidadAfectada: 'ingresos',
                        entidadId: $ingreso->id,
                        valorAnterior: $ingresoAnterior,
                        justificacion: 'RN-038.4: ingreso eliminado junto con el rendimiento de la inversión.',
                        usuarioId: Auth::id()
                    );
                }
            }

            if ($movimiento->tipo === 'LIQUIDACION_TOTAL') {
                ValoracionInversion::where('inversion_id', $inversion->id)
                    ->where('origen', 'LIQUIDACION')
                    ->where('fecha', $movimiento->fecha->toDateString())
                    ->delete();

                $inversion->update([
                    'estado' => InversionService::estadoSegunVencimiento(
                        $inversion->fecha_vencimiento === null ? null : CarbonImmutable::parse($inversion->fecha_vencimiento),
                        CarbonImmutable::today()
                    ),
                    'liquidada_en' => null,
                ]);
            }

            $anterior = $movimiento->toArray();
            $movimiento->delete();

            BitacoraService::registrar(
                accion: 'ELIMINAR_MOVIMIENTO_INVERSION',
                entidadAfectada: 'movimientos_inversion',
                entidadId: $movimiento->id,
                valorAnterior: $anterior,
                justificacion: "RNG-07: eliminación del movimiento {$movimiento->tipo} de la inversión {$inversion->id}.",
                usuarioId: Auth::id()
            );
        });

        return response()->json([
            'mensaje' => 'Movimiento eliminado.',
            'data' => InversionService::presentar($inversion->fresh(), CarbonImmutable::today()),
        ]);
    }

    /**
     * RN-038.4 / CA-038.1: ingreso de "Rentas de capital" por el rendimiento,
     * con la retención informada, en la moneda y con la tasa del movimiento.
     */
    private function registrarIngresoDelRendimiento(Inversion $inversion, string $monto, string $retencion, string $tasa, string $fecha): Ingreso
    {
        $fuente = InversionService::fuenteDeRendimientos(Auth::id());

        $montos = IngresoService::prepararMontos([
            'moneda' => $inversion->moneda,
            'monto_original' => $monto,
            'tasa_cambio_usada' => $tasa,
            'retencion_fuente' => $retencion,
            'clasificacion_tributaria' => InversionService::CLASIFICACION_RENDIMIENTOS,
        ], $fuente, $fecha);

        $ingreso = Ingreso::create(array_merge($montos, [
            'usuario_id' => Auth::id(),
            'fuente_id' => $fuente->id,
            'fecha_recepcion' => $fecha,
            'origen' => 'MANUAL',
            'estado' => 'CONFIRMADO',
            'descripcion' => "Rendimiento de la inversión «{$inversion->nombre}» (RN-038.4)",
        ]));

        BitacoraService::registrar(
            accion: 'CREAR_INGRESO',
            entidadAfectada: 'ingresos',
            entidadId: $ingreso->id,
            valorNuevo: $ingreso->fresh()->toArray(),
            justificacion: 'RN-038.4: ingreso creado automáticamente por el rendimiento de la inversión '.$inversion->id.'.',
            usuarioId: Auth::id()
        );

        return $ingreso;
    }

    /**
     * RN-037.3 / RN-038.2: unidades del movimiento, solo en inversiones con
     * unidades. Un retiro no puede llevarse más de las que hay, y la
     * liquidación total se lleva todas.
     *
     * @throws ValidationException
     */
    private function unidadesDelMovimiento(Inversion $inversion, string $tipo, array $validated): ?string
    {
        if (! $inversion->tieneUnidades()) {
            return null;
        }

        $actuales = InversionService::unidadesActuales($inversion);

        if ($tipo === 'LIQUIDACION_TOTAL') {
            return bccomp($actuales, '0', 8) === 1 ? $actuales : null;
        }

        if (! in_array($tipo, ['APORTE', 'RETIRO_PARCIAL'], true) || ! isset($validated['unidades'])) {
            return null;
        }

        $unidades = (string) $validated['unidades'];

        if ($tipo === 'RETIRO_PARCIAL' && bccomp($unidades, $actuales, 8) === 1) {
            throw ValidationException::withMessages([
                'unidades' => "El retiro no puede llevarse más unidades de las que tiene la inversión ({$actuales}).",
            ]);
        }

        return $unidades;
    }

    /**
     * RN-038.3: "Una 'Liquidación total' deja la inversión ... con valor
     * actual 0". El valor actual es la valoración más reciente (RN-039.3), así
     * que una valoración o un movimiento con fecha posterior a la liquidación
     * la dejaría con otro valor. La misma fecha sí se admite: la valoración en
     * 0 reemplaza a la de ese día.
     *
     * @throws ValidationException
     */
    private function validarLiquidacionAlFinal(Inversion $inversion, CarbonImmutable $fecha): void
    {
        $ultimaFecha = collect([
            ValoracionInversion::where('inversion_id', $inversion->id)->max('fecha'),
            MovimientoInversion::where('inversion_id', $inversion->id)->max('fecha'),
        ])->filter()->max();

        if ($ultimaFecha !== null && CarbonImmutable::parse($ultimaFecha)->gt($fecha)) {
            throw ValidationException::withMessages([
                'fecha' => 'La liquidación total no puede ser anterior al '.CarbonImmutable::parse($ultimaFecha)->format('d/m/Y')
                    .': la inversión tiene una valoración o un movimiento de esa fecha (RN-038.3). Liquídela en esa fecha o después.',
            ]);
        }
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
     * RN-038.2: fecha igual o posterior a la apertura (CA-038.2) y no futura.
     */
    private function reglas(Inversion $inversion): array
    {
        $apertura = $inversion->fecha_apertura->toDateString();

        return [
            'tipo' => ['required', Rule::in(MovimientoInversion::TIPOS)],
            'fecha' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$apertura],
            'monto' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'unidades' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,16}(\.\d{1,8})?$/'],
            // RN-038.4: la retención informada del rendimiento.
            'retencion' => ['nullable', 'numeric', 'min:0', 'lte:monto', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'nota' => ['nullable', 'string', 'max:255'],
            'tasa_cambio_usada' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d{1,14}(\.\d{1,4})?$/'],
        ];
    }

    private function mensajes(): array
    {
        return [
            'tipo.required' => 'Indique el tipo de movimiento.',
            'tipo.in' => 'El tipo de movimiento no es válido (RN-038.1).',
            'fecha.required' => 'Indique la fecha del movimiento.',
            'fecha.date' => 'La fecha no tiene un formato válido (AAAA-MM-DD).',
            'fecha.before_or_equal' => 'La fecha del movimiento no puede ser posterior a hoy.',
            'fecha.after_or_equal' => 'La fecha del movimiento no puede ser anterior a la apertura de la inversión (CA-038.2).',
            'monto.required' => 'Indique el monto del movimiento.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.min' => 'El monto no puede ser negativo.',
            'monto.max' => 'El monto no puede superar $ 999.999.999.999,99.',
            'monto.regex' => 'El monto admite hasta 12 dígitos enteros y 2 decimales.',
            'unidades.gt' => 'Las unidades deben ser mayores que cero.',
            'unidades.regex' => 'Las unidades admiten hasta 8 decimales.',
            'retencion.min' => 'La retención no puede ser negativa.',
            'retencion.lte' => 'La retención no puede superar el monto del rendimiento.',
            'retencion.regex' => 'La retención admite hasta 12 dígitos enteros y 2 decimales.',
            'nota.max' => 'La nota admite máximo 255 caracteres.',
            'tasa_cambio_usada.gt' => 'La tasa de cambio debe ser mayor que cero.',
            'tasa_cambio_usada.regex' => 'La tasa de cambio admite hasta 4 decimales.',
        ];
    }
}
