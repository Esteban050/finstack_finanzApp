<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Servicio de Cálculo de Impuestos y Reglas Tributarias de Inversiones (RF-IMP / Sección 5 y 9).
 * Realiza todos los cálculos monetarios con bcmath con alta precisión y desglose normativo.
 */
class CalculoImpuestoInversionService
{
    /**
     * 1. CDT / CDAT (5.2 & 9.2)
     */
    public static function calcularCdt(
        string $capital,
        string $tasaValor,
        string $tipoTasa,
        int $plazoDias,
        int $baseDias = 365,
        string $periodicidadPago = 'VENCIMIENTO',
        ?string $fecha = null,
        ?string $tarifaMarginal = null,
        ?string $componenteInflacionario = null
    ): array {
        $fechaHecho = $fecha ?? CarbonImmutable::today()->toDateString();

        // 1. Convertir tasa a Efectiva Anual (EA) si es nominal
        $tasaEa = (float) $tasaValor;
        if (strtoupper($tipoTasa) === 'NOMINAL') {
            // Asumir mensual como base nominal común si no se especifica
            $m = 12;
            $tasaEa = (((1 + ($tasaEa / 100) / $m) ** $m) - 1) * 100;
        }

        // 2. Rendimiento bruto: C * ((1 + EA)^(d/B) - 1)
        $factor = (1 + ($tasaEa / 100)) ** ($plazoDias / $baseDias);
        $factorStr = sprintf('%.15F', $factor);
        $factorMenosUno = bcsub($factorStr, '1', 12);
        $bruto = IngresoService::redondear(bcmul($capital, $factorMenosUno, 6));

        // 3. Retención en la fuente: RETEFUENTE_RENTA_FIJA (4% oficial Decreto 572 de 2025)
        $tarifaRet = ParametroTributarioService::obtenerValor('RETEFUENTE_RENTA_FIJA', $fechaHecho);
        $paramRet = ParametroTributarioService::obtener('RETEFUENTE_RENTA_FIJA', $fechaHecho);
        $retencion = IngresoService::redondear(bcmul($bruto, bcdiv($tarifaRet, '100', 8), 6));

        // 4. Neto
        $neto = bcsub($bruto, $retencion, 2);

        // 5. Rentabilidad neta EA: ((C + neto) / C) ^ (B / d) - 1
        $capitalMasNeto = bcadd($capital, $neto, 2);
        $relacion = (float) bcdiv($capitalMasNeto, $capital, 10);
        $rentabilidadNetaEa = (($relacion ** ($baseDias / $plazoDias)) - 1) * 100;

        // 6. Estimación informativa de renta
        $impuestoRentaEstimado = '0.00';
        $rentaGravable = $bruto;
        if ($componenteInflacionario !== null && $tarifaMarginal !== null) {
            $factorNoGravado = bcdiv($componenteInflacionario, '100', 4);
            $factorGravado = bcsub('1.00', $factorNoGravado, 4);
            $rentaGravable = IngresoService::redondear(bcmul($bruto, $factorGravado, 4));
            $impuestoTeorico = bcmul($rentaGravable, bcdiv($tarifaMarginal, '100', 4), 2);
            $impuestoRentaEstimado = bcsub($impuestoTeorico, $retencion, 2);
            if (bccomp($impuestoRentaEstimado, '0', 2) < 0) {
                $impuestoRentaEstimado = '0.00';
            }
        }

        $desglose = [
            [
                'concepto' => 'Rendimiento bruto',
                'base' => $capital,
                'tarifa' => number_format($tasaEa, 2) . '% EA',
                'valor' => $bruto,
                'norma' => 'Fórmula financiera C × ((1 + EA)^(d/B) − 1)',
            ],
            [
                'concepto' => 'Retención en la fuente',
                'base' => $bruto,
                'tarifa' => number_format((float) $tarifaRet, 2) . '%',
                'valor' => $retencion,
                'norma' => $paramRet->norma_referencia ?? 'Decreto 572 de 2025 (4% renta fija)',
            ],
        ];

        return [
            'bruto' => $bruto,
            'retencion_total' => $retencion,
            'gmf_total' => '0.00',
            'comisiones' => '0.00',
            'neto' => $neto,
            'rentabilidad_neta_ea' => sprintf('%.4f', $rentabilidadNetaEa),
            'renta_gravable' => $rentaGravable,
            'impuesto_renta_estimado' => $impuestoRentaEstimado,
            'detalle_lineas' => $desglose,
        ];
    }

    /**
     * 2. Cuenta de Alto Rendimiento - Abono de Intereses (5.3, 9.2, 9.6.3)
     */
    public static function calcularAbonoCuenta(
        string $saldo,
        string $tasaEa,
        int $dias,
        int $baseDias = 365,
        ?string $fecha = null
    ): array {
        $fechaHecho = $fecha ?? CarbonImmutable::today()->toDateString();

        // interes = saldo × ((1 + EA)^(días/baseDias) − 1)
        $factor = (1 + (((float) $tasaEa) / 100)) ** ($dias / $baseDias);
        $factorStr = sprintf('%.15F', $factor);
        $factorMenosUno = bcsub($factorStr, '1', 10);
        $bruto = IngresoService::redondear(bcmul($saldo, $factorMenosUno, 4));

        // Retención 7% sobre rendimientos financieros (Art. 395 ET)
        // Art. 1.2.4.2.1 Decreto 1625 de 2016: Base mínima diaria de no sujeción a retención (0,055 UVT/día)
        $baseDiariaUvt = ParametroTributarioService::obtenerValor('RETEFUENTE_CUENTA_AHORRO_BASE_DIARIA_UVT', $fechaHecho);
        $uvt = ParametroTributarioService::obtenerUvt($fechaHecho);
        $topeMinimoPeriodo = IngresoService::redondear(bcmul(bcmul((string) $dias, $baseDiariaUvt, 6), $uvt, 4));

        $tarifaRet = ParametroTributarioService::obtenerValor('RETEFUENTE_REND_FINANCIEROS', $fechaHecho);
        $paramRet = ParametroTributarioService::obtener('RETEFUENTE_REND_FINANCIEROS', $fechaHecho);

        if (bccomp($bruto, $topeMinimoPeriodo, 2) < 0) {
            $retencion = '0.00';
            $tarifaRetAplicada = '0.00';
            $normaRet = "Art. 1.2.4.2.1 Dec. 1625/2016: Rendimiento ($ " . number_format((float) $bruto, 2) . ") inferior a la base mínima de 0,055 UVT/día ($ " . number_format((float) $topeMinimoPeriodo, 2) . " para {$dias} días). Tarifa 0%";
        } else {
            $retencion = IngresoService::redondear(bcmul($bruto, bcdiv($tarifaRet, '100', 6), 4));
            $tarifaRetAplicada = $tarifaRet;
            $normaRet = ($paramRet->norma_referencia ?? 'Art. 395 Estatuto Tributario (7%)') . " (Supera base mínima de 0,055 UVT/día: $ " . number_format((float) $topeMinimoPeriodo, 2) . " para {$dias} días)";
        }

        $neto = bcsub($bruto, $retencion, 2);

        $desglose = [
            [
                'concepto' => 'Interés causado bruto',
                'base' => $saldo,
                'tarifa' => number_format((float) $tasaEa, 2) . '% EA',
                'valor' => $bruto,
                'norma' => 'Interés compuesto por días de saldo constante',
            ],
            [
                'concepto' => 'Retención en la fuente',
                'base' => $bruto,
                'tarifa' => number_format((float) $tarifaRetAplicada, 2) . '%',
                'valor' => $retencion,
                'norma' => $normaRet,
            ],
        ];

        return [
            'bruto' => $bruto,
            'retencion_total' => $retencion,
            'gmf_total' => '0.00',
            'comisiones' => '0.00',
            'neto' => $neto,
            'detalle_lineas' => $desglose,
        ];
    }

    /**
     * 2. Cuenta de Alto Rendimiento - Retiro y GMF (4x1000) con tope de 350 UVT (RF-IMP-03, 9.8.4)
     */
    public static function calcularRetiroCuenta(
        string $montoRetiro,
        bool $esCuentaGmfExenta,
        string $retirosMesAcumuladosPrevios = '0.00',
        ?string $fecha = null
    ): array {
        $fechaHecho = $fecha ?? CarbonImmutable::today()->toDateString();
        $gmfTarifa = ParametroTributarioService::obtenerValor('GMF_TARIFA', $fechaHecho); // 0.4%
        $paramGmf = ParametroTributarioService::obtener('GMF_TARIFA', $fechaHecho);

        $baseGravada = '0.00';
        $gmf = '0.00';
        $norma = '';

        if (! $esCuentaGmfExenta) {
            // Cuenta gravada: 0,4% pleno
            $baseGravada = $montoRetiro;
            $gmf = IngresoService::redondear(bcmul($baseGravada, bcdiv($gmfTarifa, '100', 6), 4));
            $norma = $paramGmf->norma_referencia ?? 'Art. 871 ET (0,4% pleno sobre retiros)';
        } else {
            // Cuenta exenta: tope mensual 350 UVT (Art. 879 num. 1 ET)
            $topeUvt = ParametroTributarioService::obtenerValor('GMF_TOPE_EXENTO_UVT', $fechaHecho);
            $uvt = ParametroTributarioService::obtenerUvt($fechaHecho);
            $topeCop = IngresoService::redondear(bcmul($topeUvt, $uvt, 4));

            $acumuladoTotal = bcadd($retirosMesAcumuladosPrevios, $montoRetiro, 2);

            if (bccomp($acumuladoTotal, $topeCop, 2) <= 0) {
                $baseGravada = '0.00';
                $gmf = '0.00';
                $norma = "Art. 879 num. 1 ET (Exento: acumulado {$acumuladoTotal} ≤ tope 350 UVT = {$topeCop})";
            } else {
                if (bccomp($retirosMesAcumuladosPrevios, $topeCop, 2) >= 0) {
                    $baseGravada = $montoRetiro;
                } else {
                    $baseGravada = bcsub($acumuladoTotal, $topeCop, 2);
                }
                $gmf = IngresoService::redondear(bcmul($baseGravada, bcdiv($gmfTarifa, '100', 6), 4));
                $norma = "Art. 879 num. 1 ET (Gravado exceso de 350 UVT: base {$baseGravada})";
            }
        }

        $neto = bcsub($montoRetiro, $gmf, 2);

        $desglose = [
            [
                'concepto' => 'Retiro solicitado',
                'base' => $montoRetiro,
                'tarifa' => 'N/A',
                'valor' => $montoRetiro,
                'norma' => 'Transacción de retiro',
            ],
            [
                'concepto' => 'Gravamen a los Movimientos Financieros (GMF 4x1000)',
                'base' => $baseGravada,
                'tarifa' => number_format((float) $gmfTarifa, 2) . '%',
                'valor' => $gmf,
                'norma' => $norma,
            ],
        ];

        return [
            'monto_retiro' => $montoRetiro,
            'base_gravada_gmf' => $baseGravada,
            'gmf_aplicado' => $gmf,
            'neto_desembolsado' => $neto,
            'detalle_lineas' => $desglose,
        ];
    }

    /**
     * 3. Acciones / ETF - Recálculo de costo promedio ponderado tras compra (9.6.1)
     */
    public static function recalcularCostoPromedio(
        string $unidadesPrevias,
        string $costoPromedioPrevio,
        string $unidadesNuevas,
        string $precioNuevo,
        string $comision = '0.00',
        string $trm = '1.0000'
    ): array {
        $costoAnterior = bcmul($unidadesPrevias, $costoPromedioPrevio, 8);
        $costoNuevoCompra = bcmul($unidadesNuevas, $precioNuevo, 8);
        $costoTotalCop = bcadd(bcadd($costoAnterior, $costoNuevoCompra, 8), $comision, 8);

        $totalUnidades = bcadd($unidadesPrevias, $unidadesNuevas, 8);

        if (bccomp($totalUnidades, '0', 8) <= 0) {
            return [
                'unidades_total' => '0.00000000',
                'costo_promedio_unitario' => '0.00000000',
            ];
        }

        $nuevoCostoPromedio = bcdiv($costoTotalCop, $totalUnidades, 8);

        return [
            'unidades_total' => $totalUnidades,
            'costo_promedio_unitario' => $nuevoCostoPromedio,
        ];
    }

    /**
     * 3. Acciones / ETF - Venta y Clasificación Fiscal (5.4, 9.2, 9.6.2)
     */
    public static function calcularVentaAcciones(
        string $unidadesVendidas,
        string $precioVenta,
        string $costoPromedioUnitario,
        string $comisionVenta = '0.00',
        bool $estaInscritoBvc = false,
        string $porcentajeCirculacionVendidoAnio = '0.00',
        string $fechaPrimeraCompra = '',
        ?string $fechaVenta = null,
        ?string $tarifaMarginalUsuario = null,
        string $trmVenta = '1.0000',
        string $trmCompra = '1.0000'
    ): array {
        $fechaHecho = $fechaVenta ?? CarbonImmutable::today()->toDateString();

        $ingresoOriginal = IngresoService::redondear(bcmul($unidadesVendidas, $precioVenta, 6));
        $costoOriginal = IngresoService::redondear(bcmul($unidadesVendidas, $costoPromedioUnitario, 6));
        $comisionOriginal = IngresoService::redondear($comisionVenta);
        $utilidadOriginal = bcsub(bcsub($ingresoOriginal, $costoOriginal, 2), $comisionOriginal, 2);
        $netoAntesImpuestoOriginal = bcsub($ingresoOriginal, $comisionOriginal, 2);

        $ingresoCop = bcmul(bcmul($unidadesVendidas, $precioVenta, 6), $trmVenta, 2);
        $costoCop = bcmul(bcmul($unidadesVendidas, $costoPromedioUnitario, 6), $trmCompra, 2);
        $comisionCop = bcmul($comisionVenta, $trmVenta, 2);

        $utilidadCop = bcsub(bcsub($ingresoCop, $costoCop, 2), $comisionCop, 2);

        $claseFiscal = 'NO_GRAVADA';
        $tarifaImpuesto = '0.00';
        $impuestoEstimado = '0.00';
        $norma = '';

        if (bccomp($utilidadCop, '0', 2) <= 0) {
            $claseFiscal = 'PERDIDA';
            $tarifaImpuesto = '0.00';
            $impuestoEstimado = '0.00';
            $norma = 'Sección 9.1.5: Las pérdidas se informan y no se compensan.';
        } elseif ($estaInscritoBvc && bccomp($porcentajeCirculacionVendidoAnio, '3.00', 2) <= 0) {
            $claseFiscal = 'NO_GRAVADA';
            $tarifaImpuesto = '0.00';
            $impuestoEstimado = '0.00';
            $norma = 'Art. 36-1 Estatuto Tributario (Acción BVC venta ≤ 3% de acciones en circulación en el año)';
        } else {
            // Evaluar años cumplidos
            $aniosTenencia = 0;
            if (! empty($fechaPrimeraCompra)) {
                $aniosTenencia = (int) CarbonImmutable::parse($fechaPrimeraCompra)->diffInYears(CarbonImmutable::parse($fechaHecho));
            }

            $aniosRequeridosGo = (int) ParametroTributarioService::obtenerValor('GO_ANIOS_TENENCIA', $fechaHecho);

            if ($aniosTenencia >= $aniosRequeridosGo) {
                $claseFiscal = 'GANANCIA_OCASIONAL';
                $tarifaImpuesto = ParametroTributarioService::obtenerValor('GO_TARIFA', $fechaHecho); // 15%
                $impuestoEstimado = IngresoService::redondear(bcmul($utilidadCop, bcdiv($tarifaImpuesto, '100', 6), 4));
                $norma = "Arts. 300 y 314 Estatuto Tributario (Tenencia ≥ {$aniosRequeridosGo} años: Tarifa 15% Ganancia Ocasional)";
            } else {
                $claseFiscal = 'RENTA_ORDINARIA';
                $tarifaImpuesto = $tarifaMarginalUsuario ?? '0.00';
                $impuestoEstimado = IngresoService::redondear(bcmul($utilidadCop, bcdiv($tarifaImpuesto, '100', 6), 4));
                $norma = "Art. 241 Estatuto Tributario (Tenencia < {$aniosRequeridosGo} años: Renta ordinaria a tarifa marginal {$tarifaImpuesto}%)";
            }
        }

        $desglose = [
            [
                'concepto' => 'Ingreso bruto por venta',
                'base' => $unidadesVendidas . ' unidades @ ' . $precioVenta,
                'tarifa' => 'TRM: ' . $trmVenta,
                'valor' => $ingresoCop,
                'norma' => 'Venta en mercado bursátil',
            ],
            [
                'concepto' => 'Costo fiscal promedio ponderado',
                'base' => $unidadesVendidas . ' unidades @ ' . $costoPromedioUnitario,
                'tarifa' => 'TRM compra: ' . $trmCompra,
                'valor' => $costoCop,
                'norma' => 'Sección 9.2: Posición unificada con costo promedio ponderado',
            ],
            [
                'concepto' => 'Comisión de venta',
                'base' => $comisionVenta,
                'tarifa' => 'N/A',
                'valor' => $comisionCop,
                'norma' => 'Gasto de corretaje bursátil',
            ],
            [
                'concepto' => 'Utilidad realizada calculada',
                'base' => 'Ingreso - Costo - Comisión',
                'tarifa' => 'N/A',
                'valor' => $utilidadCop,
                'norma' => 'Utilidad fiscal de la operación',
            ],
            [
                'concepto' => "Impuesto estimado ({$claseFiscal})",
                'base' => bccomp($utilidadCop, '0', 2) > 0 ? $utilidadCop : '0.00',
                'tarifa' => number_format((float) $tarifaImpuesto, 2) . '%',
                'valor' => $impuestoEstimado,
                'norma' => $norma,
            ],
        ];

        return [
            'ingreso_bruto_original' => $ingresoOriginal,
            'costo_original' => $costoOriginal,
            'comision_original' => $comisionOriginal,
            'utilidad_original' => $utilidadOriginal,
            'neto_antes_impuesto_original' => $netoAntesImpuestoOriginal,
            'anios_tenencia' => $aniosTenencia ?? 0,
            'ingreso_bruto_cop' => $ingresoCop,
            'costo_cop' => $costoCop,
            'comision_cop' => $comisionCop,
            'utilidad_cop' => $utilidadCop,
            'clase_fiscal' => $claseFiscal,
            'tarifa_impuesto' => $tarifaImpuesto,
            'impuesto_renta_estimado' => $impuestoEstimado,
            'retencion_total' => '0.00', // Sin retención en venta BVC/exterior
            'gmf_total' => '0.00',
            'neto_antes_impuesto' => bcsub($ingresoCop, $comisionCop, 2),
            'neto_despues_impuesto' => bcsub(bcsub($ingresoCop, $comisionCop, 2), $impuestoEstimado, 2),
            'detalle_lineas' => $desglose,
        ];
    }

    /**
     * 3. Dividendos de Acciones y ETF (Art. 242 E.T., 9.2, 9.6.4, 9.8.1)
     * - Acciones nacionales BVC: Aplica tabla oficial Art. 242 E.T. (0% hasta 1.090 UVT, 15% sobre el exceso).
     * - Activos extranjeros / ETF: Retención parametrizable según tipo de activo / reparto o en origen.
     * - Soporte multi-moneda (COP y USD): Si el dividendo está en USD, se convierte a COP usando la TRM
     *   (suministrada o consultada automáticamente de la base de datos).
     */
    public static function calcularDividendo(
        string $unidades,
        string $valorDividendoPorUnidad,
        string $tipoReparto = 'DISTRIBUTIVO',
        string $impuestoOrigen = '0.00',
        ?string $fecha = null,
        string $mercado = 'BVC',
        string $subtipo = 'ACCION',
        string $moneda = 'COP',
        ?string $trm = null
    ): array {
        $fechaHecho = $fecha ?? CarbonImmutable::today()->toDateString();
        $moneda = strtoupper(trim($moneda ?: 'COP'));

        // Resolver TRM si la moneda es USD
        if ($moneda === 'USD') {
            if ($trm === null || $trm === '' || $trm === '1.0000') {
                try {
                    $trm = TrmService::tasaPara('USD', null, $fechaHecho);
                } catch (\Throwable $e) {
                    $trm = $trm ?: '1.0000';
                }
            }
        } else {
            $trm = '1.0000';
        }

        $brutoMonedaOriginal = IngresoService::redondear(bcmul($unidades, $valorDividendoPorUnidad, 4));

        if ($moneda === 'USD') {
            $brutoCop = IngresoService::redondear(bcmul($brutoMonedaOriginal, $trm, 4));
            // Si el impuesto en origen viene en USD, se convierte a COP para el cálculo fiscal consolidado
            $impuestoOrigenCop = IngresoService::redondear(bcmul($impuestoOrigen, $trm, 4));
        } else {
            $brutoCop = $brutoMonedaOriginal;
            $impuestoOrigenCop = $impuestoOrigen;
        }

        $esNacionalBvc = strtoupper($mercado) === 'BVC' && strtoupper($subtipo) !== 'ETF';

        if ($esNacionalBvc) {
            // Tabla oficial del Parágrafo Art. 242 Estatuto Tributario (modificado Ley 2277 de 2022)
            $uvt = ParametroTributarioService::obtenerValor('UVT', $fechaHecho);
            $umbralUvt = ParametroTributarioService::obtenerValor('DIVIDENDOS_UMBRAL_UVT_BVC', $fechaHecho); // 1.090 UVT
            $tarifaExceso = ParametroTributarioService::obtenerValor('DIVIDENDOS_TARIFA_EXCESO_BVC', $fechaHecho); // 15%
            $topeExentoCop = bcmul($umbralUvt, $uvt, 2);

            if (bccomp($brutoCop, $topeExentoCop, 2) <= 0) {
                $retencionLocalCop = '0.00';
                $tarifaRetEfectiva = '0.00';
                $normaRet = "Parágrafo Art. 242 E.T. (Ley 2277/2022): Dividendos ≤ 1.090 UVT ($ " . number_format((float) $topeExentoCop, 0, ',', '.') . ") tienen tarifa 0%";
            } else {
                $excesoCop = bcsub($brutoCop, $topeExentoCop, 2);
                $retencionLocalCop = IngresoService::redondear(bcmul($excesoCop, bcdiv($tarifaExceso, '100', 6), 4));
                $tarifaRetEfectiva = bcmul(bcdiv($retencionLocalCop, $brutoCop, 6), '100', 2);
                $normaRet = "Parágrafo Art. 242 E.T. (Ley 2277/2022): 15% sobre exceso de 1.090 UVT ($ " . number_format((float) $topeExentoCop, 0, ',', '.') . ")";
            }
        } else {
            // Retención según tipo_reparto
            $claveTarifa = strtoupper($tipoReparto) === 'ACUMULATIVO'
                ? 'DIVIDENDOS_RETENCION_ACUMULATIVO'
                : 'DIVIDENDOS_RETENCION_INDIVIDUAL';

            $tarifaRet = ParametroTributarioService::obtenerValor($claveTarifa, $fechaHecho);
            $paramRet = ParametroTributarioService::obtener($claveTarifa, $fechaHecho);

            $retencionLocalCop = IngresoService::redondear(bcmul($brutoCop, bcdiv($tarifaRet, '100', 6), 4));
            $tarifaRetEfectiva = $tarifaRet;
            $normaRet = $paramRet->norma_referencia ?? 'Art. 242 ET / Sección 9.8.1';
        }

        $netoCop = bcsub(bcsub($brutoCop, $impuestoOrigenCop, 2), $retencionLocalCop, 2);

        // Si es en USD, calcular también equivalencias en moneda original
        if ($moneda === 'USD' && bccomp($trm, '0', 4) > 0) {
            $retencionLocalOriginal = IngresoService::redondear(bcdiv($retencionLocalCop, $trm, 4));
            $netoMonedaOriginal = bcsub(bcsub($brutoMonedaOriginal, $impuestoOrigen, 2), $retencionLocalOriginal, 2);
        } else {
            $retencionLocalOriginal = $retencionLocalCop;
            $netoMonedaOriginal = $netoCop;
        }

        $baseTexto = $moneda === 'USD'
            ? "{$unidades} acciones @ {$valorDividendoPorUnidad} USD (TRM: {$trm})"
            : "{$unidades} acciones @ {$valorDividendoPorUnidad} COP";

        $desglose = [
            [
                'concepto' => 'Dividendo bruto liquidado',
                'base' => $baseTexto,
                'tarifa' => $moneda === 'USD' ? "TRM: {$trm}" : 'N/A',
                'valor' => $brutoCop,
                'norma' => 'Decreto y pago de dividendos' . ($moneda === 'USD' ? " ($ " . number_format((float)$brutoMonedaOriginal, 2) . " USD)" : ''),
            ],
            [
                'concepto' => 'Retención en origen internacional',
                'base' => $moneda === 'USD' ? "$ " . number_format((float)$brutoMonedaOriginal, 2) . " USD ($ " . number_format((float)$brutoCop, 2) . " COP)" : "$ " . number_format((float)$brutoCop, 2) . " COP",
                'tarifa' => 'En origen',
                'valor' => $impuestoOrigenCop,
                'norma' => 'Tax withholding extranjero (si aplica)' . ($moneda === 'USD' && bccomp($impuestoOrigen, '0', 2) > 0 ? " ($ " . number_format((float)$impuestoOrigen, 2) . " USD)" : ''),
            ],
            [
                'concepto' => 'Retención en la fuente local',
                'base' => "$ " . number_format((float)$brutoCop, 2) . " COP",
                'tarifa' => number_format((float) $tarifaRetEfectiva, 2) . '%',
                'valor' => $retencionLocalCop,
                'norma' => $normaRet . ($moneda === 'USD' ? " ($ " . number_format((float)$retencionLocalOriginal, 2) . " USD)" : ''),
            ],
        ];

        return [
            'bruto' => $brutoCop,
            'bruto_cop' => $brutoCop,
            'bruto_original' => $brutoMonedaOriginal,
            'impuesto_origen' => $impuestoOrigenCop,
            'impuesto_origen_original' => $impuestoOrigen,
            'retencion_aplicada' => $retencionLocalCop,
            'retencion_aplicada_original' => $retencionLocalOriginal,
            'neto' => $netoCop,
            'neto_cop' => $netoCop,
            'neto_original' => $netoMonedaOriginal,
            'moneda' => $moneda,
            'trm' => $trm,
            'detalle_lineas' => $desglose,
        ];
    }

    /**
     * 4. FIC - Redención con imputación a utilidad y tarifa ponderada (5.5, 9.2, 9.4)
     */
    public static function calcularRedencionFic(
        string $montoRedencion,
        string $valorActualFondo,
        string $totalAportesNetos,
        string $utilidadYaRedimida,
        string $tarifaPonderada,
        string $comisionSalida = '0.00'
    ): array {
        // Rendimiento acumulado = valor actual - total aportes netos
        $rendimientoAcumulado = bcsub($valorActualFondo, $totalAportesNetos, 2);
        if (bccomp($rendimientoAcumulado, '0', 2) < 0) {
            $rendimientoAcumulado = '0.00';
        }

        // Utilidad pendiente de retiro U = rendimiento acumulado - utilidad ya redimida
        $utilidadPendienteU = bcsub($rendimientoAcumulado, $utilidadYaRedimida, 2);
        if (bccomp($utilidadPendienteU, '0', 2) < 0) {
            $utilidadPendienteU = '0.00';
        }

        // Imputación prioritaria a utilidad: parte_utilidad = min(M, max(0, U))
        $parteUtilidad = bccomp($montoRedencion, $utilidadPendienteU, 2) <= 0
            ? $montoRedencion
            : $utilidadPendienteU;

        $parteCapital = bcsub($montoRedencion, $parteUtilidad, 2);

        // Retención practicada solo sobre la parte imputada a utilidad
        $retencion = IngresoService::redondear(bcmul($parteUtilidad, bcdiv($tarifaPonderada, '100', 6), 4));

        $neto = bcsub(bcsub($montoRedencion, $retencion, 2), $comisionSalida, 2);

        $desglose = [
            [
                'concepto' => 'Monto total de redención',
                'base' => $montoRedencion,
                'tarifa' => 'N/A',
                'valor' => $montoRedencion,
                'norma' => 'Orden de redención del fondo',
            ],
            [
                'concepto' => 'Imputación a Utilidad Acumulada',
                'base' => "Pendiente U = {$utilidadPendienteU}",
                'tarifa' => 'Prioridad 1',
                'valor' => $parteUtilidad,
                'norma' => 'Art. 1.2.4.2.78 Dec. 1625/2016 (Imputación preferente a utilidad)',
            ],
            [
                'concepto' => 'Imputación a Reembolso de Capital',
                'base' => "Excedente de redención",
                'tarifa' => 'Prioridad 2',
                'valor' => $parteCapital,
                'norma' => 'Reembolso no gravado de capital propio aportado',
            ],
            [
                'concepto' => 'Retención en la fuente ponderada del fondo',
                'base' => $parteUtilidad,
                'tarifa' => number_format((float) $tarifaPonderada, 2) . '%',
                'valor' => $retencion,
                'norma' => 'Tarifa ponderada por composición de cartera del fondo (Sección 9.4)',
            ],
            [
                'concepto' => 'Comisión de salida',
                'base' => $montoRedencion,
                'tarifa' => 'N/A',
                'valor' => $comisionSalida,
                'norma' => 'Comisión de administración o salida',
            ],
        ];

        return [
            'monto_redencion' => $montoRedencion,
            'parte_utilidad' => $parteUtilidad,
            'parte_capital' => $parteCapital,
            'tarifa_ponderada' => $tarifaPonderada,
            'retencion_aplicada' => $retencion,
            'comision_salida' => $comisionSalida,
            'neto' => $neto,
            'detalle_lineas' => $desglose,
        ];
    }

    /**
     * 5. FPV - Validación de Topes de Aporte (5.6, 9.8.2)
     */
    public static function validarTopeAporteFpv(
        string $montoAporte,
        string $ingresoAnual,
        string $aportesAfcAnio = '0.00',
        string $aportesFpvAnio = '0.00',
        ?string $fecha = null
    ): array {
        $fechaHecho = $fecha ?? CarbonImmutable::today()->toDateString();
        $uvt = ParametroTributarioService::obtenerUvt($fechaHecho);
        $topeUvt = ParametroTributarioService::obtenerValor('FPV_TOPE_UVT', $fechaHecho); // 3.800 UVT
        $topeCopUvt = IngresoService::redondear(bcmul($topeUvt, $uvt, 4));

        $topePorc = ParametroTributarioService::obtenerValor('FPV_TOPE_PORC_INGRESO', $fechaHecho); // 30%
        $topeCopIngreso = IngresoService::redondear(bcmul($ingresoAnual, bcdiv($topePorc, '100', 4), 4));

        // Menor entre 30% del ingreso y 3.800 UVT
        $limiteMaximo = bccomp($topeCopIngreso, $topeCopUvt, 2) <= 0 ? $topeCopIngreso : $topeCopUvt;

        $aportesPrevios = bcadd($aportesAfcAnio, $aportesFpvAnio, 2);
        $cupoDisponible = bcsub($limiteMaximo, $aportesPrevios, 2);
        if (bccomp($cupoDisponible, '0', 2) < 0) {
            $cupoDisponible = '0.00';
        }

        $parteDentroTope = bccomp($montoAporte, $cupoDisponible, 2) <= 0 ? $montoAporte : $cupoDisponible;
        $parteExcedente = bcsub($montoAporte, $parteDentroTope, 2);
        $excedeTope = bccomp($parteExcedente, '0', 2) > 0;

        return [
            'monto_aporte' => $montoAporte,
            'limite_maximo_anual' => $limiteMaximo,
            'cupo_disponible' => $cupoDisponible,
            'parte_dentro_tope' => $parteDentroTope,
            'parte_excedente' => $parteExcedente,
            'excede_tope' => $excedeTope,
            'norma' => 'Art. 126-1 ET: Menor entre 30% del ingreso y 3.800 UVT anuales',
        ];
    }

    /**
     * 5. FPV - Retiro y Retención Contingente (5.6, 9.6.5)
     */
    public static function calcularRetiroFpv(
        string $montoRetiro,
        bool $cumplePermanenciaODestino,
        string $proporcionRendimiento = '0.00',
        ?string $fecha = null,
        string $aportesSinBeneficioDisponibles = '0.00'
    ): array {
        $fechaHecho = $fecha ?? CarbonImmutable::today()->toDateString();
        $gmf = '0.00'; // 5.6.5 / RF-IMP-03: GMF = 0 en retiros de FPV

        if ($cumplePermanenciaODestino) {
            return [
                'monto_retiro' => $montoRetiro,
                'aportes_retirados' => $montoRetiro,
                'rendimientos_retirados' => '0.00',
                'retencion_aplicada' => '0.00',
                'gmf_aplicado' => '0.00',
                'neto' => $montoRetiro,
                'detalle_lineas' => [
                    [
                        'concepto' => 'Retiro FPV con beneficio tributario',
                        'base' => $montoRetiro,
                        'tarifa' => '0%',
                        'valor' => '0.00',
                        'norma' => 'Art. 126-1 ET (Permanencia ≥ 10 años o destino vivienda/pensión)',
                    ],
                ],
            ];
        }

        // Retiro anticipado sin beneficio (9.6.5): 7% sobre aporte con beneficio + 7% sobre rendimientos
        $tarifa = ParametroTributarioService::obtenerValor('FPV_RETENCION_RETIRO_ANTICIPADO', $fechaHecho); // 7%
        $paramRet = ParametroTributarioService::obtener('FPV_RETENCION_RETIRO_ANTICIPADO', $fechaHecho);

        $rendimientosRetirados = IngresoService::redondear(bcmul($montoRetiro, $proporcionRendimiento, 4));
        $aportesTotalesRetirados = bcsub($montoRetiro, $rendimientosRetirados, 2);

        // Se consumen primero los aportes que no tuvieron beneficio tributario (exentos de retención contingente)
        $retiroSinBeneficio = bccomp($aportesTotalesRetirados, $aportesSinBeneficioDisponibles, 2) <= 0
            ? $aportesTotalesRetirados
            : $aportesSinBeneficioDisponibles;

        // La porción restante corresponde a aportes con beneficio tributario (sujeta a retención contingente)
        $aportesConBeneficioRetirados = bcsub($aportesTotalesRetirados, $retiroSinBeneficio, 2);

        $retAportes = IngresoService::redondear(bcmul($aportesConBeneficioRetirados, bcdiv($tarifa, '100', 4), 4));
        $retRendimientos = IngresoService::redondear(bcmul($rendimientosRetirados, bcdiv($tarifa, '100', 4), 4));
        $retencionTotal = bcadd($retAportes, $retRendimientos, 2);

        $neto = bcsub($montoRetiro, $retencionTotal, 2);

        $desglose = [];

        if (bccomp($retiroSinBeneficio, '0', 2) > 0) {
            $desglose[] = [
                'concepto' => 'Retiro de aportes sin beneficio tributario previo',
                'base' => $retiroSinBeneficio,
                'tarifa' => '0.00%',
                'valor' => '0.00',
                'norma' => 'Art. 126-1 ET: Aportes que no constituyeron renta exenta al momento del ingreso no sufren retención contingente',
            ];
        }

        if (bccomp($aportesConBeneficioRetirados, '0', 2) > 0) {
            $desglose[] = [
                'concepto' => 'Retención contingente sobre aportes con beneficio',
                'base' => $aportesConBeneficioRetirados,
                'tarifa' => number_format((float) $tarifa, 2) . '%',
                'valor' => $retAportes,
                'norma' => $paramRet->norma_referencia ?? 'Art. 1.2.4.1.33 Dec. 1625/2016 (7% contingente)',
            ];
        }

        if (bccomp($rendimientosRetirados, '0', 2) > 0) {
            $desglose[] = [
                'concepto' => 'Retención sobre rendimientos del fondo',
                'base' => $rendimientosRetirados,
                'tarifa' => number_format((float) $tarifa, 2) . '%',
                'valor' => $retRendimientos,
                'norma' => 'Retención sobre rendimientos retirados sin permanencia (7%)',
            ];
        }

        return [
            'monto_retiro' => $montoRetiro,
            'aportes_retirados' => $aportesConBeneficioRetirados,
            'retiro_sin_beneficio' => $retiroSinBeneficio,
            'retiro_con_beneficio' => $aportesConBeneficioRetirados,
            'rendimientos_retirados' => $rendimientosRetirados,
            'retencion_aplicada' => $retencionTotal,
            'retencion_aportes' => $retAportes,
            'retencion_rendimientos' => $retRendimientos,
            'gmf_aplicado' => '0.00',
            'neto' => $neto,
            'detalle_lineas' => $desglose,
        ];
    }

    /**
     * 6. Cesantías - Retiro y Exención por tabla Art. 206 #4 (5.7, 9.2, RF-IMP-08)
     */
    public static function calcularRetiroCesantias(
        string $montoRetirado,
        string $ingresoLaboralPromedio6m,
        ?string $fecha = null
    ): array {
        $fechaHecho = $fecha ?? CarbonImmutable::today()->toDateString();
        $exencion = ParametroTributarioService::obtenerExencionCesantias($ingresoLaboralPromedio6m, $fechaHecho);

        $porcExento = $exencion['porcentaje_exento'];
        $parteExenta = IngresoService::redondear(bcmul($montoRetirado, bcdiv($porcExento, '100', 4), 4));
        $parteGravada = bcsub($montoRetirado, $parteExenta, 2);

        $desglose = [
            [
                'concepto' => 'Retiro de Cesantías e Intereses',
                'base' => $montoRetirado,
                'tarifa' => 'Retención 0%',
                'valor' => $montoRetirado,
                'norma' => 'Art. 206 Numeral 4 ET: Retención en la fuente 0%',
            ],
            [
                'concepto' => 'Cesantías renta exenta deducible',
                'base' => "Salario 6m: {$ingresoLaboralPromedio6m} ({$exencion['ingreso_uvt']} UVT)",
                'tarifa' => number_format((float) $porcExento, 2) . '%',
                'valor' => $parteExenta,
                'norma' => $exencion['norma'],
            ],
            [
                'concepto' => 'Cesantías gravadas informativas',
                'base' => $montoRetirado,
                'tarifa' => number_format((float) (100 - (float) $porcExento), 2) . '%',
                'valor' => $parteGravada,
                'norma' => 'Sujeto a cédula general en declaración de renta',
            ],
        ];

        return [
            'monto_retirado' => $montoRetirado,
            'retencion_aplicada' => '0.00',
            'gmf_aplicado' => '0.00',
            'parte_exenta' => $parteExenta,
            'parte_gravada' => $parteGravada,
            'porcentaje_exento' => $porcExento,
            'neto' => $montoRetirado,
            'detalle_lineas' => $desglose,
        ];
    }
}
