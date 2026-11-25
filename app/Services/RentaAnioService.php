<?php

namespace App\Services;

use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\MovimientoInversion;
use App\Models\Scopes\DelUsuarioAutenticado;
use Carbon\CarbonImmutable;

/**
 * H27: año gravable con el que abre Renta: el año en curso si el usuario tiene registros en él; si no, el
 * último año anterior con registros; sin registros, el año en curso.
 */
class RentaAnioService
{
    public static function porDefecto(string $usuarioId, ?CarbonImmutable $hoy = null): int
    {
        $actual = ($hoy ?? CarbonImmutable::today())->year;
        $anios = [];

        foreach ([[Ingreso::class, 'fecha_recepcion'], [Gasto::class, 'fecha'], [MovimientoInversion::class, 'fecha']] as [$modelo, $columna]) {
            $maximo = $modelo::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->where('usuario_id', $usuarioId)
                ->whereYear($columna, '<=', $actual)
                ->max($columna);

            if ($maximo !== null) {
                $anios[] = CarbonImmutable::parse($maximo)->year;
            }
        }

        return $anios === [] ? $actual : max($anios);
    }
}
