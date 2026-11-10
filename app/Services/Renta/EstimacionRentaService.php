<?php

namespace App\Services\Renta;

use App\Models\DeclaracionRenta;
use App\Models\Usuario;

class EstimacionRentaService
{
    public function __construct(
        protected ConsolidadoRentaService $consolidadoService,
        protected InformacionComplementariaService $complementariaService
    ) {}

    /**
     * Calcula el impuesto según la tabla progresiva marginal del Artículo 241 del Estatuto Tributario.
     * Retorna el impuesto en COP redondeado al múltiplo de $1.000 más cercano (RN-046.2).
     */
    public static function calcularImpuestoArt241(float $rlgUvt, float $uvt): array
    {
        $impUvt = 0.0;
        $tarifaMarginal = '0%';
        $rangoTexto = '0 a 1.090 UVT';

        if ($rlgUvt <= 1090.0) {
            $impUvt = 0.0;
            $tarifaMarginal = '0%';
            $rangoTexto = '0 a 1.090 UVT';
        } elseif ($rlgUvt <= 1700.0) {
            $impUvt = ($rlgUvt - 1090.0) * 0.19;
            $tarifaMarginal = '19%';
            $rangoTexto = '> 1.090 a 1.700 UVT';
        } elseif ($rlgUvt <= 4100.0) {
            $impUvt = ($rlgUvt - 1700.0) * 0.28 + 116.0;
            $tarifaMarginal = '28%';
            $rangoTexto = '> 1.700 a 4.100 UVT';
        } elseif ($rlgUvt <= 8670.0) {
            $impUvt = ($rlgUvt - 4100.0) * 0.33 + 788.0;
            $tarifaMarginal = '33%';
            $rangoTexto = '> 4.100 a 8.670 UVT';
        } elseif ($rlgUvt <= 18970.0) {
            $impUvt = ($rlgUvt - 8670.0) * 0.35 + 2296.0;
            $tarifaMarginal = '35%';
            $rangoTexto = '> 8.670 a 18.970 UVT';
        } elseif ($rlgUvt <= 31000.0) {
            $impUvt = ($rlgUvt - 18970.0) * 0.37 + 5901.0;
            $tarifaMarginal = '37%';
            $rangoTexto = '> 18.970 a 31.000 UVT';
        } else {
            $impUvt = ($rlgUvt - 31000.0) * 0.39 + 10352.0;
            $tarifaMarginal = '39%';
            $rangoTexto = '> 31.000 UVT';
        }

        $impCopExacto = $impUvt * $uvt;
        $impCopRedondeado = round($impCopExacto / 1000.0) * 1000;

        return [
            'impuesto_uvt' => round($impUvt, 2),
            'impuesto_cop' => (float) $impCopRedondeado,
            'impuesto_cop_exacto' => $impCopExacto,
            'tarifa_marginal' => $tarifaMarginal,
            'rango_texto' => $rangoTexto,
        ];
    }

    /**
     * Ejecuta la depuración tributaria en 8 pasos de la Cédula General (RF-046).
     */
    public function estimar(Usuario $usuario, int $anioGravable): array
    {
        $declaracion = $this->complementariaService->obtenerDeclaracion($usuario, $anioGravable);
        $consolidado = $declaracion->datos_consolidado_cache ?? $this->consolidadoService->consolidar($usuario, $anioGravable);
        $params = $consolidado['parametros'];
        $uvt = (float) $params['uvt'];

        // Datos del consolidado
        $ingresosData = $consolidado['ingresos_brutos'];
        $incrngoData = $consolidado['incrngo'];
        $exentasInvData = $consolidado['rentas_exentas_inversiones'];
        $retencionesData = $consolidado['retenciones_practicadas'];

        // Ajuste manual de ingresos si aplica
        $ajusteIngresos = (float) $declaracion->c2_ajuste_manual_cop;

        // PASO 1: Ingresos Brutos de la Cédula General (IB)
        $paso1IngresosBrutosCop = (float) $ingresosData['total_cop'] + $ajusteIngresos;
        $paso1IngresosBrutosUvt = ConsolidadoRentaService::aUvt($paso1IngresosBrutosCop, $uvt);

        // PASO 2: INCRNGO
        $incrngoSaludPension = (float) $incrngoData['salud_pension_cop'];
        $incrngoInflacion = (float) $incrngoData['componente_inflacionario_cop'];
        $incrngoBvc = (float) $incrngoData['utilidad_exenta_bvc_cop'];
        $paso2IncrngoTotalCop = $incrngoSaludPension + $incrngoInflacion + $incrngoBvc;
        $paso2IncrngoTotalUvt = ConsolidadoRentaService::aUvt($paso2IncrngoTotalCop, $uvt);

        // Subtotal: Renta Líquida (RL)
        $rentaLiquidaCop = max(0.0, $paso1IngresosBrutosCop - $paso2IncrngoTotalCop);
        $rentaLiquidaUvt = ConsolidadoRentaService::aUvt($rentaLiquidaCop, $uvt);

        // Renta Líquida de Trabajo (para base del 25%)
        $ingresosTrabajoConCesantias = (float) $ingresosData['trabajo_cop'] + (float) $ingresosData['cesantias_cop'];
        $rentaLiquidaTrabajoCop = max(0.0, $ingresosTrabajoConCesantias - $incrngoSaludPension);

        // PASO 3: Rentas Exentas Específicas por Inversiones (FPV + Cesantías)
        // Límite FPV: max(0, 0.30 * IB - Pension Obligatoria)
        $topeFpv30Pct = max(0.0, round($paso1IngresosBrutosCop * 0.30) - $incrngoSaludPension);
        $fpvAceptadoCop = min((float) $exentasInvData['fpv_aportes_cop'], $topeFpv30Pct);
        $cesantiasExentasCop = (float) $exentasInvData['cesantias_exentas_cop'];
        $paso3RentasExentasInvCop = $fpvAceptadoCop + $cesantiasExentasCop;
        $paso3RentasExentasInvUvt = ConsolidadoRentaService::aUvt($paso3RentasExentasInvCop, $uvt);

        // PASO 4: Renta Exenta Laboral del 25% (Art. 206 num. 10 ET, máx 790 UVT)
        $base25Cop = max(0.0, $rentaLiquidaTrabajoCop - $paso3RentasExentasInvCop);
        $tope790UvtCop = $params['limite_exenta_laboral_25_uvt'] * $uvt; // 790 * UVT
        $paso4RentaExenta25Cop = min(round($base25Cop * 0.25), $tope790UvtCop);
        $paso4RentaExenta25Uvt = ConsolidadoRentaService::aUvt($paso4RentaExenta25Cop, $uvt);

        // PASO 5: Límite Global de Rentas Exentas y Deducciones (Art. 336 ET: 40% o 1.340 UVT)
        $sumaExentasBrutasCop = $paso3RentasExentasInvCop + $paso4RentaExenta25Cop;
        $limite40PctCop = round($rentaLiquidaCop * $params['limite_global_exentas_pct']); // 40% RL
        $limite1340UvtCop = $params['limite_global_exentas_uvt'] * $uvt; // 1.340 * UVT
        $topeMaximoGlobalCop = min($limite40PctCop, $limite1340UvtCop);

        $paso5TotalExentasAceptadasCop = min($sumaExentasBrutasCop, $topeMaximoGlobalCop);
        $paso5TotalExentasAceptadasUvt = ConsolidadoRentaService::aUvt($paso5TotalExentasAceptadasCop, $uvt);

        $limiteExentasAplicado = ($sumaExentasBrutasCop > $topeMaximoGlobalCop);
        $excesoNoAprovechadoCop = $limiteExentasAplicado ? ($sumaExentasBrutasCop - $topeMaximoGlobalCop) : 0.0;
        $cupoDisponibleCop = (! $limiteExentasAplicado) ? ($topeMaximoGlobalCop - $sumaExentasBrutasCop) : 0.0;

        // PASO 6: Renta Líquida Gravable (RLG)
        $paso6RlgCop = max(0.0, $rentaLiquidaCop - $paso5TotalExentasAceptadasCop);
        $paso6RlgUvt = ConsolidadoRentaService::aUvt($paso6RlgCop, $uvt);

        // PASO 7: Impuesto según Tabla Progresiva Art. 241 E.T.
        $calculoImpuesto = self::calcularImpuestoArt241($paso6RlgUvt, $uvt);
        $paso7ImpuestoRedondeadoCop = $calculoImpuesto['impuesto_cop'];
        $paso7ImpuestoUvt = $calculoImpuesto['impuesto_uvt'];

        // PASO 8: Imputación de Retenciones y Saldo Neto
        $retencionesLaborales = (float) $retencionesData['laborales_cop'];
        $retencionesInversiones = (float) $retencionesData['inversiones_cop'];
        $retencionesTotalesBrutas = $retencionesLaborales + $retencionesInversiones;
        $paso8RetencionesRedondeadasCop = (float) (round($retencionesTotalesBrutas / 1000.0) * 1000);

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

        // CÁLCULO CONTRAFACTUAL: "Impacto tributario de tus inversiones"
        // Escenario sin beneficios de inversión (sin componente inflacionario, sin Art. 36-1, sin exentas de FPV/cesantías)
        $rentaLiquidaSinBeneficiosInvCop = max(0.0, $paso1IngresosBrutosCop - $incrngoSaludPension);
        $exenta25SinInvCop = min(round($rentaLiquidaTrabajoCop * 0.25), $tope790UvtCop);
        $limiteGlobalSinInvCop = min(round($rentaLiquidaSinBeneficiosInvCop * 0.40), $limite1340UvtCop);
        $rlgSinBeneficiosInvCop = max(0.0, $rentaLiquidaSinBeneficiosInvCop - min($exenta25SinInvCop, $limiteGlobalSinInvCop));
        $rlgSinBeneficiosInvUvt = ConsolidadoRentaService::aUvt($rlgSinBeneficiosInvCop, $uvt);

        $impuestoSinBeneficios = self::calcularImpuestoArt241($rlgSinBeneficiosInvUvt, $uvt);
        $impuestoSinBeneficiosCop = $impuestoSinBeneficios['impuesto_cop'];

        $ahorroImpuestoDirectoCop = max(0.0, $impuestoSinBeneficiosCop - $paso7ImpuestoRedondeadoCop);
        $retencionesInversionesRedondeadasCop = round($retencionesInversiones / 1000.0) * 1000;
        $ahorroTotalInversionesCop = $ahorroImpuestoDirectoCop + $retencionesInversionesRedondeadasCop;

        $resultado = [
            'anio_gravable' => $anioGravable,
            'uvt' => $uvt,
            'paso1' => [
                'ingresos_brutos_cop' => $paso1IngresosBrutosCop,
                'ingresos_brutos_uvt' => $paso1IngresosBrutosUvt,
                'ingresos_trabajo_cop' => (float) $ingresosData['trabajo_cop'],
                'cesantias_cop' => (float) $ingresosData['cesantias_cop'],
                'capital_inversiones_cop' => (float) $ingresosData['capital_inversiones_cop'],
                'no_laborales_cop' => (float) $ingresosData['no_laborales_cop'],
                'utilidad_acciones_cop' => (float) $ingresosData['utilidad_acciones_menor_2a_cop'],
                'retiros_fpv_cop' => (float) $ingresosData['retiros_fpv_sin_requisitos_cop'],
                'ajuste_manual_cop' => $ajusteIngresos,
            ],
            'paso2' => [
                'incrngo_total_cop' => $paso2IncrngoTotalCop,
                'incrngo_total_uvt' => $paso2IncrngoTotalUvt,
                'salud_pension_cop' => $incrngoSaludPension,
                'componente_inflacionario_cop' => $incrngoInflacion,
                'utilidad_exenta_bvc_cop' => $incrngoBvc,
            ],
            'subtotal_renta_liquida' => [
                'renta_liquida_cop' => $rentaLiquidaCop,
                'renta_liquida_uvt' => $rentaLiquidaUvt,
                'renta_liquida_trabajo_cop' => $rentaLiquidaTrabajoCop,
            ],
            'paso3' => [
                'exentas_inversiones_cop' => $paso3RentasExentasInvCop,
                'exentas_inversiones_uvt' => $paso3RentasExentasInvUvt,
                'fpv_aceptado_cop' => $fpvAceptadoCop,
                'cesantias_exentas_cop' => $cesantiasExentasCop,
            ],
            'paso4' => [
                'base_25_cop' => $base25Cop,
                'exenta_25_laboral_cop' => $paso4RentaExenta25Cop,
                'exenta_25_laboral_uvt' => $paso4RentaExenta25Uvt,
                'tope_790_cop' => $tope790UvtCop,
            ],
            'paso5' => [
                'suma_exentas_bruta_cop' => $sumaExentasBrutasCop,
                'limite_40_pct_cop' => $limite40PctCop,
                'limite_1340_uvt_cop' => $limite1340UvtCop,
                'tope_maximo_global_cop' => $topeMaximoGlobalCop,
                'total_exentas_aceptadas_cop' => $paso5TotalExentasAceptadasCop,
                'total_exentas_aceptadas_uvt' => $paso5TotalExentasAceptadasUvt,
                'limite_aplicado' => $limiteExentasAplicado,
                'exceso_no_aprovechado_cop' => $excesoNoAprovechadoCop,
                'cupo_disponible_cop' => $cupoDisponibleCop,
            ],
            'paso6' => [
                'rlg_cop' => $paso6RlgCop,
                'rlg_uvt' => $paso6RlgUvt,
            ],
            'paso7' => [
                'impuesto_redondeado_cop' => $paso7ImpuestoRedondeadoCop,
                'impuesto_uvt' => $paso7ImpuestoUvt,
                'tarifa_marginal' => $calculoImpuesto['tarifa_marginal'],
                'rango_texto' => $calculoImpuesto['rango_texto'],
            ],
            'paso8' => [
                'retenciones_laborales_cop' => $retencionesLaborales,
                'retenciones_inversiones_cop' => $retencionesInversiones,
                'retenciones_totales_cop' => $paso8RetencionesRedondeadasCop,
                'saldo_neto_cop' => $saldoNetoCop,
                'saldo_valor_mostrado_cop' => $saldoValorMostrado,
                'tipo_resultado' => $tipoResultado,
                'saldo_label' => $saldoLabel,
            ],
            'impacto_inversiones' => [
                'impuesto_sin_beneficios_cop' => $impuestoSinBeneficiosCop,
                'impuesto_con_beneficios_cop' => $paso7ImpuestoRedondeadoCop,
                'ahorro_impuesto_directo_cop' => $ahorroImpuestoDirectoCop,
                'retenciones_inversiones_cop' => $retencionesInversionesRedondeadasCop,
                'ahorro_total_cop' => $ahorroTotalInversionesCop,
            ],
        ];

        // Guardar resultado en caché de la declaración
        $declaracion->datos_estimacion_cache = $resultado;
        $declaracion->save();

        return $resultado;
    }
}

