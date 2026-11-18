<?php

namespace App\Services;

use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MOD-09 — RF-057: punto de entrada de las alertas. Coordina las del
 * presupuesto (AlertaPresupuestoService) y las financieras
 * (AlertaFinancieraService) y decide cuándo corre cada una:
 *
 * - Al cambiar un gasto (GastoObserver) o el presupuesto: las que dependen
 *   del gasto del mes o del año y el gasto inusual de los gastos guardados.
 * - En el job diario: todas, incluidas las que cambian con los días aunque no
 *   haya gastos nuevos (proyecciones, endeudamiento, vencimientos, cierre).
 *
 * Una falla de una parte se registra en el log y no impide las demás ni lo
 * que el Usuario guardó.
 */
class AlertaService
{
    public const AL_CAMBIAR_GASTO = 'GASTO';
    public const DIARIO = 'DIARIO';

    /** Último turno agendado por usuario: tras el commit solo evalúa el último. */
    private static array $turnos = [];

    /** Gastos guardados por usuario desde la última evaluación (gasto inusual). */
    private static array $gastos = [];

    private static int $secuencia = 0;

    /**
     * Agenda la evaluación del Usuario para después del commit en curso (o ya,
     * si no hay transacción). Varios gastos guardados en la misma transacción
     * (edición masiva) disparan una sola evaluación.
     */
    public static function programar(string $usuarioId, ?string $gastoId = null): void
    {
        $turno = ++self::$secuencia;
        self::$turnos[$usuarioId] = $turno;

        if ($gastoId !== null) {
            self::$gastos[$usuarioId][$gastoId] = true;
        }

        DB::afterCommit(function () use ($usuarioId, $turno) {
            if ((self::$turnos[$usuarioId] ?? null) !== $turno) {
                return;
            }

            $gastos = array_keys(self::$gastos[$usuarioId] ?? []);
            unset(self::$turnos[$usuarioId], self::$gastos[$usuarioId]);

            self::evaluarUsuario($usuarioId, null, self::AL_CAMBIAR_GASTO, $gastos);
        });
    }

    /**
     * Evalúa las reglas del Usuario. Devuelve cuántas notificaciones nuevas
     * emitió.
     *
     * @param  array<int, string>  $gastoIds  gastos recién guardados (gasto inusual)
     */
    public static function evaluarUsuario(string $usuarioId, ?CarbonImmutable $hoy = null, string $momento = self::DIARIO, array $gastoIds = []): int
    {
        $hoy ??= CarbonImmutable::today();
        $emitidas = 0;

        $partes = [
            'presupuesto' => fn () => AlertaPresupuestoService::evaluarUsuario($usuarioId, $hoy),
            'financieras' => fn () => AlertaFinancieraService::evaluarUsuario($usuarioId, $hoy, $momento, $gastoIds),
        ];

        foreach ($partes as $parte => $evaluar) {
            try {
                $emitidas += $evaluar();
            } catch (\Throwable $e) {
                Log::warning("No se pudieron evaluar las alertas ({$parte}, RF-057): {$e->getMessage()}", [
                    'usuario_id' => $usuarioId,
                ]);
            }
        }

        return $emitidas;
    }

    /** Job diario: evalúa a todos los usuarios activos. */
    public static function evaluarTodos(?CarbonImmutable $hoy = null): int
    {
        $hoy ??= CarbonImmutable::today();
        $emitidas = 0;

        foreach (Usuario::where('estado', 'ACTIVA')->pluck('id') as $usuarioId) {
            $emitidas += self::evaluarUsuario($usuarioId, $hoy, self::DIARIO);
        }

        return $emitidas;
    }
}
