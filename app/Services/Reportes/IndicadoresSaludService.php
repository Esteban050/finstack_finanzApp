<?php

namespace App\Services\Reportes;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Presupuesto;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\Usuario;
use App\Services\EndeudamientoService;
use App\Services\PresupuestoService;
use Carbon\CarbonImmutable;

/**
 * MOD-11 — Reportes y Dashboards Dinámicos
 * HU-H03: Servicio de Indicadores de Salud Financiera y Diagnóstico (AN §6.6).
 *
 * Implementa 7 indicadores estandarizados, transparencia de cálculo ("Ver detalle")
 * y aviso legal normativo (RNG-08).
 */
class IndicadoresSaludService
{
    public const AVISO_LEGAL = 'Valores estimados con fines informativos. No constituyen asesoría tributaria, contable ni financiera.';

    public const META_AHORRO_DEFAULT = 10.0;

    public const REGLA_50_30_20_DEFAULT = [
        'esenciales' => 50.0,
        'discrecionales' => 30.0,
        'ahorro' => 20.0,
    ];

    /**
     * Calcula los 7 indicadores de salud financiera para un mes dado.
     *
     * @return array{
     *     mes: string,
     *     mes_nombre: string,
     *     aviso_legal: string,
     *     indicadores: array<int, array<string, mixed>>,
     *     resumen_diagnostico: array<string, mixed>
     * }
     */
    public function calcularIndicadores(
        Usuario $usuario,
        ?string $mesStr = null,
        ?CarbonImmutable $referencia = null
    ): array {
        $hoy = $referencia ?? CarbonImmutable::today();
        $mes = $mesStr ? CarbonImmutable::parse($mesStr.'-01')->startOfMonth() : $hoy->startOfMonth();
        $finMes = $mes->endOfMonth();
        $diasMes = $mes->daysInMonth;

        $mesAnterior = $mes->subMonthNoOverflow()->startOfMonth();
        $finMesAnterior = $mesAnterior->endOfMonth();
        $diasMesAnterior = $mesAnterior->daysInMonth;

        // Cifras del mes consultado
        $ingresosNetos = (string) (Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha_recepcion', [$mes->toDateString(), $finMes->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->sum('monto_neto_cop') ?? '0');

        $gastosQuery = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$mes->toDateString(), $finMes->toDateString()])
            ->where('estado', 'CONFIRMADO');

        $gastosTotales = (string) ($gastosQuery->sum('monto_cop') ?? '0');
        $conteoGastos = (int) $gastosQuery->count();

        // Cifras del mes anterior para comparativas
        $ingresosNetosAnt = (string) (Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha_recepcion', [$mesAnterior->toDateString(), $finMesAnterior->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->sum('monto_neto_cop') ?? '0');

        $gastosQueryAnt = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$mesAnterior->toDateString(), $finMesAnterior->toDateString()])
            ->where('estado', 'CONFIRMADO');

        $gastosTotalesAnt = (string) ($gastosQueryAnt->sum('monto_cop') ?? '0');
        $conteoGastosAnt = (int) $gastosQueryAnt->count();

        // 1. Tasa de ahorro: (ingresos netos - gastos) / ingresos netos * 100
        $balance = bcsub($ingresosNetos, $gastosTotales, 2);
        $tasaAhorro = null;
        if (bccomp($ingresosNetos, '0', 2) === 1) {
            $tasaAhorro = round((float) bcdiv(bcmul($balance, '100', 6), $ingresosNetos, 4), 2);
        }

        $estadoAhorro = 'DEFICIT';
        $etiquetaAhorro = 'Déficit';
        if ($tasaAhorro !== null) {
            if ($tasaAhorro >= self::META_AHORRO_DEFAULT) {
                $estadoAhorro = 'OPTIMO';
                $etiquetaAhorro = 'Cumple meta';
            } elseif ($tasaAhorro > 0) {
                $estadoAhorro = 'MODERADO';
                $etiquetaAhorro = 'Por mejorar';
            }
        }

        // Histórico de 6 meses para tasa de ahorro
        $historicoAhorro = $this->obtenerHistoricoAhorro($usuario, $mes);

        $indTasaAhorro = [
            'id' => 'tasa_ahorro',
            'nombre' => 'Tasa de ahorro',
            'valor' => $tasaAhorro,
            'valor_formateado' => $tasaAhorro !== null ? "{$tasaAhorro}%" : 'N/A',
            'meta' => self::META_AHORRO_DEFAULT,
            'estado' => $estadoAhorro,
            'etiqueta_estado' => $etiquetaAhorro,
            'tendencia_6m' => $historicoAhorro,
            'detalle' => [
                'formula' => '((Ingresos Netos - Gastos) / Ingresos Netos) × 100',
                'variables' => [
                    'Ingresos netos' => '$ '.number_format((float) $ingresosNetos, 0, ',', '.'),
                    'Gastos totales' => '$ '.number_format((float) $gastosTotales, 0, ',', '.'),
                    'Balance' => '$ '.number_format((float) $balance, 0, ',', '.'),
                    'Meta sugerida' => self::META_AHORRO_DEFAULT.'%',
                ],
                'interpretacion' => 'Representa el porcentaje de tus ingresos que logras conservar después de cubrir todos tus gastos. Un valor superior al 10% permite construir fondos de emergencia e invertir a largo plazo.',
            ],
        ];

        // 2. Ejecución del presupuesto: (gastado con presupuesto / asignado) * 100
        $presupuesto = Presupuesto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->where('mes', $mes->toDateString())
            ->first();

        $porcPresupuesto = null;
        $asignadoPresupuesto = '0';
        $gastadoPresupuesto = '0';
        $estadoPresupuesto = 'SIN_PRESUPUESTO';
        $etiquetaPresupuesto = 'Sin presupuesto';

        if ($presupuesto) {
            $ejecucion = PresupuestoService::ejecucion($presupuesto, $finMes);
            $asignadoPresupuesto = (string) ($ejecucion['totales']['monto_asignado'] ?? '0');
            $gastadoPresupuesto = (string) ($ejecucion['totales']['gastado'] ?? '0');

            if (bccomp($asignadoPresupuesto, '0', 2) === 1) {
                $porcPresupuesto = round((float) bcdiv(bcmul($gastadoPresupuesto, '100', 6), $asignadoPresupuesto, 4), 1);
                if ($porcPresupuesto < 80.0) {
                    $estadoPresupuesto = 'EN_CONTROL';
                    $etiquetaPresupuesto = 'En control (< 80%)';
                } elseif ($porcPresupuesto < 100.0) {
                    $estadoPresupuesto = 'EN_RIESGO';
                    $etiquetaPresupuesto = 'En riesgo (80% - 100%)';
                } else {
                    $estadoPresupuesto = 'EXCEDIDO';
                    $etiquetaPresupuesto = 'Excedido (≥ 100%)';
                }
            }
        }

        $indPresupuesto = [
            'id' => 'ejecucion_presupuesto',
            'nombre' => 'Ejecución del presupuesto',
            'valor' => $porcPresupuesto,
            'valor_formateado' => $porcPresupuesto !== null ? "{$porcPresupuesto}%" : 'Sin presupuesto',
            'estado' => $estadoPresupuesto,
            'etiqueta_estado' => $etiquetaPresupuesto,
            'asignado' => $asignadoPresupuesto,
            'gastado' => $gastadoPresupuesto,
            'marca_limite' => 100.0,
            'detalle' => [
                'formula' => '(Gastado en categorías presupuestadas / Asignado total) × 100',
                'variables' => [
                    'Monto asignado' => '$ '.number_format((float) $asignadoPresupuesto, 0, ',', '.'),
                    'Monto ejecutado' => '$ '.number_format((float) $gastadoPresupuesto, 0, ',', '.'),
                    'Diferencia' => '$ '.number_format((float) bcsub($asignadoPresupuesto, $gastadoPresupuesto, 2), 0, ',', '.'),
                ],
                'interpretacion' => 'Mide cuánto de lo planificado para el mes ha sido consumido. Mantenerse por debajo del 80% antes del cierre del mes garantiza liquidez para imprevistos.',
            ],
        ];

        // 3. Endeudamiento: (cuotas deuda mes / ingreso neto promedio 3m) * 100
        $endeudamientoCalc = EndeudamientoService::calcular($usuario->id, $finMes);
        $indicadorEndeudamiento = $endeudamientoCalc['indicador_porc'];
        $estadoEndeudamiento = 'VERDE';
        $etiquetaEndeudamiento = 'Saludable (≤ 30%)';

        if ($indicadorEndeudamiento !== null) {
            $val = (float) $indicadorEndeudamiento;
            if ($val > 40.0) {
                $estadoEndeudamiento = 'ROJO';
                $etiquetaEndeudamiento = 'Crítico (> 40%)';
            } elseif ($val > 30.0) {
                $estadoEndeudamiento = 'AMARILLO';
                $etiquetaEndeudamiento = 'Precaución (31% - 40%)';
            }
        }

        $indEndeudamiento = [
            'id' => 'endeudamiento',
            'nombre' => 'Endeudamiento',
            'valor' => $indicadorEndeudamiento,
            'valor_formateado' => $indicadorEndeudamiento !== null ? "{$indicadorEndeudamiento}%" : 'Sin deuda',
            'estado' => $estadoEndeudamiento,
            'etiqueta_estado' => $etiquetaEndeudamiento,
            'detalle' => [
                'formula' => '(Cuotas de deuda del mes / Ingreso neto promedio de los últimos meses cerrados) × 100; si hay menos de 2 meses cerrados con ingresos, se usa el ingreso neto del mes en curso',
                'variables' => [
                    'Total cuotas mes' => '$ '.number_format((float) ($endeudamientoCalc['pagos_mes']['total'] ?? 0), 0, ',', '.'),
                    'Base del ingreso' => ($endeudamientoCalc['ingresos_netos']['base'] ?? 'MESES_CERRADOS') === 'MES_EN_CURSO' ? 'Mes en curso' : 'Promedio de meses cerrados',
                    'Ingreso neto usado' => '$ '.number_format((float) ($endeudamientoCalc['ingresos_netos']['promedio'] ?? 0), 0, ',', '.'),
                ],
                'interpretacion' => 'La Superintendencia Financiera recomienda que las obligaciones de crédito no superen el 30% al 40% de tus ingresos netos mensuales para evitar sobreendeudamiento.',
            ],
        ];

        // 4. Gasto diario promedio: gastos periodo / días periodo
        $diasGasto = self::diasParaPromedio($mes, $hoy);
        $gastoDiario = round((float) bcdiv($gastosTotales, (string) $diasGasto, 4), 2);
        $gastoDiarioAnt = round((float) bcdiv($gastosTotalesAnt, (string) max($diasMesAnterior, 1), 4), 2);
        $varGastoDiario = $gastoDiarioAnt > 0 ? round((($gastoDiario - $gastoDiarioAnt) / $gastoDiarioAnt) * 100, 2) : null;
        $historicoGastoDiario = $this->obtenerHistoricoGastoDiario($usuario, $mes, $hoy);

        $indGastoDiario = [
            'id' => 'gasto_diario_promedio',
            'nombre' => 'Gasto diario promedio',
            'valor' => $gastoDiario,
            'valor_formateado' => '$ '.number_format($gastoDiario, 0, ',', '.'),
            'referencia_anterior' => '$ '.number_format($gastoDiarioAnt, 0, ',', '.'),
            'variacion_porc' => $varGastoDiario,
            'tendencia_6m' => $historicoGastoDiario,
            'detalle' => [
                'formula' => 'Gastos totales del periodo / Días transcurridos (mes en curso) o días del mes (mes cerrado)',
                'variables' => [
                    'Gastos totales' => '$ '.number_format((float) $gastosTotales, 0, ',', '.'),
                    'Días transcurridos' => (string) $diasGasto,
                    'Gasto diario mes anterior' => '$ '.number_format($gastoDiarioAnt, 0, ',', '.'),
                ],
                'interpretacion' => 'Indica el ritmo promedio de egresos diarios. Ayuda a establecer límites de consumo por día o semana.',
            ],
        ];

        // 5. Concentración del gasto: (gasto categoría más grande / gastos totales) * 100
        $categoriasGastos = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$mes->toDateString(), $finMes->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->selectRaw('categoria_id, SUM(monto_cop) as total')
            ->groupBy('categoria_id')
            ->orderByDesc('total')
            ->get();

        $catIds = $categoriasGastos->pluck('categoria_id')->filter()->all();
        $nombresCats = Categoria::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereIn('id', $catIds)
            ->pluck('nombre', 'id');

        $top3 = [];
        $acumTop3 = '0';
        foreach ($categoriasGastos->take(3) as $c) {
            $m = (string) $c->total;
            $acumTop3 = bcadd($acumTop3, $m, 2);
            $p = bccomp($gastosTotales, '0', 2) === 1 ? round((float) bcdiv(bcmul($m, '100', 6), $gastosTotales, 4), 1) : 0.0;
            $top3[] = [
                'nombre' => $nombresCats[$c->categoria_id] ?? 'Categoría',
                'monto' => $m,
                'porcentaje' => $p,
            ];
        }

        $restoMonto = bcsub($gastosTotales, $acumTop3, 2);
        $restoPorc = bccomp($gastosTotales, '0', 2) === 1 ? round((float) bcdiv(bcmul($restoMonto, '100', 6), $gastosTotales, 4), 1) : 0.0;

        $porcMax = $top3[0]['porcentaje'] ?? 0.0;
        $estadoConcentracion = 'DIVERSIFICADO';
        $etiquetaConcentracion = 'Diversificado (< 35%)';
        if ($porcMax > 50.0) {
            $estadoConcentracion = 'ALTA';
            $etiquetaConcentracion = 'Alta concentración (> 50%)';
        } elseif ($porcMax >= 35.0) {
            $estadoConcentracion = 'MODERADA';
            $etiquetaConcentracion = 'Moderada (35% - 50%)';
        }

        $indConcentracion = [
            'id' => 'concentracion_gasto',
            'nombre' => 'Concentración del gasto',
            'valor' => $porcMax,
            'valor_formateado' => "{$porcMax}%",
            'estado' => $estadoConcentracion,
            'etiqueta_estado' => $etiquetaConcentracion,
            'top3' => $top3,
            'resto' => ['monto' => $restoMonto, 'porcentaje' => $restoPorc],
            'detalle' => [
                'formula' => '(Gasto de la categoría principal / Gastos totales) × 100',
                'variables' => [
                    'Categoría principal' => ($top3[0]['nombre'] ?? 'Ninguna').' ($ '.number_format((float) ($top3[0]['monto'] ?? 0), 0, ',', '.').')',
                    'Gastos totales' => '$ '.number_format((float) $gastosTotales, 0, ',', '.'),
                    'Participación de las 3 principales' => round((float) bcdiv(bcmul($acumTop3, '100', 6), max($gastosTotales, '1'), 4), 1).'%',
                ],
                'interpretacion' => 'Mide si tus finanzas dependen excesivamente de un solo rubro. Si una categoría supera el 40%-50%, cualquier imprevisto en ella afectará fuertemente tu presupuesto.',
            ],
        ];

        // 6. Ticket promedio: gastos totales / número de transacciones
        $ticketPromedio = $conteoGastos > 0 ? round((float) bcdiv($gastosTotales, (string) $conteoGastos, 4), 2) : 0.0;
        $ticketPromedioAnt = $conteoGastosAnt > 0 ? round((float) bcdiv($gastosTotalesAnt, (string) $conteoGastosAnt, 4), 2) : 0.0;
        $varTicket = $ticketPromedioAnt > 0 ? round((($ticketPromedio - $ticketPromedioAnt) / $ticketPromedioAnt) * 100, 2) : null;

        $indTicket = [
            'id' => 'ticket_promedio',
            'nombre' => 'Ticket promedio',
            'valor' => $ticketPromedio,
            'valor_formateado' => '$ '.number_format($ticketPromedio, 0, ',', '.'),
            'transacciones_totales' => $conteoGastos,
            'referencia_anterior' => '$ '.number_format($ticketPromedioAnt, 0, ',', '.'),
            'variacion_porc' => $varTicket,
            'detalle' => [
                'formula' => 'Gastos totales / Número total de transacciones de gasto',
                'variables' => [
                    'Gastos totales' => '$ '.number_format((float) $gastosTotales, 0, ',', '.'),
                    'Número de transacciones' => (string) $conteoGastos,
                    'Ticket mes anterior' => '$ '.number_format($ticketPromedioAnt, 0, ',', '.'),
                ],
                'interpretacion' => 'Muestra el valor medio por cada desembolso realizado. Un aumento sostenido en el ticket puede indicar inflación en consumo o compras de mayor cuantía.',
            ],
        ];

        // 7. Regla 50/30/20: Esenciales, Discrecionales y Ahorro sobre ingresos netos
        $porNecesidad = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$mes->toDateString(), $finMes->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->selectRaw("COALESCE(necesidad, 'DISCRECIONAL') as nec, SUM(monto_cop) as total")
            ->groupBy('nec')
            ->pluck('total', 'nec')
            ->all();

        $esencialesCOP = (string) ($porNecesidad['ESENCIAL'] ?? '0');
        $discrecionalesCOP = (string) ($porNecesidad['DISCRECIONAL'] ?? '0');
        $ahorroCOP = bccomp($balance, '0', 2) === 1 ? $balance : '0';

        $baseCalculo = bccomp($ingresosNetos, '0', 2) === 1 ? $ingresosNetos : bcadd($gastosTotales, '1', 2);

        $porcEsenciales = round((float) bcdiv(bcmul($esencialesCOP, '100', 6), $baseCalculo, 4), 1);
        $porcDiscrecionales = round((float) bcdiv(bcmul($discrecionalesCOP, '100', 6), $baseCalculo, 4), 1);
        $porcAhorro = bccomp($ingresosNetos, '0', 2) === 1 && bccomp($balance, '0', 2) === 1
            ? round((float) bcdiv(bcmul($ahorroCOP, '100', 6), $ingresosNetos, 4), 1)
            : 0.0;

        $indRegla503020 = [
            'id' => 'regla_50_30_20',
            'nombre' => 'Regla 50 / 30 / 20',
            'valor' => [
                'esenciales' => $porcEsenciales,
                'discrecionales' => $porcDiscrecionales,
                'ahorro' => $porcAhorro,
            ],
            'referencia' => self::REGLA_50_30_20_DEFAULT,
            'montos_cop' => [
                'esenciales' => $esencialesCOP,
                'discrecionales' => $discrecionalesCOP,
                'ahorro' => $ahorroCOP,
            ],
            'detalle' => [
                'formula' => 'Distribución % de Esenciales, Discrecionales y Ahorro frente a Ingresos Netos',
                'variables' => [
                    'Gastos esenciales (Necesidades)' => '$ '.number_format((float) $esencialesCOP, 0, ',', '.')." ({$porcEsenciales}%)",
                    'Gastos discrecionales (Deseos)' => '$ '.number_format((float) $discrecionalesCOP, 0, ',', '.')." ({$porcDiscrecionales}%)",
                    'Ahorro neto' => '$ '.number_format((float) $ahorroCOP, 0, ',', '.')." ({$porcAhorro}%)",
                    'Base de ingresos netos' => '$ '.number_format((float) $ingresosNetos, 0, ',', '.'),
                ],
                'interpretacion' => 'La regla 50/30/20 asigna 50% de ingresos a necesidades básicas, 30% a gastos discrecionales y 20% al ahorro o amortización acelerada de deudas.',
            ],
        ];

        return [
            'mes' => $mes->format('Y-m'),
            'mes_nombre' => ucfirst($mes->translatedFormat('F Y')),
            'aviso_legal' => self::AVISO_LEGAL,
            'indicadores' => [
                $indTasaAhorro,
                $indPresupuesto,
                $indEndeudamiento,
                $indGastoDiario,
                $indConcentracion,
                $indTicket,
                $indRegla503020,
            ],
            'totales_mes' => [
                'ingresos_netos' => $ingresosNetos,
                'gastos_totales' => $gastosTotales,
                'balance' => $balance,
            ],
        ];
    }

    /**
     * Obtiene serie de 6 meses para la tasa de ahorro.
     *
     * @return array<int, array{mes: string, tasa: ?float}>
     */
    protected function obtenerHistoricoAhorro(Usuario $usuario, CarbonImmutable $mesFinal): array
    {
        $historico = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $mesFinal->subMonthsNoOverflow($i)->startOfMonth();
            $fin = $m->endOfMonth();

            $ing = (string) (Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->where('usuario_id', $usuario->id)
                ->whereBetween('fecha_recepcion', [$m->toDateString(), $fin->toDateString()])
                ->where('estado', 'CONFIRMADO')
                ->sum('monto_neto_cop') ?? '0');

            $gas = (string) (Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->where('usuario_id', $usuario->id)
                ->whereBetween('fecha', [$m->toDateString(), $fin->toDateString()])
                ->where('estado', 'CONFIRMADO')
                ->sum('monto_cop') ?? '0');

            $bal = bcsub($ing, $gas, 2);
            $tasa = null;
            if (bccomp($ing, '0', 2) === 1) {
                $tasa = round((float) bcdiv(bcmul($bal, '100', 6), $ing, 4), 1);
            }

            $historico[] = [
                'mes' => ucfirst($m->translatedFormat('M')),
                'mes_completo' => $m->format('Y-m'),
                'tasa' => $tasa,
            ];
        }

        return $historico;
    }

    /**
     * H19: días entre los que se divide el gasto del mes. En el mes en curso, los días transcurridos
     * (hasta hoy, mínimo 1); en un mes cerrado, todos los días del mes; en un mes futuro, 1.
     */
    private static function diasParaPromedio(CarbonImmutable $mes, CarbonImmutable $hoy): int
    {
        $inicio = $mes->startOfMonth();

        if ($hoy->lt($inicio)) {
            return 1;
        }

        if ($hoy->lte($mes->endOfMonth())) {
            return max($hoy->day, 1);
        }

        return max($mes->daysInMonth, 1);
    }

    /**
     * Obtiene serie de 6 meses para el gasto diario promedio.
     *
     * @return array<int, array{mes: string, promedio_diario: float}>
     */
    protected function obtenerHistoricoGastoDiario(Usuario $usuario, CarbonImmutable $mesFinal, ?CarbonImmutable $hoy = null): array
    {
        $historico = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $mesFinal->subMonthsNoOverflow($i)->startOfMonth();
            $fin = $m->endOfMonth();
            $dias = self::diasParaPromedio($m, $hoy ?? CarbonImmutable::today());

            $gas = (string) (Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->where('usuario_id', $usuario->id)
                ->whereBetween('fecha', [$m->toDateString(), $fin->toDateString()])
                ->where('estado', 'CONFIRMADO')
                ->sum('monto_cop') ?? '0');

            $prom = round((float) bcdiv($gas, (string) max($dias, 1), 4), 2);

            $historico[] = [
                'mes' => ucfirst($m->translatedFormat('M')),
                'mes_completo' => $m->format('Y-m'),
                'promedio_diario' => $prom,
            ];
        }

        return $historico;
    }
}
