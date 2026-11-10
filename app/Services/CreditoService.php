<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * MOD-08 — Cálculo de créditos con cuota fija (sistema francés).
 *
 * Créditos 7.2: todo se calcula con bcmath, sin punto flotante; las tasas se
 * guardan con 18 decimales y solo los valores en dinero se redondean al peso.
 */
class CreditoService
{
    /** Créditos RF-10: periodos de pago por año (m). */
    public const PERIODICIDADES = [
        'SEMANAL' => 52,
        'QUINCENAL' => 24,
        'MENSUAL' => 12,
        'BIMESTRAL' => 6,
        'TRIMESTRAL' => 4,
        'SEMESTRAL' => 2,
        'ANUAL' => 1,
    ];

    /**
     * Créditos RF-05: cómo viene expresada la tasa. MENSUAL es la tasa
     * periódica mes vencido (la que trae el extracto: "1,5 % M.V.").
     */
    public const TIPOS_TASA = ['EA', 'NOMINAL', 'MENSUAL'];

    /**
     * Créditos 2.2 / 2.4: FRANCES = cuota fija (por defecto); ALEMAN = abono
     * constante a capital, con cuota decreciente.
     */
    public const SISTEMAS = ['FRANCES', 'ALEMAN'];

    /** RF-048 / Créditos RN-02: tipo de crédito; VIVIENDA tiene plazo legal. */
    public const TIPOS_CREDITO = ['LIBRE_INVERSION', 'LIBRANZA', 'VEHICULO', 'VIVIENDA', 'TARJETA_CREDITO', 'OTRO'];

    /** Decimales internos de bcmath. */
    private const ESCALA = 30;

    /** Créditos 7.2: decimales con que se guardan las tasas. */
    private const ESCALA_TASA = 18;

    /** Raíz 365 de (1 + EA) ya calculada, por tasa (Créditos 2.5). */
    private static array $raicesDiarias = [];

    /**
     * Créditos RF-11: N = plazo en meses × m / 12. Devuelve null si no es
     * entero, como 10 meses con pago trimestral.
     */
    public static function numeroCuotas(int $plazoMeses, string $periodicidad): ?int
    {
        $producto = $plazoMeses * self::PERIODICIDADES[$periodicidad];

        return $producto % 12 === 0 ? intdiv($producto, 12) : null;
    }

    /**
     * Créditos RF-11: múltiplo de meses que da un número entero de cuotas:
     * 12 / mcd(m, 12). Trimestral y semanal: 3; quincenal y mensual: 1.
     */
    public static function multiploMeses(string $periodicidad): int
    {
        $a = self::PERIODICIDADES[$periodicidad];
        $b = 12;
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return intdiv(12, $a);
    }

    /** Créditos RF-11: meses que cubren N cuotas de la periodicidad (N × 12 / m). */
    public static function mesesEquivalentes(int $numeroCuotas, string $periodicidad): string
    {
        return bcdiv((string) ($numeroCuotas * 12), (string) self::PERIODICIDADES[$periodicidad], 4);
    }

    /**
     * Créditos RF-05 / 2.1: tasa efectiva anual (fracción) desde la tasa
     * ingresada (en %), redondeada a 18 decimales.
     */
    public static function tasaEa(string $tasaPorc, string $tipoTasa, ?string $capitalizacion = null): string
    {
        $e = self::ESCALA;
        $t = bcdiv($tasaPorc, '100', $e);

        $ea = match ($tipoTasa) {
            'EA' => $t,
            // EA = (1 + i)^12 − 1
            'MENSUAL' => bcsub(bcpow(bcadd('1', $t, $e), '12', $e), '1', $e),
            // EA = (1 + TN / k)^k − 1
            'NOMINAL' => self::eaDesdeNominal($t, self::PERIODICIDADES[$capitalizacion]),
        };

        return self::redondear($ea, self::ESCALA_TASA);
    }

    /**
     * Créditos 2.1: tasa de cada periodo de pago, i = (1 + EA)^(1/m) − 1,
     * redondeada a 18 decimales. Nunca EA / m.
     */
    public static function tasaPeriodica(string $ea, string $periodicidad): string
    {
        $e = self::ESCALA;
        $raiz = self::raiz(bcadd('1', $ea, $e), self::PERIODICIDADES[$periodicidad]);

        return self::redondear(bcsub($raiz, '1', $e), self::ESCALA_TASA);
    }

    /**
     * Créditos 2.2: cuota fija C = P·i·(1+i)^N / ((1+i)^N − 1), que es
     * P·i / (1 − (1+i)^−N) sin potencias negativas. Con tasa 0, P / N.
     * Redondeada al peso.
     */
    public static function cuota(string $monto, string $tasaPeriodica, int $numeroCuotas): string
    {
        $e = self::ESCALA;

        if (bccomp($tasaPeriodica, '0', $e) === 0) {
            return self::alPeso(bcdiv($monto, (string) $numeroCuotas, $e));
        }

        $factor = bcpow(bcadd('1', $tasaPeriodica, $e), (string) $numeroCuotas, $e);

        return self::alPeso(bcdiv(
            bcmul(bcmul($monto, $tasaPeriodica, $e), $factor, $e),
            bcsub($factor, '1', $e),
            $e
        ));
    }

    /**
     * Créditos 2.4: abono constante a capital, A = P / N al peso.
     */
    public static function abonoConstante(string $monto, int $numeroCuotas): string
    {
        return self::alPeso(bcdiv($monto, (string) $numeroCuotas, self::ESCALA));
    }

    /**
     * RF-049 / Créditos 2.3, 2.4 y 2.7: tabla de amortización. Por cuota el
     * interés es saldo × i (al peso). En el francés el abono a capital es
     * cuota − interés; en el alemán el abono es fijo y la cuota, abono +
     * interés. La última cuota abona todo el saldo que queda, así absorbe el
     * redondeo acumulado y el saldo final es $ 0.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function tablaAmortizacion(
        string $monto,
        string $tasaPeriodica,
        string $periodicidad,
        int $numeroCuotas,
        string $fechaInicio,
        string $sistema = 'FRANCES',
        array $opciones = []
    ): array {
        $aleman = $sistema === 'ALEMAN';
        $cuotaFija = $aleman ? null : self::cuota($monto, $tasaPeriodica, $numeroCuotas);
        $abonoFijo = $aleman ? self::abonoConstante($monto, $numeroCuotas) : null;
        $inicio = CarbonImmutable::parse($fechaInicio);
        $primerPago = self::primerPagoIrregular($inicio, $periodicidad, $opciones['fecha_primer_pago'] ?? null);
        $cargo = self::alPeso((string) ($opciones['cargo_fijo'] ?? '0'));

        $saldo = self::alPeso($monto);
        $tabla = [];

        for ($k = 1; $k <= $numeroCuotas; $k++) {
            $interesRegular = self::alPeso(bcmul($saldo, $tasaPeriodica, self::ESCALA));

            // Créditos RF-14: el primer periodo irregular cobra el interés por días (2.5).
            $interes = $k === 1 && $primerPago !== null
                ? self::interesPorDias($saldo, (string) $opciones['tasa_ea'], self::dias($inicio, $primerPago))
                : $interesRegular;

            if ($k === $numeroCuotas) {
                $abono = $saldo;
            } elseif ($aleman) {
                $abono = $abonoFijo;
            } else {
                // El abono es el del plan regular; si el interés cambia, cambia la cuota 1.
                $abono = bcsub($cuotaFija, $interesRegular, 0);
            }

            $cuota = bcadd($abono, $interes, 0);
            $saldoFinal = bcsub($saldo, $abono, 0);
            $seguro = self::seguroCuota($saldo, $opciones['seguro_porc_mensual'] ?? null, $periodicidad);

            $tabla[] = [
                'numero' => $k,
                'fecha' => ($primerPago !== null
                    ? self::fechaCuota($primerPago, $periodicidad, $k - 1)
                    : self::fechaCuota($inicio, $periodicidad, $k))->toDateString(),
                'saldo_inicial' => $saldo,
                'cuota' => $cuota,
                'interes' => $interes,
                'abono_capital' => $abono,
                'saldo_final' => $saldoFinal,
                'seguro' => $seguro,
                'cargos' => $cargo,
                'cuota_total' => bcadd($cuota, bcadd($seguro, $cargo, 0), 0),
            ];

            $saldo = $saldoFinal;
        }

        return $tabla;
    }

    /**
     * Créditos RF-12: vencimiento regular de la cuota k desde el desembolso.
     */
    public static function fechaVencimiento(string $fechaInicio, string $periodicidad, int $k): CarbonImmutable
    {
        return self::fechaCuota(CarbonImmutable::parse($fechaInicio), $periodicidad, $k);
    }

    /**
     * Créditos RF-12 / RF-14: la fecha del primer pago, solo si difiere del
     * vencimiento regular (si coincide, el plan es el regular).
     */
    public static function primerPagoIrregular(CarbonImmutable $inicio, string $periodicidad, ?string $fechaPrimerPago): ?CarbonImmutable
    {
        if ($fechaPrimerPago === null) {
            return null;
        }

        $primerPago = CarbonImmutable::parse($fechaPrimerPago);

        return $primerPago->equalTo(self::fechaCuota($inicio, $periodicidad, 1)) ? null : $primerPago;
    }

    /**
     * Créditos RF-02 / RF-16: seguro de una cuota, en % mensual sobre el saldo
     * al inicio de la cuota, proporcional a los meses que cubre (12 / m). Al
     * peso.
     */
    public static function seguroCuota(string $saldo, mixed $porcMensual, string $periodicidad): string
    {
        if ($porcMensual === null || bccomp((string) $porcMensual, '0', 6) === 0) {
            return '0';
        }

        $factor = bcdiv(bcmul((string) $porcMensual, '12', self::ESCALA), bcmul('100', (string) self::PERIODICIDADES[$periodicidad], self::ESCALA), self::ESCALA);

        return self::alPeso(bcmul($saldo, $factor, self::ESCALA));
    }

    /**
     * RF-049 / Créditos 2.5: totales de la tabla. La deuda total (costo total
     * proyectado) es lo que se paga en cuotas: monto más intereses. Los
     * seguros y cargos se suman aparte (RF-16, RF-24) y el total a pagar
     * los incluye.
     *
     * @param  array<int, array<string, mixed>>  $tabla
     * @return array<string, string>
     */
    public static function totales(array $tabla): array
    {
        $intereses = '0';
        $capital = '0';
        $deuda = '0';
        $segurosCargos = '0';

        foreach ($tabla as $fila) {
            $intereses = bcadd($intereses, $fila['interes'], 0);
            $capital = bcadd($capital, $fila['abono_capital'], 0);
            $deuda = bcadd($deuda, $fila['cuota'], 0);
            $segurosCargos = bcadd($segurosCargos, bcadd($fila['seguro'] ?? '0', $fila['cargos'] ?? '0', 0), 0);
        }

        return [
            'total_intereses' => $intereses,
            'total_capital' => $capital,
            'deuda_total' => $deuda,
            'total_seguros_cargos' => $segurosCargos,
            'total_a_pagar' => bcadd($deuda, $segurosCargos, 0),
        ];
    }

    /**
     * Créditos RF-06 / RN-08: la tasa en sus tres expresiones, en %: EA,
     * periódica y nominal anual capitalizable en la periodicidad de pago
     * (i × m; con pago mensual es la NMV).
     *
     * @return array<string, string>
     */
    public static function tasas(string $ea, string $tasaPeriodica, string $periodicidad): array
    {
        $m = (string) self::PERIODICIDADES[$periodicidad];

        return [
            'ea_porc' => self::porcentaje($ea),
            'periodica_porc' => self::porcentaje($tasaPeriodica),
            'nominal_porc' => self::porcentaje(bcmul($tasaPeriodica, $m, self::ESCALA)),
            'nominal_capitalizacion' => $periodicidad,
        ];
    }

    /**
     * RF-048 / RF-049: cálculo completo a partir de las condiciones del
     * crédito (ya validadas): tasas, cuota, totales y tabla.
     *
     * Créditos RF-16: la cuota se muestra como primera y última (en el
     * alemán es decreciente; en el francés la última absorbe el redondeo).
     *
     * @param  array{monto: string, tasa_ea: string, tasa_periodica: string, periodicidad: string, numero_cuotas: int, fecha_inicio: string, sistema_amortizacion?: string}  $datos
     * @return array<string, mixed>
     */
    public static function calcular(array $datos): array
    {
        $sistema = $datos['sistema_amortizacion'] ?? 'FRANCES';
        $tabla = self::tablaAmortizacion(
            (string) $datos['monto'],
            (string) $datos['tasa_periodica'],
            $datos['periodicidad'],
            (int) $datos['numero_cuotas'],
            (string) $datos['fecha_inicio'],
            $sistema,
            [
                'fecha_primer_pago' => $datos['fecha_primer_pago'] ?? null,
                'tasa_ea' => (string) $datos['tasa_ea'],
                'seguro_porc_mensual' => $datos['seguro_porc_mensual'] ?? null,
                'cargo_fijo' => $datos['cargo_fijo'] ?? null,
            ]
        );

        return [
            'numero_cuotas' => count($tabla),
            'sistema_amortizacion' => $sistema,
            'tasas' => self::tasas((string) $datos['tasa_ea'], (string) $datos['tasa_periodica'], $datos['periodicidad']),
            'cuota' => $tabla[0]['cuota'],
            'cuota_ultima' => $tabla[count($tabla) - 1]['cuota'],
            // Créditos RF-16 / RF-21: la primera cuota con seguros y cargos.
            'cuota_total' => $tabla[0]['cuota_total'],
            ...self::totales($tabla),
            'tabla' => $tabla,
        ];
    }

    /**
     * Créditos RF-17: monto máximo para una cuota máxima C, con la primera
     * cuota como referencia (en el alemán es la más alta).
     *
     * - Francés: P = C · (1 − (1 + i)^−N) / i (con tasa 0, C · N).
     * - Alemán: C₁ = P/N + P·i, así que P = C / (1/N + i).
     *
     * Como la cuota se redondea al peso, el resultado de la fórmula se ajusta
     * unos pesos hasta dar el mayor P cuya primera cuota no supera C.
     */
    public static function montoMaximo(string $cuotaMaxima, string $tasaPeriodica, int $numeroCuotas, string $sistema = 'FRANCES'): string
    {
        $e = self::ESCALA;
        $n = (string) $numeroCuotas;

        if (bccomp($tasaPeriodica, '0', $e) === 0) {
            $monto = bcmul($cuotaMaxima, $n, 0);
        } elseif ($sistema === 'ALEMAN') {
            $monto = bcdiv($cuotaMaxima, bcadd(bcdiv('1', $n, $e), $tasaPeriodica, $e), 0);
        } else {
            $factor = bcpow(bcadd('1', $tasaPeriodica, $e), $n, $e);
            // (1 − (1+i)^−N) / i = ((1+i)^N − 1) / (i · (1+i)^N)
            $monto = bcdiv(bcmul($cuotaMaxima, bcsub($factor, '1', $e), $e), bcmul($tasaPeriodica, $factor, $e), 0);
        }

        while (bccomp($monto, '0', 0) === 1 && bccomp(self::primeraCuota($monto, $tasaPeriodica, $numeroCuotas, $sistema), $cuotaMaxima, 0) === 1) {
            $monto = bcsub($monto, '1', 0);
        }

        while (bccomp(self::primeraCuota(bcadd($monto, '1', 0), $tasaPeriodica, $numeroCuotas, $sistema), $cuotaMaxima, 0) <= 0) {
            $monto = bcadd($monto, '1', 0);
        }

        return $monto;
    }

    /**
     * Créditos RF-17: primera cuota de la tabla sin generarla completa. Con
     * una sola cuota es el monto más su interés.
     */
    public static function primeraCuota(string $monto, string $tasaPeriodica, int $numeroCuotas, string $sistema = 'FRANCES'): string
    {
        $interes = self::alPeso(bcmul(self::alPeso($monto), $tasaPeriodica, self::ESCALA));

        if ($numeroCuotas === 1) {
            return bcadd(self::alPeso($monto), $interes, 0);
        }

        return $sistema === 'ALEMAN'
            ? bcadd(self::abonoConstante($monto, $numeroCuotas), $interes, 0)
            : self::cuota($monto, $tasaPeriodica, $numeroCuotas);
    }

    /**
     * Créditos RF-12: la cuota k vence k periodos después del desembolso. Los
     * meses usan addMonthsNoOverflow: si el día no existe, el último del mes.
     * La quincenal alterna +15 días y +1 mes: 15/01 → 30/01 → 15/02 → 02/03.
     */
    private static function fechaCuota(CarbonImmutable $inicio, string $periodicidad, int $k): CarbonImmutable
    {
        return match ($periodicidad) {
            'SEMANAL' => $inicio->addDays(7 * $k),
            'QUINCENAL' => $inicio->addMonthsNoOverflow(intdiv($k, 2))->addDays($k % 2 === 1 ? 15 : 0),
            default => $inicio->addMonthsNoOverflow($k * intdiv(12, self::PERIODICIDADES[$periodicidad])),
        };
    }

    /**
     * Créditos 2.5: (1 + EA)^(d/365) sin punto flotante: raíz 365 por Newton
     * (una vez por tasa) y potencia entera. Con d ≤ 0 el factor es 1.
     */
    public static function factorDias(string $ea, int $dias): string
    {
        if ($dias <= 0) {
            return '1';
        }

        $e = self::ESCALA;
        self::$raicesDiarias[$ea] ??= self::raiz(bcadd('1', $ea, $e), 365);

        return bcpow(self::$raicesDiarias[$ea], (string) $dias, $e);
    }

    /**
     * Créditos 2.5 / RF-35 / RF-39: interés de d días sobre un saldo,
     * I_d = S · [(1 + EA)^(d/365) − 1], al peso. Sirve para el interés causado
     * (con la EA corriente) y para la mora (con la EA de mora).
     */
    public static function interesPorDias(string $saldo, string $ea, int $dias): string
    {
        $factor = bcsub(self::factorDias($ea, $dias), '1', self::ESCALA);

        return self::alPeso(bcmul($saldo, $factor, self::ESCALA));
    }

    /**
     * Créditos RF-34 / RF-35: tasa del resto de un periodo cuando ya se
     * liquidaron d días de interés causado: (1 + i) / (1 + EA)^(d/365) − 1.
     * Con d = 0 es la tasa periódica completa.
     */
    public static function tasaRestoPeriodo(string $tasaPeriodica, string $ea, int $dias): string
    {
        $e = self::ESCALA;

        if ($dias <= 0) {
            return $tasaPeriodica;
        }

        $resto = bcsub(bcdiv(bcadd('1', $tasaPeriodica, $e), self::factorDias($ea, $dias), $e), '1', $e);

        return bccomp($resto, '0', $e) === 1 ? $resto : '0';
    }

    /** Días calendario de $desde a $hasta. */
    private static function dias(CarbonImmutable $desde, CarbonImmutable $hasta): int
    {
        return (int) $desde->startOfDay()->diffInDays($hasta->startOfDay(), false);
    }

    /** Créditos 2.1: EA = (1 + TN / k)^k − 1, con TN como fracción. */
    private static function eaDesdeNominal(string $nominal, int $k): string
    {
        $e = self::ESCALA;
        $base = bcadd('1', bcdiv($nominal, (string) $k, $e), $e);

        return bcsub(bcpow($base, (string) $k, $e), '1', $e);
    }

    /**
     * Créditos 7.2: raíz m-ésima de a (a ≥ 1) con el método de Newton, solo
     * bcmath: x ← ((m − 1)·x + a / x^(m−1)) / m. Arranca en 1 + (a − 1)/m, que
     * por la desigualdad de Bernoulli está por encima de la raíz, así que la
     * serie baja de forma monótona hasta que el cambio es menor que 10^−28.
     */
    private static function raiz(string $a, int $m): string
    {
        $e = self::ESCALA;

        if ($m === 1) {
            return $a;
        }

        $tolerancia = '0.'.str_repeat('0', 27).'1';
        $x = bcadd('1', bcdiv(bcsub($a, '1', $e), (string) $m, $e), $e);

        for ($iteracion = 0; $iteracion < 200; $iteracion++) {
            $siguiente = bcdiv(
                bcadd(bcmul((string) ($m - 1), $x, $e), bcdiv($a, bcpow($x, (string) ($m - 1), $e), $e), $e),
                (string) $m,
                $e
            );
            $cambio = ltrim(bcsub($x, $siguiente, $e), '-');
            $x = $siguiente;

            if (bccomp($cambio, $tolerancia, $e) === -1) {
                break;
            }
        }

        return $x;
    }

    /** Créditos RF-06: fracción a porcentaje con 4 decimales. */
    public static function porcentaje(string $fraccion): string
    {
        return self::redondear(bcmul($fraccion, '100', self::ESCALA), 4);
    }

    /** Redondeo mitad hacia arriba de un valor no negativo. */
    private static function redondear(string $valor, int $decimales): string
    {
        $mitad = '0.'.str_repeat('0', $decimales).'5';

        return bcadd($valor, $mitad, $decimales);
    }

    /** Créditos 2.7: redondeo al peso (mitad hacia arriba) de un valor no negativo. */
    public static function alPeso(string $valor): string
    {
        return self::redondear($valor, 0);
    }
}
