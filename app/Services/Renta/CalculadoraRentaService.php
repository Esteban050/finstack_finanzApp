<?php

namespace App\Services\Renta;

use Carbon\CarbonImmutable;

class CalculadoraRentaService
{
    /**
     * Evalúa los 5 conceptos financieros de la DIAN para determinar si la persona natural
     * está obligada a declarar renta y lista específicamente por cuáles conceptos supera los topes.
     */
    public function evaluarObligacion(array $datos, int $anioGravable): array
    {
        $params = ConsolidadoRentaService::obtenerParametros($anioGravable);
        $uvt = (float) $params['uvt'];

        // Topes en COP según UVT del año
        $topeC1Cop = (float) ($params['tope_patrimonio_uvt'] * $uvt); // 4.500 UVT
        $topeC2Cop = (float) ($params['tope_ingresos_uvt'] * $uvt);   // 1.400 UVT
        $topeC3Cop = (float) ($params['tope_tarjetas_uvt'] * $uvt);   // 1.400 UVT
        $topeC4Cop = (float) ($params['tope_compras_uvt'] * $uvt);    // 1.400 UVT
        $topeC5Cop = (float) ($params['tope_consignaciones_uvt'] * $uvt); // 1.400 UVT

        // Montos ingresados (no negativos)
        $valC1 = max(0.0, (float) ($datos['patrimonio_bruto_cop'] ?? 0.0));
        $valC2 = max(0.0, (float) ($datos['ingresos_brutos_cop'] ?? 0.0));
        $valC3 = max(0.0, (float) ($datos['consumos_tarjeta_cop'] ?? 0.0));
        $valC4 = max(0.0, (float) ($datos['compras_totales_cop'] ?? 0.0));
        $valC5 = max(0.0, (float) ($datos['consignaciones_cop'] ?? 0.0));

        // 1. C1: Patrimonio > 4.500 UVT
        $cumpleC1 = $valC1 > $topeC1Cop;
        $margenC1 = max(0.0, $topeC1Cop - $valC1);
        $excesoC1 = max(0.0, $valC1 - $topeC1Cop);
        $cercaC1 = (! $cumpleC1 && $topeC1Cop > 0 && ($valC1 / $topeC1Cop) >= 0.90);
        $pctC1 = $topeC1Cop > 0 ? min(100.0, round(($valC1 / $topeC1Cop) * 100, 1)) : 0.0;

        // 2. C2: Ingresos >= 1.400 UVT (operador mayor o igual)
        $cumpleC2 = $valC2 >= $topeC2Cop;
        $margenC2 = max(0.0, $topeC2Cop - $valC2);
        $excesoC2 = max(0.0, $valC2 - $topeC2Cop);
        $cercaC2 = (! $cumpleC2 && $topeC2Cop > 0 && ($valC2 / $topeC2Cop) >= 0.90);
        $pctC2 = $topeC2Cop > 0 ? min(100.0, round(($valC2 / $topeC2Cop) * 100, 1)) : 0.0;

        // 3. C3: Consumos tarjeta > 1.400 UVT
        $cumpleC3 = $valC3 > $topeC3Cop;
        $margenC3 = max(0.0, $topeC3Cop - $valC3);
        $excesoC3 = max(0.0, $valC3 - $topeC3Cop);
        $cercaC3 = (! $cumpleC3 && $topeC3Cop > 0 && ($valC3 / $topeC3Cop) >= 0.90);
        $pctC3 = $topeC3Cop > 0 ? min(100.0, round(($valC3 / $topeC3Cop) * 100, 1)) : 0.0;

        // 4. C4: Compras totales > 1.400 UVT
        $cumpleC4 = $valC4 > $topeC4Cop;
        $margenC4 = max(0.0, $topeC4Cop - $valC4);
        $excesoC4 = max(0.0, $valC4 - $topeC4Cop);
        $cercaC4 = (! $cumpleC4 && $topeC4Cop > 0 && ($valC4 / $topeC4Cop) >= 0.90);
        $pctC4 = $topeC4Cop > 0 ? min(100.0, round(($valC4 / $topeC4Cop) * 100, 1)) : 0.0;

        // 5. C5: Consignaciones / depósitos / inversiones > 1.400 UVT
        $cumpleC5 = $valC5 > $topeC5Cop;
        $margenC5 = max(0.0, $topeC5Cop - $valC5);
        $excesoC5 = max(0.0, $valC5 - $topeC5Cop);
        $cercaC5 = (! $cumpleC5 && $topeC5Cop > 0 && ($valC5 / $topeC5Cop) >= 0.90);
        $pctC5 = $topeC5Cop > 0 ? min(100.0, round(($valC5 / $topeC5Cop) * 100, 1)) : 0.0;

        $criterios = [
            'c1' => [
                'id' => 'c1',
                'codigo' => 'patrimonio',
                'nombre' => 'Patrimonio bruto al 31 de diciembre',
                'norma' => 'Art. 592 num. 1 y Art. 594-3 E.T.',
                'tope_uvt' => $params['tope_patrimonio_uvt'],
                'tope_cop' => $topeC1Cop,
                'operador' => '>',
                'valor_cop' => $valC1,
                'valor_uvt' => ConsolidadoRentaService::aUvt($valC1, $uvt),
                'cumple' => $cumpleC1,
                'cerca' => $cercaC1,
                'porcentaje' => $pctC1,
                'margen_disponible_cop' => $margenC1,
                'margen_disponible_uvt' => ConsolidadoRentaService::aUvt($margenC1, $uvt),
                'exceso_cop' => $excesoC1,
                'exceso_uvt' => ConsolidadoRentaService::aUvt($excesoC1, $uvt),
            ],
            'c2' => [
                'id' => 'c2',
                'codigo' => 'ingresos',
                'nombre' => 'Ingresos brutos del año gravable',
                'norma' => 'Art. 592 num. 1 y Art. 593 E.T.',
                'tope_uvt' => $params['tope_ingresos_uvt'],
                'tope_cop' => $topeC2Cop,
                'operador' => '>=',
                'valor_cop' => $valC2,
                'valor_uvt' => ConsolidadoRentaService::aUvt($valC2, $uvt),
                'cumple' => $cumpleC2,
                'cerca' => $cercaC2,
                'porcentaje' => $pctC2,
                'margen_disponible_cop' => $margenC2,
                'margen_disponible_uvt' => ConsolidadoRentaService::aUvt($margenC2, $uvt),
                'exceso_cop' => $excesoC2,
                'exceso_uvt' => ConsolidadoRentaService::aUvt($excesoC2, $uvt),
            ],
            'c3' => [
                'id' => 'c3',
                'codigo' => 'tarjetas',
                'nombre' => 'Consumos mediante tarjeta de crédito',
                'norma' => 'Art. 594-3 num. 1 E.T.',
                'tope_uvt' => $params['tope_tarjetas_uvt'],
                'tope_cop' => $topeC3Cop,
                'operador' => '>',
                'valor_cop' => $valC3,
                'valor_uvt' => ConsolidadoRentaService::aUvt($valC3, $uvt),
                'cumple' => $cumpleC3,
                'cerca' => $cercaC3,
                'porcentaje' => $pctC3,
                'margen_disponible_cop' => $margenC3,
                'margen_disponible_uvt' => ConsolidadoRentaService::aUvt($margenC3, $uvt),
                'exceso_cop' => $excesoC3,
                'exceso_uvt' => ConsolidadoRentaService::aUvt($excesoC3, $uvt),
            ],
            'c4' => [
                'id' => 'c4',
                'codigo' => 'compras',
                'nombre' => 'Compras y consumos totales del año',
                'norma' => 'Art. 594-3 num. 2 E.T.',
                'tope_uvt' => $params['tope_compras_uvt'],
                'tope_cop' => $topeC4Cop,
                'operador' => '>',
                'valor_cop' => $valC4,
                'valor_uvt' => ConsolidadoRentaService::aUvt($valC4, $uvt),
                'cumple' => $cumpleC4,
                'cerca' => $cercaC4,
                'porcentaje' => $pctC4,
                'margen_disponible_cop' => $margenC4,
                'margen_disponible_uvt' => ConsolidadoRentaService::aUvt($margenC4, $uvt),
                'exceso_cop' => $excesoC4,
                'exceso_uvt' => ConsolidadoRentaService::aUvt($excesoC4, $uvt),
            ],
            'c5' => [
                'id' => 'c5',
                'codigo' => 'consignaciones',
                'nombre' => 'Consignaciones bancarias, depósitos o inversiones',
                'norma' => 'Art. 594-3 num. 3 E.T.',
                'tope_uvt' => $params['tope_consignaciones_uvt'],
                'tope_cop' => $topeC5Cop,
                'operador' => '>',
                'valor_cop' => $valC5,
                'valor_uvt' => ConsolidadoRentaService::aUvt($valC5, $uvt),
                'cumple' => $cumpleC5,
                'cerca' => $cercaC5,
                'porcentaje' => $pctC5,
                'margen_disponible_cop' => $margenC5,
                'margen_disponible_uvt' => ConsolidadoRentaService::aUvt($margenC5, $uvt),
                'exceso_cop' => $excesoC5,
                'exceso_uvt' => ConsolidadoRentaService::aUvt($excesoC5, $uvt),
            ],
        ];

        // Lista de conceptos específicos que obligan a declarar
        $conceptosQueObligan = [];
        foreach ($criterios as $crit) {
            if ($crit['cumple']) {
                $conceptosQueObligan[] = [
                    'id' => $crit['id'],
                    'codigo' => $crit['codigo'],
                    'nombre' => $crit['nombre'],
                    'norma' => $crit['norma'],
                    'valor_cop' => $crit['valor_cop'],
                    'valor_uvt' => $crit['valor_uvt'],
                    'tope_cop' => $crit['tope_cop'],
                    'tope_uvt' => $crit['tope_uvt'],
                    'operador' => $crit['operador'],
                    'exceso_cop' => $crit['exceso_cop'],
                    'exceso_uvt' => $crit['exceso_uvt'],
                ];
            }
        }

        $estaObligado = count($conceptosQueObligan) > 0;
        $veredicto = $estaObligado ? 'OBLIGADO' : 'NO_OBLIGADO';
        $veredictoLabel = $estaObligado ? 'OBLIGADO A DECLARAR' : 'NO OBLIGADO A DECLARAR';

        return [
            'anio_gravable' => $anioGravable,
            'uvt' => $uvt,
            'esta_obligado' => $estaObligado,
            'veredicto' => $veredicto,
            'veredicto_label' => $veredictoLabel,
            'total_criterios_evaluados' => count($criterios),
            'criterios_cumplidos_conteo' => count($conceptosQueObligan),
            'conceptos_que_obligan' => $conceptosQueObligan,
            'criterios' => $criterios,
        ];
    }

    /**
     * Ejecuta la liquidación de la Cédula General (Arts. 241 y 336 E.T.) para calcular
     * el monto a pagar o saldo a favor. Todos los campos de depuración son opcionales.
     */
    public function calcularLiquidacion(array $datos, int $anioGravable): array
    {
        $params = ConsolidadoRentaService::obtenerParametros($anioGravable);
        $uvt = (float) $params['uvt'];

        // 1. Ingresos Brutos de la Cédula General (IB)
        $ingresosBrutosTotal = max(0.0, (float) ($datos['ingresos_brutos_cop'] ?? 0.0));
        
        // Desglose opcional: Si no se desglosan, el total de ingresos brutos se asume como ingreso laboral
        $ingresosTrabajo = isset($datos['ingresos_trabajo_cop']) && $datos['ingresos_trabajo_cop'] !== null && $datos['ingresos_trabajo_cop'] !== ''
            ? max(0.0, (float) $datos['ingresos_trabajo_cop'])
            : $ingresosBrutosTotal;
        $cesantias = max(0.0, (float) ($datos['cesantias_cop'] ?? 0.0));
        $ingresosCapital = max(0.0, (float) ($datos['ingresos_capital_cop'] ?? 0.0));
        $ingresosNoLaborales = max(0.0, (float) ($datos['ingresos_no_laborales_cop'] ?? 0.0));

        // Si se especificaron subcomponentes que sumen más que el total ingresado, reconciliar
        $sumaComponentes = $ingresosTrabajo + $cesantias + $ingresosCapital + $ingresosNoLaborales;
        if ($sumaComponentes > $ingresosBrutosTotal && $ingresosBrutosTotal > 0) {
            $paso1IngresosBrutosCop = $sumaComponentes;
        } else {
            $paso1IngresosBrutosCop = max($ingresosBrutosTotal, $sumaComponentes);
        }
        $paso1IngresosBrutosUvt = ConsolidadoRentaService::aUvt($paso1IngresosBrutosCop, $uvt);

        // 2. INCRNGO (Opcionales)
        $saludPension = max(0.0, (float) ($datos['salud_pension_cop'] ?? 0.0));
        $compInflacionario = max(0.0, (float) ($datos['componente_inflacionario_cop'] ?? 0.0));
        $paso2IncrngoTotalCop = $saludPension + $compInflacionario;
        $paso2IncrngoTotalUvt = ConsolidadoRentaService::aUvt($paso2IncrngoTotalCop, $uvt);

        // Subtotal: Renta Líquida Ordinaria (RL)
        $rentaLiquidaCop = max(0.0, $paso1IngresosBrutosCop - $paso2IncrngoTotalCop);
        $rentaLiquidaUvt = ConsolidadoRentaService::aUvt($rentaLiquidaCop, $uvt);

        // Renta Líquida de Trabajo (para calcular el 25% exento laboral)
        $rentaLiquidaTrabajoCop = max(0.0, ($ingresosTrabajo + $cesantias) - $saludPension);

        // 3. Rentas Exentas Voluntarias / Inversiones (Opcionales)
        $fpvAfc = max(0.0, (float) ($datos['fpv_afc_cop'] ?? 0.0));
        $topeFpv30Pct = max(0.0, round($paso1IngresosBrutosCop * 0.30) - $saludPension);
        $fpvAceptadoCop = min($fpvAfc, $topeFpv30Pct);

        $cesantiasExentas = max(0.0, (float) ($datos['cesantias_exentas_cop'] ?? 0.0));
        $paso3RentasExentasInvCop = $fpvAceptadoCop + $cesantiasExentas;
        $paso3RentasExentasInvUvt = ConsolidadoRentaService::aUvt($paso3RentasExentasInvCop, $uvt);

        // 4. Renta Exenta Laboral del 25% (Art. 206 num. 10 E.T., máx 790 UVT)
        $base25Cop = max(0.0, $rentaLiquidaTrabajoCop - $paso3RentasExentasInvCop);
        $tope790UvtCop = (float) ($params['limite_exenta_laboral_25_uvt'] * $uvt);
        $paso4RentaExenta25Cop = min(round($base25Cop * 0.25), $tope790UvtCop);
        $paso4RentaExenta25Uvt = ConsolidadoRentaService::aUvt($paso4RentaExenta25Cop, $uvt);

        // 5. Límite Global de Rentas Exentas y Deducciones (Art. 336 E.T.: 40% o 1.340 UVT)
        $sumaExentasBrutasCop = $paso3RentasExentasInvCop + $paso4RentaExenta25Cop;
        $limite40PctCop = round($rentaLiquidaCop * (float) $params['limite_global_exentas_pct']);
        $limite1340UvtCop = (float) ($params['limite_global_exentas_uvt'] * $uvt);
        $topeMaximoGlobalCop = min($limite40PctCop, $limite1340UvtCop);

        $paso5TotalExentasAceptadasCop = min($sumaExentasBrutasCop, $topeMaximoGlobalCop);
        $paso5TotalExentasAceptadasUvt = ConsolidadoRentaService::aUvt($paso5TotalExentasAceptadasCop, $uvt);

        $limiteExentasAplicado = ($sumaExentasBrutasCop > $topeMaximoGlobalCop);
        $excesoNoAprovechadoCop = $limiteExentasAplicado ? ($sumaExentasBrutasCop - $topeMaximoGlobalCop) : 0.0;
        $cupoDisponibleCop = (! $limiteExentasAplicado) ? ($topeMaximoGlobalCop - $sumaExentasBrutasCop) : 0.0;

        // 6. Renta Líquida Gravable (RLG)
        $paso6RlgCop = max(0.0, $rentaLiquidaCop - $paso5TotalExentasAceptadasCop);
        $paso6RlgUvt = ConsolidadoRentaService::aUvt($paso6RlgCop, $uvt);

        // 7. Impuesto según Tabla Progresiva Marginal Art. 241 E.T.
        $calculoImpuesto = EstimacionRentaService::calcularImpuestoArt241($paso6RlgUvt, $uvt);
        $paso7ImpuestoRedondeadoCop = (float) $calculoImpuesto['impuesto_cop'];
        $paso7ImpuestoUvt = (float) $calculoImpuesto['impuesto_uvt'];
        $tarifaMarginal = $calculoImpuesto['tarifa_marginal'];
        $rangoTexto = $calculoImpuesto['rango_texto'];

        // 8. Imputación de Retenciones en la Fuente (Opcional)
        $retencionesBrutas = max(0.0, (float) ($datos['retenciones_fuente_cop'] ?? 0.0));
        $paso8RetencionesRedondeadasCop = (float) (round($retencionesBrutas / 1000.0) * 1000);

        // Saldo neto a pagar o a favor
        $saldoNetoCop = $paso7ImpuestoRedondeadoCop - $paso8RetencionesRedondeadasCop;

        if ($saldoNetoCop > 0) {
            $tipoResultado = 'SALDO_A_PAGAR';
            $saldoLabel = 'Saldo neto a pagar';
            $saldoValorMostrado = $saldoNetoCop;
        } elseif ($saldoNetoCop < 0) {
            $tipoResultado = 'SALDO_A_FAVOR';
            $saldoLabel = 'Saldo a favor';
            $saldoValorMostrado = abs($saldoNetoCop);
        } else {
            $tipoResultado = 'CERO';
            $saldoLabel = 'Saldo en ceros';
            $saldoValorMostrado = 0.0;
        }

        return [
            'ingresos_brutos_cop' => $paso1IngresosBrutosCop,
            'ingresos_brutos_uvt' => $paso1IngresosBrutosUvt,
            'incrngo_total_cop' => $paso2IncrngoTotalCop,
            'incrngo_total_uvt' => $paso2IncrngoTotalUvt,
            'renta_liquida_cop' => $rentaLiquidaCop,
            'renta_liquida_uvt' => $rentaLiquidaUvt,
            'exentas_voluntarias_cop' => $paso3RentasExentasInvCop,
            'exentas_voluntarias_uvt' => $paso3RentasExentasInvUvt,
            'exenta_25_laboral_cop' => $paso4RentaExenta25Cop,
            'exenta_25_laboral_uvt' => $paso4RentaExenta25Uvt,
            'exentas_aceptadas_cop' => $paso5TotalExentasAceptadasCop,
            'exentas_aceptadas_uvt' => $paso5TotalExentasAceptadasUvt,
            'limite_exentas_aplicado' => $limiteExentasAplicado,
            'exceso_no_aprovechado_cop' => $excesoNoAprovechadoCop,
            'cupo_disponible_cop' => $cupoDisponibleCop,
            'renta_liquida_gravable_cop' => $paso6RlgCop,
            'renta_liquida_gravable_uvt' => $paso6RlgUvt,
            'impuesto_cop' => $paso7ImpuestoRedondeadoCop,
            'impuesto_uvt' => $paso7ImpuestoUvt,
            'tarifa_marginal' => $tarifaMarginal,
            'rango_texto' => $rangoTexto,
            'retenciones_cop' => $paso8RetencionesRedondeadasCop,
            'saldo_neto_cop' => $saldoNetoCop,
            'tipo_resultado' => $tipoResultado,
            'saldo_label' => $saldoLabel,
            'saldo_valor' => $saldoValorMostrado,
        ];
    }

    /**
     * Ejecuta la simulación completa reuniendo la evaluación de obligación y la liquidación de impuesto.
     */
    public function calcularCompleto(array $datos, int $anioGravable): array
    {
        $obligacion = $this->evaluarObligacion($datos, $anioGravable);
        $liquidacion = $this->calcularLiquidacion($datos, $anioGravable);
        $params = ConsolidadoRentaService::obtenerParametros($anioGravable);

        return [
            'anio_gravable' => $anioGravable,
            'parametros' => $params,
            'obligacion' => $obligacion,
            'liquidacion' => $liquidacion,
        ];
    }
}
