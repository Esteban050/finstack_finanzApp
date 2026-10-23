<?php

namespace App\Http\Controllers;

use App\Models\AceptacionPolitica;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PoliticaTratamientoController extends Controller
{
    /**
     * RF-009 RN-009.3: Consultar texto de la política y el historial de aceptaciones
     */
    public function mostrarPolitica(): View
    {
        $usuario = Auth::user();
        $versionVigente = config('app.version_politica_datos', 'v1.0');
        $historial = $usuario ? $usuario->aceptacionesPolitica()->orderByDesc('fecha_aceptacion')->get() : collect();

        return view('politica.mostrar', compact('versionVigente', 'historial'));
    }

    /**
     * RF-009 RN-009.2: Aceptación obligatoria de nueva versión de la política
     */
    public function aceptarPolitica(Request $request): RedirectResponse
    {
        $request->validate(['acepto' => 'required|accepted']);
        $usuario = Auth::user();
        $versionVigente = config('app.version_politica_datos', 'v1.0');

        AceptacionPolitica::create([
            'usuario_id' => $usuario->id,
            'version_politica' => $versionVigente,
            'fecha_aceptacion' => now(),
            'ip_origen' => $request->ip() ?? '127.0.0.1',
            'medio' => 'WEB_ACTUALIZACION',
        ]);

        BitacoraService::registrar(
            accion: 'ACEPTACION_NUEVA_POLITICA_DATOS',
            entidadAfectada: 'aceptaciones_politica',
            valorNuevo: ['version_politica' => $versionVigente],
            usuarioId: $usuario->id
        );

        return redirect()->intended('/dashboard')->with('status', 'Política de tratamiento de datos aceptada exitosamente.');
    }
}
