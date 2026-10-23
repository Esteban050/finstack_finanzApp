<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPoliticaAceptadaMiddleware
{
    /**
     * Handle an incoming request.
     * RN-009.2: Si hay una nueva versión de la política, el usuario debe aceptarla.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $usuario = Auth::user();
            $versionVigente = config('app.version_politica_datos', 'v1.0');

            // Verificar si el usuario ya aceptó la versión vigente
            $haAceptado = $usuario->aceptacionesPolitica()
                ->where('version_politica', $versionVigente)
                ->whereNull('revocada_en')
                ->exists();

            if (!$haAceptado && !$request->routeIs('politica.*') && !$request->routeIs('logout')) {
                return redirect()->route('politica.mostrar');
            }
        }

        return $next($request);
    }
}
