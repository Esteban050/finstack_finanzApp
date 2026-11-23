<?php

namespace App\Http\Middleware;

use App\Models\SesionUsuario;
use App\Services\AuthSecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InactivityTimeoutMiddleware
{
    /** RN-006.1: 15 minutos sin actividad. */
    public const INACTIVIDAD_MAXIMA_SEGUNDOS = 15 * 60;

    /** RN-006.1: 8 horas desde que se creó la sesión, haya o no actividad. */
    public const DURACION_MAXIMA_SEGUNDOS = 8 * 60 * 60;

    /**
     * Handle an incoming request.
     * RN-006.1: Expira tras 15 minutos de inactividad o 8 horas totales.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $jti = session('scfp_jti') ?? $request->cookie('scfp_session_token');

            if ($jti) {
                $sesion = SesionUsuario::where('token_jti', $jti)->first();

                if (!$sesion || $sesion->revocada) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors([
                        'correo' => 'Tu sesión ha sido revocada.',
                    ]);
                }

                // RN-006.1: inactividad de 15 minutos. La diferencia se toma en valor
                // absoluto: en Carbon 3 now()->diffInSeconds(pasado) es negativa y la
                // sesión nunca expiraba.
                $ahora = now();
                $segundosInactivo = $sesion->ultima_actividad
                    ? $sesion->ultima_actividad->diffInSeconds($ahora, true)
                    : 0;
                if ($segundosInactivo >= self::INACTIVIDAD_MAXIMA_SEGUNDOS) {
                    AuthSecurityService::cerrarSesionActual($jti);
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors([
                        'correo' => 'Tu sesión expiró por inactividad.',
                    ]);
                }

                // RN-006.1: duración máxima de 8 horas desde que se creó la sesión.
                if ($sesion->creado_en && $sesion->creado_en->diffInSeconds($ahora, true) >= self::DURACION_MAXIMA_SEGUNDOS) {
                    AuthSecurityService::cerrarSesionActual($jti);
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors([
                        'correo' => 'Tu sesión ha expirado por límite de tiempo máximo (8 horas).',
                    ]);
                }

                // Actualizar timestamp de última actividad
                $sesion->update(['ultima_actividad' => $ahora]);
            }
        }

        return $next($request);
    }
}
