<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminMiddleware
{
    /**
     * Handle an incoming request.
     * CA-003.2 / CA-013.3: Acceso denegado (HTTP 403) a no administradores.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !auth()->user()->esAdmin()) {
            abort(403, 'Acceso denegado. Se requieren permisos de Administrador.');
        }

        return $next($request);
    }
}
