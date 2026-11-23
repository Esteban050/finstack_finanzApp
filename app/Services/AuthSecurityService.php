<?php

namespace App\Services;

use App\Models\BitacoraAuditoria;
use App\Models\SesionUsuario;
use App\Models\Usuario;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class AuthSecurityService
{
    /** RN-004.1: ventana en la que se cuentan los intentos fallidos consecutivos. */
    public const VENTANA_INTENTOS_SEGUNDOS = 15 * 60;

    /**
     * Registrar intento fallido de inicio de sesión (RF-004, RN-004.1)
     */
    public static function registrarIntentoFallido(Usuario $usuario): void
    {
        // RN-004.1: solo cuentan los fallos consecutivos de los últimos 15 minutos.
        // intentos_fallidos dice cuántos fallos seguidos lleva la cuenta (se pone en
        // 0 al entrar, al desbloquear o al restablecer la contraseña); la hora de
        // cada uno se toma de la bitácora, y los que quedaron fuera de la ventana
        // dejan de contar.
        $consecutivos = (int) $usuario->intentos_fallidos;
        $enVentana = 0;
        if ($consecutivos > 0) {
            $desde = now()->subSeconds(self::VENTANA_INTENTOS_SEGUNDOS);
            $enVentana = BitacoraAuditoria::where('usuario_id', $usuario->id)
                ->where('accion', 'INTENTO_LOGIN_FALLIDO')
                ->orderByDesc('fecha_evento')
                ->limit($consecutivos)
                ->pluck('fecha_evento')
                ->filter(fn ($fecha) => $fecha !== null && $fecha->greaterThanOrEqualTo($desde))
                ->count();
        }
        $usuario->update(['intentos_fallidos' => $enVentana + 1]);

        BitacoraService::registrar(
            accion: 'INTENTO_LOGIN_FALLIDO',
            entidadAfectada: 'usuarios',
            entidadId: $usuario->id,
            valorNuevo: ['intentos_fallidos' => $usuario->intentos_fallidos],
            usuarioId: $usuario->id
        );

        if ($usuario->intentos_fallidos >= 5) {
            $bloqueadoHasta = now()->addMinutes(15);
            $usuario->update([
                'estado' => 'BLOQUEADA',
                'bloqueado_hasta' => $bloqueadoHasta,
            ]);

            BitacoraService::registrar(
                accion: 'BLOQUEO_TEMPORAL_CUENTA',
                entidadAfectada: 'usuarios',
                entidadId: $usuario->id,
                valorNuevo: ['bloqueado_hasta' => $bloqueadoHasta->toIso8601String()],
                justificacion: '5 intentos fallidos consecutivos en 15 minutos (RN-004.1)',
                usuarioId: $usuario->id
            );

            // RN-004.3: Enviar correo al titular informando el bloqueo, hora e IP
            self::enviarNotificacionBloqueo($usuario);

            // RN-059.4: también en el centro de notificaciones (el correo ya salió).
            NotificacionService::seguridadCuenta($usuario->id, 'BLOQUEO_TEMPORAL', [
                'bloqueado_hasta' => $bloqueadoHasta->toIso8601String(),
                'ip' => Request::ip(),
            ], enviarCorreo: false);
        }
    }

    /**
     * Registrar inicio de sesión exitoso
     */
    public static function registrarInicioSesionExitoso(Usuario $usuario, bool $recordarDispositivo = false): SesionUsuario
    {
        $usuario->update([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'ultimo_acceso' => now(),
        ]);

        $jti = (string) Str::uuid();
        $ip = Request::ip() ?? '127.0.0.1';
        $userAgent = Request::userAgent();

        // RF-006: Inactividad 15 min, max duración 8 horas
        $expiraEn = now()->addHours(8);
        $recordarHasta = $recordarDispositivo ? now()->addDays(30) : null;

        $sesion = SesionUsuario::create([
            'usuario_id' => $usuario->id,
            'token_jti' => $jti,
            'ip_origen' => $ip,
            'user_agent' => $userAgent,
            'recordar_dispositivo_until' => $recordarHasta,
            'ultima_actividad' => now(),
            'expira_en' => $expiraEn,
            'revocada' => false,
            'creado_en' => now(),
        ]);

        // RN-003.3: JTI en sesión y en cookie HttpOnly, SameSite=Strict y Secure.
        // Secure sigue la misma configuración que la cookie de sesión de Laravel
        // (SESSION_SECURE_COOKIE): desactivada en local por HTTP y activa en HTTPS.
        session(['scfp_jti' => $jti]);
        Cookie::queue(
            Cookie::make('scfp_session_token', $jti, 480, '/', null, (bool) config('session.secure', false), true, false, 'Strict')
        );

        BitacoraService::registrar(
            accion: 'INICIO_SESION_EXITOSO',
            entidadAfectada: 'usuarios',
            entidadId: $usuario->id,
            valorNuevo: ['jti' => $jti, 'ip' => $ip],
            usuarioId: $usuario->id
        );

        return $sesion;
    }

    /**
     * Cerrar sesión actual (RF-006, RN-006.3)
     */
    public static function cerrarSesionActual(?string $jti = null): void
    {
        $jti = $jti ?? session('scfp_jti') ?? Request::cookie('scfp_session_token');

        if ($jti) {
            SesionUsuario::where('token_jti', $jti)->update(['revocada' => true]);
        }

        BitacoraService::registrar(
            accion: 'CIERRE_SESION',
            entidadAfectada: 'sesiones_usuario',
            valorNuevo: ['jti' => $jti],
            usuarioId: auth()->id()
        );

        session()->forget('scfp_jti');
        Cookie::queue(Cookie::forget('scfp_session_token'));
    }

    /**
     * Cerrar todas las demás sesiones excepto la actual (RF-007, RN-007.2)
     */
    public static function cerrarOtrasSesiones(Usuario $usuario, ?string $currentJti = null): void
    {
        $currentJti = $currentJti ?? session('scfp_jti');

        SesionUsuario::where('usuario_id', $usuario->id)
            ->where('token_jti', '!=', $currentJti)
            ->where('revocada', false)
            ->update(['revocada' => true]);
    }

    /**
     * Cerrar todas las sesiones activas (RF-005, RN-005.4)
     */
    public static function cerrarTodasLasSesiones(Usuario $usuario): void
    {
        SesionUsuario::where('usuario_id', $usuario->id)
            ->where('revocada', false)
            ->update(['revocada' => true]);

        self::olvidarDispositivosRecordados($usuario);
    }

    /**
     * RN-012.6: cambiar la contraseña anula todos los dispositivos recordados,
     * también el actual; en el próximo inicio de sesión se vuelve a pedir el código.
     */
    public static function olvidarDispositivosRecordados(Usuario $usuario): void
    {
        SesionUsuario::where('usuario_id', $usuario->id)
            ->whereNotNull('recordar_dispositivo_until')
            ->update(['recordar_dispositivo_until' => null]);
    }

    /**
     * Verifica si el dispositivo actual está recordado para MFA (RF-012, RN-012.6)
     */
    public static function dispositivoEstaRecordado(Usuario $usuario): bool
    {
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        return SesionUsuario::where('usuario_id', $usuario->id)
            ->where('ip_origen', $ip)
            ->where('user_agent', $userAgent)
            ->whereNotNull('recordar_dispositivo_until')
            ->where('recordar_dispositivo_until', '>', now())
            ->exists();
    }

    protected static function enviarNotificacionBloqueo(Usuario $usuario): void
    {
        // Enviar email informando fecha, hora, IP (RN-004.3)
        // Se puede usar Mailable o log si Mailer no está configurado
        try {
            Mail::raw(
                "Hola {$usuario->nombres},\n\nTu cuenta ha sido bloqueada temporalmente por 15 minutos debido a 5 intentos fallidos consecutivos.\nHora: " . now()->format('Y-m-d H:i:s') . "\nIP de origen: " . Request::ip() . "\n\nSi no fuiste tú, te recomendamos restablecer tu contraseña.",
                function ($message) use ($usuario) {
                    $message->to($usuario->correo)
                        ->subject('Alerta de seguridad: Cuenta bloqueada temporalmente - SCFP');
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("No se pudo enviar correo de bloqueo: {$e->getMessage()}");
        }
    }
}
