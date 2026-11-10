<?php

namespace App\Services\Renta;

use App\Models\Ingreso;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\ParametroNormativo;
use App\Models\ParametroTributario;
use App\Models\Usuario;
use Carbon\CarbonImmutable;

class ConsolidadoRentaService
{
    /**
     * Obtiene los parámetros normativos y tributarios vigentes para el año gravable.
     */
    public static function obtenerParametros(int $anioGravable): array
    {
        $normativo = ParametroNormativo::where('anio_vigencia', $anioGravable)
            ->where('es_activa', true)
            ->orderByDesc('version')
            ->first();

        // UVT oficial
        $uvt = $normativo ? (float) $normativo->valor_uvt : ($anioGravable === 2026 ? 52374.0 : 49799.0);

        // Componente inflacionario anual decretado
        $compParam = ParametroTributario::where('clave', "COMPONENTE_INFLACIONARIO_{$anioGravable}")
            ->first();

        if ($compParam) {
            $pctInflacionario = (float) $compParam->valor_numerico / 100.0;
        } else {
            $pctInflacionario = ($anioGravable === 2025) ? 0.6540 : 0.5088;
        }

        return [
            'anio_gravable' => $anioGravable,
            'uvt' => $uvt,
            'pct_componente_inflacionario' => $pctInflacionario,
            'tope_patrimonio_uvt' => $normativo ? (int) $normativo->tope_patrimonio_uvt : 4500,
            'tope_ingresos_uvt' => $normativo ? (int) $normativo->tope_ingresos_uvt : 1400,
            'tope_tarjetas_uvt' => $normativo ? (int) $normativo->tope_tc_uvt : 1400,
            'tope_compras_uvt' => $normativo ? (int) $normativo->tope_compras_uvt : 1400,
            'tope_consignaciones_uvt' => $normativo ? (int) $normativo->tope_consignaciones_uvt : 1400,
            'limite_global_exentas_pct' => $normativo ? ((float) $normativo->porc_limite_global_exentas / 100.0) : 0.40,
            'limite_global_exentas_uvt' => $normativo ? (int) $normativo->limite_global_exentas_uvt : 1340,
            'limite_exenta_laboral_25_uvt' => $normativo ? (int) $normativo->limite_renta_exenta_25_uvt : 790,
            'limite_fpv_ingreso_bruto_pct' => 0.30,
        ];
    }

    /**
     * Consolida toda la información de Ingresos, Inversiones y Retiros para el año gravable.
     */
    public function consolidar(Usuario $usuario, int $anioGravable): array
    {
        $params = self::obtenerParametros($anioGravable);
        $uvt = $params['uvt'];
        $desde = "{$anioGravable}-01-01";
        $hasta = "{$anioGravable}-12-31";

        // 1. MÓDULO DE INGRESOS (Sin global scopes para garantizar contexto de usuario)
        $ingresos = Ingreso::withoutGlobalScopes()
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha_recepcion', [$desde, $hasta])
            ->whereNull('eliminado_en')
            ->get();

        $ingresosTrabajoCop = 0.0;
        $ingresosNoLaboralesCop = 0.0;
        $retencionesLaboralesCop = 0.0;
        $aportesSaludPensionCop = 0.0;
        $drillDownIngresosTrabajo = [];
        $drillDownIngresosNoLaborales = [];

        foreach ($ingresos as $ingreso) {
            $montoBruto = (float) $ingreso->monto_bruto_cop;
            $retencion = (float) $ingreso->retencion_fuente_cop;
            $aportes = (float) $ingreso->aportes_descontados_cop;

            $aportesSaludPensionCop += $aportes;
            $retencionesLaboralesCop += $retencion;

            if ($ingreso->clasificacion_tributaria === 'RENTAS_TRABAJO') {
                $ingresosTrabajoCop += $montoBruto;
                $drillDownIngresosTrabajo[] = [
                    'id' => $ingreso->id,
                    'descripcion' => $ingreso->descripcion ?? 'Ingreso laboral',
                    'fecha' => $ingreso->fecha_recepcion ? CarbonImmutable::parse($ingreso->fecha_recepcion)->toDateString() : $desde,
                    'monto_bruto_cop' => $montoBruto,
                    'retencion_cop' => $retencion,
                    'aportes_cop' => $aportes,
                ];
            } elseif ($ingreso->clasificacion_tributaria === 'RENTAS_NO_LABORALES') {
                $ingresosNoLaboralesCop += $montoBruto;
                $drillDownIngresosNoLaborales[] = [
                    'id' => $ingreso->id,
                    'descripcion' => $ingreso->descripcion ?? 'Ingreso no laboral',
                    'fecha' => $ingreso->fecha_recepcion ? CarbonImmutable::parse($ingreso->fecha_recepcion)->toDateString() : $desde,
                    'monto_bruto_cop' => $montoBruto,
                    'retencion_cop' => $retencion,
                    'aportes_cop' => $aportes,
                ];
            }
        }

        // 2. MÓDULO DE INVERSIONES
        $inversiones = Inversion::withoutGlobalScopes()
            ->with(['detalleCdt', 'detalleCuenta', 'detalleRentaVariable', 'detalleFic', 'detalleFpv', 'detalleCesantias'])
            ->where('usuario_id', $usuario->id)
            ->whereNull('eliminado_en')
            ->get();

        $movimientosAnio = MovimientoInversion::withoutGlobalScopes()
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereNull('eliminado_en')
            ->get();

        // 2.1 Rendimientos de Capital (CDTs, Cuentas de Alto Rendimiento, FICs)
        $rendimientosCdtCuentasFicCop = 0.0;
        $retencionesInversionesCop = 0.0;
        $drillDownRentasCapital = [];

        // 2.2 Utilidad en venta de Acciones/ETFs < 2 años
        $utilidadVentaAccionesMenor2AniosCop = 0.0;
        $utilidadExentaBvcCop = 0.0;
        $drillDownVentasAcciones = [];

        // 2.3 Aportes a FPV en el año y retiros anticipados
        $aportesFpvAnioCop = 0.0;
        $retirosFpvSinRequisitosCop = 0.0;

        // 2.4 Cesantías consignadas en el año
        $cesantiasConsignadasAnioCop = 0.0;

        // 2.5 Base Inversiones para C5 (Piso Inversiones)
        $pisoInversionesC5Cop = 0.0;

        // Mapear movimientos por inversión
        $movsPorInversion = $movimientosAnio->groupBy('inversion_id');

        foreach ($inversiones as $inv) {
            $movs = $movsPorInversion->get($inv->id, collect());

            // Aperturas en el año suman a C5
            if ($inv->fecha_apertura && $inv->fecha_apertura >= $desde && $inv->fecha_apertura <= $hasta) {
                $pisoInversionesC5Cop += (float) $inv->monto_inicial;
            }

            foreach ($movs as $mov) {
                $montoCop = (float) ($mov->monto_cop ?? $mov->monto);
                $retencion = (float) $mov->retencion_aplicada;
                $retencionesInversionesCop += $retencion;

                // Aperturas / aportes / compras del año para Piso C5 (si no es movimiento inicial)
                if (! $mov->es_inicial) {
                    if (in_array($mov->tipo, ['APORTE', 'COMPRA'], true)) {
                        $pisoInversionesC5Cop += $montoCop;
                    }
                }

                // Rendimientos de capital (CDT, Cuentas, FIC)
                if (in_array($inv->tipo, ['CDT', 'CUENTA_ALTO_RENDIMIENTO', 'FIC'], true)) {
                    if (in_array($mov->tipo, ['ABONO_INTERES', 'RENDIMIENTO', 'REDENCION'], true)) {
                        // En abonos de interés o rendimientos brutos
                        $rendimientoBruto = (float) ($mov->monto_bruto ?? $montoCop);
                        if ($mov->tipo === 'REDENCION' && $inv->tipo === 'CDT') {
                            // En redención de CDT, solo la porción de rendimientos si está calculada
                            $rendimientoBruto = max(0.0, $rendimientoBruto - (float) $inv->monto_inicial);
                        }
                        if ($rendimientoBruto > 0) {
                            $rendimientosCdtCuentasFicCop += $rendimientoBruto;
                            $drillDownRentasCapital[] = [
                                'producto' => $inv->nombre,
                                'tipo' => $inv->tipo,
                                'fecha' => CarbonImmutable::parse($mov->fecha)->toDateString(),
                                'rendimiento_cop' => $rendimientoBruto,
                                'retencion_cop' => $retencion,
                            ];
                        }
                    }
                }

                // Acciones y ETFs (Rentas no laborales o ganancia ocasional)
                if ($inv->tipo === 'ACCION_ETF') {
                    if ($mov->tipo === 'VENTA') {
                        // Calcular días de tenencia
                        $fechaCompra = $inv->fecha_apertura ? CarbonImmutable::parse($inv->fecha_apertura) : CarbonImmutable::parse($mov->fecha);
                        $diasTenencia = $fechaCompra->diffInDays(CarbonImmutable::parse($mov->fecha));

                        if ($diasTenencia < 730) {
                            // Tenencia < 2 años: Cédula General (Rentas no laborales)
                            $costoFiscal = (float) ($inv->precio_unitario_compra ?? 0) * (float) ($mov->unidades ?? 1);
                            $utilidadVenta = max(0.0, $montoCop - $costoFiscal);

                            if ($utilidadVenta > 0) {
                                $utilidadVentaAccionesMenor2AniosCop += $utilidadVenta;

                                // Art. 36-1 ET: Exención 100% si cotiza en BVC o MGC
                                $detalleRv = $inv->detalleRentaVariable;
                                $esBvcOMgc = ($detalleRv && ($detalleRv->esta_inscrito_bvc || $detalleRv->mercado === 'BVC'));

                                if ($esBvcOMgc) {
                                    $utilidadExentaBvcCop += $utilidadVenta;
                                }

                                $drillDownVentasAcciones[] = [
                                    'producto' => $inv->nombre,
                                    'simbolo' => $inv->simbolo ?? ($detalleRv ? $detalleRv->simbolo : ''),
                                    'fecha_venta' => CarbonImmutable::parse($mov->fecha)->toDateString(),
                                    'utilidad_cop' => $utilidadVenta,
                                    'cotiza_en_bvc_o_mgc' => $esBvcOMgc,
                                ];
                            }
                        }
                    }
                }

                // FPV: Aportes del año y retiros
                if ($inv->tipo === 'FPV') {
                    if ($mov->tipo === 'APORTE') {
                        $aportesFpvAnioCop += $montoCop;
                    } elseif ($mov->tipo === 'RETIRO') {
                        // Si se retiró sin cumplir requisitos (según nota o metadatos)
                        if (str_contains(strtolower($mov->nota ?? ''), 'sin requisitos') || str_contains(strtolower($mov->nota ?? ''), 'anticipado')) {
                            $retirosFpvSinRequisitosCop += $montoCop;
                        }
                    }
                }

                // Cesantías: Aportes / consignaciones en el año
                if ($inv->tipo === 'CESANTIAS') {
                    if (in_array($mov->tipo, ['APORTE', 'ABONO_INTERES'], true)) {
                        $cesantiasConsignadasAnioCop += $montoCop;
                    }
                }
            }
        }

        // Si hay aportes de FPV registrados en la inversión inicial en el año
        foreach ($inversiones as $inv) {
            if ($inv->fecha_apertura && $inv->fecha_apertura >= $desde && $inv->fecha_apertura <= $hasta) {
                if ($inv->tipo === 'FPV' && $aportesFpvAnioCop == 0) {
                    $aportesFpvAnioCop += (float) $inv->monto_inicial;
                }
                if ($inv->tipo === 'CESANTIAS' && $cesantiasConsignadasAnioCop == 0) {
                    $cesantiasConsignadasAnioCop += (float) $inv->monto_inicial;
                }
            }
        }

        // 3. PATRIMONIO BRUTO DE INVERSIONES AL 31/12 (RF-043 RN-043.1 num 5 y CA-043.2)
        $corte31Dic = CarbonImmutable::parse($hasta);
        $patrimonioBrutoInversionesCop = 0.0;
        $productosActivos31Dic = [];

        foreach ($inversiones as $inv) {
            // Verificar si estaba activa al 31/12 (apertura <= 31/12 y no liquidada antes de 31/12)
            $aperturaOk = ! $inv->fecha_apertura || $inv->fecha_apertura <= $hasta;
            $noLiquidadaAntes = ! $inv->liquidada_en || $inv->liquidada_en > $hasta;

            if ($aperturaOk && $noLiquidadaAntes) {
                $saldoPatrimonial = 0.0;
                $tratamiento = '';
                $rendimientoAnualProd = 0.0;
                $retencionAnualProd = 0.0;

                // Calcular rendimiento y retención anual acumulados del producto
                $movsProd = $movsPorInversion->get($inv->id, collect());
                foreach ($movsProd as $mp) {
                    if (in_array($mp->tipo, ['ABONO_INTERES', 'RENDIMIENTO', 'DIVIDENDO'], true)) {
                        $rendimientoAnualProd += (float) ($mp->monto_bruto ?? $mp->monto_cop ?? $mp->monto);
                    }
                    $retencionAnualProd += (float) $mp->retencion_aplicada;
                }

                switch ($inv->tipo) {
                    case 'CDT':
                        $detalleCdt = $inv->detalleCdt;
                        $capital = (float) ($detalleCdt ? $detalleCdt->capital : $inv->monto_inicial);
                        // Intereses causados no pagados (estimación si vigente)
                        $saldoPatrimonial = $capital;
                        $tratamiento = 'Rentas de capital · Retención 4% · Componente inflacionario';
                        break;

                    case 'CUENTA_ALTO_RENDIMIENTO':
                        $detalleCuenta = $inv->detalleCuenta;
                        $saldoPatrimonial = (float) ($detalleCuenta ? $detalleCuenta->saldo_disponible : $inv->monto_inicial);
                        $tratamiento = 'Rentas de capital · Retención 7% · Componente inflacionario';
                        break;

                    case 'FIC':
                        $detalleFic = $inv->detalleFic;
                        $saldoPatrimonial = (float) ($detalleFic ? $detalleFic->saldo_actual : $inv->monto_inicial);
                        $tratamiento = 'Rentas de capital · Retención según fondo · Componente inflacionario';
                        break;

                    case 'ACCION_ETF':
                        $detalleRv = $inv->detalleRentaVariable;
                        $costoFiscal = (float) ($detalleRv ? ($detalleRv->unidades_total * $detalleRv->costo_promedio_unitario) : $inv->monto_inicial);
                        $saldoPatrimonial = $costoFiscal > 0 ? $costoFiscal : (float) $inv->monto_inicial;
                        $tratamiento = ($detalleRv && ($detalleRv->esta_inscrito_bvc || $detalleRv->mercado === 'BVC'))
                            ? 'Renta No Laboral · Exento Art. 36-1 ET (BVC/MGC)'
                            : 'Renta No Laboral · Gravado ordinario';
                        break;

                    case 'FPV':
                        $detalleFpv = $inv->detalleFpv;
                        $saldoPatrimonial = (float) ($detalleFpv ? $detalleFpv->saldo_actual : $inv->monto_inicial);
                        $tratamiento = 'Patrimonio autónomo · Renta Exenta Art. 126-1 ET';
                        break;

                    case 'CESANTIAS':
                        $detalleCes = $inv->detalleCesantias;
                        $saldoPatrimonial = (float) ($detalleCes ? $detalleCes->saldo : $inv->monto_inicial);
                        $tratamiento = 'Patrimonio fondo · Renta Exenta Art. 206 num. 4 ET';
                        break;
                }

                $patrimonioBrutoInversionesCop += $saldoPatrimonial;

                $productosActivos31Dic[] = [
                    'id' => $inv->id,
                    'nombre' => $inv->nombre,
                    'tipo' => $inv->tipo,
                    'entidad' => $inv->entidad,
                    'tratamiento_tributario' => $tratamiento,
                    'rendimiento_anual_cop' => $rendimientoAnualProd,
                    'retencion_anual_cop' => $retencionAnualProd,
                    'saldo_patrimonial_cop' => $saldoPatrimonial,
                    'saldo_patrimonial_uvt' => self::aUvt($saldoPatrimonial, $uvt),
                ];
            }
        }

        // 4. CONSOLIDACIÓN DE CONCEPTOS TRIBUTARIOS
        // 4.1 Ingresos Brutos Totales Cédula General
        $ingresosBrutosTotalCop = $ingresosTrabajoCop +
            $cesantiasConsignadasAnioCop +
            $rendimientosCdtCuentasFicCop +
            $ingresosNoLaboralesCop +
            $utilidadVentaAccionesMenor2AniosCop +
            $retirosFpvSinRequisitosCop;

        // 4.2 INCRNGO
        $incrngoInflacionCop = round($rendimientosCdtCuentasFicCop * $params['pct_componente_inflacionario']);
        $incrngoTotalCop = $aportesSaludPensionCop + $incrngoInflacionCop + $utilidadExentaBvcCop;

        // 4.3 Rentas Exentas Inversiones
        // FPV límite: hasta el 30% del ingreso bruto total menos pensión obligatoria
        $topeFpvCop = max(0.0, ($ingresosBrutosTotalCop * $params['limite_fpv_ingreso_bruto_pct']) - $aportesSaludPensionCop);
        $fpvAceptadoCop = min($aportesFpvAnioCop, $topeFpvCop);

        // Cesantías exentas (100% si salario promedio 6m <= 350 UVT)
        $salarioPromedioMensual = $ingresosTrabajoCop > 0 ? ($ingresosTrabajoCop / 12.0) : 0.0;
        $porcentajeCesantiasExentas = ($salarioPromedioMensual <= (350 * $uvt)) ? 1.0 : 0.90;
        $cesantiasExentasCop = round($cesantiasConsignadasAnioCop * $porcentajeCesantiasExentas);

        $rentasExentasInversionesCop = $fpvAceptadoCop + $cesantiasExentasCop;

        // 4.4 Retenciones Totales
        $retencionesTotalesCop = $retencionesLaboralesCop + $retencionesInversionesCop;

        return [
            'anio_gravable' => $anioGravable,
            'parametros' => $params,
            'ingresos_brutos' => [
                'trabajo_cop' => $ingresosTrabajoCop,
                'trabajo_uvt' => self::aUvt($ingresosTrabajoCop, $uvt),
                'cesantias_cop' => $cesantiasConsignadasAnioCop,
                'cesantias_uvt' => self::aUvt($cesantiasConsignadasAnioCop, $uvt),
                'capital_inversiones_cop' => $rendimientosCdtCuentasFicCop,
                'capital_inversiones_uvt' => self::aUvt($rendimientosCdtCuentasFicCop, $uvt),
                'no_laborales_cop' => $ingresosNoLaboralesCop,
                'no_laborales_uvt' => self::aUvt($ingresosNoLaboralesCop, $uvt),
                'utilidad_acciones_menor_2a_cop' => $utilidadVentaAccionesMenor2AniosCop,
                'utilidad_acciones_menor_2a_uvt' => self::aUvt($utilidadVentaAccionesMenor2AniosCop, $uvt),
                'retiros_fpv_sin_requisitos_cop' => $retirosFpvSinRequisitosCop,
                'total_cop' => $ingresosBrutosTotalCop,
                'total_uvt' => self::aUvt($ingresosBrutosTotalCop, $uvt),
                'drill_down' => [
                    'trabajo' => $drillDownIngresosTrabajo,
                    'capital' => $drillDownRentasCapital,
                    'no_laborales' => array_merge($drillDownIngresosNoLaborales, $drillDownVentasAcciones),
                ],
            ],
            'incrngo' => [
                'salud_pension_cop' => $aportesSaludPensionCop,
                'salud_pension_uvt' => self::aUvt($aportesSaludPensionCop, $uvt),
                'componente_inflacionario_cop' => $incrngoInflacionCop,
                'componente_inflacionario_uvt' => self::aUvt($incrngoInflacionCop, $uvt),
                'utilidad_exenta_bvc_cop' => $utilidadExentaBvcCop,
                'utilidad_exenta_bvc_uvt' => self::aUvt($utilidadExentaBvcCop, $uvt),
                'total_cop' => $incrngoTotalCop,
                'total_uvt' => self::aUvt($incrngoTotalCop, $uvt),
            ],
            'rentas_exentas_inversiones' => [
                'fpv_aportes_cop' => $aportesFpvAnioCop,
                'fpv_aceptado_cop' => $fpvAceptadoCop,
                'fpv_aceptado_uvt' => self::aUvt($fpvAceptadoCop, $uvt),
                'cesantias_exentas_cop' => $cesantiasExentasCop,
                'cesantias_exentas_uvt' => self::aUvt($cesantiasExentasCop, $uvt),
                'total_cop' => $rentasExentasInversionesCop,
                'total_uvt' => self::aUvt($rentasExentasInversionesCop, $uvt),
            ],
            'retenciones_practicadas' => [
                'laborales_cop' => $retencionesLaboralesCop,
                'laborales_uvt' => self::aUvt($retencionesLaboralesCop, $uvt),
                'inversiones_cop' => $retencionesInversionesCop,
                'inversiones_uvt' => self::aUvt($retencionesInversionesCop, $uvt),
                'total_cop' => $retencionesTotalesCop,
                'total_uvt' => self::aUvt($retencionesTotalesCop, $uvt),
            ],
            'patrimonio_bruto_31_12' => [
                'total_cop' => $patrimonioBrutoInversionesCop,
                'total_uvt' => self::aUvt($patrimonioBrutoInversionesCop, $uvt),
                'productos_activos' => $productosActivos31Dic,
            ],
            'piso_inversiones_c5' => [
                'total_cop' => $pisoInversionesC5Cop,
                'total_uvt' => self::aUvt($pisoInversionesC5Cop, $uvt),
            ],
        ];
    }

    /**
     * RN-043.2: Convierte un valor en COP a UVT redondeado a 2 decimales.
     */
    public static function aUvt(float|int|string $valorCop, float|int|string $valorUvt): float
    {
        $cop = (float) $valorCop;
        $uvt = (float) $valorUvt;

        if ($uvt <= 0) {
            return 0.0;
        }

        return round($cop / $uvt, 2);
    }
}

