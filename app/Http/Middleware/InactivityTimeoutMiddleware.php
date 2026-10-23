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

                // Verificar inactividad > 15 minutos
                $minutosInactivo = now()->diffInMinutes($sesion->ultima_actividad);
                if ($minutosInactivo >= 15) {
                    AuthSecurityService::cerrarSesionActual($jti);
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors([
                        'correo' => 'Tu sesión expiró por inactividad.',
                    ]);
                }

                // Verificar tiempo máximo de 8 horas desde creación
                if (now()->diffInHours($sesion->creado_en) >= 8) {
                    AuthSecurityService::cerrarSesionActual($jti);
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors([
                        'correo' => 'Tu sesión ha expirado por límite de tiempo máximo (8 horas).',
                    ]);
                }

                // Actualizar timestamp de última actividad
                $sesion->update(['ultima_actividad' => now()]);
            }
        }

        return $next($request);
    }
}
