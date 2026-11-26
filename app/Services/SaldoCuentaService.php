<?php

namespace App\Services;

use App\Models\CuentaBancaria;
use App\Models\Gasto;
use App\Models\Scopes\DelUsuarioAutenticado;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /**
     * Bloquea la fila de la cuenta hasta el fin de la transacción: dos salidas
     * simultáneas no pueden gastar el mismo saldo. Llamar ANTES de escribir la salida.
     */
    public static function bloquear(?string $cuentaId): void
    {
        if ($cuentaId !== null) {
            CuentaBancaria::withoutGlobalScope(DelUsuarioAutenticado::class)->whereKey($cuentaId)->lockForUpdate()->first();
        }
    }

    /**
     * Lo que la salida le quita a su cuenta: su monto más su GMF vigente (0 si no
     * está confirmada, está eliminada o no tiene cuenta). Lee el estado guardado.
     */
    public static function requeridoPorSalida(Gasto $gasto): string
    {
        $actual = DB::table('gastos')->where('id', $gasto->id)->first(['cuenta_id', 'estado', 'eliminado_en', 'monto_cop']);
        if ($actual === null || $actual->cuenta_id === null || $actual->estado !== 'CONFIRMADO' || $actual->eliminado_en !== null) {
            return '0.00';
        }

        $gmf = DB::table('gastos')->where('gasto_origen_gmf_id', $gasto->id)->whereNull('eliminado_en')->sum('monto_cop');

        return bcadd(bcadd((string) $actual->monto_cop, (string) $gmf, 2), '0', 2);
    }

    /**
     * Rechaza la salida si, ya guardada (y con su GMF sincronizado), deja la cuenta en
     * negativo. Se llama dentro de la transacción, tras `bloquear` y
     * `GmfCuentaService::sincronizar`: el saldo derivado ya descuenta esta salida, así que
     * `saldo + requerido` es lo que había disponible antes de ella. Una edición que no
     * aumenta lo que sale de la misma cuenta no se bloquea (no debe impedirse a sí misma,
     * ni por un saldo que ya estaba negativo).
     *
     * @param  string  $requeridoAnterior  `requeridoPorSalida` leído antes de editar.
     * @param  string|null  $cuentaAnterior  Cuenta del gasto antes de editar.
     *
     * @throws ValidationException
     */
    public static function validarSalida(Gasto $gasto, string $requeridoAnterior = '0.00', ?string $cuentaAnterior = null): void
    {
        $requerido = self::requeridoPorSalida($gasto);
        $cuentaId = DB::table('gastos')->where('id', $gasto->id)->value('cuenta_id');
        if ($cuentaId === null || bccomp($requerido, '0', 2) <= 0) {
            return;
        }
        if ($cuentaId === $cuentaAnterior && bccomp($requerido, $requeridoAnterior, 2) <= 0) {
            return;
        }

        $saldo = self::saldos([$cuentaId])[$cuentaId] ?? '0.00';
        if (bccomp($saldo, '0', 2) >= 0) {
            return;
        }

        $disponible = bcadd($saldo, $requerido, 2);
        $mostrado = bccomp($disponible, '0', 2) === 1 ? $disponible : '0.00';
        $formato = number_format((float) $mostrado, str_ends_with($mostrado, '.00') ? 0 : 2, ',', '.');

        throw ValidationException::withMessages([
            'cuenta_id' => 'Saldo insuficiente en la cuenta. Disponible: $ '.$formato,
        ]);
    }
}
