<?php

namespace App\Services;

use App\Models\Presupuesto;
use Carbon\CarbonImmutable;

/**
 * MOD-09 — RF-057: alertas del presupuesto del mes.
 *
 * - UMBRAL_LINEA (RN-057.1 / RN-057.2): la ejecución de una línea llega a un
 *   umbral. Una por umbral, categoría y mes.
 * - PRESUPUESTO_TOTAL: la ejecución total llega a un umbral.
 * - PROYECCION_LINEA (RN-057.3): desde la mitad del mes, la proyección al
 *   cierre de una línea llega al umbral y la línea aún no llegó al 100 %.
 *
 * Si una evaluación cruza varios umbrales a la vez solo se emite el más alto.
 * La clave usa la categoría y no el id de la línea, porque editar el
 * presupuesto recrea las líneas.
 */
class AlertaPresupuestoService
{
    /**
     * Evalúa las reglas activas sobre el presupuesto del mes actual. Devuelve
     * cuántas notificaciones nuevas emitió.
     */
    public static function evaluarUsuario(string $usuarioId, ?CarbonImmutable $hoy = null): int
    {
        $hoy ??= CarbonImmutable::today();
        $presupuesto = PresupuestoService::asegurarMes($usuarioId, $hoy);

        if ($presupuesto === null) {
            return 0;
        }

        $reglas = ReglaAlertaService::vigentes($usuarioId);
        $ejecucion = PresupuestoService::ejecucion($presupuesto, $hoy);
        $emitidas = 0;

        $regla = $reglas[ReglaAlertaService::UMBRAL_LINEA];
        if ($regla['activa']) {
            foreach ($ejecucion['lineas'] as $linea) {
                $umbral = self::umbralAlcanzado($regla['parametros']['umbrales'], $linea['ejecucion_porc']);

                if ($umbral !== null && self::emitir($presupuesto, $regla, $umbral, "UMBRAL_LINEA:{$linea['categoria_id']}:{$ejecucion['mes']}:{$umbral}", [
                    ...self::datosLinea($ejecucion, $linea),
                    'umbral' => $umbral,
                ])) {
                    $emitidas++;
                }
            }
        }

        $regla = $reglas[ReglaAlertaService::PRESUPUESTO_TOTAL];
        if ($regla['activa']) {
            $totales = $ejecucion['totales'];
            $umbral = self::umbralAlcanzado($regla['parametros']['umbrales'], $totales['ejecucion_porc']);

            if ($umbral !== null && self::emitir($presupuesto, $regla, $umbral, "PRESUPUESTO_TOTAL:{$ejecucion['mes']}:{$umbral}", [
                'mes' => $ejecucion['mes'],
                'presupuesto_id' => $presupuesto->id,
                'umbral' => $umbral,
                'ejecucion_porc' => $totales['ejecucion_porc'],
                'asignado' => bcadd($totales['monto_asignado'], $totales['saldo_trasladado'], 0),
                'gastado' => $totales['gastado'],
                'disponible' => $totales['disponible'],
            ])) {
                $emitidas++;
            }
        }

        $regla = $reglas[ReglaAlertaService::PROYECCION_LINEA];
        // RN-057.3: "desde la mitad del mes".
        if ($regla['activa'] && $ejecucion['dia_corte'] * 2 > $ejecucion['dias_mes']) {
            $umbral = (int) $regla['parametros']['umbral'];

            foreach ($ejecucion['lineas'] as $linea) {
                $yaExcedida = bccomp((string) $linea['ejecucion_porc'], '100', 2) >= 0;
                $llega = bccomp((string) $linea['proyeccion_porc'], (string) $umbral, 2) >= 0;

                if (! $yaExcedida && $llega && self::emitir($presupuesto, $regla, null, "PROYECCION_LINEA:{$linea['categoria_id']}:{$ejecucion['mes']}", [
                    ...self::datosLinea($ejecucion, $linea),
                    'umbral' => $umbral,
                    'proyeccion' => $linea['proyeccion'],
                    'proyeccion_porc' => $linea['proyeccion_porc'],
                    'gasto_habitual' => $linea['gasto_habitual'],
                    'pendientes_recurrentes' => $linea['pendientes_recurrentes'],
                ])) {
                    $emitidas++;
                }
            }
        }

        return $emitidas;
    }

    /** El umbral más alto que el % alcanza, o null si no llega a ninguno. */
    private static function umbralAlcanzado(array $umbrales, ?string $porcentaje): ?int
    {
        if ($porcentaje === null) {
            return null;
        }

        $alcanzado = null;
        foreach ($umbrales as $umbral) {
            if (bccomp($porcentaje, (string) $umbral, 2) >= 0 && ($alcanzado === null || $umbral > $alcanzado)) {
                $alcanzado = (int) $umbral;
            }
        }

        return $alcanzado;
    }

    private static function emitir(Presupuesto $presupuesto, array $regla, ?int $umbral, string $clave, array $datos): bool
    {
        return NotificacionService::emitir(
            usuarioId: $presupuesto->usuario_id,
            tipo: $regla['tipo'],
            clave: $clave,
            prioridad: ReglaAlertaService::prioridad($regla, $umbral),
            datos: $datos,
            enviarCorreo: $regla['enviar_correo'],
            referenciaTipo: 'PRESUPUESTO',
            referenciaId: $presupuesto->id
        ) !== null;
    }

    /** RN-057.5: cifras de la línea al momento de la alerta. */
    private static function datosLinea(array $ejecucion, array $linea): array
    {
        return [
            'mes' => $ejecucion['mes'],
            'presupuesto_id' => $ejecucion['presupuesto_id'],
            'linea_id' => $linea['linea_id'],
            'categoria_id' => $linea['categoria_id'],
            'nombre' => $linea['nombre'],
            'ejecucion_porc' => $linea['ejecucion_porc'],
            'asignado' => bcadd($linea['monto_asignado'], $linea['saldo_trasladado'], 0),
            'gastado' => $linea['gastado'],
            'disponible' => $linea['disponible'],
        ];
    }
}
