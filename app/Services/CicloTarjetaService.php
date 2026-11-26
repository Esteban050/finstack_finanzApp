<?php

namespace App\Services;

use App\Models\Credito;
use Carbon\CarbonImmutable;

/**
 * Ciclo de facturación de una tarjeta de crédito a partir de su día de corte y su día de pago.
 * Un día mayor que los del mes se ajusta al último día del mes (p. ej. 31 en febrero → 28).
 */
class CicloTarjetaService
{
    /** ¿La tarjeta tiene configurado su ciclo? Las anteriores a corte/pago no lo tienen. */
    public static function tieneCiclo(Credito $tarjeta): bool
    {
        return $tarjeta->dia_corte !== null && $tarjeta->dia_pago !== null;
    }

    /** Fecha del día $dia en el mes de $referencia, ajustada al último día del mes. */
    public static function fechaDelMes(CarbonImmutable $referencia, int $dia): CarbonImmutable
    {
        $inicio = $referencia->startOfMonth();

        return $inicio->setDay(min($dia, $inicio->daysInMonth));
    }

    /** Primera fecha con ese día del mes que sea igual o posterior a $hoy. */
    public static function proximaFecha(int $dia, CarbonImmutable $hoy): CarbonImmutable
    {
        $esteMes = self::fechaDelMes($hoy, $dia);

        return $esteMes->gte($hoy) ? $esteMes : self::fechaDelMes($hoy->startOfMonth()->addMonth(), $dia);
    }

    /** Corte al que pertenece una compra: el de su mes si cae hasta el día de corte; si no, el del mes siguiente. */
    public static function corteDeCompra(CarbonImmutable $fecha, int $diaCorte): CarbonImmutable
    {
        $corte = self::fechaDelMes($fecha, $diaCorte);

        return $fecha->lte($corte) ? $corte : self::fechaDelMes($fecha->startOfMonth()->addMonth(), $diaCorte);
    }

    /** Fecha de pago del extracto de un corte: el primer día de pago posterior a él. */
    public static function pagoDeCorte(CarbonImmutable $corte, int $diaPago): CarbonImmutable
    {
        $pago = self::fechaDelMes($corte, $diaPago);

        return $pago->gt($corte) ? $pago : self::fechaDelMes($corte->startOfMonth()->addMonth(), $diaPago);
    }

    /** @return array{dia_corte: int, dia_pago: int, proximo_corte: string, proximo_pago: string}|null */
    public static function proximas(Credito $tarjeta, CarbonImmutable $hoy): ?array
    {
        if (! self::tieneCiclo($tarjeta)) {
            return null;
        }

        return [
            'dia_corte' => (int) $tarjeta->dia_corte,
            'dia_pago' => (int) $tarjeta->dia_pago,
            'proximo_corte' => self::proximaFecha((int) $tarjeta->dia_corte, $hoy)->toDateString(),
            'proximo_pago' => self::proximaFecha((int) $tarjeta->dia_pago, $hoy)->toDateString(),
        ];
    }
}
