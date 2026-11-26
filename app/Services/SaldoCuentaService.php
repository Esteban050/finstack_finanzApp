<?php

namespace App\Services;

use App\Models\CuentaBancaria;
use Illuminate\Support\Facades\DB;

/**
 * Saldo disponible de las cuentas de débito/ahorro. Se DERIVA, nunca se guarda:
 * saldo_inicial + Σ ingresos confirmados − Σ gastos confirmados (incluye el GMF),
 * ambos con eliminado_en nulo. Así editar o borrar un movimiento no deja el saldo
 * desfasado. Aritmética con bcmath.
 */
class SaldoCuentaService
{
    /** Saldo disponible de una cuenta (cadena con 2 decimales). */
    public static function saldoDisponible(CuentaBancaria $cuenta): string
    {
        return self::saldos([$cuenta->id])[$cuenta->id]
            ?? bcadd((string) $cuenta->saldo_inicial, '0', 2);
    }

    /**
     * Saldos de varias cuentas con dos consultas agregadas.
     *
     * @param  array<int, string>  $cuentaIds
     * @return array<string, string> id de la cuenta => saldo disponible
     */
    public static function saldos(array $cuentaIds): array
    {
        if ($cuentaIds === []) {
            return [];
        }

        $iniciales = DB::table('cuentas_bancarias')->whereIn('id', $cuentaIds)->pluck('saldo_inicial', 'id');
        $ingresos = DB::table('ingresos')->whereIn('cuenta_id', $cuentaIds)
            ->whereNull('eliminado_en')->where('estado', 'CONFIRMADO')
            ->groupBy('cuenta_id')->selectRaw('cuenta_id, SUM(monto_neto_cop)::text AS total')->pluck('total', 'cuenta_id');
        $gastos = DB::table('gastos')->whereIn('cuenta_id', $cuentaIds)
            ->whereNull('eliminado_en')->where('estado', 'CONFIRMADO')
            ->groupBy('cuenta_id')->selectRaw('cuenta_id, SUM(monto_cop)::text AS total')->pluck('total', 'cuenta_id');

        $saldos = [];
        foreach ($iniciales as $id => $inicial) {
            $saldo = bcadd((string) $inicial, (string) ($ingresos[$id] ?? '0'), 2);
            $saldos[$id] = bcsub($saldo, (string) ($gastos[$id] ?? '0'), 2);
        }

        return $saldos;
    }
}
