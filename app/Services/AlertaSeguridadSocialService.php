<?php

namespace App\Services;

use App\Models\BitacoraAuditoria;
use App\Models\CalendarioTributario;
use App\Models\PagoSeguridadSocial;
use App\Models\PerfilTributario;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * MOD-05 — RF-036: Alerta de vencimiento del pago de seguridad social de los
 * Independientes y Contratistas, según el calendario (RF-016) y los dos
 * últimos dígitos del documento.
 *
 * El calendario guarda una fecha por rango de dígitos y año, pero el aporte es
 * mensual: el día del mes de esa fecha aplica a los 12 meses de la vigencia
 * (decisión del equipo, SC-001). El periodo que vence es el mes anterior al de
 * la fecha límite (mes vencido).
 */
class AlertaSeguridadSocialService
{
    /** RF-016: tipo de obligación del calendario para los aportes del independiente. */
    public const TIPO_OBLIGACION = 'SEGURIDAD_SOCIAL_INDEP';

    /** RN-036.1: alertas 5 días y 1 día antes de la fecha límite. */
    public const DIAS_DE_AVISO = [5, 1];

    /**
     * Días después de la fecha límite en que todavía se envía el "Vencido" si el
     * proceso diario no corrió el día siguiente. Evita avisar periodos antiguos.
     */
    private const DIAS_RECUPERACION_VENCIDO = 5;

    public const AVISO = 'AVISO';
    public const VENCIDO = 'VENCIDO';

    /** Estados de un periodo en la consulta del Usuario. */
    public const ESTADO_PAGADO = 'PAGADO';
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_POR_VENCER = 'POR_VENCER';
    public const ESTADO_VENCIDO = 'VENCIDO';

    public const ACCION_ALERTA = 'ALERTA_SEGURIDAD_SOCIAL';
    public const ACCION_SIN_CALENDARIO = 'AVISO_ADMIN_SIN_CALENDARIO_SEGURIDAD_SOCIAL';

    /** RN-036.2: advertencia de la alerta "Vencido". */
    public const ADVERTENCIA_MORA = 'Pagar después de la fecha límite puede generar intereses de mora.';

    /**
     * RF-016: fila del calendario que cubre los dígitos del documento en el año.
     */
    public static function calendarioPara(int $anio, string $digitos): ?CalendarioTributario
    {
        return CalendarioTributario::where('anio_vigencia', $anio)
            ->where('tipo_obligacion', self::TIPO_OBLIGACION)
            ->where('digito_desde', '<=', $digitos)
            ->where('digito_hasta', '>=', $digitos)
            ->first();
    }

    /** RN-036.3: ¿el Administrador cargó el calendario de la vigencia? */
    public static function hayCalendario(int $anio): bool
    {
        return CalendarioTributario::where('anio_vigencia', $anio)
            ->where('tipo_obligacion', self::TIPO_OBLIGACION)
            ->exists();
    }

    /**
     * Fecha límite del mes: el día del mes de `fecha_vencimiento`, o el último
     * día si el mes es más corto (un 31 en abril es el 30).
     */
    public static function fechaLimite(CalendarioTributario $calendario, int $anio, int $mes): CarbonImmutable
    {
        $inicio = CarbonImmutable::create($anio, $mes, 1);

        return $inicio->setDay(min($calendario->fecha_vencimiento->day, $inicio->daysInMonth));
    }

    /** Mes vencido: la fecha límite de abril es la del periodo de marzo. */
    public static function periodoQueVence(CarbonImmutable $fechaLimite): string
    {
        return $fechaLimite->startOfMonth()->subMonth()->format('Y-m');
    }

    /** RN-036.1: "si el periodo aún no tiene un pago registrado (RF-035)". */
    public static function periodoPagado(string $usuarioId, string $periodo): bool
    {
        return PagoSeguridadSocial::withoutGlobalScopes()
            ->whereNull('eliminado_en')
            ->where('usuario_id', $usuarioId)
            ->where('periodo', $periodo)
            ->exists();
    }

    /**
     * RN-036.1 / RN-036.2: la alerta que corresponde hoy para una fecha límite.
     * "Vencido" sale el día siguiente a la fecha límite, porque ese día todavía
     * se puede pagar (decisión del equipo, SC-001).
     *
     * @return array{tipo: string, dias: int}|null
     */
    public static function alertaDelDia(CarbonImmutable $fechaLimite, CarbonImmutable $hoy): ?array
    {
        $dias = (int) round($hoy->startOfDay()->diffInDays($fechaLimite->startOfDay(), false));

        // Cada aviso cubre hasta el día del siguiente: si el proceso no corrió el
        // día exacto, la alerta sale en la próxima ejecución. `dias` es la clave
        // del aviso en la bitácora, así que no se repite (yaEnviada).
        return match (true) {
            $dias >= 2 && $dias <= 5 => ['tipo' => self::AVISO, 'dias' => 5],
            $dias >= 0 && $dias <= 1 => ['tipo' => self::AVISO, 'dias' => 1],
            $dias <= -1 && $dias >= -self::DIAS_RECUPERACION_VENCIDO => ['tipo' => self::VENCIDO, 'dias' => -1],
            default => null,
        };
    }

    /**
     * RF-036: fechas límite y estado de los periodos alrededor de hoy, para la
     * consulta del Usuario: los dos meses anteriores, el actual y el siguiente.
     */
    public static function estadoDePeriodos(string $usuarioId, string $digitos, CarbonImmutable $hoy): array
    {
        $periodos = [];

        foreach ([-2, -1, 0, 1] as $desplazamiento) {
            $mes = $hoy->startOfMonth()->addMonths($desplazamiento);
            $calendario = self::calendarioPara($mes->year, $digitos);

            if ($calendario === null) {
                $periodos[] = [
                    'periodo' => $mes->subMonth()->format('Y-m'),
                    'fecha_limite' => null,
                    'estado' => null,
                    'mensaje' => self::hayCalendario($mes->year)
                        ? "El calendario de aportes de {$mes->year} no tiene una fecha para los dígitos {$digitos}."
                        : "No hay calendario de aportes para la vigencia {$mes->year} (RN-036.3).",
                ];

                continue;
            }

            $limite = self::fechaLimite($calendario, $mes->year, $mes->month);
            $periodo = self::periodoQueVence($limite);

            $periodos[] = [
                'periodo' => $periodo,
                'fecha_limite' => $limite->toDateString(),
                'estado' => self::estado($usuarioId, $periodo, $limite, $hoy),
                'mensaje' => null,
            ];
        }

        return $periodos;
    }

    /**
     * RN-036.1 a RN-036.3: alertas del día para todos los Independientes y
     * Contratistas activos. Lo ejecuta a diario `scfp:alertas-seguridad-social`.
     *
     * @return array{enviadas: int, vigencias_sin_calendario: array<int>}
     */
    public static function procesar(CarbonImmutable $hoy): array
    {
        // Una fecha límite de este mes, del anterior (Vencido al día siguiente
        // del último día) o del siguiente (aviso de 5 días a fin de mes).
        $meses = collect([-1, 0, 1])->map(fn ($desplazamiento) => $hoy->startOfMonth()->addMonths($desplazamiento));

        // RN-036.3: sin calendario para la vigencia no se generan alertas y se
        // informa al Administrador, una sola vez por vigencia.
        $sinCalendario = $meses->pluck('year')->unique()->reject(fn ($anio) => self::hayCalendario($anio))->values()->all();

        foreach ($sinCalendario as $anio) {
            self::avisarSinCalendario($anio);
        }

        $enviadas = 0;

        foreach (self::perfilesAAvisar() as $perfil) {
            foreach ($meses as $mes) {
                if (in_array($mes->year, $sinCalendario, true)) {
                    continue;
                }

                $calendario = self::calendarioPara($mes->year, $perfil->ultimos_digitos_nit);

                if ($calendario === null) {
                    continue;
                }

                $limite = self::fechaLimite($calendario, $mes->year, $mes->month);
                $alerta = self::alertaDelDia($limite, $hoy);

                if ($alerta === null) {
                    continue;
                }

                $periodo = self::periodoQueVence($limite);

                // CA-036.2: si ya registró el pago, la alerta no se genera.
                if (self::periodoPagado($perfil->usuario_id, $periodo)) {
                    continue;
                }

                if (self::yaEnviada($perfil->usuario_id, $periodo, $alerta)) {
                    continue;
                }

                if (self::enviar($perfil->usuario, $periodo, $limite, $alerta)) {
                    $enviadas++;
                }
            }
        }

        return ['enviadas' => $enviadas, 'vigencias_sin_calendario' => $sinCalendario];
    }

    private static function estado(string $usuarioId, string $periodo, CarbonImmutable $limite, CarbonImmutable $hoy): string
    {
        if (self::periodoPagado($usuarioId, $periodo)) {
            return self::ESTADO_PAGADO;
        }

        $dias = (int) round($hoy->startOfDay()->diffInDays($limite->startOfDay(), false));

        if ($dias < 0) {
            return self::ESTADO_VENCIDO;
        }

        return $dias <= max(self::DIAS_DE_AVISO) ? self::ESTADO_POR_VENCER : self::ESTADO_PENDIENTE;
    }

    /**
     * RF-036: perfiles activos de Independiente o Contratista con los dígitos
     * del documento, de cuentas activas.
     */
    private static function perfilesAAvisar(): iterable
    {
        return PerfilTributario::with('usuario')
            ->whereNull('vigente_hasta')
            ->whereNotNull('ultimos_digitos_nit')
            ->whereHas('usuario', fn ($query) => $query->where('estado', 'ACTIVA'))
            ->get()
            ->filter(fn (PerfilTributario $perfil) => SeguridadSocialService::tieneOcupacion(
                $perfil,
                SeguridadSocialService::OCUPACIONES_INDEPENDIENTE
            ));
    }

    /** Cada alerta se envía una sola vez: la bitácora registra las enviadas. */
    private static function yaEnviada(string $usuarioId, string $periodo, array $alerta): bool
    {
        return BitacoraAuditoria::where('accion', self::ACCION_ALERTA)
            ->where('usuario_id', $usuarioId)
            ->where('valor_nuevo->periodo', $periodo)
            ->where('valor_nuevo->tipo', $alerta['tipo'])
            // `->>` devuelve texto en Postgres: el número se compara como cadena.
            ->where('valor_nuevo->dias', (string) $alerta['dias'])
            ->exists();
    }

    /**
     * RN-036.1 / RN-036.2: no hay centro de notificaciones (RF-058 no está
     * especificado), así que la alerta va por correo y queda en la bitácora.
     */
    private static function enviar(Usuario $usuario, string $periodo, CarbonImmutable $limite, array $alerta): bool
    {
        $fecha = $limite->format('d/m/Y');

        [$asunto, $cuerpo] = $alerta['tipo'] === self::VENCIDO
            ? [
                "SCFP - Venció el pago de seguridad social del periodo {$periodo}",
                "Hola {$usuario->nombres},\n\nEl {$fecha} venció el plazo para pagar tus aportes a seguridad social del periodo {$periodo} y no tienes un pago registrado.\n".self::ADVERTENCIA_MORA."\n\nSi ya pagaste, registra el pago en el módulo de seguridad social.",
            ]
            : [
                "SCFP - Tu pago de seguridad social vence el {$fecha}",
                "Hola {$usuario->nombres},\n\nEl {$fecha} vence el plazo para pagar tus aportes a seguridad social del periodo {$periodo}. Aún no tienes un pago registrado para ese periodo.\n\nSi ya pagaste, registra el pago en el módulo de seguridad social.",
            ];

        try {
            Mail::raw($cuerpo, fn ($mensaje) => $mensaje->to($usuario->correo)->subject($asunto));
        } catch (\Throwable $e) {
            Log::warning("No se pudo enviar la alerta de seguridad social (RF-036): {$e->getMessage()}", [
                'usuario_id' => $usuario->id,
                'periodo' => $periodo,
            ]);

            return false;
        }

        BitacoraService::registrar(
            accion: self::ACCION_ALERTA,
            entidadAfectada: 'pagos_seguridad_social',
            valorNuevo: [
                'periodo' => $periodo,
                'fecha_limite' => $limite->toDateString(),
                'tipo' => $alerta['tipo'],
                'dias' => $alerta['dias'],
            ],
            justificacion: $alerta['tipo'] === self::VENCIDO
                ? "RN-036.2: alerta Vencido del periodo {$periodo}."
                : "RN-036.1: alerta {$alerta['dias']} día(s) antes de la fecha límite del periodo {$periodo}.",
            usuarioId: $usuario->id
        );

        return true;
    }

    /**
     * RN-036.3: "Si no hay calendario para la vigencia, no se generan alertas y
     * se le informa al Administrador". Por correo, una sola vez por vigencia.
     */
    private static function avisarSinCalendario(int $anio): void
    {
        $yaAvisado = BitacoraAuditoria::where('accion', self::ACCION_SIN_CALENDARIO)
            ->where('valor_nuevo->anio', (string) $anio)
            ->exists();

        if ($yaAvisado) {
            return;
        }

        $correos = Usuario::where('rol', 'ADMIN')
            ->where('estado', 'ACTIVA')
            ->pluck('correo');

        foreach ($correos as $correo) {
            try {
                Mail::raw(
                    "Hola,\n\nNo hay calendario de aportes a seguridad social (SEGURIDAD_SOCIAL_INDEP) para la vigencia {$anio}, así que no se están generando las alertas de vencimiento de los independientes.\n\nPuede cargarlo desde la administración del calendario tributario (RF-016).",
                    fn ($mensaje) => $mensaje->to($correo)->subject("SCFP - Falta el calendario de seguridad social {$anio}")
                );
            } catch (\Throwable $e) {
                Log::warning("No se pudo enviar el aviso de calendario faltante a un Administrador: {$e->getMessage()}");
            }
        }

        BitacoraService::registrar(
            accion: self::ACCION_SIN_CALENDARIO,
            entidadAfectada: 'calendario_tributario',
            valorNuevo: ['anio' => $anio],
            justificacion: "RN-036.3: sin calendario de seguridad social para {$anio}; aviso enviado a {$correos->count()} administradores."
        );
    }
}
