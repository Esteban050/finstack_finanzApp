<?php

namespace App\Services;

use App\Models\ParametroNormativo;
use App\Models\TarifaEscalonada;

class TarifaCalculoService
{
    /**
     * RF-014 RN-014.4: Validar continuidad de tramos en una tabla escalonada.
     * Retorna ['valido' => bool, 'error' => ?string]
     */
    public static function validarContinuidadRangos(array $tramos): array
    {
        // Ordenar tramos por rango_desde ascendente
        usort($tramos, fn ($a, $b) => $a['rango_desde'] <=> $b['rango_desde']);

        if (empty($tramos)) {
            return ['valido' => false, 'error' => 'La tabla no puede estar vacía.'];
        }

        // Debe iniciar en 0
        if (floatval($tramos[0]['rango_desde']) !== 0.0) {
            return ['valido' => false, 'error' => 'El primer tramo debe iniciar en 0.'];
        }

        $count = count($tramos);
        for ($i = 0; $i < $count; $i++) {
            $desde = floatval($tramos[$i]['rango_desde']);
            $hasta = $tramos[$i]['rango_hasta'] !== null ? floatval($tramos[$i]['rango_hasta']) : null;

            if ($hasta !== null && $hasta <= $desde) {
                return [
                    'valido' => false,
                    'error' => "El tramo de {$desde} a {$hasta} es inválido: el valor 'hasta' debe ser mayor a 'desde'.",
                ];
            }

            if ($i < $count - 1) {
                if ($hasta === null) {
                    return [
                        'valido' => false,
                        'error' => "Solo el último tramo puede ser 'En adelante' (sin límite superior).",
                    ];
                }

                $siguienteDesde = floatval($tramos[$i + 1]['rango_desde']);
                if (abs($hasta - $siguienteDesde) > 0.001) {
                    return [
                        'valido' => false,
                        'error' => "Existe un hueco o traslape entre el tramo hasta {$hasta} y el tramo desde {$siguienteDesde}.",
                    ];
                }
            } else {
                // Último tramo debe ser en adelante (null) para RENTA_ART241
                if ($hasta !== null) {
                    return [
                        'valido' => false,
                        'error' => "El último tramo debe terminar en 'En adelante' (sin límite superior) para cubrir todos los valores.",
                    ];
                }
            }
        }

        return ['valido' => true];
    }

    /**
     * RF-014 RN-014.1 & CA-014.2: Calcular impuesto sobre la renta según Art. 241 ET.
     * Ejemplo CA-014.2: base gravable 2.000 UVT -> (2000 - 1700) * 28% + 116 = 200 UVT
     */
    public static function calcularImpuestoRenta(float $baseUvt, ParametroNormativo $parametro): float
    {
        if ($baseUvt <= 0) {
            return 0.0;
        }

        $tarifas = TarifaEscalonada::where('parametro_normativo_id', $parametro->id)
            ->where('tipo_tabla', 'RENTA_ART241')
            ->orderBy('rango_desde')
            ->get();

        foreach ($tarifas as $tarifa) {
            $desde = floatval($tarifa->rango_desde);
            $hasta = $tarifa->rango_hasta !== null ? floatval($tarifa->rango_hasta) : INF;

            if ($baseUvt > $desde && $baseUvt <= $hasta) {
                $tarifaPorc = floatval($tarifa->tarifa_porc) / 100.0;
                $impuestoBase = floatval($tarifa->impuesto_base_uvt);

                $impuestoUvt = (($baseUvt - $desde) * $tarifaPorc) + $impuestoBase;
                return round($impuestoUvt, 2);
            }
        }

        return 0.0;
    }
}
