<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Models\PagoSeguridadSocial;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Services\BitacoraService;
use App\Services\GastoService;
use App\Services\SeguridadSocialService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MOD-05 — RF-035: Registro de pagos de aportes a seguridad social por PILA.
 *
 * Cada pago lleva su gasto de "Seguridad social" (RN-035.3): se crean, editan y
 * eliminan juntos, en la misma transacción. Por eso ese gasto no se toca desde
 * /gastos (GastoController lo rechaza).
 *
 * Solo responde JSON: esta entrega no incluye interfaz Blade.
 */
class PagoSeguridadSocialController extends Controller
{
    /** RNG-10: Tamaños de página admitidos. */
    private const POR_PAGINA = [10, 20, 50, 100];

    /**
     * RF-035: Pagos registrados, del periodo más reciente al más antiguo.
     */
    public function index(Request $request): JsonResponse
    {
        $porPagina = (int) $request->query('por_pagina', 20);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 20;
        }

        $pagos = PagoSeguridadSocial::query()
            ->orderByDesc('periodo')
            ->orderByDesc('fecha_pago')
            ->paginate($porPagina)
            ->withQueryString();

        return response()->json([
            'mensaje' => 'Pagos de seguridad social consultados.',
            'data' => [
                'pagos' => $pagos->items(),
                'meta' => [
                    'pagina_actual' => $pagos->currentPage(),
                    'por_pagina' => $pagos->perPage(),
                    'total' => $pagos->total(),
                    'ultima_pagina' => $pagos->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * RN-035.1 / RN-035.3: Registrar el pago y, en la misma transacción, el
     * gasto de "Seguridad social" por el total (CA-035.1).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->reglas(), $this->mensajes());
        $this->validarTotal($validated);
        $this->validarPeriodoLibre($validated);

        try {
            $pago = DB::transaction(function () use ($validated) {
                $pago = new PagoSeguridadSocial(array_merge($this->atributosPago($validated), [
                    'usuario_id' => Auth::id(),
                ]));

                $gasto = Gasto::create(array_merge(
                    SeguridadSocialService::atributosGastoPila($pago, $validated['medio_pago'] ?? SeguridadSocialService::MEDIO_PAGO_PILA),
                    ['usuario_id' => Auth::id(), 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO']
                ));

                $pago->gasto_id = $gasto->id;
                $pago->save();

                BitacoraService::registrar(
                    accion: 'CREAR_GASTO',
                    entidadAfectada: 'gastos',
                    entidadId: $gasto->id,
                    valorNuevo: $gasto->toArray(),
                    justificacion: 'RN-035.3: gasto creado automáticamente por el pago PILA del periodo '.$pago->periodo.'.',
                    usuarioId: Auth::id()
                );

                BitacoraService::registrar(
                    accion: 'REGISTRAR_PAGO_SEGURIDAD_SOCIAL',
                    entidadAfectada: 'pagos_seguridad_social',
                    entidadId: $pago->id,
                    valorNuevo: $pago->fresh()->toArray(),
                    justificacion: 'RF-035: pago de aportes del periodo '.$pago->periodo.'.',
                    usuarioId: Auth::id()
                );

                return $pago->fresh();
            });
        } catch (UniqueConstraintViolationException $e) {
            throw $this->rechazoPorPeriodoOcupado($e, $validated['periodo']);
        }

        return response()->json([
            'mensaje' => 'Pago registrado. Se creó su gasto en "Seguridad social".',
            'data' => $this->presentar($pago),
        ], 201);
    }

    /**
     * RF-035: Detalle del pago, con su gasto y la comparación con la
     * liquidación estimada del periodo (RN-035.5).
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'mensaje' => 'Pago consultado.',
            'data' => $this->presentar($this->pagoPropio($id)),
        ]);
    }

    /**
     * RN-035.3: Editar el pago actualiza su gasto, para que los dos sigan
     * coincidiendo en fecha y monto.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $pago = $this->pagoPropio($id);
        $validated = $request->validate($this->reglas(), $this->mensajes());
        $this->validarTotal($validated);
        $this->validarPeriodoLibre($validated, $pago->id);

        try {
            $pago = DB::transaction(function () use ($pago, $validated) {
                $anterior = $pago->toArray();
                $pago->update($this->atributosPago($validated));

                $gasto = $pago->gasto_id === null ? null : Gasto::find($pago->gasto_id);
                $medioPago = $validated['medio_pago'] ?? $gasto?->medio_pago ?? SeguridadSocialService::MEDIO_PAGO_PILA;
                $atributos = SeguridadSocialService::atributosGastoPila($pago, $medioPago);

                if ($gasto === null) {
                    $gasto = Gasto::create(array_merge($atributos, [
                        'usuario_id' => Auth::id(),
                        'origen' => 'MANUAL',
                        'estado' => 'CONFIRMADO',
                    ]));
                    $pago->update(['gasto_id' => $gasto->id]);
                    $accionGasto = 'CREAR_GASTO';
                    $gastoAnterior = null;
                } else {
                    $gastoAnterior = $gasto->toArray();
                    $gasto->update($atributos);
                    $accionGasto = 'EDITAR_GASTO';
                }

                BitacoraService::registrar(
                    accion: $accionGasto,
                    entidadAfectada: 'gastos',
                    entidadId: $gasto->id,
                    valorAnterior: $gastoAnterior,
                    valorNuevo: $gasto->fresh()->toArray(),
                    justificacion: 'RN-035.3: gasto actualizado por la edición del pago PILA del periodo '.$pago->periodo.'.',
                    usuarioId: Auth::id()
                );

                BitacoraService::registrar(
                    accion: 'EDITAR_PAGO_SEGURIDAD_SOCIAL',
                    entidadAfectada: 'pagos_seguridad_social',
                    entidadId: $pago->id,
                    valorAnterior: $anterior,
                    valorNuevo: $pago->fresh()->toArray(),
                    justificacion: 'RF-035: edición del pago de aportes.',
                    usuarioId: Auth::id()
                );

                return $pago->fresh();
            });
        } catch (UniqueConstraintViolationException $e) {
            throw $this->rechazoPorPeriodoOcupado($e, $validated['periodo']);
        }

        return response()->json([
            'mensaje' => 'Pago actualizado junto con su gasto.',
            'data' => $this->presentar($pago),
        ]);
    }

    /**
     * RN-035.3 / RNG-07: Eliminar el pago elimina también su gasto. Los dos se
     * eliminan de forma lógica.
     */
    public function destroy(string $id): JsonResponse
    {
        $pago = $this->pagoPropio($id);

        DB::transaction(function () use ($pago) {
            $gasto = $pago->gasto_id === null ? null : Gasto::find($pago->gasto_id);

            if ($gasto !== null) {
                $gastoAnterior = $gasto->toArray();
                $gasto->delete();

                BitacoraService::registrar(
                    accion: 'ELIMINAR_GASTO',
                    entidadAfectada: 'gastos',
                    entidadId: $gasto->id,
                    valorAnterior: $gastoAnterior,
                    justificacion: 'RN-035.3: gasto eliminado junto con el pago PILA del periodo '.$pago->periodo.'.',
                    usuarioId: Auth::id()
                );
            }

            $anterior = $pago->toArray();
            $pago->delete();

            BitacoraService::registrar(
                accion: 'ELIMINAR_PAGO_SEGURIDAD_SOCIAL',
                entidadAfectada: 'pagos_seguridad_social',
                entidadId: $pago->id,
                valorAnterior: $anterior,
                justificacion: 'RF-035: eliminación del pago de aportes y de su gasto.',
                usuarioId: Auth::id()
            );
        });

        return response()->json(['mensaje' => 'Pago eliminado junto con su gasto.']);
    }

    /**
     * RN-035.4: Aportes obligatorios de salud y pensión pagados en el año, que
     * se acumulan como ingreso no constitutivo de renta (RF-043).
     */
    public function resumenAnual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
        ], [
            'anio.integer' => 'El año debe ser un número entero.',
            'anio.between' => 'El año debe estar entre 2000 y 2100.',
        ]);

        $anio = (int) ($validated['anio'] ?? CarbonImmutable::today()->year);

        return response()->json([
            'mensaje' => 'Resumen anual de aportes consultado.',
            'data' => SeguridadSocialService::resumenAnual(Auth::id(), $anio),
        ]);
    }

    /**
     * RF-035: el pago con su gasto y la comparación con la liquidación
     * (RN-035.5). La comparación es un cálculo de seguridad social, así que
     * lleva el aviso de la RNG-08.
     */
    private function presentar(PagoSeguridadSocial $pago): array
    {
        return array_merge($pago->toArray(), [
            'gasto' => $pago->gasto_id === null ? null : Gasto::find($pago->gasto_id),
            'comparacion' => SeguridadSocialService::diferenciasConLiquidacion($pago),
            'aviso_legal' => SeguridadSocialService::AVISO_LEGAL,
        ]);
    }

    private function atributosPago(array $validated): array
    {
        return [
            'periodo' => $validated['periodo'],
            'fecha_pago' => $validated['fecha_pago'],
            'numero_planilla' => $validated['numero_planilla'] ?? null,
            'es_correccion' => (bool) ($validated['es_correccion'] ?? false),
            'salud' => (string) $validated['salud'],
            'pension' => (string) $validated['pension'],
            'fsp' => (string) ($validated['fsp'] ?? '0'),
            'arl' => (string) ($validated['arl'] ?? '0'),
            'intereses_mora' => (string) ($validated['intereses_mora'] ?? '0'),
        ];
    }

    /**
     * RNG-09: el total del pago, que es el monto del gasto, debe ser mayor que
     * cero y caber en el rango permitido.
     *
     * @throws ValidationException
     */
    private function validarTotal(array $validated): void
    {
        $total = SeguridadSocialService::totalPago($validated);

        if (! GastoService::montoEnRango($total)) {
            throw ValidationException::withMessages([
                'salud' => bccomp($total, '0', 2) === 1
                    ? 'El total del pago no puede superar $ 999.999.999.999,99.'
                    : 'El pago debe tener un valor mayor que cero.',
            ]);
        }
    }

    /**
     * CA-035.2: "Al intentar registrar otra vez el periodo 2026-03 sin
     * 'corrección', el sistema lo rechaza".
     *
     * RN-035.2: la planilla de corrección registra la diferencia frente al pago
     * original, así que el periodo debe tenerlo (sin contar el pago editado).
     *
     * @throws ValidationException
     */
    private function validarPeriodoLibre(array $validated, ?string $exceptoPagoId = null): void
    {
        $hayOriginal = SeguridadSocialService::periodoYaPagado($validated['periodo'], $exceptoPagoId);

        if ((bool) ($validated['es_correccion'] ?? false)) {
            if (! $hayOriginal) {
                throw ValidationException::withMessages([
                    'es_correccion' => "El periodo {$validated['periodo']} no tiene un pago original: la planilla de corrección registra la diferencia frente a ese pago. Registre primero el pago del periodo.",
                ]);
            }

            return;
        }

        if ($hayOriginal) {
            throw $this->periodoOcupado($validated['periodo']);
        }
    }

    /**
     * CA-035.2: dos registros simultáneos del mismo periodo pasan los dos por
     * validarPeriodoLibre y el índice uq_pago_periodo frena al segundo. Se
     * responde el mismo 422 en lugar de un 500. Otra violación se relanza tal cual.
     */
    private function rechazoPorPeriodoOcupado(UniqueConstraintViolationException $e, string $periodo): \Throwable
    {
        return str_contains($e->getMessage(), 'uq_pago_periodo') ? $this->periodoOcupado($periodo) : $e;
    }

    private function periodoOcupado(string $periodo): ValidationException
    {
        return ValidationException::withMessages([
            'periodo' => "Ya hay un pago registrado para el periodo {$periodo}. Si es una planilla de corrección, márquela como tal.",
        ]);
    }

    /**
     * RNG-01: El global scope ya devolvió 404 para recursos ajenos. Aquí solo se
     * distingue el caso que la regla manda registrar en bitácora (RF-019).
     */
    private function pagoPropio(string $id): PagoSeguridadSocial
    {
        $pago = PagoSeguridadSocial::find($id);

        if ($pago !== null) {
            return $pago;
        }

        $esDeOtro = PagoSeguridadSocial::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereKey($id)
            ->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'pagos_seguridad_social',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }

    /**
     * RN-035.1: Campos del pago. El soporte adjunto (RF-030) no está en el
     * alcance de esta entrega.
     */
    private function reglas(): array
    {
        $monto = ['numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];

        return [
            'periodo' => [
                'required',
                'string',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
                function (string $atributo, mixed $valor, \Closure $falla) {
                    if (is_string($valor)
                        && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)
                        && SeguridadSocialService::inicioDelPeriodo($valor)->gt(CarbonImmutable::today()->startOfMonth())) {
                        $falla('El periodo cotizado no puede ser posterior al mes actual.');
                    }
                },
            ],
            'fecha_pago' => ['required', 'date', 'before_or_equal:today'],
            'numero_planilla' => ['nullable', 'string', 'max:20'],
            'es_correccion' => ['nullable', 'boolean'],
            'salud' => ['required', ...$monto],
            'pension' => ['required', ...$monto],
            'fsp' => ['nullable', ...$monto],
            'arl' => ['nullable', ...$monto],
            'intereses_mora' => ['nullable', ...$monto],
            'medio_pago' => ['nullable', Rule::in(Gasto::MEDIOS_PAGO)],
        ];
    }

    private function mensajes(): array
    {
        $mensajes = [
            'periodo.required' => 'Indique el periodo cotizado (AAAA-MM).',
            'periodo.regex' => 'El periodo debe tener el formato AAAA-MM.',
            'fecha_pago.required' => 'Indique la fecha de pago.',
            'fecha_pago.date' => 'La fecha de pago no tiene un formato válido (AAAA-MM-DD).',
            'fecha_pago.before_or_equal' => 'La fecha de pago no puede ser posterior a hoy.',
            'numero_planilla.max' => 'El número de planilla admite máximo 20 caracteres.',
            'es_correccion.boolean' => 'La marca de planilla de corrección debe ser verdadero o falso.',
            'medio_pago.in' => 'El medio de pago no es válido.',
        ];

        $nombres = [
            'salud' => 'salud',
            'pension' => 'pensión',
            'fsp' => 'FSP',
            'arl' => 'ARL',
            'intereses_mora' => 'intereses de mora',
        ];

        foreach ($nombres as $campo => $nombre) {
            $mensajes["{$campo}.required"] = "Indique el valor pagado de {$nombre}.";
            $mensajes["{$campo}.numeric"] = "El valor de {$nombre} debe ser un número.";
            $mensajes["{$campo}.min"] = "El valor de {$nombre} no puede ser negativo.";
            $mensajes["{$campo}.regex"] = "El valor de {$nombre} admite hasta 12 dígitos enteros y 2 decimales.";
        }

        return $mensajes;
    }
}
