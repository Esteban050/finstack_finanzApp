<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Notificacion;
use App\Models\PlantillaRecurrencia;
use App\Models\PreferenciaNotificacion;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * MOD-09 — RN-059.3: resumen periódico por correo.
 *
 * - SEMANAL: los lunes, con la semana anterior (lunes a domingo).
 * - MENSUAL: el día 1, con el mes anterior.
 *
 * Bloques que el Usuario elige: ingresos, gastos y balance del periodo
 * (INGRESOS_GASTOS); cómo va el presupuesto por categoría (PRESUPUESTO); las
 * alertas que se generaron (ALERTAS); y los pagos y vencimientos de los
 * próximos 15 días (VENCIMIENTOS). Sin preferencias guardadas no se envía.
 */
class ResumenCorreoService
{
    /** Días hacia adelante del bloque de vencimientos. */
    public const DIAS_VENCIMIENTOS = 15;

    /** @return array{frecuencia: string, bloques: array<int, string>} */
    public static function preferencias(string $usuarioId): array
    {
        $pref = PreferenciaNotificacion::withoutGlobalScope(DelUsuarioAutenticado::class)->find($usuarioId);

        return [
            'frecuencia' => $pref?->frecuencia_resumen ?? PreferenciaNotificacion::NINGUNA,
            'bloques' => $pref?->bloques_resumen ?? PreferenciaNotificacion::BLOQUES,
        ];
    }

    /**
     * RN-059.3: guarda la frecuencia y los bloques (los que no vengan no
     * cambian).
     *
     * @return array{frecuencia: string, bloques: array<int, string>}
     */
    public static function guardarPreferencias(string $usuarioId, ?string $frecuencia, ?array $bloques): array
    {
        $anterior = self::preferencias($usuarioId);
        $nuevas = [
            'frecuencia_resumen' => $frecuencia ?? $anterior['frecuencia'],
            // Se guardan en el orden del catálogo, sin repetir.
            'bloques_resumen' => $bloques === null
                ? $anterior['bloques']
                : array_values(array_intersect(PreferenciaNotificacion::BLOQUES, $bloques)),
        ];

        DB::transaction(function () use ($usuarioId, $anterior, $nuevas) {
            PreferenciaNotificacion::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->updateOrCreate(['usuario_id' => $usuarioId], $nuevas);

            BitacoraService::registrar(
                accion: 'EDITAR_PREFERENCIAS_RESUMEN',
                entidadAfectada: 'preferencias_notificacion',
                entidadId: $usuarioId,
                valorAnterior: $anterior,
                valorNuevo: $nuevas,
                justificacion: 'RN-059.3: el Usuario ajustó su resumen por correo.',
                usuarioId: $usuarioId
            );
        });

        return self::preferencias($usuarioId);
    }

    /** RF-057: "Restablecer valores sugeridos" también apaga el resumen. */
    public static function restablecer(string $usuarioId): void
    {
        PreferenciaNotificacion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->delete();
    }

    /**
     * Periodo que toca enviar hoy para la frecuencia, o null si hoy no toca.
     *
     * @return array{clave: string, desde: CarbonImmutable, hasta: CarbonImmutable}|null
     */
    public static function periodo(string $frecuencia, CarbonImmutable $hoy): ?array
    {
        $hoy = $hoy->startOfDay();

        if ($frecuencia === PreferenciaNotificacion::SEMANAL && $hoy->isMonday()) {
            $desde = $hoy->subDays(7);

            return ['clave' => 'SEMANAL:'.$desde->toDateString(), 'desde' => $desde, 'hasta' => $hoy->subDay()];
        }

        if ($frecuencia === PreferenciaNotificacion::MENSUAL && $hoy->day === 1) {
            $desde = $hoy->subMonthNoOverflow();

            return ['clave' => 'MENSUAL:'.$desde->format('Y-m'), 'desde' => $desde, 'hasta' => $hoy->subDay()];
        }

        return null;
    }

    /** Comando diario: envía los resúmenes que tocan hoy. Devuelve cuántos envió. */
    public static function enviarPendientes(?CarbonImmutable $hoy = null): int
    {
        $hoy ??= CarbonImmutable::today();
        $enviados = 0;

        foreach ([PreferenciaNotificacion::SEMANAL, PreferenciaNotificacion::MENSUAL] as $frecuencia) {
            $periodo = self::periodo($frecuencia, $hoy);

            if ($periodo === null) {
                continue;
            }

            $pendientes = PreferenciaNotificacion::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->where('frecuencia_resumen', $frecuencia)
                ->where(fn ($q) => $q->whereNull('ultimo_resumen')->orWhere('ultimo_resumen', '<>', $periodo['clave']))
                ->whereHas('usuario', fn ($q) => $q->where('estado', 'ACTIVA'))
                ->get();

            foreach ($pendientes as $pref) {
                if (self::enviar($pref, $periodo, $hoy)) {
                    $enviados++;
                }
            }
        }

        return $enviados;
    }

    /**
     * Datos del resumen, por bloque (los no elegidos no se calculan).
     *
     * @param  array<int, string>  $bloques
     * @return array<string, mixed>
     */
    public static function contenido(string $usuarioId, CarbonImmutable $desde, CarbonImmutable $hasta, array $bloques, CarbonImmutable $hoy): array
    {
        $contenido = ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()];

        if (in_array('INGRESOS_GASTOS', $bloques, true)) {
            $ingresos = CreditoService::alPeso((string) Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->where('usuario_id', $usuarioId)
                ->where('estado', 'CONFIRMADO')
                ->whereBetween('fecha_recepcion', [$desde->toDateString(), $hasta->toDateString()])
                ->sum('monto_neto_cop'));
            $gastos = CreditoService::alPeso((string) Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->where('usuario_id', $usuarioId)
                ->where('estado', 'CONFIRMADO')
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->sum('monto_cop'));

            $contenido['ingresos_gastos'] = [
                'ingresos_netos' => $ingresos,
                'gastos' => $gastos,
                'balance' => bcsub($ingresos, $gastos, 0),
            ];
        }

        if (in_array('PRESUPUESTO', $bloques, true)) {
            $presupuesto = PresupuestoService::delMes($usuarioId, $hasta->startOfMonth());
            $contenido['presupuesto'] = null;

            if ($presupuesto !== null) {
                $ejecucion = PresupuestoService::ejecucion($presupuesto, $hoy);
                $contenido['presupuesto'] = [
                    'mes' => $ejecucion['mes'],
                    'asignado' => bcadd($ejecucion['totales']['monto_asignado'], $ejecucion['totales']['saldo_trasladado'], 0),
                    'gastado' => $ejecucion['totales']['gastado'],
                    'ejecucion_porc' => $ejecucion['totales']['ejecucion_porc'],
                    'lineas' => array_map(fn ($l) => [
                        'nombre' => $l['nombre'],
                        'asignado' => bcadd($l['monto_asignado'], $l['saldo_trasladado'], 0),
                        'gastado' => $l['gastado'],
                        'ejecucion_porc' => $l['ejecucion_porc'],
                    ], $ejecucion['lineas']),
                ];
            }
        }

        if (in_array('ALERTAS', $bloques, true)) {
            $contenido['alertas'] = Notificacion::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->where('usuario_id', $usuarioId)
                ->whereBetween('creado_en', [$desde->startOfDay(), $hasta->endOfDay()])
                ->groupBy('tipo')
                ->selectRaw('tipo, COUNT(*) AS cantidad')
                ->orderByDesc('cantidad')
                ->toBase()
                ->get()
                ->map(fn ($f) => ['tipo' => $f->tipo, 'cantidad' => (int) $f->cantidad])
                ->all();
        }

        if (in_array('VENCIMIENTOS', $bloques, true)) {
            $contenido['vencimientos'] = self::vencimientos($usuarioId, $hoy);
        }

        return $contenido;
    }

    /**
     * Pagos de los próximos 15 días: cuotas de crédito sin pagar y gastos
     * recurrentes que se generan.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function vencimientos(string $usuarioId, CarbonImmutable $hoy): array
    {
        $hasta = $hoy->addDays(self::DIAS_VENCIMIENTOS);
        $filas = [];

        foreach (Credito::withoutGlobalScope(DelUsuarioAutenticado::class)->where('usuario_id', $usuarioId)->get() as $credito) {
            foreach (PagoCreditoService::situacion($credito, $hoy)['cuotas'] as $cuota) {
                if ($cuota['estado'] !== 'PAGADA' && CarbonImmutable::parse($cuota['fecha'])->betweenIncluded($hoy, $hasta)) {
                    $filas[] = [
                        'fecha' => $cuota['fecha'],
                        'concepto' => 'CUOTA_CREDITO',
                        'descripcion' => "Cuota {$cuota['numero']} · {$credito->banco}",
                        'monto' => $cuota['cuota_total'],
                        'es_monto_estimado' => false,
                    ];
                }
            }
        }

        $plantillas = PlantillaRecurrencia::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('tipo_movimiento', 'GASTO')
            ->where('activa', true)
            ->get();

        foreach ($plantillas as $plantilla) {
            $datos = $plantilla->datos_plantilla;

            foreach (RecurrenciaService::ocurrenciasPendientes($plantilla, $hoy, $hasta) as $fecha) {
                $filas[] = [
                    'fecha' => $fecha->toDateString(),
                    'concepto' => 'GASTO_RECURRENTE',
                    'descripcion' => $datos['descripcion'] ?? ($datos['comercio_beneficiario'] ?? 'Gasto recurrente'),
                    'monto' => CreditoService::alPeso((string) ($datos['monto_cop'] ?? '0')),
                    'es_monto_estimado' => (bool) $plantilla->es_monto_estimado,
                ];
            }
        }

        usort($filas, fn ($a, $b) => strcmp($a['fecha'], $b['fecha']));

        return $filas;
    }

    /** Arma y envía el resumen; si el correo falla, queda en el log y se reintenta mañana. */
    private static function enviar(PreferenciaNotificacion $pref, array $periodo, CarbonImmutable $hoy): bool
    {
        $usuario = Usuario::find($pref->usuario_id);

        if ($usuario === null) {
            return false;
        }

        $contenido = self::contenido($usuario->id, $periodo['desde'], $periodo['hasta'], $pref->bloques_resumen, $hoy);
        [$asunto, $cuerpo] = self::texto($usuario, $pref->frecuencia_resumen, $contenido);

        try {
            Mail::raw($cuerpo, fn ($mensaje) => $mensaje->to($usuario->correo)->subject($asunto));
        } catch (\Throwable $e) {
            Log::warning("No se pudo enviar el resumen por correo (RN-059.3): {$e->getMessage()}", [
                'usuario_id' => $usuario->id,
                'periodo' => $periodo['clave'],
            ]);

            return false;
        }

        $pref->update(['ultimo_resumen' => $periodo['clave']]);

        BitacoraService::registrar(
            accion: 'ENVIO_RESUMEN_CORREO',
            entidadAfectada: 'preferencias_notificacion',
            entidadId: $usuario->id,
            valorNuevo: ['periodo' => $periodo['clave'], 'bloques' => $pref->bloques_resumen],
            justificacion: 'RN-059.3: resumen periódico por correo.',
            usuarioId: $usuario->id
        );

        return true;
    }

    /** @return array{0: string, 1: string} asunto y cuerpo */
    private static function texto(Usuario $usuario, string $frecuencia, array $c): array
    {
        $desde = CarbonImmutable::parse($c['desde'])->format('d/m/Y');
        $hasta = CarbonImmutable::parse($c['hasta'])->format('d/m/Y');
        $asunto = $frecuencia === PreferenciaNotificacion::SEMANAL
            ? "SCFP - Tu resumen de la semana ({$desde} al {$hasta})"
            : 'SCFP - Tu resumen de '.CarbonImmutable::parse($c['desde'])->locale('es')->translatedFormat('F \d\e Y');

        $partes = ["Hola {$usuario->nombres},", "Este es tu resumen del {$desde} al {$hasta}."];

        if (isset($c['ingresos_gastos'])) {
            $ig = $c['ingresos_gastos'];
            $partes[] = "Ingresos, gastos y balance\n- Ingresos netos: ".self::pesos($ig['ingresos_netos'])."\n- Gastos: ".self::pesos($ig['gastos'])."\n- Balance: ".self::pesos($ig['balance']);
        }

        if (array_key_exists('presupuesto', $c)) {
            $p = $c['presupuesto'];
            $partes[] = $p === null
                ? "Presupuesto\n- No tienes presupuesto para este mes."
                : "Presupuesto de {$p['mes']}: ".self::pesos($p['gastado']).' de '.self::pesos($p['asignado']).' ('.self::porcentaje($p['ejecucion_porc']).")\n"
                    .implode("\n", array_map(fn ($l) => "- {$l['nombre']}: ".self::pesos($l['gastado']).' de '.self::pesos($l['asignado']).' ('.self::porcentaje($l['ejecucion_porc']).')', $p['lineas']));
        }

        if (isset($c['alertas'])) {
            $total = array_sum(array_column($c['alertas'], 'cantidad'));
            $partes[] = $total === 0
                ? "Alertas\n- No se generaron alertas en el periodo."
                : "Alertas: {$total} en el periodo\n".implode("\n", array_map(fn ($a) => '- '.self::nombreAlerta($a['tipo']).": {$a['cantidad']}", $c['alertas']));
        }

        if (isset($c['vencimientos'])) {
            $partes[] = $c['vencimientos'] === []
                ? "Próximos 15 días\n- No tienes pagos programados."
                : "Próximos 15 días\n".implode("\n", array_map(
                    fn ($v) => '- '.CarbonImmutable::parse($v['fecha'])->format('d/m').": {$v['descripcion']} · ".self::pesos($v['monto']).($v['es_monto_estimado'] ? ' (estimado)' : ''),
                    $c['vencimientos']
                ));
        }

        $partes[] = 'Puedes cambiar o apagar este resumen en Mi perfil › Alertas y notificaciones.';

        return [$asunto, implode("\n\n", $partes)];
    }

    private static function nombreAlerta(string $tipo): string
    {
        return [
            'UMBRAL_LINEA' => 'Umbral de una categoría',
            'PRESUPUESTO_TOTAL' => 'Presupuesto total',
            'PROYECCION_LINEA' => 'Vas a pasarte',
            'INGRESOS_VS_GASTOS' => 'Gastas más de lo que ganas',
            'GASTO_PROYECTADO_ALTO' => 'Gasto proyectado alto',
            'LIMITE_GASTO_MENSUAL' => 'Límite de gasto mensual',
            'GASTO_INUSUAL' => 'Gasto inusual',
            'GASTOS_HORMIGA' => 'Gastos hormiga',
            'GASTO_DISCRECIONAL_ALTO' => 'Gasto discrecional alto',
            'TOPE_DIAN_TARJETA' => 'Consumos con tarjeta de crédito (DIAN)',
            'TOPE_DIAN_COMPRAS' => 'Compras y consumos (DIAN)',
            'ENDEUDAMIENTO' => 'Endeudamiento',
            'VENCIMIENTOS' => 'Vencimientos',
            'SEGURIDAD_CUENTA' => 'Seguridad de la cuenta',
            'SEGURIDAD_SOCIAL' => 'Seguridad social',
        ][$tipo] ?? $tipo;
    }

    private static function pesos(string $valor): string
    {
        $negativo = str_starts_with($valor, '-');

        return ($negativo ? '− ' : '').'$ '.number_format((float) ltrim($valor, '-'), 0, ',', '.');
    }

    private static function porcentaje(?string $valor): string
    {
        return $valor === null ? '—' : str_replace('.', ',', $valor).' %';
    }
}
