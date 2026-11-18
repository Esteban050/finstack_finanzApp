<?php

namespace App\Observers;

use App\Models\Gasto;
use App\Services\AlertaService;
use Carbon\CarbonImmutable;

/**
 * RN-025.4 / RF-057: un gasto que cambia puede disparar alertas (umbral del
 * presupuesto, tope mensual, gasto inusual, topes de la DIAN…). Cubre todo lo
 * que escribe gastos: el registro manual, la edición masiva, las recurrencias
 * y los pagos de crédito.
 *
 * Solo agenda la evaluación: corre después del commit, una vez por usuario.
 */
class GastoObserver
{
    public function saved(Gasto $gasto): void
    {
        $this->programar($gasto);
    }

    public function deleted(Gasto $gasto): void
    {
        $this->programar($gasto);
    }

    public function restored(Gasto $gasto): void
    {
        $this->programar($gasto);
    }

    /**
     * La fecha nueva o la anterior (si se movió) debe caer en el año en curso:
     * las alertas vigilan el mes actual y los acumulados del año (DIAN).
     */
    private function programar(Gasto $gasto): void
    {
        $anio = CarbonImmutable::today()->year;

        foreach ([$gasto->fecha, $gasto->getOriginal('fecha')] as $fecha) {
            if ($fecha !== null && CarbonImmutable::parse($fecha)->year === $anio) {
                AlertaService::programar($gasto->usuario_id, $gasto->exists ? $gasto->id : null);

                return;
            }
        }
    }
}
