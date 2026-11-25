<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * MOD-09 — RF-058: centro de notificaciones.
 *
 * Una notificación se identifica por su `clave` (RN-057.2): emitir dos veces
 * el mismo evento no la duplica. Los textos de la aplicación los arma el
 * frontend con el tipo y `datos`; aquí solo se arma el del correo.
 */
class NotificacionService
{
    /**
     * Registra la notificación si su clave es nueva y, si la regla lo pide,
     * la envía por correo (RN-059.1). Devuelve la notificación creada o null
     * si ya existía.
     */
    public static function emitir(
        string $usuarioId,
        string $tipo,
        string $clave,
        string $prioridad,
        array $datos,
        bool $enviarCorreo = false,
        ?string $referenciaTipo = null,
        ?string $referenciaId = null
    ): ?Notificacion {
        // ON CONFLICT DO NOTHING: si otra evaluación la creó al mismo tiempo,
        // aquí simplemente no se inserta.
        $insertadas = Notificacion::withoutGlobalScope(DelUsuarioAutenticado::class)->insertOrIgnore([
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'prioridad' => $prioridad,
            'estado' => Notificacion::NO_LEIDA,
            'clave' => $clave,
            'datos' => json_encode($datos, JSON_UNESCAPED_UNICODE),
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            // La hora de la aplicación, igual que el resto de marcas de tiempo.
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        if ($insertadas === 0) {
            return null;
        }

        $notificacion = Notificacion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('clave', $clave)
            ->first();

        if ($enviarCorreo) {
            self::enviarCorreo($notificacion);
        }

        return $notificacion;
    }

    /**
     * RN-059.4: evento de seguridad de la cuenta (bloqueo, cambio o
     * restablecimiento de contraseña, solicitud de supresión). Siempre se
     * registra; el correo va salvo cuando el flujo de origen ya envía el suyo.
     * Nunca interrumpe el flujo de seguridad que lo llama.
     */
    public static function seguridadCuenta(string $usuarioId, string $evento, array $datos = [], bool $enviarCorreo = true): ?Notificacion
    {
        try {
            return self::emitir(
                usuarioId: $usuarioId,
                tipo: ReglaAlertaService::SEGURIDAD_CUENTA,
                clave: 'SEGURIDAD_CUENTA:'.$evento.':'.Str::uuid(),
                prioridad: Notificacion::CRITICA,
                datos: ['evento' => $evento, ...$datos, 'ocurrido_en' => now()->toIso8601String()],
                enviarCorreo: $enviarCorreo
            );
        } catch (\Throwable $e) {
            Log::warning("No se pudo registrar la notificación de seguridad (RN-059.4): {$e->getMessage()}", [
                'usuario_id' => $usuarioId,
                'evento' => $evento,
            ]);

            return null;
        }
    }

    /** RN-058.3: cambia el estado y registra cuándo. */
    public static function cambiarEstado(Notificacion $notificacion, string $estado): Notificacion
    {
        $ahora = now();

        $notificacion->update(match ($estado) {
            Notificacion::LEIDA => ['estado' => $estado, 'leida_en' => $notificacion->leida_en ?? $ahora, 'archivada_en' => null],
            Notificacion::NO_LEIDA => ['estado' => $estado, 'leida_en' => null, 'archivada_en' => null],
            Notificacion::ARCHIVADA => ['estado' => $estado, 'leida_en' => $notificacion->leida_en ?? $ahora, 'archivada_en' => $ahora],
        });

        return $notificacion;
    }

    /** RF-058: marca como leídas todas las no leídas. Devuelve cuántas cambió. */
    public static function marcarTodasLeidas(string $usuarioId): int
    {
        return Notificacion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('estado', Notificacion::NO_LEIDA)
            ->update(['estado' => Notificacion::LEIDA, 'leida_en' => now(), 'actualizado_en' => now()]);
    }

    /** RN-058.4: número de no leídas para la campana. */
    public static function noLeidas(string $usuarioId): int
    {
        return Notificacion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('estado', Notificacion::NO_LEIDA)
            ->count();
    }

    /** Borra las archivadas hace más de `dias_archivadas` días. */
    public static function purgarArchivadas(?CarbonImmutable $hoy = null): int
    {
        $limite = ($hoy ?? CarbonImmutable::now())->subDays((int) config('scfp.alertas.dias_archivadas'));

        return Notificacion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('estado', Notificacion::ARCHIVADA)
            ->where('archivada_en', '<', $limite)
            ->delete();
    }

    /**
     * RN-059.1: la notificación también por correo. Si el envío falla queda en
     * el log y la notificación de la aplicación se conserva.
     */
    private static function enviarCorreo(Notificacion $notificacion): void
    {
        $usuario = Usuario::find($notificacion->usuario_id);

        if ($usuario === null) {
            return;
        }

        [$asunto, $cuerpo] = self::textoCorreo($usuario, $notificacion);

        try {
            Mail::raw($cuerpo, fn ($mensaje) => $mensaje->to($usuario->correo)->subject($asunto));
        } catch (\Throwable $e) {
            Log::warning("No se pudo enviar la notificación por correo (RN-059.1): {$e->getMessage()}", [
                'usuario_id' => $usuario->id,
                'notificacion_id' => $notificacion->id,
            ]);

            return;
        }

        $notificacion->update(['correo_enviado_en' => now()]);
    }

    /** @return array{0: string, 1: string} asunto y cuerpo */
    private static function textoCorreo(Usuario $usuario, Notificacion $notificacion): array
    {
        $d = $notificacion->datos;
        $mes = isset($d['mes']) ? self::nombreMes($d['mes']) : '';

        [$asunto, $detalle] = match ($notificacion->tipo) {
            ReglaAlertaService::UMBRAL_LINEA => [
                "SCFP - {$d['nombre']} llegó al {$d['umbral']} % de su presupuesto",
                "{$d['nombre']} lleva ".self::pesos($d['gastado']).' de '.self::pesos($d['asignado'])." en {$mes}: el ".self::porcentaje($d['ejecucion_porc']).' de lo asignado.',
            ],
            ReglaAlertaService::PRESUPUESTO_TOTAL => [
                "SCFP - Tu presupuesto de {$mes} llegó al {$d['umbral']} %",
                'Llevas '.self::pesos($d['gastado']).' de '.self::pesos($d['asignado']).' en todas las líneas del presupuesto: el '.self::porcentaje($d['ejecucion_porc']).'.',
            ],
            ReglaAlertaService::PROYECCION_LINEA => [
                "SCFP - {$d['nombre']} podría cerrar {$mes} por encima del presupuesto",
                "Con lo que sueles gastar el resto del mes, {$d['nombre']} cerraría en ".self::pesos($d['proyeccion']).': el '.self::porcentaje($d['proyeccion_porc']).' de '.self::pesos($d['asignado']).'. Te quedan '.self::pesos($d['disponible']).'.',
            ],
            ReglaAlertaService::INGRESOS_VS_GASTOS => [
                ($d['cierre'] ? "SCFP - En {$mes} gastaste más de lo que ingresó" : "SCFP - Estás gastando más de lo que ganas en {$mes}"),
                'Gastos: '.self::pesos($d['gastos']).'. Ingresos netos: '.self::pesos($d['ingresos_recibidos'])
                    .($d['cierre'] ? '' : ' recibidos y '.self::pesos($d['ingresos_pendientes']).' por recibir')
                    .'. Diferencia: '.self::pesos($d['diferencia']).'.',
            ],
            ReglaAlertaService::GASTO_PROYECTADO_ALTO => [
                "SCFP - Tu gasto de {$mes} podría llegar al ".self::porcentaje($d['porcentaje']).' de tu ingreso',
                'Con lo que llevas y lo que sueles gastar el resto del mes, cerrarías en '.self::pesos($d['proyeccion']).' frente a un ingreso promedio de '.self::pesos($d['ingreso_promedio']).'.',
            ],
            ReglaAlertaService::LIMITE_GASTO_MENSUAL => [
                "SCFP - Pasaste tu tope de gasto de {$mes}",
                'Llevas '.self::pesos($d['gastado']).' y tu tope es '.self::pesos($d['monto']).'.',
            ],
            ReglaAlertaService::GASTO_INUSUAL => [
                'SCFP - Registraste un gasto inusual',
                'Un gasto de '.self::pesos($d['monto'])." en {$d['subcategoria']} es ".self::porcentaje($d['veces_promedio'], '').' veces tu promedio ('.self::pesos($d['promedio']).').',
            ],
            ReglaAlertaService::GASTOS_HORMIGA => [
                "SCFP - Tus gastos pequeños de {$mes} ya suman ".self::pesos($d['total']),
                "{$d['cantidad']} gastos menores de ".self::pesos($d['monto_max']).' suman '.self::pesos($d['total']).' en el mes.',
            ],
            ReglaAlertaService::GASTO_DISCRECIONAL_ALTO => [
                "SCFP - Tus gastos discrecionales de {$mes} pasan el {$d['umbral']} % de tu ingreso",
                'Llevas '.self::pesos($d['discrecional']).' en gastos discrecionales: el '.self::porcentaje($d['porcentaje']).' de tu ingreso promedio.',
            ],
            ReglaAlertaService::TOPE_DIAN_TARJETA, ReglaAlertaService::TOPE_DIAN_COMPRAS => [
                "SCFP - Llegaste al {$d['umbral']} % del tope para declarar renta de {$d['anio']}",
                ($notificacion->tipo === ReglaAlertaService::TOPE_DIAN_TARJETA ? 'Tus consumos con tarjeta de crédito' : 'Tus compras y consumos')
                    ." de {$d['anio']} suman ".self::pesos($d['acumulado']).' de '.self::pesos($d['tope'])." ({$d['tope_uvt']} UVT): el ".self::porcentaje($d['porcentaje']).'.',
            ],
            ReglaAlertaService::USO_CUPO_TARJETA => [
                "SCFP - Tu tarjeta {$d['banco']} usa el ".self::porcentaje($d['porcentaje']).' del cupo',
                "Los consumos de tu tarjeta {$d['banco']} suman ".self::pesos($d['saldo']).' de un cupo de '.self::pesos($d['cupo']).' (disponible: '.self::pesos($d['disponible']).'). Superaste tu umbral del '.$d['umbral'].' %.',
            ],
            ReglaAlertaService::ENDEUDAMIENTO => [
                'SCFP - Tu nivel de endeudamiento subió',
                'Tus cuotas de este mes son el '.self::porcentaje($d['indicador_porc']).' de tu ingreso promedio (superan el '.$d['umbral'].' %).',
            ],
            ReglaAlertaService::VENCIMIENTOS => self::textoVencimiento($d),
            ReglaAlertaService::SEGURIDAD_CUENTA => self::textoSeguridad($d),
            default => ['SCFP - Tienes una notificación nueva', 'Tienes una notificación nueva en el centro de notificaciones.'],
        };

        return [$asunto, "Hola {$usuario->nombres},\n\n{$detalle}\n\nPuedes ver el detalle en el centro de notificaciones de FinanzApp."];
    }

    /** @return array{0: string, 1: string} */
    private static function textoVencimiento(array $d): array
    {
        $fecha = CarbonImmutable::parse($d['fecha'])->format('d/m/Y');

        return match ($d['subtipo']) {
            'CUOTA_VENCIDA' => [
                "SCFP - Tienes una cuota vencida con {$d['banco']}",
                "La cuota {$d['numero']} de tu crédito con {$d['banco']} venció el {$fecha} y tiene ".self::pesos($d['pendiente']).' pendientes.',
            ],
            'CUOTA_PROXIMA' => [
                "SCFP - Tu cuota con {$d['banco']} vence el {$fecha}",
                "La cuota {$d['numero']} de tu crédito con {$d['banco']} vence el {$fecha}: ".self::pesos($d['pendiente']).' por pagar.',
            ],
            'RECURRENTE_POR_CONFIRMAR' => [
                'SCFP - Tienes un gasto recurrente por confirmar',
                "El gasto recurrente de {$d['subcategoria']} del {$fecha} (".self::pesos($d['monto']).') espera que confirmes el monto real.',
            ],
            default => [
                "SCFP - El {$fecha} se genera un gasto con monto estimado",
                'El '.$fecha.' se genera un gasto recurrente estimado en '.self::pesos($d['monto_estimado']).'. Recuerda confirmar el monto real.',
            ],
        };
    }

    /** @return array{0: string, 1: string} */
    private static function textoSeguridad(array $d): array
    {
        return match ($d['evento'] ?? null) {
            'CAMBIO_CONTRASENA' => ['SCFP - Se cambió la contraseña de tu cuenta', 'La contraseña de tu cuenta se cambió. Si no fuiste tú, restablécela de inmediato y revisa tus sesiones activas.'],
            'RESTABLECIMIENTO_CONTRASENA' => ['SCFP - Se restableció la contraseña de tu cuenta', 'La contraseña de tu cuenta se restableció con el enlace de recuperación. Si no fuiste tú, contáctanos de inmediato.'],
            'BLOQUEO_TEMPORAL' => ['SCFP - Tu cuenta se bloqueó temporalmente', 'Tu cuenta se bloqueó por varios intentos fallidos de inicio de sesión.'],
            'SOLICITUD_SUPRESION' => ['SCFP - Recibimos la solicitud de supresión de tu cuenta', 'Recibimos la solicitud para eliminar tu cuenta y tus datos.'],
            default => ['SCFP - Actividad de seguridad en tu cuenta', 'Hubo actividad de seguridad en tu cuenta.'],
        };
    }

    private static function nombreMes(string $mes): string
    {
        return CarbonImmutable::createFromFormat('!Y-m', $mes)->locale('es')->translatedFormat('F \d\e Y');
    }

    private static function pesos(string $valor): string
    {
        $negativo = str_starts_with($valor, '-');

        return ($negativo ? '− ' : '').'$ '.number_format((float) ltrim($valor, '-'), 0, ',', '.');
    }

    private static function porcentaje(?string $valor, string $sufijo = ' %'): string
    {
        return $valor === null ? '—' : str_replace('.', ',', $valor).$sufijo;
    }
}
