<?php

namespace App\Services;

use App\Models\Inversion;
use App\Models\ParametroNormativo;
use App\Models\SimulacionInversion;
use App\Models\UsuarioPerfilTributario;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * MOD-06 — RF-042 / RF-SIM-01 a RF-SIM-08:
 * Servicio de Simulación Financiera y Tributaria por Producto y Comparación de Alternativas.
 */
class SimulacionInversionService
{
    public const MAX_ALTERNATIVAS = 4;
    public const MAX_GUARDADAS = 20;

    public const PERIODICIDADES = [
        'MENSUAL' => 1,
        'TRIMESTRAL' => 3,
        'SEMESTRAL' => 6,
        'ANUAL' => 12,
    ];

    private const ESCALA = 10;

    /**
     * RF-SIM-01 / RF-SIM-02: Simulación específica por producto con líneas de desglose normativo.
     */
    public static function simularPorProducto(string $tipo, array $datos, ?string $usuarioId = null): array
    {
        $hoy = CarbonImmutable::today()->toDateString();
        $perfil = null;
        if ($usuarioId !== null) {
            $perfil = UsuarioPerfilTributario::where('usuario_id', $usuarioId)
                ->where('anio_gravable', CarbonImmutable::today()->year)
                ->first();
        }

        $tarifaMarginal = $perfil ? (string) $perfil->tarifa_marginal_estimada : ($datos['tarifa_marginal'] ?? '0.00');
        $versionParametros = ParametroTributarioService::versionParametros($datos['fecha'] ?? $hoy);

        $resultado = match (strtoupper($tipo)) {
            'CDT' => CalculoImpuestoInversionService::calcularCdt(
                capital: (string) ($datos['capital'] ?? $datos['monto_inicial'] ?? '0'),
                tasaValor: (string) ($datos['tasa_ea'] ?? $datos['tasa_valor'] ?? '0'),
                tipoTasa: $datos['tipo_tasa'] ?? 'EA',
                plazoDias: (int) ($datos['plazo_dias'] ?? 360),
                baseDias: (int) ($datos['base_dias'] ?? 365),
                periodicidadPago: $datos['periodicidad_pago'] ?? 'VENCIMIENTO',
                fecha: $datos['fecha'] ?? $hoy,
                tarifaMarginal: $tarifaMarginal
            ),
            'CUENTA_ALTO_RENDIMIENTO' => [
                'abono' => CalculoImpuestoInversionService::calcularAbonoCuenta(
                    saldo: (string) ($datos['saldo'] ?? $datos['monto_inicial'] ?? '0'),
                    tasaEa: (string) ($datos['tasa_ea'] ?? '0'),
                    dias: (int) ($datos['dias'] ?? 30),
                    baseDias: (int) ($datos['base_dias'] ?? 365),
                    fecha: $datos['fecha'] ?? $hoy
                ),
                'retiro' => CalculoImpuestoInversionService::calcularRetiroCuenta(
                    montoRetiro: (string) ($datos['monto_retiro'] ?? '0'),
                    esCuentaGmfExenta: (bool) ($datos['es_cuenta_gmf_exenta'] ?? false),
                    retirosMesAcumuladosPrevios: (string) ($datos['retiros_mes_acumulados_previos'] ?? '0.00'),
                    fecha: $datos['fecha'] ?? $hoy
                ),
            ],
            'ACCION_ETF' => (strtoupper($datos['operacion'] ?? 'VENTA') === 'DIVIDENDO')
                ? CalculoImpuestoInversionService::calcularDividendo(
                    unidades: (string) ($datos['unidades'] ?? $datos['unidades_vendidas'] ?? '0'),
                    valorDividendoPorUnidad: (string) ($datos['valor_dividendo_por_unidad'] ?? $datos['precio_venta'] ?? '0'),
                    tipoReparto: $datos['tipo_reparto'] ?? 'NO_APLICA',
                    impuestoOrigen: (string) ($datos['impuesto_origen'] ?? '0.00'),
                    fecha: $datos['fecha'] ?? $hoy,
                    mercado: $datos['mercado'] ?? 'BVC',
                    subtipo: $datos['subtipo_renta_variable'] ?? 'ACCION',
                    moneda: $datos['moneda_dividendo'] ?? $datos['moneda'] ?? 'COP',
                    trm: $datos['trm_dividendo'] ?? $datos['trm'] ?? null
                )
                : CalculoImpuestoInversionService::calcularVentaAcciones(
                    unidadesVendidas: (string) ($datos['unidades_vendidas'] ?? $datos['unidades'] ?? '0'),
                    precioVenta: (string) ($datos['precio_venta'] ?? '0'),
                    costoPromedioUnitario: (string) ($datos['costo_promedio_unitario'] ?? '0'),
                    comisionVenta: (string) ($datos['comision_venta'] ?? '0.00'),
                    estaInscritoBvc: (bool) ($datos['esta_inscrito_bvc'] ?? false),
                    porcentajeCirculacionVendidoAnio: (string) ($datos['porcentaje_circulacion_vendido_anio'] ?? '0.00'),
                    fechaPrimeraCompra: $datos['fecha_primera_compra'] ?? $hoy,
                    fechaVenta: $datos['fecha_venta'] ?? $hoy,
                    tarifaMarginalUsuario: $tarifaMarginal,
                    trmVenta: (string) ($datos['trm_venta'] ?? '1.0000'),
                    trmCompra: (string) ($datos['trm_compra'] ?? '1.0000')
                ),
            'FIC' => CalculoImpuestoInversionService::calcularRedencionFic(
                montoRedencion: (string) ($datos['monto_redencion'] ?? $datos['monto'] ?? '0'),
                valorActualFondo: (string) ($datos['valor_actual'] ?? '0'),
                totalAportesNetos: (string) ($datos['total_aportes_netos'] ?? '0'),
                utilidadYaRedimida: (string) ($datos['utilidad_ya_redimida'] ?? '0.00'),
                tarifaPonderada: (string) ($datos['tarifa_ponderada'] ?? '6.10'),
                comisionSalida: (string) ($datos['comision_salida'] ?? '0.00')
            ),
            'FPV' => CalculoImpuestoInversionService::calcularRetiroFpv(
                montoRetiro: (string) ($datos['monto_retiro'] ?? $datos['monto'] ?? '0'),
                cumplePermanenciaODestino: (bool) ($datos['cumple_permanencia'] ?? false),
                proporcionRendimiento: (string) ($datos['proporcion_rendimiento'] ?? '0.00'),
                fecha: $datos['fecha'] ?? $hoy
            ),
            'CESANTIAS' => CalculoImpuestoInversionService::calcularRetiroCesantias(
                montoRetirado: (string) ($datos['monto_retirado'] ?? $datos['monto'] ?? '0'),
                ingresoLaboralPromedio6m: (string) ($perfil?->ingreso_laboral_promedio_6m ?? $datos['ingreso_laboral_promedio_6m'] ?? '0'),
                fecha: $datos['fecha'] ?? $hoy
            ),
            default => throw new RuntimeException("Tipo de inversión no soportado para simulación: {$tipo}"),
        };

        return [
            'tipo' => $tipo,
            'version_parametros' => $versionParametros,
            'fecha_simulacion' => $hoy,
            'datos_entrada' => $datos,
            'resultado' => $resultado,
        ];
    }

    /**
     * RF-SIM-07 / RF-PAR-05: Guardar simulación como instantánea.
     */
    public static function guardarInstantanea(
        string $usuarioId,
        string $nombre,
        string $tipo,
        array $datosEntrada,
        array $resultado,
        ?string $inversionId = null
    ): SimulacionInversion {
        $hoy = CarbonImmutable::today()->toDateString();
        $versionParametros = ParametroTributarioService::versionParametros($hoy);

        $bruto = $resultado['bruto'] ?? $resultado['ingreso_bruto_cop'] ?? $resultado['monto_redencion'] ?? $resultado['monto_retiro'] ?? null;
        $retencionTotal = $resultado['retencion_total'] ?? $resultado['retencion_aplicada'] ?? null;
        $gmfTotal = $resultado['gmf_total'] ?? $resultado['gmf_aplicado'] ?? null;
        $comisiones = $resultado['comisiones'] ?? $resultado['comision_cop'] ?? $resultado['comision_salida'] ?? null;
        $neto = $resultado['neto'] ?? $resultado['neto_despues_impuesto'] ?? null;
        $rentabilidadNetaEa = $resultado['rentabilidad_neta_ea'] ?? null;
        $detalleLineas = $resultado['detalle_lineas'] ?? null;
        $impuestoRentaEstimado = $resultado['impuesto_renta_estimado'] ?? null;

        return SimulacionInversion::create([
            'usuario_id' => $usuarioId,
            'inversion_id' => $inversionId,
            'nombre' => $nombre,
            'fecha_simulacion' => $hoy,
            'parametros_entrada' => array_merge(['tipo' => $tipo], $datosEntrada),
            'version_parametros' => $versionParametros,
            'bruto' => $bruto,
            'retencion_total' => $retencionTotal,
            'gmf_total' => $gmfTotal,
            'comisiones' => $comisiones,
            'neto' => $neto,
            'rentabilidad_neta_ea' => $rentabilidadNetaEa,
            'detalle_lineas' => $detalleLineas,
            'impuesto_renta_estimado' => $impuestoRentaEstimado,
        ]);
    }

    /**
     * Comparador de alternativas genéricas existente (RF-042).
     */
    public static function comparar(array $alternativas, CarbonImmutable $hoy): array
    {
        $parametro = ParametroNormativo::where('anio_vigencia', $hoy->year)->where('es_activa', true)->first();
        $inflacion = $parametro === null ? null : (string) $parametro->ipc_anual;

        $resultados = array_map(fn (array $alternativa) => self::simular($alternativa, $inflacion), array_values($alternativas));

        $mayor = array_reduce(
            $resultados,
            fn (?string $mayor, array $resultado) => $mayor === null || bccomp($resultado['valor_final_neto'], $mayor, 2) === 1 ? $resultado['valor_final_neto'] : $mayor
        );

        foreach ($resultados as $i => $resultado) {
            $resultados[$i]['es_mejor'] = bccomp($resultado['valor_final_neto'], $mayor, 2) === 0;
        }

        return [
            'inflacion_vigencia' => $inflacion,
            'alternativas' => $resultados,
            'mejores' => array_keys(array_filter($resultados, fn (array $resultado) => $resultado['es_mejor'])),
            'mensajes' => $inflacion === null
                ? ["Valor en pesos de hoy no calculado: no existen parámetros normativos para la vigencia {$hoy->year}. Contacte al administrador."]
                : [],
            'aviso_legal' => SeguridadSocialService::AVISO_LEGAL,
        ];
    }

    public static function simular(array $alternativa, ?string $inflacionPorc): array
    {
        $meses = (int) $alternativa['plazo_meses'];
        $montoInicial = bcadd((string) $alternativa['monto_inicial'], '0', 2);
        $aporte = bcadd((string) ($alternativa['aporte_periodico'] ?? '0'), '0', 2);
        $cada = self::PERIODICIDADES[$alternativa['periodicidad_aporte'] ?? 'MENSUAL'];
        $retencion = (string) ($alternativa['retencion_porc'] ?? '0');
        $comisionAnual = (string) ($alternativa['comision_anual_porc'] ?? '0');

        $factorMensual = sprintf('%.15F', (1 + ((float) $alternativa['tasa_ea']) / 100) ** (1 / 12));
        $comisionMensual = bcdiv($comisionAnual, '1200', self::ESCALA);

        $saldo = $montoInicial;
        $aportado = $montoInicial;
        $comisiones = '0';
        $evolucion = [['mes' => 0, 'saldo' => $montoInicial, 'aportado' => $aportado]];

        for ($mes = 1; $mes <= $meses; $mes++) {
            $comision = bcmul($saldo, $comisionMensual, self::ESCALA);
            $saldo = bcmul($saldo, $factorMensual, self::ESCALA);

            if (bccomp($aporte, '0', 2) === 1 && $mes % $cada === 0) {
                $saldo = bcadd($saldo, $aporte, self::ESCALA);
                $aportado = bcadd($aportado, $aporte, 2);
            }

            $saldo = bcsub($saldo, $comision, self::ESCALA);
            $comisiones = bcadd($comisiones, $comision, self::ESCALA);

            $evolucion[] = ['mes' => $mes, 'saldo' => IngresoService::redondear($saldo), 'aportado' => $aportado];
        }

        $bruto = IngresoService::redondear($saldo);
        $rendimiento = bcsub($bruto, $aportado, 2);

        $impuestos = bccomp($rendimiento, '0', 2) === 1
            ? IngresoService::redondear(bcdiv(bcmul($rendimiento, $retencion, self::ESCALA), '100', self::ESCALA))
            : '0.00';
        $neto = bcsub($bruto, $impuestos, 2);

        $pesosHoy = null;
        if ($inflacionPorc !== null) {
            $deflactor = sprintf('%.15F', (1 + ((float) $inflacionPorc) / 100) ** ($meses / 12));
            $pesosHoy = IngresoService::redondear(bcdiv($neto, $deflactor, self::ESCALA));
        }

        return [
            'nombre' => $alternativa['nombre'],
            'datos' => [
                'monto_inicial' => $montoInicial,
                'aporte_periodico' => $aporte,
                'periodicidad_aporte' => bccomp($aporte, '0', 2) === 1 ? ($alternativa['periodicidad_aporte'] ?? 'MENSUAL') : null,
                'tasa_ea' => (string) $alternativa['tasa_ea'],
                'plazo_meses' => $meses,
                'retencion_porc' => $retencion,
                'comision_anual_porc' => $comisionAnual,
            ],
            'total_aportado' => $aportado,
            'total_comisiones' => IngresoService::redondear($comisiones),
            'valor_final_bruto' => $bruto,
            'impuestos' => $impuestos,
            'valor_final_neto' => $neto,
            'ganancia_neta' => bcsub($neto, $aportado, 2),
            'valor_final_pesos_hoy' => $pesosHoy,
            'evolucion' => $evolucion,
        ];
    }
}
