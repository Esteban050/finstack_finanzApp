<?php

namespace App\Services\Reportes;

use App\Models\Categoria;
use App\Models\FuenteIngreso;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\Usuario;
use Carbon\CarbonImmutable;

/**
 * MOD-11 — Reportes y Dashboards Dinámicos
 * HU-H04: Servicio de Flujo de Caja Histórico Agrupado (RF-066).
 *
 * Cumple con CA-066.1: Flujo neto mensual coincide con el Balance del Dashboard.
 */
class FlujoCajaService
{
    public const AGRUPACIONES = ['semanal', 'mensual', 'trimestral', 'anual'];

    /**
     * Resuelve el rango de fechas y agrupación.
     *
     * @return array{desde: CarbonImmutable, hasta: CarbonImmutable, agrupacion: string}
     */
    public function resolverParametros(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        ?string $agrupacion = 'mensual',
        ?CarbonImmutable $referencia = null
    ): array {
        $hoy = $referencia ?? CarbonImmutable::today();

        $agrupacion = in_array($agrupacion, self::AGRUPACIONES, true) ? $agrupacion : 'mensual';

        if ($fechaDesde && $fechaHasta) {
            $desde = CarbonImmutable::parse($fechaDesde)->startOfDay();
            $hasta = CarbonImmutable::parse($fechaHasta)->endOfDay();
        } else {
            // Por defecto últimos 6 meses completos incluyendo el actual
            $desde = $hoy->subMonthsNoOverflow(5)->startOfMonth();
            $hasta = $hoy->endOfMonth();
        }

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'agrupacion' => $agrupacion,
        ];
    }

    /**
     * Genera la lista de intervalos según la agrupación.
     *
     * @return array<int, array{clave: string, etiqueta: string, inicio: CarbonImmutable, fin: CarbonImmutable}>
     */
    public function generarIntervalos(CarbonImmutable $desde, CarbonImmutable $hasta, string $agrupacion): array
    {
        $intervalos = [];
        $cursor = $desde;

        while ($cursor->lte($hasta)) {
            switch ($agrupacion) {
                case 'semanal':
                    $inicio = $cursor->startOfWeek();
                    $fin = $cursor->endOfWeek();
                    $clave = $inicio->format('o-\WW');
                    $etiqueta = 'Sem ' . $inicio->isoWeek . ' (' . $inicio->format('d/m') . ' - ' . $fin->format('d/m') . ')';
                    $cursor = $inicio->addWeek();
                    break;

                case 'trimestral':
                    $inicio = $cursor->firstOfQuarter()->startOfDay();
                    $fin = $cursor->lastOfQuarter()->endOfDay();
                    $clave = $inicio->year . '-T' . $inicio->quarter;
                    $etiqueta = 'T' . $inicio->quarter . ' ' . $inicio->year;
                    $cursor = $fin->addDay();
                    break;

                case 'anual':
                    $inicio = $cursor->startOfYear();
                    $fin = $cursor->endOfYear();
                    $clave = (string) $inicio->year;
                    $etiqueta = 'Año ' . $inicio->year;
                    $cursor = $inicio->addYear();
                    break;

                case 'mensual':
                default:
                    $inicio = $cursor->startOfMonth();
                    $fin = $cursor->endOfMonth();
                    $clave = $inicio->format('Y-m');
                    $etiqueta = ucfirst($inicio->translatedFormat('M Y'));
                    $cursor = $inicio->addMonthNoOverflow();
                    break;
            }

            $intervalos[$clave] = [
                'clave' => $clave,
                'etiqueta' => $etiqueta,
                'inicio' => $inicio,
                'fin' => $fin,
            ];
        }

        return array_values($intervalos);
    }

    /**
     * Genera el reporte completo de flujo de caja con gráficos y matriz desglosada.
     */
    public function consultarFlujoCaja(
        Usuario $usuario,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        ?string $agrupacion = 'mensual',
        ?CarbonImmutable $referencia = null
    ): array {
        $params = $this->resolverParametros($fechaDesde, $fechaHasta, $agrupacion, $referencia);
        $desde = $params['desde'];
        $hasta = $params['hasta'];
        $agrupacion = $params['agrupacion'];

        $intervalos = $this->generarIntervalos($desde, $hasta, $agrupacion);

        // Consultar ingresos y fuentes en el periodo total
        $ingresos = Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha_recepcion', [$desde->toDateString(), $hasta->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->with('fuente')
            ->get();

        // Consultar gastos y categorías en el periodo total
        $gastos = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->with('categoria')
            ->get();

        // Catálogos presentes en la consulta
        $fuentesMap = [];
        foreach ($ingresos as $ing) {
            $fId = $ing->fuente_id ?? 'sin_fuente';
            $fNombre = $ing->fuente?->nombre ?? 'Otras fuentes';
            $fuentesMap[$fId] = $fNombre;
        }

        $categoriasMap = [];
        foreach ($gastos as $gas) {
            $cId = $gas->categoria_id ?? 'sin_categoria';
            $cNombre = $gas->categoria?->nombre ?? 'Otras categorías';
            $categoriasMap[$cId] = $cNombre;
        }

        // Estructura por intervalo
        $filasPeriodos = [];
        $flujoAcumulado = '0';

        $totalIngresosPeriodo = '0';
        $totalGastosPeriodo = '0';

        foreach ($intervalos as $int) {
            $fInicioStr = $int['inicio']->toDateString();
            $fFinStr = $int['fin']->toDateString();

            // Filtrar ingresos del intervalo
            $ingresosInt = $ingresos->filter(function ($i) use ($fInicioStr, $fFinStr) {
                $f = $i->fecha_recepcion->toDateString();
                return $f >= $fInicioStr && $f <= $fFinStr;
            });

            $desgloseIngresos = [];
            $totalIngresosInt = '0';
            foreach ($fuentesMap as $fId => $fNom) {
                $montoFuente = (string) $ingresosInt->where('fuente_id', $fId)->sum('monto_neto_cop');
                $desgloseIngresos[$fId] = $montoFuente;
                $totalIngresosInt = bcadd($totalIngresosInt, $montoFuente, 2);
            }

            // Filtrar gastos del intervalo
            $gastosInt = $gastos->filter(function ($g) use ($fInicioStr, $fFinStr) {
                $f = $g->fecha->toDateString();
                return $f >= $fInicioStr && $f <= $fFinStr;
            });

            $desgloseGastos = [];
            $totalGastosInt = '0';
            foreach ($categoriasMap as $cId => $cNom) {
                $montoCat = (string) $gastosInt->where('categoria_id', $cId)->sum('monto_cop');
                $desgloseGastos[$cId] = $montoCat;
                $totalGastosInt = bcadd($totalGastosInt, $montoCat, 2);
            }

            $flujoNetoInt = bcsub($totalIngresosInt, $totalGastosInt, 2);
            $flujoAcumulado = bcadd($flujoAcumulado, $flujoNetoInt, 2);

            $totalIngresosPeriodo = bcadd($totalIngresosPeriodo, $totalIngresosInt, 2);
            $totalGastosPeriodo = bcadd($totalGastosPeriodo, $totalGastosInt, 2);

            $filasPeriodos[] = [
                'clave' => $int['clave'],
                'etiqueta' => $int['etiqueta'],
                'inicio' => $fInicioStr,
                'fin' => $fFinStr,
                'ingresos_por_fuente' => $desgloseIngresos,
                'gastos_por_categoria' => $desgloseGastos,
                'total_ingresos' => $totalIngresosInt,
                'total_gastos' => $totalGastosInt,
                'flujo_neto' => $flujoNetoInt,
                'flujo_acumulado' => $flujoAcumulado,
                'es_negativo' => bccomp($flujoNetoInt, '0', 2) === -1,
            ];
        }

        $balancePeriodo = bcsub($totalIngresosPeriodo, $totalGastosPeriodo, 2);

        // Series para Chart.js combinado (Barras Flujo Neto + Línea Flujo Acumulado)
        $chartLabels = [];
        $chartFlujoNeto = [];
        $chartFlujoAcumulado = [];
        $chartColoresBarras = [];

        foreach ($filasPeriodos as $fila) {
            $chartLabels[] = $fila['etiqueta'];
            $valNeto = (float) $fila['flujo_neto'];
            $chartFlujoNeto[] = $valNeto;
            $chartFlujoAcumulado[] = (float) $fila['flujo_acumulado'];
            // Resaltar en rojo los periodos negativos
            $chartColoresBarras[] = $valNeto < 0 ? '#ef4444' : '#10b981';
        }

        $graficoCombinado = [
            'labels' => $chartLabels,
            'flujo_neto' => $chartFlujoNeto,
            'flujo_acumulado' => $chartFlujoAcumulado,
            'colores_barras' => $chartColoresBarras,
        ];

        return [
            'parametros' => [
                'fecha_desde' => $desde->format('Y-m-d'),
                'fecha_hasta' => $hasta->format('Y-m-d'),
                'agrupacion' => $agrupacion,
                'rango_texto' => 'Del ' . $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y'),
            ],
            'catalogo_fuentes' => $fuentesMap,
            'catalogo_categorias' => $categoriasMap,
            'periodos' => $filasPeriodos,
            'totales' => [
                'total_ingresos' => $totalIngresosPeriodo,
                'total_gastos' => $totalGastosPeriodo,
                'balance_neto' => $balancePeriodo,
                'flujo_acumulado_final' => $flujoAcumulado,
            ],
            'grafico' => $graficoCombinado,
            'nota_explicativa' => 'El flujo neto refleja la diferencia entre los ingresos netos confirmados y los gastos confirmados del periodo. El flujo acumulado representa la trayectoria de liquidez a lo largo del tiempo.',
        ];
    }
}
