<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Credito;
use App\Models\CuotaCredito;
use App\Models\Gasto;
use App\Models\ImputacionPagoCredito;
use App\Models\PagoCredito;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * MOD-08 — Créditos, fase 2: plan guardado, pagos con imputación, abonos
 * extraordinarios, mora y estado de cuenta (documento de créditos, citado
 * como "Créditos RF-xx").
 *
 * Todo lo que se muestra se deriva del plan vigente y de las imputaciones de
 * los pagos no reversados: no hay saldos guardados que puedan descuadrarse.
 * Los montos son pesos enteros (Créditos 2.7).
 */
class PagoCreditoService
{
    /** Subcategoría del catálogo para el gasto de cada pago (decisión del usuario). */
    public const CATEGORIA_GASTO = 'Obligaciones financieras';
    public const SUBCATEGORIA_GASTO = 'Cuota de crédito / hipoteca';

    /** Filas por INSERT al guardar un plan (un semanal a 30 años son 1.560). */
    private const LOTE = 500;

    /*
    |--------------------------------------------------------------------------
    | Plan guardado (Créditos RF-22, RF-26)
    |--------------------------------------------------------------------------
    */

    /**
     * Créditos RF-22: guarda el plan del crédito como la versión indicada, con
     * interés y capital por cuota en campos separados.
     */
    public static function crearPlan(Credito $credito, int $version): void
    {
        $tabla = CreditoService::tablaAmortizacion(
            (string) $credito->monto,
            (string) $credito->tasa_periodica,
            $credito->periodicidad,
            $credito->numero_cuotas,
            $credito->fecha_inicio->toDateString(),
            $credito->sistema_amortizacion,
            self::opcionesPlan($credito)
        );

        self::insertarCuotas($credito, $version, array_map(fn (array $fila) => self::filaGuardable($fila), $tabla));
    }

    /**
     * Créditos RF-26: al editar las condiciones de un crédito sin pagos
     * vigentes, el plan entero se reemplaza por una versión nueva y la
     * anterior se conserva.
     */
    public static function reemplazarPlan(Credito $credito): void
    {
        $version = $credito->version_plan_vigente + 1;

        CuotaCredito::where('credito_id', $credito->id)
            ->whereNull('reemplazada_en_version')
            ->update(['reemplazada_en_version' => $version]);

        $credito->update(['version_plan_vigente' => $version]);
        self::crearPlan($credito, $version);
    }

    /*
    |--------------------------------------------------------------------------
    | Situación a una fecha (Créditos RF-25, RF-37, RF-38, RF-39, RF-41..43)
    |--------------------------------------------------------------------------
    */

    /**
     * Situación del crédito a una fecha de corte, a partir del plan vigente y
     * de las imputaciones de los pagos no reversados.
     *
     * - Cuota exigible: vence hasta la fecha. Vencida: vence antes y no está
     *   pagada (Créditos RF-38: se vence el día siguiente a su fecha).
     * - Mora (RF-39, RN-09): solo sobre el capital vencido, por tramos entre
     *   pagos, con la EA de mora.
     * - Interés causado (2.5): sobre el capital aún no exigible, desde el
     *   último vencimiento o el último abono a capital.
     * - Saldo para cancelar (2.5, RF-37): capital pendiente + interés exigible
     *   + interés causado + mora.
     *
     * @return array<string, mixed>
     */
    public static function situacion(Credito $credito, CarbonImmutable $fecha): array
    {
        $cuotas = CuotaCredito::where('credito_id', $credito->id)
            ->whereNull('reemplazada_en_version')
            ->orderBy('numero')
            ->get();

        $pagos = PagoCredito::where('credito_id', $credito->id)
            ->vigentes()
            ->where('fecha', '<=', $fecha->toDateString())
            ->with('imputaciones')
            ->orderBy('numero')
            ->get();

        $pagadoCuota = [];
        $capitalPorFecha = [];
        $pagadoConcepto = array_fill_keys(ImputacionPagoCredito::CONCEPTOS, '0');
        $ultimoAbono = null;

        foreach ($pagos as $pago) {
            $fechaPago = $pago->fecha->toDateString();

            if ($pago->aplicacion_excedente === 'ABONO_CAPITAL') {
                $ultimoAbono = CarbonImmutable::parse($fechaPago);
            }

            foreach ($pago->imputaciones as $imputacion) {
                $valor = self::peso($imputacion->valor);
                $pagadoConcepto[$imputacion->concepto] = bcadd($pagadoConcepto[$imputacion->concepto], $valor, 0);

                if ($imputacion->cuota_id === null) {
                    continue;
                }

                $pagadoCuota[$imputacion->cuota_id][$imputacion->concepto] =
                    bcadd($pagadoCuota[$imputacion->cuota_id][$imputacion->concepto] ?? '0', $valor, 0);

                if ($imputacion->concepto === 'CAPITAL') {
                    $capitalPorFecha[$imputacion->cuota_id][$fechaPago] =
                        bcadd($capitalPorFecha[$imputacion->cuota_id][$fechaPago] ?? '0', $valor, 0);
                }
            }
        }

        $tasaMora = $credito->tasa_mora_ea !== null ? (string) $credito->tasa_mora_ea : null;
        $periodoInicio = CarbonImmutable::parse($credito->fecha_inicio->toDateString());
        $filas = [];

        foreach ($cuotas as $cuota) {
            $vence = CarbonImmutable::parse($cuota->fecha_vencimiento->toDateString());
            $interes = self::peso($cuota->interes);
            $capital = self::peso($cuota->capital);
            $interesPagado = $pagadoCuota[$cuota->id]['INTERES_CORRIENTE'] ?? '0';
            $capitalPagado = $pagadoCuota[$cuota->id]['CAPITAL'] ?? '0';
            $moraPagada = $pagadoCuota[$cuota->id]['INTERES_MORA'] ?? '0';
            $seguro = self::peso($cuota->seguro);
            $cargos = self::peso($cuota->cargos);
            $seguroPagado = $pagadoCuota[$cuota->id]['SEGURO'] ?? '0';
            $cargoPagado = $pagadoCuota[$cuota->id]['CARGO'] ?? '0';

            $interesPendiente = self::noNegativo(bcsub($interes, $interesPagado, 0));
            $capitalPendiente = self::noNegativo(bcsub($capital, $capitalPagado, 0));
            $seguroPendiente = self::noNegativo(bcsub($seguro, $seguroPagado, 0));
            $cargoPendiente = self::noNegativo(bcsub($cargos, $cargoPagado, 0));
            // Créditos RF-25: pagada es con sus seguros y cargos.
            $pagada = self::esCero($interesPendiente) && self::esCero($capitalPendiente)
                && self::esCero($seguroPendiente) && self::esCero($cargoPendiente);

            $moraCausada = $tasaMora === null
                ? '0'
                : self::moraCuota($capital, $vence, $capitalPorFecha[$cuota->id] ?? [], $fecha, $tasaMora);
            $moraPendiente = self::noNegativo(bcsub($moraCausada, $moraPagada, 0));

            $vencida = ! $pagada && $vence->lt($fecha);
            $hayPago = ! self::esCero(bcadd(bcadd($interesPagado, $capitalPagado, 0), bcadd($seguroPagado, $cargoPagado, 0), 0));

            $estado = match (true) {
                $pagada => 'PAGADA',
                $vencida => 'VENCIDA',
                $hayPago => 'PAGADA_PARCIAL',
                default => 'PENDIENTE',
            };

            if ($vence->lte($fecha) && $vence->gt($periodoInicio)) {
                $periodoInicio = $vence;
            }

            $filas[] = [
                'id' => $cuota->id,
                'numero' => $cuota->numero,
                'version_plan' => $cuota->version_plan,
                'fecha' => $vence->toDateString(),
                'saldo_inicial' => self::peso($cuota->saldo_inicial),
                'cuota' => self::peso($cuota->cuota),
                'interes' => $interes,
                'abono_capital' => $capital,
                'saldo_final' => self::peso($cuota->saldo_final),
                'seguro' => $seguro,
                'cargos' => $cargos,
                'cuota_total' => bcadd(self::peso($cuota->cuota), bcadd($seguro, $cargos, 0), 0),
                'estado' => $estado,
                'interes_pagado' => $interesPagado,
                'capital_pagado' => $capitalPagado,
                'seguro_pagado' => $seguroPagado,
                'cargo_pagado' => $cargoPagado,
                'interes_pendiente' => $interesPendiente,
                'capital_pendiente' => $capitalPendiente,
                'seguro_pendiente' => $seguroPendiente,
                'cargo_pendiente' => $cargoPendiente,
                'mora_pendiente' => $moraPendiente,
                'dias_mora' => $vencida ? self::dias($vence, $fecha) : 0,
                'exigible' => $vence->lte($fecha),
            ];
        }

        $sumar = fn (callable $filtro, string $campo) => array_reduce(
            array_filter($filas, $filtro),
            fn (string $total, array $fila) => bcadd($total, $fila[$campo], 0),
            '0'
        );
        $todas = fn () => true;
        $exigibles = fn (array $f) => $f['exigible'];
        $vencidas = fn (array $f) => $f['estado'] === 'VENCIDA';
        $futuras = fn (array $f) => ! $f['exigible'];

        // Créditos 2.5: interés causado desde el último vencimiento o abono.
        $referencia = $ultimoAbono !== null && $ultimoAbono->gt($periodoInicio) ? $ultimoAbono : $periodoInicio;
        $diasCausado = max(0, self::dias($referencia, $fecha));
        $capitalNoExigible = $sumar($futuras, 'capital_pendiente');
        $siguiente = array_values(array_filter($filas, $futuras))[0] ?? null;
        $causado = CreditoService::interesPorDias($capitalNoExigible, (string) $credito->tasa_ea, $diasCausado);
        // Lo que ya se anticipó del interés de la próxima cuota cubre el causado.
        $causadoPendiente = self::noNegativo(bcsub($causado, $siguiente['interes_pagado'] ?? '0', 0));

        $saldoCapital = $sumar($todas, 'capital_pendiente');
        $interesExigible = $sumar($exigibles, 'interes_pendiente');
        $mora = $sumar($todas, 'mora_pendiente');
        // Créditos 2.5: los seguros y cargos exigibles se deben; los futuros no.
        $segurosCargosExigibles = bcadd($sumar($exigibles, 'seguro_pendiente'), $sumar($exigibles, 'cargo_pendiente'), 0);

        $pagadoTotal = array_reduce($pagadoConcepto, fn ($t, $v) => bcadd($t, $v, 0), '0');

        return [
            'fecha' => $fecha->toDateString(),
            'cuotas' => $filas,
            'periodo_inicio' => $periodoInicio->toDateString(),
            'referencia_causado' => $referencia->toDateString(),
            'dias_causado' => $diasCausado,
            'saldo_capital' => $saldoCapital,
            'capital_exigible' => $sumar($exigibles, 'capital_pendiente'),
            'interes_exigible' => $interesExigible,
            'capital_vencido' => $sumar($vencidas, 'capital_pendiente'),
            'interes_vencido' => $sumar($vencidas, 'interes_pendiente'),
            'seguros_cargos_exigibles' => $segurosCargosExigibles,
            'seguros_cargos_vencidos' => bcadd($sumar($vencidas, 'seguro_pendiente'), $sumar($vencidas, 'cargo_pendiente'), 0),
            'interes_causado' => $causadoPendiente,
            'mora' => $mora,
            'dias_mora' => array_reduce($filas, fn ($max, $f) => max($max, $f['dias_mora']), 0),
            'saldo_para_cancelar' => bcadd(
                bcadd(bcadd($saldoCapital, $interesExigible, 0), bcadd($causadoPendiente, $mora, 0), 0),
                $segurosCargosExigibles,
                0
            ),
            'pagado' => $pagadoConcepto + ['TOTAL' => $pagadoTotal],
            'ultimo_pago' => $pagos->last(),
        ];
    }

    /**
     * Créditos RF-41, RF-42, RF-43: estado de cuenta a partir de la situación.
     *
     * @return array<string, mixed>
     */
    public static function estadoCuenta(Credito $credito, array $situacion): array
    {
        $cuenta = fn (string $estado) => count(array_filter($situacion['cuotas'], fn ($f) => $f['estado'] === $estado));
        $proxima = array_values(array_filter($situacion['cuotas'], fn ($f) => $f['estado'] !== 'PAGADA'))[0] ?? null;

        $pendientePlan = array_reduce(
            $situacion['cuotas'],
            fn ($t, $f) => bcadd($t, bcadd($f['interes_pendiente'], $f['capital_pendiente'], 0), 0),
            '0'
        );
        $pendienteSeguros = array_reduce(
            $situacion['cuotas'],
            fn ($t, $f) => bcadd($t, bcadd($f['seguro_pendiente'], $f['cargo_pendiente'], 0), 0),
            '0'
        );

        $original = CuotaCredito::where('credito_id', $credito->id)->where('version_plan', 1)->sum('cuota');
        $pagado = $situacion['pagado'];
        $segurosPagados = bcadd($pagado['SEGURO'], $pagado['CARGO'], 0);
        $costo = bcadd(bcadd(bcsub($pagado['TOTAL'], $segurosPagados, 0), $pendientePlan, 0), $situacion['mora'], 0);
        $seguros = bcadd($segurosPagados, $pendienteSeguros, 0);

        $estado = match (true) {
            self::esCero($situacion['saldo_para_cancelar']) => 'PAGADO',
            $cuenta('VENCIDA') > 0 => 'EN_MORA',
            default => 'AL_DIA',
        };

        return [
            'fecha_corte' => $situacion['fecha'],
            'estado' => $estado,
            'saldo_capital' => $situacion['saldo_capital'],
            'capital_vencido' => $situacion['capital_vencido'],
            'interes_vencido' => $situacion['interes_vencido'],
            'seguros_cargos_vencidos' => $situacion['seguros_cargos_vencidos'],
            'interes_causado' => $situacion['interes_causado'],
            'mora' => $situacion['mora'],
            'dias_mora' => $situacion['dias_mora'],
            'saldo_para_cancelar' => $situacion['saldo_para_cancelar'],
            'cuotas' => [
                'total' => count($situacion['cuotas']),
                'pagadas' => $cuenta('PAGADA'),
                'pagadas_parcialmente' => $cuenta('PAGADA_PARCIAL'),
                'pendientes' => $cuenta('PENDIENTE'),
                'vencidas' => $cuenta('VENCIDA'),
            ],
            'proxima_cuota' => $proxima === null ? null : [
                'numero' => $proxima['numero'],
                'fecha' => $proxima['fecha'],
                'valor_pendiente' => bcadd(
                    bcadd(bcadd($proxima['interes_pendiente'], $proxima['capital_pendiente'], 0), $proxima['mora_pendiente'], 0),
                    bcadd($proxima['seguro_pendiente'], $proxima['cargo_pendiente'], 0),
                    0
                ),
            ],
            // Créditos RF-42: el abono extraordinario también es capital.
            'total_pagado' => [
                'capital' => bcadd($pagado['CAPITAL'], $pagado['ABONO_EXTRAORDINARIO'], 0),
                'interes_corriente' => $pagado['INTERES_CORRIENTE'],
                'interes_mora' => $pagado['INTERES_MORA'],
                'seguros' => $pagado['SEGURO'],
                'cargos' => $pagado['CARGO'],
                'total' => $pagado['TOTAL'],
            ],
            // Créditos RF-43 / 2.5: capital, intereses y mora (pagados y por pagar);
            // los seguros y cargos van aparte y el total a pagar los suma.
            'costo_total_proyectado' => $costo,
            'seguros_cargos_proyectados' => $seguros,
            'total_a_pagar_proyectado' => bcadd($costo, $seguros, 0),
            'costo_total_original' => self::peso((string) $original),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Imputación de un pago (Créditos 2.6, RF-29..RF-37)
    |--------------------------------------------------------------------------
    */

    /**
     * Calcula cómo se aplica un pago sin guardar nada (Créditos RF-36: la
     * simulación usa exactamente el mismo cálculo que el registro).
     *
     * Orden (2.6, RN-05): mora, interés corriente exigible, capital exigible y
     * excedente. El excedente es anticipo de las próximas cuotas o abono a
     * capital (RF-32); el abono liquida antes el interés causado (RF-35) y
     * regenera el plan reduciendo plazo o cuota (RF-34). Un pago por el saldo
     * para cancelar deja el crédito pagado (RF-37).
     *
     * @param  array{fecha: string, valor: string, aplicacion_excedente?: ?string, reducir?: ?string}  $datos
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function imputar(Credito $credito, array $datos): array
    {
        $fecha = CarbonImmutable::parse($datos['fecha']);
        $valor = self::peso($datos['valor']);
        $s = self::situacion($credito, $fecha);

        if (self::esCero($s['saldo_para_cancelar'])) {
            throw ValidationException::withMessages(['valor' => 'El crédito ya está pagado.']);
        }

        if (bccomp($valor, $s['saldo_para_cancelar'], 0) === 1) {
            throw ValidationException::withMessages([
                'valor' => 'El pago supera el saldo para cancelar al '.$fecha->format('d/m/Y').': $ '.self::formato($s['saldo_para_cancelar']).'.',
            ]);
        }

        $resto = $valor;
        $imputaciones = [];
        $aplicar = function (?array $fila, string $concepto, string $pendiente) use (&$resto, &$imputaciones): void {
            $monto = bccomp($resto, $pendiente, 0) <= 0 ? $resto : $pendiente;

            if (self::esCero($monto)) {
                return;
            }

            $imputaciones[] = [
                'cuota_id' => $fila['id'] ?? null,
                'cuota_numero' => $fila['numero'] ?? null,
                'concepto' => $concepto,
                'valor' => $monto,
            ];
            $resto = bcsub($resto, $monto, 0);
        };

        // Créditos 2.6: seguros y cargos exigibles, mora, interés exigible y
        // capital exigible, de la cuota más antigua a la más nueva.
        foreach (array_filter($s['cuotas'], fn ($f) => $f['exigible']) as $fila) {
            $aplicar($fila, 'SEGURO', $fila['seguro_pendiente']);
            $aplicar($fila, 'CARGO', $fila['cargo_pendiente']);
        }
        foreach ($s['cuotas'] as $fila) {
            $aplicar($fila, 'INTERES_MORA', $fila['mora_pendiente']);
        }
        foreach (array_filter($s['cuotas'], fn ($f) => $f['exigible']) as $fila) {
            $aplicar($fila, 'INTERES_CORRIENTE', $fila['interes_pendiente']);
        }
        foreach (array_filter($s['cuotas'], fn ($f) => $f['exigible']) as $fila) {
            $aplicar($fila, 'CAPITAL', $fila['capital_pendiente']);
        }

        $excedente = $resto;
        $futuras = array_values(array_filter($s['cuotas'], fn ($f) => ! $f['exigible']));
        $total = bccomp($valor, $s['saldo_para_cancelar'], 0) === 0;
        $aplicacion = null;
        $reducir = null;
        $planNuevo = null;
        $ahorro = null;

        if (! self::esCero($excedente)) {
            // RF-37: el pago total no pregunta a dónde va el excedente: cancela todo.
            $aplicacion = $total ? 'ABONO_CAPITAL' : ($datos['aplicacion_excedente'] ?? null);

            if ($aplicacion === null) {
                throw ValidationException::withMessages([
                    'aplicacion_excedente' => 'El pago deja un excedente de $ '.self::formato($excedente).' después de lo exigible: indique si es ANTICIPO de las próximas cuotas o ABONO_CAPITAL.',
                ]);
            }

            if ($aplicacion === 'ANTICIPO') {
                foreach ($futuras as $fila) {
                    $aplicar($fila, 'SEGURO', $fila['seguro_pendiente']);
                    $aplicar($fila, 'CARGO', $fila['cargo_pendiente']);
                    $aplicar($fila, 'INTERES_CORRIENTE', $fila['interes_pendiente']);
                    $aplicar($fila, 'CAPITAL', $fila['capital_pendiente']);
                }
            } else {
                $reducir = $total ? null : ($datos['reducir'] ?? null);

                if (! $total && $reducir === null) {
                    throw ValidationException::withMessages([
                        'reducir' => 'Indique si el abono a capital reduce el PLAZO o la CUOTA (Créditos RN-04).',
                    ]);
                }

                $conAnticipo = array_filter($futuras, fn ($f) => ! self::esCero(bcadd(
                    bcadd($f['interes_pagado'], $f['capital_pagado'], 0),
                    bcadd($f['seguro_pagado'], $f['cargo_pagado'], 0),
                    0
                )));
                if (! $total && $conAnticipo !== []) {
                    throw ValidationException::withMessages([
                        'aplicacion_excedente' => 'La próxima cuota ya tiene un anticipo: complete su pago como ANTICIPO antes de hacer un abono a capital.',
                    ]);
                }

                // RF-35: primero el interés causado hasta el día del abono.
                $aplicar($futuras[0] ?? null, 'INTERES_CORRIENTE', $s['interes_causado']);

                if (! $total && self::esCero($resto)) {
                    throw ValidationException::withMessages([
                        'valor' => 'El excedente ($ '.self::formato($excedente).') solo alcanza para el interés causado ($ '.self::formato($s['interes_causado']).'): regístrelo como ANTICIPO o aumente el valor.',
                    ]);
                }

                $abono = $resto;
                $aplicar(null, 'ABONO_EXTRAORDINARIO', $abono);

                $capitalFuturo = array_reduce($futuras, fn ($t, $f) => bcadd($t, $f['capital_pendiente'], 0), '0');
                $saldoNuevo = self::noNegativo(bcsub($capitalFuturo, $abono, 0));
                $planNuevo = self::regenerar($credito, $s, $futuras, $saldoNuevo, $reducir ?? 'CUOTA', $fecha);

                // RF-36: ahorro = intereses que faltaban − (causado de hoy + intereses del plan nuevo).
                $interesesAntes = array_reduce($futuras, fn ($t, $f) => bcadd($t, $f['interes_pendiente'], 0), '0');
                $interesesDespues = array_reduce($planNuevo, fn ($t, $f) => bcadd($t, $f['interes'], 0), '0');
                $ahorro = bcsub($interesesAntes, bcadd($s['interes_causado'], $interesesDespues, 0), 0);
            }
        }

        return [
            'fecha' => $fecha->toDateString(),
            'valor' => $valor,
            'saldo_para_cancelar_antes' => $s['saldo_para_cancelar'],
            'imputaciones' => $imputaciones,
            'resumen' => self::resumen($imputaciones),
            'excedente' => $excedente,
            'aplicacion_excedente' => $aplicacion,
            'reducir' => $reducir,
            'cancela_credito' => $total,
            'cuotas_reemplazadas' => $planNuevo === null ? [] : array_column($futuras, 'id'),
            'plan_nuevo' => $planNuevo,
            'ahorro_intereses' => $ahorro,
        ];
    }

    /**
     * Créditos RF-34 / RF-26: plan de las cuotas que faltan después de un
     * abono, con las mismas fechas. La primera cuota cobra solo el resto del
     * periodo, porque el interés causado hasta hoy ya se pagó.
     *
     * - Francés, CUOTA: mismo número de cuotas, C' = S'·i / (1 − (1+i)^−Nr).
     * - Francés, PLAZO: misma cuota C hasta que el saldo llega a 0; la última
     *   se ajusta (equivale a N' = ⌈−ln(1 − S'·i/C) / ln(1+i)⌉ sin logaritmos).
     * - Alemán (2.4), CUOTA: abono constante A' = S' / Nr.
     * - Alemán, PLAZO: el mismo abono A hasta que el saldo llega a 0.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function regenerar(Credito $credito, array $s, array $futuras, string $saldo, string $reducir, CarbonImmutable $fecha): array
    {
        if (self::esCero($saldo) || $futuras === []) {
            return [];
        }

        $i = (string) $credito->tasa_periodica;
        $diasPeriodo = max(0, self::dias(CarbonImmutable::parse($s['periodo_inicio']), $fecha));
        $primera = CreditoService::tasaRestoPeriodo($i, (string) $credito->tasa_ea, $diasPeriodo);
        $n = count($futuras);
        $aleman = $credito->sistema_amortizacion === 'ALEMAN';

        if ($aleman) {
            $abonoFijo = $reducir === 'CUOTA' ? CreditoService::abonoConstante($saldo, $n) : $futuras[0]['abono_capital'];
        } else {
            $cuotaFija = $reducir === 'CUOTA' ? CreditoService::cuota($saldo, $i, $n) : $futuras[0]['cuota'];
        }

        $plan = [];
        // Créditos RF-14: si lo que se regenera arranca en la cuota 1 de un primer
        // periodo irregular, su interés es por días hasta ese vencimiento.
        $irregular = $futuras[0]['numero'] === 1 && CreditoService::primerPagoIrregular(
            CarbonImmutable::parse($credito->fecha_inicio->toDateString()),
            $credito->periodicidad,
            $credito->fecha_primer_pago?->toDateString()
        ) !== null;
        $opciones = self::opcionesPlan($credito);
        $cargo = CreditoService::alPeso((string) ($opciones['cargo_fijo'] ?? '0'));

        foreach ($futuras as $k => $futura) {
            $interes = $k === 0 && $irregular
                ? CreditoService::interesPorDias($saldo, (string) $credito->tasa_ea, self::dias($fecha, CarbonImmutable::parse($futura['fecha'])))
                : CreditoService::alPeso(bcmul($saldo, $k === 0 ? $primera : $i, 30));

            if ($aleman) {
                $ultima = $k === $n - 1 || bccomp($saldo, $abonoFijo, 0) <= 0;
                $abono = $ultima ? $saldo : $abonoFijo;
            } else {
                $ultima = $k === $n - 1 || bccomp(bcadd($saldo, $interes, 0), $cuotaFija, 0) <= 0;
                $abono = $ultima ? $saldo : bcsub($cuotaFija, $interes, 0);
            }
            $saldoFinal = bcsub($saldo, $abono, 0);

            $seguro = CreditoService::seguroCuota($saldo, $opciones['seguro_porc_mensual'], $credito->periodicidad);

            $plan[] = [
                'numero' => $futura['numero'],
                'fecha' => $futura['fecha'],
                'saldo_inicial' => $saldo,
                'cuota' => bcadd($abono, $interes, 0),
                'interes' => $interes,
                'abono_capital' => $abono,
                'saldo_final' => $saldoFinal,
                'seguro' => $seguro,
                'cargos' => $cargo,
                'cuota_total' => bcadd(bcadd($abono, $interes, 0), bcadd($seguro, $cargo, 0), 0),
            ];

            $saldo = $saldoFinal;

            if ($ultima) {
                break;
            }
        }

        return $plan;
    }

    /*
    |--------------------------------------------------------------------------
    | Registro y reverso (Créditos RF-29..RF-33, MOD-04)
    |--------------------------------------------------------------------------
    */

    /**
     * Créditos RF-29 / RF-31: guarda el pago, su detalle de imputación, el
     * plan nuevo si hubo abono (RF-26) y el gasto en MOD-04. El crédito debe
     * venir bloqueado (lockForUpdate) dentro de una transacción.
     *
     * @param  array{fecha: string, valor: string, medio: string, referencia?: ?string, aplicacion_excedente?: ?string, reducir?: ?string}  $datos
     *
     * @throws ValidationException
     */
    public static function registrar(Credito $credito, array $datos): PagoCredito
    {
        $subcategoria = self::subcategoriaGasto();
        $resultado = self::imputar($credito, $datos);

        $pago = PagoCredito::create([
            'credito_id' => $credito->id,
            'usuario_id' => $credito->usuario_id,
            'numero' => (int) PagoCredito::withoutGlobalScopes()->where('credito_id', $credito->id)->max('numero') + 1,
            'fecha' => $resultado['fecha'],
            'valor' => $resultado['valor'],
            'medio' => $datos['medio'],
            'referencia' => $datos['referencia'] ?? null,
            'aplicacion_excedente' => $resultado['aplicacion_excedente'],
            'reducir' => $resultado['reducir'],
        ]);

        foreach ($resultado['imputaciones'] as $imputacion) {
            ImputacionPagoCredito::create([
                'pago_id' => $pago->id,
                'cuota_id' => $imputacion['cuota_id'],
                'concepto' => $imputacion['concepto'],
                'valor' => $imputacion['valor'],
            ]);
        }

        if ($resultado['plan_nuevo'] !== null) {
            $version = $credito->version_plan_vigente + 1;

            CuotaCredito::whereIn('id', $resultado['cuotas_reemplazadas'])
                ->update(['reemplazada_en_version' => $version]);

            self::insertarCuotas($credito, $version, array_map(fn (array $f) => self::filaGuardable($f), $resultado['plan_nuevo']));

            $credito->update(['version_plan_vigente' => $version]);
            $pago->version_plan_generada = $version;
        }

        [$gasto, $gastoIntereses] = self::crearGastos($credito, $pago, $resultado['resumen'], $subcategoria);
        $pago->gasto_id = $gasto?->id;
        $pago->gasto_intereses_id = $gastoIntereses?->id;
        $pago->save();

        BitacoraService::registrar(
            accion: 'REGISTRAR_PAGO_CREDITO',
            entidadAfectada: 'pagos_credito',
            entidadId: $pago->id,
            valorNuevo: [...$pago->fresh()->toArray(), 'imputaciones' => $resultado['imputaciones']],
            justificacion: 'Créditos RF-29 / RF-31: pago de un crédito con su imputación.',
            usuarioId: Auth::id()
        );

        return $pago->fresh(['imputaciones']);
    }

    /**
     * Créditos RF-33: reversa el último pago vigente. Sus imputaciones dejan
     * de contar (no se borran), el plan que generó se deshace y su gasto se
     * elimina de forma lógica (RNG-07). Solo el último: así ningún pago
     * posterior queda imputado sobre una historia que cambió.
     *
     * @throws ValidationException
     */
    public static function reversar(Credito $credito, PagoCredito $pago, string $motivo): PagoCredito
    {
        if ($pago->reversado_en !== null) {
            throw ValidationException::withMessages(['pago' => 'El pago ya está reversado.']);
        }

        $ultimo = PagoCredito::where('credito_id', $credito->id)->vigentes()->orderByDesc('numero')->first();

        // Sin pagos vigentes (p. ej. otro reverso lo dejó así entre la consulta y esta línea).
        if ($ultimo === null) {
            throw ValidationException::withMessages(['pago' => 'El crédito no tiene pagos vigentes para reversar.']);
        }

        if ($ultimo->id !== $pago->id) {
            throw ValidationException::withMessages([
                'pago' => 'Solo se puede reversar el último pago vigente (el del '.$ultimo->fecha->format('d/m/Y').' por $ '.self::formato(self::peso($ultimo->valor)).'). Reverse primero los posteriores.',
            ]);
        }

        $anterior = $pago->toArray();

        if ($pago->version_plan_generada !== null) {
            $version = $pago->version_plan_generada;

            CuotaCredito::where('credito_id', $credito->id)->where('version_plan', $version)->delete();
            CuotaCredito::where('credito_id', $credito->id)->where('reemplazada_en_version', $version)
                ->update(['reemplazada_en_version' => null]);
            $credito->update(['version_plan_vigente' => $version - 1]);
        }

        $pago->update(['reversado_en' => now(), 'motivo_reverso' => $motivo]);

        $gastos = Gasto::whereIn('id', array_filter([$pago->gasto_id, $pago->gasto_intereses_id]))->get();
        foreach ($gastos as $gasto) {
            $gasto->delete();
        }

        BitacoraService::registrar(
            accion: 'REVERSAR_PAGO_CREDITO',
            entidadAfectada: 'pagos_credito',
            entidadId: $pago->id,
            valorAnterior: $anterior,
            valorNuevo: [...$pago->fresh()->toArray(), 'gastos_eliminados' => $gastos->pluck('id')->all()],
            justificacion: 'Créditos RF-33: reverso de un pago. '.$motivo,
            usuarioId: Auth::id()
        );

        return $pago->fresh(['imputaciones']);
    }

    /**
     * Créditos RF-31 / RF-42: totales de unas imputaciones por concepto.
     *
     * @return array<string, string>
     */
    public static function resumen(array $imputaciones): array
    {
        $resumen = array_fill_keys(ImputacionPagoCredito::CONCEPTOS, '0');

        foreach ($imputaciones as $imputacion) {
            $resumen[$imputacion['concepto']] = bcadd($resumen[$imputacion['concepto']], self::peso($imputacion['valor']), 0);
        }

        return $resumen;
    }

    /** Créditos 2.7: monto en pesos enteros ("1000.00" → "1000"). */
    public static function peso(mixed $valor): string
    {
        return bcadd((string) $valor, '0', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Internos
    |--------------------------------------------------------------------------
    */

    /**
     * Gasto en MOD-04 por el pago (decisión del usuario). En vivienda los
     * intereses corrientes van en un gasto aparte, potencialmente deducible
     * (intereses de vivienda); el resto, no deducible.
     *
     * @return array{0: ?Gasto, 1: ?Gasto}
     */
    private static function crearGastos(Credito $credito, PagoCredito $pago, array $resumen, Categoria $subcategoria): array
    {
        $base = [
            'usuario_id' => $credito->usuario_id,
            'categoria_id' => $subcategoria->categoria_padre_id,
            'subcategoria_id' => $subcategoria->id,
            'fecha' => $pago->fecha->toDateString(),
            'moneda' => 'COP',
            'tasa_cambio_usada' => '1.0000',
            'naturaleza' => $subcategoria->naturaleza_sugerida ?? 'FIJO',
            'necesidad' => $subcategoria->necesidad_sugerida ?? 'ESENCIAL',
            'medio_pago' => $pago->medio,
            'comercio_beneficiario' => mb_substr($credito->banco, 0, 80),
            'origen' => 'MANUAL',
            'estado' => 'CONFIRMADO',
        ];
        $descripcion = "Pago crédito {$credito->banco} · {$credito->proposito}";
        $valor = self::peso($pago->valor);

        $crear = fn (string $monto, string $relevancia, ?string $subtipo, string $sufijo) => Gasto::create([
            ...$base,
            'monto_original' => $monto,
            'monto_cop' => $monto,
            'relevancia_tributaria' => $relevancia,
            'subtipo_deduccion' => $subtipo,
            'descripcion' => mb_substr($descripcion.$sufijo, 0, 255),
        ]);

        if ($credito->tipo_credito !== 'VIVIENDA') {
            return [$crear($valor, 'NO_DEDUCIBLE', null, ''), null];
        }

        $intereses = $resumen['INTERES_CORRIENTE'];
        $resto = bcsub($valor, $intereses, 0);

        return [
            self::esCero($resto) ? null : $crear($resto, 'NO_DEDUCIBLE', null, ' (capital y mora)'),
            self::esCero($intereses) ? null : $crear($intereses, 'POTENCIALMENTE_DEDUCIBLE', 'INTERESES_VIVIENDA', ' (intereses)'),
        ];
    }

    /**
     * Subcategoría "Obligaciones financieras › Cuota de crédito / hipoteca" del
     * catálogo global (como la del PILA en MOD-05).
     *
     * @throws ValidationException
     */
    private static function subcategoriaGasto(): Categoria
    {
        $subcategoria = Categoria::where('tipo', 'GASTO')
            ->where('es_predeterminada', true)
            ->where('activa', true)
            ->where('nombre', self::SUBCATEGORIA_GASTO)
            ->whereHas('padre', fn ($q) => $q->whereNull('categoria_padre_id')->where('activa', true)->where('nombre', self::CATEGORIA_GASTO))
            ->first();

        if ($subcategoria === null) {
            throw ValidationException::withMessages([
                'gasto' => 'La categoría "'.self::CATEGORIA_GASTO.' › '.self::SUBCATEGORIA_GASTO.'" no está disponible en el catálogo (RF-015). Contacte al administrador.',
            ]);
        }

        return $subcategoria;
    }

    /**
     * Créditos RF-39 / RN-09: mora de una cuota hasta la fecha, solo sobre su
     * capital vencido. Corre desde el vencimiento y se parte en cada pago que
     * abonó a ese capital: K · [(1 + EA_mora)^(días/365) − 1] por tramo.
     *
     * @param  array<string, string>  $capitalPorFecha  abonos al capital de la cuota, por fecha
     */
    private static function moraCuota(string $capital, CarbonImmutable $vence, array $capitalPorFecha, CarbonImmutable $fecha, string $tasaMora): string
    {
        if ($fecha->lte($vence)) {
            return '0';
        }

        ksort($capitalPorFecha);
        $pendiente = $capital;
        $desde = $vence;
        $mora = '0';

        foreach ($capitalPorFecha as $dia => $monto) {
            $cuando = CarbonImmutable::parse($dia);

            if ($cuando->gt($fecha)) {
                break;
            }

            if ($cuando->gt($vence) && bccomp($pendiente, '0', 0) === 1) {
                $mora = bcadd($mora, CreditoService::interesPorDias($pendiente, $tasaMora, self::dias($desde, $cuando)), 0);
                $desde = $cuando;
            }

            $pendiente = bcsub($pendiente, $monto, 0);
        }

        if (bccomp($pendiente, '0', 0) === 1) {
            $mora = bcadd($mora, CreditoService::interesPorDias($pendiente, $tasaMora, self::dias($desde, $fecha)), 0);
        }

        return $mora;
    }

    /**
     * Créditos RF-12 / RF-14 / RF-16: lo que el plan necesita del crédito
     * además de monto, tasa, periodicidad y sistema.
     *
     * @return array<string, mixed>
     */
    private static function opcionesPlan(Credito $credito): array
    {
        return [
            'fecha_primer_pago' => $credito->fecha_primer_pago?->toDateString(),
            'tasa_ea' => (string) $credito->tasa_ea,
            'seguro_porc_mensual' => $credito->seguro_porc_mensual,
            'cargo_fijo' => $credito->cargo_fijo,
        ];
    }

    /** Fila de la tabla de amortización con los nombres de columna de cuotas_credito. */
    private static function filaGuardable(array $fila): array
    {
        return [
            'numero' => $fila['numero'],
            'fecha_vencimiento' => $fila['fecha'],
            'saldo_inicial' => $fila['saldo_inicial'],
            'cuota' => $fila['cuota'],
            'interes' => $fila['interes'],
            'capital' => $fila['abono_capital'],
            'saldo_final' => $fila['saldo_final'],
            'seguro' => $fila['seguro'] ?? '0',
            'cargos' => $fila['cargos'] ?? '0',
        ];
    }

    /** Inserta las cuotas de una versión del plan por lotes. */
    private static function insertarCuotas(Credito $credito, int $version, array $filas): void
    {
        $ahora = now();
        $registros = array_map(fn (array $f) => [
            ...$f,
            'credito_id' => $credito->id,
            'usuario_id' => $credito->usuario_id,
            'version_plan' => $version,
            'creado_en' => $ahora,
            'actualizado_en' => $ahora,
        ], $filas);

        foreach (array_chunk($registros, self::LOTE) as $lote) {
            CuotaCredito::insert($lote);
        }
    }

    /** Días calendario de $desde a $hasta (negativo si $hasta es anterior). */
    private static function dias(CarbonImmutable $desde, CarbonImmutable $hasta): int
    {
        return (int) $desde->startOfDay()->diffInDays($hasta->startOfDay(), false);
    }

    private static function esCero(string $valor): bool
    {
        return bccomp($valor, '0', 0) === 0;
    }

    private static function noNegativo(string $valor): string
    {
        return bccomp($valor, '0', 0) === -1 ? '0' : $valor;
    }

    /** $ 1.234.567 para los mensajes. */
    private static function formato(string $pesos): string
    {
        return number_format((float) $pesos, 0, ',', '.');
    }
}
