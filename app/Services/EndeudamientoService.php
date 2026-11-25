<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\CuotaCredito;
use App\Models\Ingreso;
use App\Models\Scopes\DelUsuarioAutenticado;
use Carbon\CarbonImmutable;

/**
 * MOD-08 — RF-053: indicador de endeudamiento del mes.
 *
 * Entrega los datos del cálculo, no su presentación: el semáforo, los
 * umbrales y los textos los maneja el frontend.
 */
class EndeudamientoService
{
    /**
     * RN-053.1: Σ pagos del mes de los créditos / promedio de ingresos netos
     * de los últimos meses completos con ingresos × 100 (ver
     * baseIngresos: H08 usa el mes en curso si los cerrados no bastan). RN-053.3: sin ingresos en esos
     * meses el indicador no es calculable (null).
     *
     * @return array<string, mixed>
     */
    public static function calcular(string $usuarioId, ?CarbonImmutable $hoy = null): array
    {
        $mes = ($hoy ?? CarbonImmutable::today())->startOfMonth();
        $pagos = self::pagosDelMes($usuarioId, $mes);
        $ingresos = self::baseIngresos($usuarioId, $mes);

        $indicador = null;
        if (bccomp($ingresos['promedio'], '0', 0) === 1) {
            $fraccion = bcdiv(bcmul($pagos['total'], '100', 10), $ingresos['promedio'], 10);
            $indicador = bcadd($fraccion, '0.005', 2);
        }

        return [
            'periodo' => $mes->format('Y-m'),
            'pagos_mes' => $pagos,
            'ingresos_netos' => $ingresos,
            'indicador_porc' => $indicador,
        ];
    }

    /**
     * H09: ÚNICA definición de "ingreso promedio" de la aplicación (Presupuestos, Notificaciones,
     * Reportes, Créditos): el promedio de baseIngresos (meses cerrados con ingresos, o el mes en
     * curso si no bastan), al peso.
     */
    public static function ingresoPromedio(string $usuarioId, CarbonImmutable $mes): string
    {
        return self::baseIngresos($usuarioId, $mes)['promedio'];
    }

    /**
     * H08 (decisión: usar el mes en curso): denominador del indicador. Si menos de 2 de los
     * últimos 3 meses cerrados tienen ingresos, o su promedio es 0, se usa el ingreso neto del
     * mes en curso (si lo hay). `base` indica cuál se usó: MESES_CERRADOS o MES_EN_CURSO.
     *
     * @return array{base: string, meses_cerrados_con_ingresos: int, meses: array<int, array{mes: string, ingreso_neto: string}>, total: string, promedio: string}
     */
    public static function baseIngresos(string $usuarioId, CarbonImmutable $mes): array
    {
        $cerrados = self::ingresosUltimosMesesConIngresos($usuarioId, $mes);
        $ventana = self::ingresosNetosPorMes($usuarioId, $mes)['meses'];
        $conIngresos = count(array_filter($ventana, fn (array $m) => bccomp($m['ingreso_neto'], '0', 0) === 1));

        if ($conIngresos >= 2 && bccomp($cerrados['promedio'], '0', 0) === 1) {
            return ['base' => 'MESES_CERRADOS', 'meses_cerrados_con_ingresos' => $conIngresos] + $cerrados;
        }

        $inicio = $mes->startOfMonth();
        $actual = CreditoService::alPeso((string) Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'CONFIRMADO')
            ->whereBetween('fecha_recepcion', [$inicio->toDateString(), $inicio->endOfMonth()->toDateString()])
            ->sum('monto_neto_cop'));

        if (bccomp($actual, '0', 0) !== 1) {
            return ['base' => 'MESES_CERRADOS', 'meses_cerrados_con_ingresos' => $conIngresos] + $cerrados;
        }

        return [
            'base' => 'MES_EN_CURSO',
            'meses_cerrados_con_ingresos' => $conIngresos,
            'meses' => [['mes' => $inicio->format('Y-m'), 'ingreso_neto' => $actual]],
            'total' => $actual,
            'promedio' => $actual,
        ];
    }

    /**
     * RN-053.1: cuotas del plan vigente (con seguros y cargos) que vencen en
     * el mes, por crédito. Un crédito sin cuotas en el mes (pagado, o de
     * periodicidad mayor que no vence este mes) no aparece.
     *
     * @return array{creditos: array<int, array<string, mixed>>, total: string}
     */
    public static function pagosDelMes(string $usuarioId, CarbonImmutable $mes): array
    {
        $desde = $mes->startOfMonth()->toDateString();
        $hasta = $mes->endOfMonth()->toDateString();

        $creditos = Credito::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->with(['cuotasVigentes' => fn ($q) => $q
                ->withoutGlobalScope(DelUsuarioAutenticado::class)
                ->whereBetween('fecha_vencimiento', [$desde, $hasta])])
            ->get()
            ->filter(fn (Credito $c) => $c->cuotasVigentes->isNotEmpty())
            ->sortBy(fn (Credito $c) => $c->cuotasVigentes->first()->fecha_vencimiento->toDateString())
            ->values();

        $total = '0';
        $filas = $creditos->map(function (Credito $c) use (&$total) {
            $cuota = $c->cuotasVigentes->reduce(fn (string $t, CuotaCredito $q) => bcadd($t, (string) $q->cuota, 2), '0');
            $segurosCargos = $c->cuotasVigentes->reduce(
                fn (string $t, CuotaCredito $q) => bcadd($t, bcadd((string) $q->seguro, (string) $q->cargos, 2), 2),
                '0'
            );
            $totalCredito = bcadd($cuota, $segurosCargos, 0);
            $total = bcadd($total, $totalCredito, 0);

            return [
                'credito_id' => $c->id,
                'banco' => $c->banco,
                'proposito' => $c->proposito,
                'tipo_credito' => $c->tipo_credito,
                'numero_cuotas' => $c->cuotasVigentes->count(),
                'cuota' => PagoCreditoService::peso($cuota),
                'seguros_cargos' => PagoCreditoService::peso($segurosCargos),
                'total' => $totalCredito,
            ];
        })->all();

        return ['creditos' => $filas, 'total' => $total];
    }

    /**
     * RN-053.1 / Créditos RN-03: ingreso neto (bruto − retención − aportes,
     * RN-021.2) de los ingresos confirmados en cada uno de los últimos meses
     * completos antes de $mes. Un mes sin ingresos vale 0 y cuenta en el
     * promedio, que se redondea al peso. Lo usan el presupuesto (RN-055.4)
     * y las alertas de capacidad (Créditos RN-03 / RN-07); el indicador de
     * endeudamiento usa ingresosUltimosMesesConIngresos.
     *
     * @return array{meses: array<int, array{mes: string, ingreso_neto: string}>, total: string, promedio: string}
     */
    public static function ingresosNetosPorMes(string $usuarioId, CarbonImmutable $mes): array
    {
        $cantidad = (int) config('scfp.creditos.meses_promedio_ingresos');
        $inicio = $mes->startOfMonth();
        $desde = $inicio->subMonthsNoOverflow($cantidad);

        $porMes = Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'CONFIRMADO')
            ->whereBetween('fecha_recepcion', [$desde->toDateString(), $inicio->subDay()->toDateString()])
            ->selectRaw("to_char(fecha_recepcion, 'YYYY-MM') AS mes, COALESCE(SUM(monto_neto_cop), 0) AS neto")
            ->groupByRaw("to_char(fecha_recepcion, 'YYYY-MM')")
            ->pluck('neto', 'mes');

        $meses = [];
        $total = '0';
        for ($k = 0; $k < $cantidad; $k++) {
            $clave = $desde->addMonthsNoOverflow($k)->format('Y-m');
            // Los ingresos en USD dejan centavos: redondeo al peso, mitad hacia arriba.
            $neto = CreditoService::alPeso((string) ($porMes[$clave] ?? '0'));
            $meses[] = ['mes' => $clave, 'ingreso_neto' => $neto];
            $total = bcadd($total, $neto, 0);
        }

        return [
            'meses' => $meses,
            'total' => $total,
            'promedio' => CreditoService::alPeso(bcdiv($total, (string) $cantidad, 10)),
        ];
    }

    /**
     * RF-053 / RN-053.1 (decisión del equipo): ingreso neto (RN-021.2) de los
     * últimos meses completos que tienen ingresos confirmados, hasta 3,
     * buscando hacia atrás desde el mes anterior a $mes y como máximo 12
     * meses (config scfp.creditos.meses_busqueda_ingresos). El mes en curso
     * no cuenta. El promedio divide entre los meses encontrados y se
     * redondea al peso; sin meses es 0 (RN-053.3).
     *
     * @return array{meses: array<int, array{mes: string, ingreso_neto: string}>, total: string, promedio: string}
     */
    public static function ingresosUltimosMesesConIngresos(string $usuarioId, CarbonImmutable $mes): array
    {
        $cantidad = (int) config('scfp.creditos.meses_promedio_ingresos');
        $busqueda = (int) config('scfp.creditos.meses_busqueda_ingresos');
        $inicio = $mes->startOfMonth();

        $porMes = Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'CONFIRMADO')
            ->whereBetween('fecha_recepcion', [
                $inicio->subMonthsNoOverflow($busqueda)->toDateString(),
                $inicio->subDay()->toDateString(),
            ])
            ->selectRaw("to_char(fecha_recepcion, 'YYYY-MM') AS mes, SUM(monto_neto_cop) AS neto")
            ->groupByRaw("to_char(fecha_recepcion, 'YYYY-MM')")
            ->havingRaw('SUM(monto_neto_cop) > 0')
            ->orderByDesc('mes')
            ->limit($cantidad)
            ->pluck('neto', 'mes')
            ->sortKeys();

        $meses = [];
        $total = '0';
        foreach ($porMes as $clave => $valor) {
            // Los ingresos en USD dejan centavos: redondeo al peso, mitad hacia arriba.
            $neto = CreditoService::alPeso((string) $valor);
            $meses[] = ['mes' => $clave, 'ingreso_neto' => $neto];
            $total = bcadd($total, $neto, 0);
        }

        return [
            'meses' => $meses,
            'total' => $total,
            'promedio' => $meses === [] ? '0' : CreditoService::alPeso(bcdiv($total, (string) count($meses), 10)),
        ];
    }
}
