<?php

namespace App\Services;

use App\Models\ParametroNormativo;
use Carbon\CarbonImmutable;

/**
 * MOD-06 — RF-042: Simulación y comparación de alternativas de inversión
 * hipotéticas, sin registrarlas como inversiones.
 *
 * El saldo se lleva con bcmath a 10 decimales y se redondea a 2 solo en la
 * salida (RNG-02). La tasa mensual equivalente es una potencia fraccionaria y
 * se calcula en punto flotante, solo como factor (decisión 8, SC-001).
 */
class SimulacionInversionService
{
    /** RF-042: "hasta 4 alternativas". */
    public const MAX_ALTERNATIVAS = 4;

    /** RN-042.4: máximo 20 simulaciones guardadas por Usuario. */
    public const MAX_GUARDADAS = 20;

    /** RN-042.1: meses entre aportes según su periodicidad. */
    public const PERIODICIDADES = [
        'MENSUAL' => 1,
        'TRIMESTRAL' => 3,
        'SEMESTRAL' => 6,
        'ANUAL' => 12,
    ];

    private const ESCALA = 10;

    /**
     * RN-042.3 / CA-042.2: resultados de cada alternativa y la de mayor valor
     * final neto, marcada con `es_mejor` (si empatan, todas las empatadas).
     *
     * El valor en pesos de hoy usa la inflación de la vigencia actual; sin
     * parámetros no se calcula y se informa con el mensaje de la RNG-04.
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

    /**
     * RN-042.2: capitalización mensual. Tasa mensual = (1 + EA)^(1/12) − 1; cada mes
     *   saldo = saldo × (1 + tasa mensual) + aporte − comisión mensual,
     * con comisión mensual = saldo al inicio del mes × comisión anual ÷ 12
     * (decisión 6, SC-001). El aporte entra en los meses que tocan según su
     * periodicidad. La retención se aplica al final sobre el rendimiento
     * acumulado, si lo hay.
     *
     * RN-042.3: total aportado, valor final bruto, impuestos, valor final neto,
     * ganancia neta, valor final en pesos de hoy y la evolución mes a mes.
     */
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

        // RN-042.2: la retención es sobre el rendimiento; sin rendimiento no hay impuesto.
        $impuestos = bccomp($rendimiento, '0', 2) === 1
            ? IngresoService::redondear(bcdiv(bcmul($rendimiento, $retencion, self::ESCALA), '100', self::ESCALA))
            : '0.00';
        $neto = bcsub($bruto, $impuestos, 2);

        // RN-042.3: valor final en pesos de hoy, descontando la inflación de la vigencia.
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
