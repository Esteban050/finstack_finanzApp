<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Credito;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\PlantillaRecurrencia;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Services\Renta\ConsolidadoRentaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * MOD-09 — RF-057 / RN-059.2: alertas financieras que no dependen del
 * presupuesto (pantalla 04 del mockup):
 *
 * - Ingresos frente a gastos: INGRESOS_VS_GASTOS, GASTO_PROYECTADO_ALTO y
 *   LIMITE_GASTO_MENSUAL.
 * - Hábitos de gasto: GASTO_INUSUAL, GASTOS_HORMIGA y GASTO_DISCRECIONAL_ALTO.
 * - Topes de la DIAN: TOPE_DIAN_TARJETA y TOPE_DIAN_COMPRAS (1.400 UVT del año).
 * - Obligaciones: ENDEUDAMIENTO (sube de franja) y VENCIMIENTOS (cuotas y
 *   recurrentes por confirmar).
 *
 * Cada notificación tiene una clave que no se repite (RN-057.2). Las cifras
 * van en `datos`, sin textos: el frontend los arma.
 */
class AlertaFinancieraService
{
    public static function evaluarUsuario(string $usuarioId, CarbonImmutable $hoy, string $momento = AlertaService::DIARIO, array $gastoIds = []): int
    {
        $reglas = ReglaAlertaService::vigentes($usuarioId);
        $mes = PresupuestoService::mesActual($hoy);
        $diario = $momento === AlertaService::DIARIO;
        $activa = fn (string $tipo) => $reglas[$tipo]['activa'];
        $emitidas = 0;

        if ($activa(ReglaAlertaService::INGRESOS_VS_GASTOS)) {
            $emitidas += self::ingresosVsGastos($usuarioId, $reglas[ReglaAlertaService::INGRESOS_VS_GASTOS], $mes, cierre: false);

            // Al cierre (el día 1 o cuando corra el job) se evalúa con los valores reales.
            if ($diario) {
                $emitidas += self::ingresosVsGastos($usuarioId, $reglas[ReglaAlertaService::INGRESOS_VS_GASTOS], $mes->subMonthNoOverflow(), cierre: true);
            }
        }

        if ($activa(ReglaAlertaService::GASTO_PROYECTADO_ALTO)) {
            $emitidas += self::gastoProyectadoAlto($usuarioId, $reglas[ReglaAlertaService::GASTO_PROYECTADO_ALTO], $mes, $hoy);
        }

        if ($activa(ReglaAlertaService::LIMITE_GASTO_MENSUAL)) {
            $emitidas += self::limiteMensual($usuarioId, $reglas[ReglaAlertaService::LIMITE_GASTO_MENSUAL], $mes);
        }

        if ($activa(ReglaAlertaService::GASTO_INUSUAL)) {
            $emitidas += self::gastoInusual($usuarioId, $reglas[ReglaAlertaService::GASTO_INUSUAL], $gastoIds);
        }

        if ($activa(ReglaAlertaService::GASTOS_HORMIGA)) {
            $emitidas += self::hormiga($usuarioId, $reglas[ReglaAlertaService::GASTOS_HORMIGA], $mes);
        }

        if ($activa(ReglaAlertaService::GASTO_DISCRECIONAL_ALTO)) {
            $emitidas += self::discrecional($usuarioId, $reglas[ReglaAlertaService::GASTO_DISCRECIONAL_ALTO], $mes);
        }

        foreach ([ReglaAlertaService::TOPE_DIAN_TARJETA, ReglaAlertaService::TOPE_DIAN_COMPRAS] as $tipo) {
            if ($activa($tipo)) {
                $emitidas += self::topeDian($usuarioId, $reglas[$tipo], $hoy->year);
            }
        }

        // Cambian con los días y no con los gastos: solo en el job diario.
        if ($diario && $activa(ReglaAlertaService::ENDEUDAMIENTO)) {
            $emitidas += self::endeudamiento($usuarioId, $reglas[ReglaAlertaService::ENDEUDAMIENTO], $mes, $hoy);
        }

        if ($diario && $activa(ReglaAlertaService::VENCIMIENTOS)) {
            $emitidas += self::vencimientos($usuarioId, $reglas[ReglaAlertaService::VENCIMIENTOS], $hoy);
        }

        return $emitidas;
    }

    /*
    |--------------------------------------------------------------------------
    | Ingresos frente a gastos
    |--------------------------------------------------------------------------
    */

    /**
     * Gastos del mes > ingresos netos recibidos + recurrentes de ingreso que
     * faltan del mes (para no alarmar antes del pago del salario). Al cierre
     * se compara solo con los ingresos reales.
     */
    private static function ingresosVsGastos(string $usuarioId, array $regla, CarbonImmutable $mes, bool $cierre): int
    {
        $gastos = self::suma(self::gastosDelMes($usuarioId, $mes));

        if (bccomp($gastos, '0', 2) !== 1) {
            return 0;
        }

        $recibidos = CreditoService::alPeso((string) self::ingresosDelMes($usuarioId, $mes, 'CONFIRMADO')->sum('monto_neto_cop'));
        $pendientes = $cierre ? '0' : self::ingresosPendientes($usuarioId, $mes);
        $ingresos = bcadd($recibidos, $pendientes, 0);

        if (bccomp($gastos, $ingresos, 0) !== 1) {
            return 0;
        }

        $clave = ($cierre ? 'INGRESOS_VS_GASTOS_CIERRE:' : 'INGRESOS_VS_GASTOS:').$mes->format('Y-m');

        return self::emitir($usuarioId, $regla, $clave, [
            'mes' => $mes->format('Y-m'),
            'cierre' => $cierre,
            'gastos' => $gastos,
            'ingresos_recibidos' => $recibidos,
            'ingresos_pendientes' => $pendientes,
            'diferencia' => bcsub($gastos, $ingresos, 0),
        ]);
    }

    /** Desde la mitad del mes, gasto total proyectado ≥ umbral % del ingreso promedio. */
    private static function gastoProyectadoAlto(string $usuarioId, array $regla, CarbonImmutable $mes, CarbonImmutable $hoy): int
    {
        $proyeccion = PresupuestoService::proyeccionGlobal($usuarioId, $hoy);

        if ($proyeccion['dia_corte'] * 2 <= $proyeccion['dias_mes']) {
            return 0;
        }

        $ingreso = PresupuestoService::ingresoBase($usuarioId, $mes);
        $porcentaje = self::porcentaje($proyeccion['proyeccion'], $ingreso);
        $umbral = (int) $regla['parametros']['umbral'];

        if ($porcentaje === null || bccomp($porcentaje, (string) $umbral, 2) < 0) {
            return 0;
        }

        return self::emitir($usuarioId, $regla, 'GASTO_PROYECTADO_ALTO:'.$mes->format('Y-m'), [
            'mes' => $mes->format('Y-m'),
            'umbral' => $umbral,
            'gastado' => $proyeccion['gastado'],
            'proyeccion' => $proyeccion['proyeccion'],
            'metodo_proyeccion' => $proyeccion['metodo_proyeccion'],
            'ingreso_promedio' => $ingreso,
            'porcentaje' => $porcentaje,
        ]);
    }

    /** El gasto del mes supera el tope fijo que eligió el Usuario. */
    private static function limiteMensual(string $usuarioId, array $regla, CarbonImmutable $mes): int
    {
        $monto = $regla['parametros']['monto'] ?? null;

        if ($monto === null) {
            return 0;
        }

        $gastado = self::suma(self::gastosDelMes($usuarioId, $mes));

        if (bccomp($gastado, (string) $monto, 0) !== 1) {
            return 0;
        }

        return self::emitir($usuarioId, $regla, 'LIMITE_GASTO_MENSUAL:'.$mes->format('Y-m'), [
            'mes' => $mes->format('Y-m'),
            'monto' => (string) $monto,
            'gastado' => $gastado,
            'diferencia' => bcsub($gastado, (string) $monto, 0),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Hábitos de gasto
    |--------------------------------------------------------------------------
    */

    /**
     * Un gasto mayor que N veces el promedio de su subcategoría en los 6 meses
     * anteriores, con un mínimo de 5 gastos previos. Se evalúa sobre los
     * gastos recién guardados.
     */
    private static function gastoInusual(string $usuarioId, array $regla, array $gastoIds): int
    {
        if ($gastoIds === []) {
            return 0;
        }

        $veces = (string) $regla['parametros']['veces'];
        $meses = (int) config('scfp.alertas.gasto_inusual_meses');
        $minimo = (int) config('scfp.alertas.gasto_inusual_minimo_gastos');
        $emitidas = 0;

        $gastos = self::gastos($usuarioId)->whereIn('id', $gastoIds)->with('subcategoria:id,nombre')->get();

        foreach ($gastos as $gasto) {
            $fecha = CarbonImmutable::parse($gasto->fecha);
            $previos = self::gastos($usuarioId)
                ->where('subcategoria_id', $gasto->subcategoria_id)
                ->where('id', '<>', $gasto->id)
                ->where('fecha', '>=', $fecha->subMonthsNoOverflow($meses)->toDateString())
                ->where('fecha', '<', $fecha->toDateString())
                ->selectRaw('COUNT(*) AS cantidad, COALESCE(SUM(monto_cop), 0) AS total')
                ->toBase()
                ->first();

            if ((int) $previos->cantidad < $minimo) {
                continue;
            }

            $promedio = bcdiv((string) $previos->total, (string) $previos->cantidad, 10);
            $monto = (string) $gasto->monto_cop;

            if (bccomp($monto, bcmul($promedio, $veces, 10), 10) !== 1) {
                continue;
            }

            $emitidas += self::emitir($usuarioId, $regla, 'GASTO_INUSUAL:'.$gasto->id, [
                'gasto_id' => $gasto->id,
                'fecha' => $fecha->toDateString(),
                'subcategoria_id' => $gasto->subcategoria_id,
                'subcategoria' => $gasto->subcategoria?->nombre,
                'comercio_beneficiario' => $gasto->comercio_beneficiario,
                'monto' => CreditoService::alPeso($monto),
                'promedio' => CreditoService::alPeso($promedio),
                'veces_promedio' => self::redondear2(bcdiv($monto, $promedio, 10)),
                'veces' => (int) $veces,
                'gastos_previos' => (int) $previos->cantidad,
            ], 'GASTO', $gasto->id);
        }

        return $emitidas;
    }

    /** Los gastos pequeños (< monto_max) del mes suman más que el tope. */
    private static function hormiga(string $usuarioId, array $regla, CarbonImmutable $mes): int
    {
        $montoMax = (string) $regla['parametros']['monto_max'];
        $tope = (string) $regla['parametros']['tope'];

        $pequenos = self::gastosDelMes($usuarioId, $mes)->where('monto_cop', '<', $montoMax);
        $total = self::suma(clone $pequenos);

        if (bccomp($total, $tope, 0) !== 1) {
            return 0;
        }

        $porSubcategoria = (clone $pequenos)
            ->groupBy('subcategoria_id')
            ->selectRaw('subcategoria_id, SUM(monto_cop) AS total, COUNT(*) AS cantidad')
            ->orderByDesc('total')
            ->limit(3)
            ->toBase()
            ->get();
        $nombres = Categoria::whereIn('id', $porSubcategoria->pluck('subcategoria_id'))->pluck('nombre', 'id');

        return self::emitir($usuarioId, $regla, 'GASTOS_HORMIGA:'.$mes->format('Y-m'), [
            'mes' => $mes->format('Y-m'),
            'monto_max' => $montoMax,
            'tope' => $tope,
            'cantidad' => (clone $pequenos)->count(),
            'total' => $total,
            'por_subcategoria' => $porSubcategoria->map(fn ($f) => [
                'subcategoria_id' => $f->subcategoria_id,
                'nombre' => $nombres[$f->subcategoria_id] ?? null,
                'total' => CreditoService::alPeso((string) $f->total),
                'cantidad' => (int) $f->cantidad,
            ])->all(),
        ]);
    }

    /** Referencia 50/30/20: los gastos DISCRECIONAL del mes superan el % del ingreso promedio. */
    private static function discrecional(string $usuarioId, array $regla, CarbonImmutable $mes): int
    {
        $ingreso = PresupuestoService::ingresoBase($usuarioId, $mes);

        if (bccomp($ingreso, '0', 0) !== 1) {
            return 0;
        }

        $discrecional = self::suma(self::gastosDelMes($usuarioId, $mes)->where('necesidad', 'DISCRECIONAL'));
        $porcentaje = self::porcentaje($discrecional, $ingreso);
        $umbral = (int) $regla['parametros']['umbral'];

        if (bccomp($porcentaje, (string) $umbral, 2) !== 1) {
            return 0;
        }

        return self::emitir($usuarioId, $regla, 'GASTO_DISCRECIONAL_ALTO:'.$mes->format('Y-m'), [
            'mes' => $mes->format('Y-m'),
            'umbral' => $umbral,
            'discrecional' => $discrecional,
            'ingreso_promedio' => $ingreso,
            'porcentaje' => $porcentaje,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Topes de la DIAN
    |--------------------------------------------------------------------------
    */

    /**
     * RN-023.2: lo acumulado en el año (consumos con tarjeta de crédito o
     * compras y consumos totales) llega al umbral % del tope para declarar
     * (1.400 UVT, de parametros_normativos del año).
     */
    private static function topeDian(string $usuarioId, array $regla, int $anio): int
    {
        $tarjeta = $regla['tipo'] === ReglaAlertaService::TOPE_DIAN_TARJETA;
        $parametros = ConsolidadoRentaService::obtenerParametros($anio);
        $uvt = (string) (int) round($parametros['uvt']);
        $topeUvt = (int) ($tarjeta ? $parametros['tope_tarjetas_uvt'] : $parametros['tope_compras_uvt']);
        $tope = bcmul($uvt, (string) $topeUvt, 0);

        $acumulado = self::gastos($usuarioId)
            ->whereBetween('fecha', ["{$anio}-01-01", "{$anio}-12-31"])
            ->when($tarjeta, fn ($q) => $q->where('medio_pago', 'TARJETA_CREDITO'));
        $acumulado = self::suma($acumulado);

        $porcentaje = self::porcentaje($acumulado, $tope);
        $umbral = (int) $regla['parametros']['umbral'];

        if ($porcentaje === null || bccomp($porcentaje, (string) $umbral, 2) < 0) {
            return 0;
        }

        return self::emitir($usuarioId, $regla, "{$regla['tipo']}:{$anio}:{$umbral}", [
            'anio' => $anio,
            'umbral' => $umbral,
            'acumulado' => $acumulado,
            'tope' => $tope,
            'tope_uvt' => $topeUvt,
            'uvt' => $uvt,
            'porcentaje' => $porcentaje,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Obligaciones
    |--------------------------------------------------------------------------
    */

    /**
     * RF-053: el indicador de endeudamiento del mes queda en una franja más
     * alta que la del mes anterior (cada umbral superado es una franja).
     */
    private static function endeudamiento(string $usuarioId, array $regla, CarbonImmutable $mes, CarbonImmutable $hoy): int
    {
        $umbrales = $regla['parametros']['umbrales'];
        $actual = EndeudamientoService::calcular($usuarioId, $hoy);
        $anterior = EndeudamientoService::calcular($usuarioId, $mes->subMonthNoOverflow());

        $franja = fn (?string $indicador) => $indicador === null
            ? 0
            : count(array_filter($umbrales, fn ($u) => bccomp($indicador, (string) $u, 2) === 1));

        $franjaActual = $franja($actual['indicador_porc']);
        $franjaAnterior = $franja($anterior['indicador_porc']);

        if ($franjaActual === 0 || $franjaActual <= $franjaAnterior) {
            return 0;
        }

        $umbral = (int) $umbrales[$franjaActual - 1];

        return self::emitir($usuarioId, $regla, 'ENDEUDAMIENTO:'.$mes->format('Y-m').":{$umbral}", [
            'mes' => $mes->format('Y-m'),
            'umbral' => $umbral,
            'umbrales' => $umbrales,
            'indicador_porc' => $actual['indicador_porc'],
            'indicador_anterior_porc' => $anterior['indicador_porc'],
            'pagos_mes' => $actual['pagos_mes']['total'],
            'ingreso_promedio' => $actual['ingresos_netos']['promedio'],
        ]);
    }

    /**
     * RN-059.2: cuotas de crédito que vencen en los próximos N días o ya
     * vencieron sin pagarse, gastos recurrentes esperando confirmación y
     * recurrentes con monto estimado que se generan en los próximos N días.
     */
    private static function vencimientos(string $usuarioId, array $regla, CarbonImmutable $hoy): int
    {
        $dias = (int) $regla['parametros']['dias_antes'];
        $limite = $hoy->addDays($dias);
        $emitidas = 0;

        $creditos = Credito::withoutGlobalScope(DelUsuarioAutenticado::class)->where('usuario_id', $usuarioId)->get();

        foreach ($creditos as $credito) {
            foreach (PagoCreditoService::situacion($credito, $hoy)['cuotas'] as $cuota) {
                $fecha = CarbonImmutable::parse($cuota['fecha']);
                $pendiente = array_reduce(
                    ['interes_pendiente', 'capital_pendiente', 'seguro_pendiente', 'cargo_pendiente'],
                    fn ($t, $campo) => bcadd($t, (string) $cuota[$campo], 0),
                    '0'
                );
                $base = [
                    'credito_id' => $credito->id,
                    'banco' => $credito->banco,
                    'proposito' => $credito->proposito,
                    'cuota_id' => $cuota['id'],
                    'numero' => $cuota['numero'],
                    'fecha' => $cuota['fecha'],
                    'cuota_total' => $cuota['cuota_total'],
                    'pendiente' => $pendiente,
                ];

                if ($cuota['estado'] === 'VENCIDA') {
                    $emitidas += self::emitir($usuarioId, $regla, "VENCIMIENTO_CUOTA:{$cuota['id']}:VENCIDA", [
                        'subtipo' => 'CUOTA_VENCIDA',
                        ...$base,
                        'dias_mora' => $cuota['dias_mora'],
                    ], 'CREDITO', $credito->id);
                } elseif (in_array($cuota['estado'], ['PENDIENTE', 'PAGADA_PARCIAL'], true) && $fecha->betweenIncluded($hoy, $limite)) {
                    $emitidas += self::emitir($usuarioId, $regla, "VENCIMIENTO_CUOTA:{$cuota['id']}:PROXIMA", [
                        'subtipo' => 'CUOTA_PROXIMA',
                        ...$base,
                        'dias_para_vencer' => (int) $hoy->diffInDays($fecha),
                    ], 'CREDITO', $credito->id);
                }
            }
        }

        // RN-028.2: recurrentes generados que esperan confirmación (por confirmar o con monto estimado).
        $porConfirmar = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'PENDIENTE')
            ->where('fecha', '<=', $limite->toDateString())
            ->with(['subcategoria:id,nombre', 'plantillaRecurrencia:id,es_monto_estimado'])
            ->get();

        foreach ($porConfirmar as $gasto) {
            $emitidas += self::emitir($usuarioId, $regla, "VENCIMIENTO_RECURRENTE:{$gasto->id}", [
                'subtipo' => 'RECURRENTE_POR_CONFIRMAR',
                'gasto_id' => $gasto->id,
                'fecha' => CarbonImmutable::parse($gasto->fecha)->toDateString(),
                'subcategoria_id' => $gasto->subcategoria_id,
                'subcategoria' => $gasto->subcategoria?->nombre,
                'monto' => CreditoService::alPeso((string) $gasto->monto_cop),
                'es_monto_estimado' => (bool) $gasto->plantillaRecurrencia?->es_monto_estimado,
            ], 'GASTO', $gasto->id);
        }

        // Recurrentes con monto estimado que se generan en los próximos N días.
        $estimadas = PlantillaRecurrencia::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('tipo_movimiento', 'GASTO')
            ->where('activa', true)
            ->where('es_monto_estimado', true)
            ->get();

        foreach ($estimadas as $plantilla) {
            foreach (RecurrenciaService::ocurrenciasPendientes($plantilla, $hoy->addDay(), $limite) as $fecha) {
                $datos = $plantilla->datos_plantilla;

                $emitidas += self::emitir($usuarioId, $regla, "VENCIMIENTO_ESTIMADO:{$plantilla->id}:{$fecha->toDateString()}", [
                    'subtipo' => 'RECURRENTE_ESTIMADO_PROXIMO',
                    'plantilla_id' => $plantilla->id,
                    'fecha' => $fecha->toDateString(),
                    'subcategoria_id' => $datos['subcategoria_id'] ?? null,
                    'monto_estimado' => CreditoService::alPeso((string) ($datos['monto_cop'] ?? '0')),
                    'dias_para_vencer' => (int) $hoy->diffInDays($fecha),
                ], 'PLANTILLA_RECURRENCIA', $plantilla->id);
            }
        }

        return $emitidas;
    }

    /*
    |--------------------------------------------------------------------------
    | Apoyo
    |--------------------------------------------------------------------------
    */

    private static function gastos(string $usuarioId): Builder
    {
        return Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'CONFIRMADO');
    }

    private static function gastosDelMes(string $usuarioId, CarbonImmutable $mes): Builder
    {
        return self::gastos($usuarioId)
            ->whereBetween('fecha', [$mes->toDateString(), $mes->endOfMonth()->toDateString()]);
    }

    private static function ingresosDelMes(string $usuarioId, CarbonImmutable $mes, string $estado): Builder
    {
        return Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('estado', $estado)
            ->whereBetween('fecha_recepcion', [$mes->toDateString(), $mes->endOfMonth()->toDateString()]);
    }

    /**
     * Ingresos netos que faltan del mes: los PENDIENTE de confirmar y las
     * ocurrencias aún no generadas de las plantillas de ingreso activas.
     */
    private static function ingresosPendientes(string $usuarioId, CarbonImmutable $mes): string
    {
        $total = (string) self::ingresosDelMes($usuarioId, $mes, 'PENDIENTE')->sum('monto_neto_cop');

        $plantillas = PlantillaRecurrencia::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('tipo_movimiento', 'INGRESO')
            ->where('activa', true)
            ->get();

        foreach ($plantillas as $plantilla) {
            $datos = $plantilla->datos_plantilla;
            // RN-021.2: neto = bruto − retención − aportes.
            $neto = bcsub(
                bcsub((string) ($datos['monto_bruto_cop'] ?? '0'), (string) ($datos['retencion_fuente_cop'] ?? '0'), 2),
                (string) ($datos['aportes_descontados_cop'] ?? '0'),
                2
            );

            foreach (RecurrenciaService::ocurrenciasPendientes($plantilla, $mes, $mes->endOfMonth()->startOfDay()) as $ignorada) {
                $total = bcadd($total, $neto, 2);
            }
        }

        return CreditoService::alPeso($total);
    }

    private static function suma(Builder $query): string
    {
        return CreditoService::alPeso((string) $query->sum('monto_cop'));
    }

    /** num / den × 100 con 2 decimales (mitad hacia arriba); null si den = 0. */
    private static function porcentaje(string $numerador, string $denominador): ?string
    {
        if (bccomp($denominador, '0', 2) !== 1) {
            return null;
        }

        return self::redondear2(bcdiv(bcmul($numerador, '100', 10), $denominador, 10));
    }

    private static function redondear2(string $valor): string
    {
        return bcadd($valor, '0.005', 2);
    }

    private static function emitir(string $usuarioId, array $regla, string $clave, array $datos, ?string $referenciaTipo = null, ?string $referenciaId = null): int
    {
        return NotificacionService::emitir(
            usuarioId: $usuarioId,
            tipo: $regla['tipo'],
            clave: $clave,
            prioridad: ReglaAlertaService::prioridad($regla),
            datos: $datos,
            enviarCorreo: $regla['enviar_correo'],
            referenciaTipo: $referenciaTipo,
            referenciaId: $referenciaId
        ) !== null ? 1 : 0;
    }
}
