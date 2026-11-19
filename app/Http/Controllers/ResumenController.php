<?php

namespace App\Http\Controllers;

use App\Services\Reportes\DashboardResumenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * MOD-11 — HU-H01 y HU-H02: Controlador del Dashboard Principal / Resumen (RF-065).
 */
class ResumenController extends Controller
{
    public function __construct(
        protected DashboardResumenService $resumenService
    ) {}

    /**
     * Consulta el estado consolidado del Dashboard Principal.
     */
    public function index(Request $request): JsonResponse|View
    {
        $usuario = auth()->user();

        $periodo = (string) $request->query('periodo', 'mes-actual');
        $fechaDesde = $request->query('fecha_desde');
        $fechaHasta = $request->query('fecha_hasta');

        $datos = $this->resumenService->obtenerDashboard(
            $usuario,
            $periodo,
            $fechaDesde,
            $fechaHasta
        );

        if ($request->expectsJson() || !$request->hasHeader('Accept') || str_contains($request->header('Accept'), 'application/json')) {
            return response()->json([
                'mensaje' => 'Dashboard consolidado del periodo.',
                'data' => $datos,
            ]);
        }

        if (view()->exists('resumen.index')) {
            return view('resumen.index', compact('datos', 'usuario'));
        }

        return response()->json([
            'mensaje' => 'Dashboard consolidado del periodo.',
            'data' => $datos,
        ]);
    }
}
