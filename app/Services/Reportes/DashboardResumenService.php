<?php

namespace App\Services\Reportes;

use App\Models\Categoria;
use App\Models\Credito;
use App\Models\CuentaBancaria;
use App\Models\CuotaCredito;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\PlantillaRecurrencia;
use App\Models\Presupuesto;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\Usuario;
use App\Services\EndeudamientoService;
use App\Services\PagoCreditoService;
use App\Services\PresupuestoService;
use App\Services\RentabilidadService;
use App\Services\SaldoCuentaService;
use Carbon\CarbonImmutable;

/**
 * MOD-11 — Reportes y Dashboards Dinámicos
 * HU-H01 y HU-H02: Servicio de Consolidación del Dashboard Principal (Resumen) y KPIs (RF-065).
 *
 * Aplica aritmética precisa con bcmath para moneda COP (RNG-02).
 */
class DashboardResumenService
{
    /**
     * Resuelve los límites de fechas del periodo seleccionado y su periodo anterior equivalente (RN-065.1, RN-065.5).
     *
     * @return array{
     *     periodo: string,
     *     desde: CarbonImmutable,
     *     hasta: CarbonImmutable,
     *     desde_anterior: CarbonImmutable,
     *     hasta_anterior: CarbonImmutable,
     *     dias: int,
     *     etiqueta: string
     * }
     */
    public function resolverPeriodo(
        string $periodo = 'mes-actual',
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        ?CarbonImmutable $referencia = null
    ): array {
        $hoy = $referencia ?? CarbonImmutable::today();

        switch ($periodo) {
            case 'mes-anterior':
                $desde = $hoy->subMonthNoOverflow()->startOfMonth();
                $hasta = $hoy->subMonthNoOverflow()->endOfMonth();
                $desdeAnterior = $desde->subMonthNoOverflow()->startOfMonth();
                $hastaAnterior = $desde->subMonthNoOverflow()->endOfMonth();
                $etiqueta = 'Mes anterior ('.$desde->translatedFormat('F Y').')';
                break;

            case 'trimestre':
                $desde = $hoy->firstOfQuarter()->startOfDay();
                $hasta = $hoy->lastOfQuarter()->endOfDay();
                $desdeAnterior = $desde->subMonthsNoOverflow(3)->firstOfQuarter()->startOfDay();
                $hastaAnterior = $desde->subMonthsNoOverflow(3)->lastOfQuarter()->endOfDay();
                $etiqueta = 'Trimestre '.$hoy->quarter.' de '.$hoy->year;
                break;

            case 'ano':
                $desde = $hoy->startOfYear();
                $hasta = $hoy->endOfYear();
                $desdeAnterior = $desde->subYearNoOverflow()->startOfYear();
                $hastaAnterior = $desde->subYearNoOverflow()->endOfYear();
                $etiqueta = 'Año '.$hoy->year;
                break;

            case 'personalizado':
                if ($fechaDesde && $fechaHasta) {
                    $desde = CarbonImmutable::parse($fechaDesde)->startOfDay();
                    $hasta = CarbonImmutable::parse($fechaHasta)->endOfDay();
                    $dias = (int) $desde->startOfDay()->diffInDays($hasta->startOfDay()) + 1;
                    $hastaAnterior = $desde->subDay()->endOfDay();
                    $desdeAnterior = $hastaAnterior->subDays($dias - 1)->startOfDay();
                    $etiqueta = 'Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y');
                    break;
                }
                // Si faltan fechas, cae por defecto en mes actual
                // no break
            case 'mes-actual':
            default:
                $periodo = 'mes-actual';
                $desde = $hoy->startOfMonth();
                $hasta = $hoy->endOfMonth();
                $desdeAnterior = $desde->subMonthNoOverflow()->startOfMonth();
                $hastaAnterior = $desde->subMonthNoOverflow()->endOfMonth();
                $etiqueta = 'Mes actual ('.$desde->translatedFormat('F Y').')';
                break;
        }

        $dias = (int) $desde->startOfDay()->diffInDays($hasta->startOfDay()) + 1;

        return [
            'periodo' => $periodo,
            'desde' => $desde,
            'hasta' => $hasta,
            'desde_anterior' => $desdeAnterior,
            'hasta_anterior' => $hastaAnterior,
            'dias' => $dias,
            'etiqueta' => $etiqueta,
        ];
    }

    /**
     * Consulta cuotas de crédito y gastos recurrentes a vencer en los próximos 15 días (RN-065.2).
     *
     * @return array<int, array{
     *     tipo: string,
     *     titulo: string,
     *     monto: string,
     *     fecha_vencimiento: string,
     *     dias_restantes: int,
     *     es_estimado: bool,
     *     enlace_accion: string,
     *     origen_id: string
     * }>
     */
    public function obtenerProximos15Dias(Usuario $usuario, ?CarbonImmutable $hoy = null): array
    {
        $hoy = $hoy ?? CarbonImmutable::today();
        $limite = $hoy->addDays(15)->endOfDay();
        $items = [];

        // 1. Cuotas de crédito vigentes pendientes
        $cuotas = CuotaCredito::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereNull('reemplazada_en_version')
            ->whereBetween('fecha_vencimiento', [$hoy->toDateString(), $limite->toDateString()])
            ->where('saldo_final', '>', 0)
            ->with('credito')
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();

        foreach ($cuotas as $cuota) {
            $fechaVenc = CarbonImmutable::parse($cuota->fecha_vencimiento);
            $nombreCredito = $cuota->credito?->nombre_personalizado ?? 'Crédito';
            $items[] = [
                'tipo' => 'CREDITO',
                'titulo' => "Cuota #{$cuota->numero} · {$nombreCredito}",
                'monto' => (string) $cuota->cuota,
                'fecha_vencimiento' => $fechaVenc->format('Y-m-d'),
                'dias_restantes' => (int) $hoy->diffInDays($fechaVenc, false),
                'es_estimado' => false,
                'enlace_accion' => route('creditos.show', ['id' => $cuota->credito_id]),
                'origen_id' => (string) $cuota->id,
            ];
        }

        // 2. Gastos recurrentes programados activos
        $recurrentes = PlantillaRecurrencia::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->where('tipo_movimiento', 'GASTO')
            ->where('activa', true)
            ->whereBetween('proxima_ejecucion', [$hoy->toDateString(), $limite->toDateString()])
            ->orderBy('proxima_ejecucion', 'asc')
            ->get();

        foreach ($recurrentes as $rec) {
            $fechaProx = CarbonImmutable::parse($rec->proxima_ejecucion);
            $datos = is_array($rec->datos_plantilla) ? $rec->datos_plantilla : json_decode($rec->datos_plantilla ?? '[]', true);
            $titulo = $datos['descripcion'] ?? $datos['comercio_beneficiario'] ?? 'Gasto recurrente';
            $monto = (string) ($datos['monto_cop'] ?? $datos['monto'] ?? '0');

            $items[] = [
                'tipo' => 'GASTO_RECURRENTE',
                'titulo' => $titulo,
                'monto' => $monto,
                'fecha_vencimiento' => $fechaProx->format('Y-m-d'),
                'dias_restantes' => (int) $hoy->diffInDays($fechaProx, false),
                'es_estimado' => (bool) $rec->es_monto_estimado,
                'enlace_accion' => route('gastos.recurrentes.index'),
                'origen_id' => (string) $rec->id,
            ];
        }

        usort($items, fn ($a, $b) => strcmp($a['fecha_vencimiento'], $b['fecha_vencimiento']));

        return $items;
    }

    /**
     * Calcula los totales consolidados de ingresos netos y gastos en un rango de fechas.
     *
     * @return array{
     *     ingresos_netos: string,
     *     gastos: string,
     *     balance: string,
     *     tasa_ahorro: ?float,
     *     conteo_ingresos: int,
     *     conteo_gastos: int
     * }
     */
    public function calcularTotales(Usuario $usuario, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $desdeStr = $desde->toDateString();
        $hastaStr = $hasta->toDateString();

        // Ingresos confirmados (RN-021.2: monto_neto_cop)
        $ingresosQuery = Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha_recepcion', [$desdeStr, $hastaStr])
            ->where('estado', 'CONFIRMADO');

        $ingresosNetos = (string) ($ingresosQuery->sum('monto_neto_cop') ?? '0');
        $conteoIngresos = (int) $ingresosQuery->count();

        // Gastos confirmados (CA-065.1: coincide con la suma del listado)
        $gastosQuery = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desdeStr, $hastaStr])
            ->where('estado', 'CONFIRMADO');

        $gastos = (string) ($gastosQuery->sum('monto_cop') ?? '0');
        $conteoGastos = (int) $gastosQuery->count();

        $balance = bcsub($ingresosNetos, $gastos, 2);

        $tasaAhorro = null;
        if (bccomp($ingresosNetos, '0', 2) === 1) {
            $tasaAhorro = round((float) bcdiv(bcmul($balance, '100', 6), $ingresosNetos, 4), 2);
        }

        return [
            'ingresos_netos' => $ingresosNetos,
            'gastos' => $gastos,
            'balance' => $balance,
            'tasa_ahorro' => $tasaAhorro,
            'conteo_ingresos' => $conteoIngresos,
            'conteo_gastos' => $conteoGastos,
        ];
    }

    /**
     * Calcula la variación porcentual y absoluta entre dos periodos (RN-065.5).
     *
     * @return array{
     *     actual: string,
     *     anterior: string,
     *     variacion_absoluta: string,
     *     variacion_porc: ?float,
     *     direccion: string,
     *     signo: string
     * }
     */
    public function calcularVariacion(string $actual, string $anterior): array
    {
        $diff = bcsub($actual, $anterior, 2);
        $signo = '+';
        $direccion = 'igual';

        $comp = bccomp($diff, '0', 2);
        if ($comp === 1) {
            $signo = '+';
            $direccion = 'subio';
        } elseif ($comp === -1) {
            $signo = '-';
            $direccion = 'bajo';
        }

        $porc = null;
        if (bccomp($anterior, '0', 2) !== 0) {
            $porc = round((float) bcdiv(bcmul($diff, '100', 6), $anterior, 4), 2);
        }

        return [
            'actual' => $actual,
            'anterior' => $anterior,
            'variacion_absoluta' => $diff,
            'variacion_porc' => $porc,
            'direccion' => $direccion,
            'signo' => $signo,
        ];
    }

    /**
     * Obtiene el consolidado completo del Dashboard y sus 7 tarjetas KPI (HU-H01).
     */
    public function obtenerDashboard(
        Usuario $usuario,
        string $periodo = 'mes-actual',
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        ?CarbonImmutable $referencia = null
    ): array {
        $p = $this->resolverPeriodo($periodo, $fechaDesde, $fechaHasta, $referencia);
        $hoy = $referencia ?? CarbonImmutable::today();

        $totales = $this->calcularTotales($usuario, $p['desde'], $p['hasta']);
        $totalesAnt = $this->calcularTotales($usuario, $p['desde_anterior'], $p['hasta_anterior']);

        // 1. Ingresos netos
        $kpiIngresos = [
            'clave' => 'ingresos_netos',
            'nombre' => 'Ingresos netos',
            'valor' => $totales['ingresos_netos'],
            'variacion' => $this->calcularVariacion($totales['ingresos_netos'], $totalesAnt['ingresos_netos']),
            'enlace' => route('ingresos.index'),
            'icono' => 'act-ingreso',
        ];

        // 2. Gastos
        $kpiGastos = [
            'clave' => 'gastos',
            'nombre' => 'Gastos',
            'valor' => $totales['gastos'],
            'variacion' => $this->calcularVariacion($totales['gastos'], $totalesAnt['gastos']),
            'enlace' => route('gastos.index'),
            'icono' => 'act-gasto',
        ];

        // 3. Balance
        $kpiBalance = [
            'clave' => 'balance',
            'nombre' => 'Balance',
            'valor' => $totales['balance'],
            'variacion' => $this->calcularVariacion($totales['balance'], $totalesAnt['balance']),
            'enlace' => route('reportes.flujo-de-caja'),
            'icono' => 'ui-resumen',
        ];

        // 4. Tasa de ahorro
        $tasaActualStr = $totales['tasa_ahorro'] !== null ? (string) $totales['tasa_ahorro'] : '0';
        $tasaAntStr = $totalesAnt['tasa_ahorro'] !== null ? (string) $totalesAnt['tasa_ahorro'] : '0';
        $kpiAhorro = [
            'clave' => 'tasa_ahorro',
            'nombre' => 'Tasa de ahorro',
            'valor' => $totales['tasa_ahorro'],
            'valor_formateado' => $totales['tasa_ahorro'] !== null ? "{$totales['tasa_ahorro']}%" : 'N/A',
            'variacion' => $this->calcularVariacion($tasaActualStr, $tasaAntStr),
            'enlace' => route('reportes.indicadores'),
            'icono' => 'cat-seguros',
        ];

        // 5. Ejecución del presupuesto
        $mesPresupuesto = $p['desde']->format('Y-m');
        $presupuesto = Presupuesto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->where('mes', "{$mesPresupuesto}-01")
            ->first();

        $porcPresupuesto = null;
        $asignadoPresupuesto = '0';
        $gastadoPresupuesto = '0';
        if ($presupuesto) {
            $ejecucion = PresupuestoService::ejecucion($presupuesto, $hoy);
            $asignadoPresupuesto = (string) ($ejecucion['totales']['monto_asignado'] ?? '0');
            $gastadoPresupuesto = (string) ($ejecucion['totales']['gastado'] ?? '0');
            if (bccomp($asignadoPresupuesto, '0', 2) === 1) {
                $porcPresupuesto = round((float) bcdiv(bcmul($gastadoPresupuesto, '100', 6), $asignadoPresupuesto, 6), 2);
            }
        }

        $kpiPresupuesto = [
            'clave' => 'ejecucion_presupuesto',
            'nombre' => 'Ejecución del presupuesto',
            'valor' => $porcPresupuesto,
            'valor_formateado' => $porcPresupuesto !== null ? "{$porcPresupuesto}%" : 'Sin presupuesto',
            'asignado' => $asignadoPresupuesto,
            'gastado' => $gastadoPresupuesto,
            'enlace' => route('presupuestos.show', ['mes' => $mesPresupuesto]),
            'icono' => 'ui-categorias',
        ];

        // 6. Endeudamiento con semáforo (EndeudamientoService)
        $endeudamientoData = EndeudamientoService::calcular($usuario->id, $p['hasta']);
        $indicadorEndeudamiento = $endeudamientoData['indicador_porc'];
        $semaforoEndeudamiento = 'VERDE';
        if ($indicadorEndeudamiento !== null) {
            $valEndeud = (float) $indicadorEndeudamiento;
            if ($valEndeud > 40.0) {
                $semaforoEndeudamiento = 'ROJO';
            } elseif ($valEndeud > 30.0) {
                $semaforoEndeudamiento = 'AMARILLO';
            }
        }

        $kpiEndeudamiento = [
            'clave' => 'endeudamiento',
            'nombre' => 'Endeudamiento',
            'valor' => $indicadorEndeudamiento,
            'valor_formateado' => $indicadorEndeudamiento !== null ? "{$indicadorEndeudamiento}%" : 'Sin deuda',
            'semaforo' => $semaforoEndeudamiento,
            'pagos_mes' => $endeudamientoData['pagos_mes']['total'] ?? '0',
            'enlace' => route('creditos.endeudamiento'),
            'icono' => 'cat-obligaciones-financieras',
        ];

        // 7. Portafolio de inversiones: mismo consolidado que el módulo Inversiones (H05).
        // La fecha de corte no puede ser posterior a hoy (RF-041), como en Inversiones.
        $corteInversiones = $p['hasta']->startOfDay()->greaterThan($hoy->startOfDay()) ? $hoy->startOfDay() : $p['hasta']->startOfDay();
        $portafolioData = RentabilidadService::portafolio($corteInversiones);
        $valorPortafolio = (string) ($portafolioData['valor_total'] ?? '0');
        $kpiPortafolio = [
            'clave' => 'portafolio',
            'nombre' => 'Portafolio de inversiones',
            'valor' => $valorPortafolio,
            'inversiones_activas' => (int) ($portafolioData['cantidad_inversiones'] ?? 0),
            'rendimiento_estimado' => $portafolioData['ganancia'] ?? '0',
            'enlace' => route('inversiones.portafolio'),
            'icono' => 'src-rendimientos-financieros',
        ];

        // Alerta de balance negativo y causas de incremento
        $alertaDeficit = null;
        if (bccomp($totales['gastos'], $totales['ingresos_netos'], 2) === 1) {
            $deficit = bcsub($totales['gastos'], $totales['ingresos_netos'], 2);
            $categoriasCausantes = $this->obtenerCategoriasConMayorIncremento($usuario, $p['desde'], $p['hasta'], $p['desde_anterior'], $p['hasta_anterior']);

            $alertaDeficit = [
                'deficit' => $deficit,
                'categorias_incremento' => $categoriasCausantes,
                'enlace_comparativo' => route('reportes.comparativo', [
                    'desde_a' => $p['desde_anterior']->format('Y-m-d'),
                    'hasta_a' => $p['hasta_anterior']->format('Y-m-d'),
                    'desde_b' => $p['desde']->format('Y-m-d'),
                    'hasta_b' => $p['hasta']->format('Y-m-d'),
                ]),
            ];
        }

        // Obligaciones de los próximos 15 días
        $proximos15Dias = $this->obtenerProximos15Dias($usuario, $hoy);

        // Gráficos de comportamiento financiero (HU-H02)
        $graficos = $this->obtenerGraficosResumen($usuario, $p['desde'], $p['hasta'], $hoy);

        return [
            'cuentas' => $this->obtenerCuentas($usuario, $hoy),
            'periodo' => $p,
            'totales' => $totales,
            'totales_anterior' => $totalesAnt,
            'kpis' => [
                $kpiIngresos,
                $kpiGastos,
                $kpiBalance,
                $kpiAhorro,
                $kpiPresupuesto,
                $kpiEndeudamiento,
                $kpiPortafolio,
            ],
            'alerta_deficit' => $alertaDeficit,
            'proximos_15_dias' => $proximos15Dias,
            'graficos' => $graficos,
            'estados_vacios' => [
                'sin_ingresos' => $totales['conteo_ingresos'] === 0,
                'sin_gastos' => $totales['conteo_gastos'] === 0,
                'sin_movimientos' => ($totales['conteo_ingresos'] === 0 && $totales['conteo_gastos'] === 0),
            ],
        ];
    }

    /**
     * Cuentas de débito/ahorro con su saldo disponible y tarjetas de crédito con
     * cupo, usado (saldo de capital a hoy) y disponible. Va primero en el Dashboard.
     *
     * @return array{debito: array<int, array<string, mixed>>, tarjetas: array<int, array<string, mixed>>}
     */
    public function obtenerCuentas(Usuario $usuario, CarbonImmutable $hoy): array
    {
        $cuentas = CuentaBancaria::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)->with('entidad:id,nombre')->orderBy('creado_en')->get();
        $saldos = SaldoCuentaService::saldos($cuentas->pluck('id')->all());

        $debito = $cuentas->map(fn (CuentaBancaria $c) => [
            'id' => $c->id,
            'nombre' => trim($c->nombre_banco.($c->alias ? ' · '.$c->alias : '')),
            'saldo_disponible' => $saldos[$c->id] ?? '0.00',
            'exenta_gmf' => $c->es_exenta_gmf,
        ])->all();

        $tarjetas = Credito::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)->where('tipo_credito', 'TARJETA_CREDITO')
            ->whereNotNull('cupo')->orderBy('banco')->get()
            ->map(function (Credito $t) use ($hoy) {
                $cupo = bcadd((string) $t->cupo, '0', 2);
                $usado = (string) PagoCreditoService::situacion($t, $hoy)['saldo_capital'];
                $disponible = bcsub($cupo, $usado, 2);

                return [
                    'id' => $t->id,
                    'nombre' => $t->banco,
                    'cupo' => $cupo,
                    'usado' => bcadd($usado, '0', 2),
                    'disponible' => bccomp($disponible, '0', 2) === 1 ? $disponible : '0.00',
                    'enlace' => route('creditos.show', $t->id),
                ];
            })->all();

        return ['debito' => $debito, 'tarjetas' => $tarjetas];
    }

    /**
     * Encuentra las categorías cuyos gastos tuvieron mayor incremento en pesos frente al periodo anterior.
     *
     * @return array<int, array{categoria_id: string, nombre: string, aumento: string}>
     */
    public function obtenerCategoriasConMayorIncremento(
        Usuario $usuario,
        CarbonImmutable $desdeActual,
        CarbonImmutable $hastaActual,
        CarbonImmutable $desdeAnt,
        CarbonImmutable $hastaAnt,
        int $limite = 3
    ): array {
        // Gastos actuales por categoría
        $actuales = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desdeActual->toDateString(), $hastaActual->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->selectRaw('categoria_id, SUM(monto_cop) as total')
            ->groupBy('categoria_id')
            ->pluck('total', 'categoria_id')
            ->all();

        // Gastos anteriores por categoría
        $anteriores = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desdeAnt->toDateString(), $hastaAnt->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->selectRaw('categoria_id, SUM(monto_cop) as total')
            ->groupBy('categoria_id')
            ->pluck('total', 'categoria_id')
            ->all();

        $diferencias = [];
        foreach ($actuales as $catId => $totalActual) {
            $totalAnt = $anteriores[$catId] ?? '0';
            $diff = bcsub((string) $totalActual, (string) $totalAnt, 2);
            if (bccomp($diff, '0', 2) === 1) {
                $diferencias[$catId] = $diff;
            }
        }

        arsort($diferencias);
        $topIds = array_slice(array_keys($diferencias), 0, $limite);

        if (empty($topIds)) {
            return [];
        }

        $categorias = Categoria::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereIn('id', $topIds)
            ->pluck('nombre', 'id');

        $resultado = [];
        foreach ($topIds as $catId) {
            $resultado[] = [
                'categoria_id' => $catId,
                'nombre' => $categorias[$catId] ?? 'Categoría',
                'aumento' => $diferencias[$catId],
            ];
        }

        return $resultado;
    }

    /**
     * Genera las series de datos para los 4 gráficos interactivos del Resumen (HU-H02).
     *
     * @return array{
     *     evolucion_12_meses: array{labels: array<int, string>, ingresos: array<int, float>, gastos: array<int, float>, deficit_flags: array<int, bool>},
     *     categorias_top: array<int, array{categoria_id: ?string, nombre: string, total: string, porcentaje: float, enlace: string}>,
     *     fijo_vs_variable: array{labels: array<int, string>, data: array<int, float>, montos: array<int, string>},
     *     esencial_vs_discrecional: array{labels: array<int, string>, data: array<int, float>, montos: array<int, string>}
     * }
     */
    public function obtenerGraficosResumen(
        Usuario $usuario,
        CarbonImmutable $desde,
        CarbonImmutable $hasta,
        ?CarbonImmutable $referencia = null
    ): array {
        $hoy = $referencia ?? CarbonImmutable::today();

        // 1. Gráfico 1: Evolución de los últimos 12 meses
        $mesesLabels = [];
        $serieIngresos = [];
        $serieGastos = [];
        $deficitFlags = [];

        for ($i = 11; $i >= 0; $i--) {
            $mesInicio = $hoy->subMonthsNoOverflow($i)->startOfMonth();
            $mesFin = $mesInicio->endOfMonth();

            $tot = $this->calcularTotales($usuario, $mesInicio, $mesFin);
            $mesesLabels[] = ucfirst($mesInicio->translatedFormat('M Y'));
            $ing = (float) $tot['ingresos_netos'];
            $gas = (float) $tot['gastos'];

            $serieIngresos[] = $ing;
            $serieGastos[] = $gas;
            $deficitFlags[] = ($gas > $ing);
        }

        $evolucion12Meses = [
            'labels' => $mesesLabels,
            'ingresos' => $serieIngresos,
            'gastos' => $serieGastos,
            'deficit_flags' => $deficitFlags,
        ];

        // 2. Gráfico 2: Distribución de gastos por categoría (Top 8 + Otras)
        $gastosPorCategoria = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->selectRaw('categoria_id, SUM(monto_cop) as total')
            ->groupBy('categoria_id')
            ->orderByDesc('total')
            ->get();

        $totalGastosPeriodo = '0';
        foreach ($gastosPorCategoria as $g) {
            $totalGastosPeriodo = bcadd($totalGastosPeriodo, (string) $g->total, 2);
        }

        $catIds = $gastosPorCategoria->pluck('categoria_id')->filter()->all();
        $nombresCategorias = Categoria::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereIn('id', $catIds)
            ->pluck('nombre', 'id');

        $categoriasTop = [];
        $otrasMonto = '0';

        foreach ($gastosPorCategoria as $index => $g) {
            $monto = (string) $g->total;
            if ($index < 8) {
                $porc = bccomp($totalGastosPeriodo, '0', 2) === 1
                    ? round((float) bcdiv(bcmul($monto, '100', 6), $totalGastosPeriodo, 4), 2)
                    : 0.0;

                $categoriasTop[] = [
                    'categoria_id' => $g->categoria_id,
                    'nombre' => $nombresCategorias[$g->categoria_id] ?? 'Categoría',
                    'total' => $monto,
                    'porcentaje' => $porc,
                    'enlace' => route('reportes.gastos', ['categoria_id' => $g->categoria_id]),
                ];
            } else {
                $otrasMonto = bcadd($otrasMonto, $monto, 2);
            }
        }

        if (bccomp($otrasMonto, '0', 2) === 1) {
            $porcOtras = bccomp($totalGastosPeriodo, '0', 2) === 1
                ? round((float) bcdiv(bcmul($otrasMonto, '100', 6), $totalGastosPeriodo, 4), 2)
                : 0.0;

            $categoriasTop[] = [
                'categoria_id' => null,
                'nombre' => 'Otras categorías',
                'total' => $otrasMonto,
                'porcentaje' => $porcOtras,
                'enlace' => route('reportes.gastos'),
            ];
        }

        // 3. Gráficos tipo dona: Fijo vs. Variable y Esencial vs. Discrecional
        $porNaturaleza = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->selectRaw("COALESCE(naturaleza, 'VARIABLE') as nat, SUM(monto_cop) as total")
            ->groupBy('nat')
            ->pluck('total', 'nat')
            ->all();

        $montoFijo = (string) ($porNaturaleza['FIJO'] ?? '0');
        $montoVar = (string) ($porNaturaleza['VARIABLE'] ?? '0');

        $fijoVsVariable = [
            'labels' => ['Fijo', 'Variable'],
            'data' => [(float) $montoFijo, (float) $montoVar],
            'montos' => [$montoFijo, $montoVar],
        ];

        $porNecesidad = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuario->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('estado', 'CONFIRMADO')
            ->selectRaw("COALESCE(necesidad, 'DISCRECIONAL') as nec, SUM(monto_cop) as total")
            ->groupBy('nec')
            ->pluck('total', 'nec')
            ->all();

        $montoEsencial = (string) ($porNecesidad['ESENCIAL'] ?? '0');
        $montoDiscrecional = (string) ($porNecesidad['DISCRECIONAL'] ?? '0');

        $esencialVsDiscrecional = [
            'labels' => ['Esencial', 'Discrecional'],
            'data' => [(float) $montoEsencial, (float) $montoDiscrecional],
            'montos' => [$montoEsencial, $montoDiscrecional],
        ];

        return [
            'evolucion_12_meses' => $evolucion12Meses,
            'categorias_top' => $categoriasTop,
            'fijo_vs_variable' => $fijoVsVariable,
            'esencial_vs_discrecional' => $esencialVsDiscrecional,
        ];
    }
}
