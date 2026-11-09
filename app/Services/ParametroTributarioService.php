<?php

namespace App\Services;

use App\Models\ParametroTributario;
use App\Models\TablaExencionCesantias;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * RF-PAR-01 / RF-PAR-02 / RN 6.7:
 * Servicio para consultar parámetros tributarios vigentes a la fecha del hecho económico.
 * Falla de forma explícita si el parámetro no existe o no tiene vigencia aplicable.
 */
class ParametroTributarioService
{
    /**
     * Obtiene el modelo de parámetro vigente en la fecha indicada.
     *
     * @throws RuntimeException
     */
    public static function obtener(string $clave, ?string $fecha = null): ParametroTributario
    {
        $fechaConsulta = $fecha !== null ? CarbonImmutable::parse($fecha)->toDateString() : CarbonImmutable::today()->toDateString();

        $parametro = ParametroTributario::where('clave', $clave)
            ->where('vigente_desde', '<=', $fechaConsulta)
            ->where(function ($query) use ($fechaConsulta) {
                $query->whereNull('vigente_hasta')
                    ->orWhere('vigente_hasta', '>=', $fechaConsulta);
            })
            ->orderByDesc('vigente_desde')
            ->first();

        if ($parametro === null) {
            throw new RuntimeException("Parámetro tributario '{$clave}' no encontrado o no vigente para la fecha '{$fechaConsulta}'. (RN 6.7: El sistema debe fallar con mensaje claro y no usar valor por defecto silencioso)");
        }

        return $parametro;
    }

    /**
     * Obtiene directamente el valor numérico en string con precisión.
     */
    public static function obtenerValor(string $clave, ?string $fecha = null): string
    {
        return (string) self::obtener($clave, $fecha)->valor_numerico;
    }

    /**
     * Obtiene el valor de la UVT vigente.
     */
    public static function obtenerUvt(?string $fecha = null): string
    {
        return self::obtenerValor('UVT', $fecha);
    }

    /**
     * Obtiene el porcentaje exento de cesantías según Art. 206 Num. 4 ET.
     * Si ingreso mensual promedio 6m <= 350 UVT => 100% exento.
     * Si es superior, se busca en la tabla gradual.
     */
    public static function obtenerExencionCesantias(string $ingresoLaboralPromedio6m, ?string $fecha = null): array
    {
        $fechaConsulta = $fecha !== null ? CarbonImmutable::parse($fecha)->toDateString() : CarbonImmutable::today()->toDateString();
        $uvt = self::obtenerUvt($fechaConsulta);

        if (bccomp($uvt, '0', 4) <= 0) {
            throw new RuntimeException("El valor de la UVT para la fecha '{$fechaConsulta}' es inválido.");
        }

        $ingresoUvt = bcdiv($ingresoLaboralPromedio6m, $uvt, 4);

        // Si es menor o igual a 350 UVT, exención del 100%
        if (bccomp($ingresoUvt, '350.00', 4) <= 0) {
            return [
                'porcentaje_exento' => '100.00',
                'ingreso_uvt' => $ingresoUvt,
                'uvt' => $uvt,
                'norma' => 'Art. 206 num. 4 ET (100% hasta 350 UVT)',
            ];
        }

        // Buscar tramo en tabla_exencion_cesantias
        $tramo = TablaExencionCesantias::where('uvt_desde', '<=', (float) $ingresoUvt)
            ->where(function ($query) use ($ingresoUvt) {
                $query->whereNull('uvt_hasta')
                    ->orWhere('uvt_hasta', '>=', (float) $ingresoUvt);
            })
            ->where('vigente_desde', '<=', $fechaConsulta)
            ->orderByDesc('vigente_desde')
            ->first();

        if ($tramo !== null) {
            return [
                'porcentaje_exento' => (string) $tramo->porcentaje_exento,
                'ingreso_uvt' => $ingresoUvt,
                'uvt' => $uvt,
                'norma' => 'Art. 206 num. 4 ET (Tabla gradual por UVT)',
            ];
        }

        // Si excede 650 UVT
        return [
            'porcentaje_exento' => '0.00',
            'ingreso_uvt' => $ingresoUvt,
            'uvt' => $uvt,
            'norma' => 'Art. 206 num. 4 ET (> 650 UVT: 0% exento)',
        ];
    }

    /**
     * RF-PAR-05: Versión reproducible de los parámetros tributarios clave en una fecha.
     */
    public static function versionParametros(?string $fecha = null): string
    {
        $fechaConsulta = $fecha !== null ? CarbonImmutable::parse($fecha)->toDateString() : CarbonImmutable::today()->toDateString();
        $uvt = self::obtenerUvt($fechaConsulta);
        return "PAR-{$fechaConsulta}-UVT-{$uvt}";
    }
}
