<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PerfilCompletoMiddleware
{
    /**
     * CA-008.1: Si el Usuario no completa el perfil de ocupación, el sistema no
     * deja usar MOD-05 ni MOD-07 y muestra "Completa tu perfil financiero para
     * usar este módulo".
     */
    public function handle(Request $request, Closure $next): Response
    {
        $perfil = Auth::user()?->perfilTributarioActivo;

        if ($perfil === null || empty($perfil->ocupaciones)) {
            $mensaje = 'Completa tu perfil financiero para usar este módulo';

            if ($request->expectsJson()) {
                return response()->json(['mensaje' => $mensaje], 403);
            }

            return redirect()->route('profile.show')->withErrors(['perfil' => $mensaje]);
        }

        return $next($request);
    }
}
