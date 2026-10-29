<?php

namespace App\Http\Controllers;

use App\Models\Inversion;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Services\BitacoraService;
use App\Services\RentabilidadService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * MOD-06 — Rentabilidad de una inversión (RF-040) y consolidado del
 * portafolio (RF-041), a una fecha de corte.
 *
 * Solo responde JSON: esta entrega no incluye interfaz Blade.
 */
class RentabilidadController extends Controller
{
    /**
     * RF-040: ROI, XIRR y rentabilidad real de la inversión a la fecha de
     * corte (por defecto, hoy), en la moneda de la inversión.
     */
    public function inversion(Request $request, string $id): JsonResponse
    {
        $inversion = $this->inversionPropia($id);
        $corte = $this->fechaCorte($request);

        if ($corte->lt(CarbonImmutable::parse($inversion->fecha_apertura))) {
            throw ValidationException::withMessages([
                'fecha_corte' => 'La fecha de corte no puede ser anterior a la apertura de la inversión.',
            ]);
        }

        return response()->json([
            'mensaje' => 'Rentabilidad calculada.',
            'data' => array_merge([
                'inversion_id' => $inversion->id,
                'nombre' => $inversion->nombre,
            ], RentabilidadService::indicadores($inversion, $corte)),
        ]);
    }

    /**
     * RF-041: Consolidado del portafolio a la fecha de corte (por defecto, hoy).
     */
    public function portafolio(Request $request): JsonResponse
    {
        return response()->json([
            'mensaje' => 'Portafolio consolidado.',
            'data' => RentabilidadService::portafolio($this->fechaCorte($request)),
        ]);
    }

    /** RF-040 / RF-041: fecha de corte, no posterior a hoy. */
    private function fechaCorte(Request $request): CarbonImmutable
    {
        $validated = $request->validate([
            'fecha_corte' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'fecha_corte.date' => 'La fecha de corte no tiene un formato válido (AAAA-MM-DD).',
            'fecha_corte.before_or_equal' => 'La fecha de corte no puede ser posterior a hoy.',
        ]);

        return isset($validated['fecha_corte'])
            ? CarbonImmutable::parse($validated['fecha_corte'])
            : CarbonImmutable::today();
    }

    /**
     * RNG-01: El global scope ya devolvió 404 para recursos ajenos. Aquí solo se
     * distingue el caso que la regla manda registrar en bitácora (RF-019).
     */
    private function inversionPropia(string $id): Inversion
    {
        $inversion = Inversion::find($id);

        if ($inversion !== null) {
            return $inversion;
        }

        $esDeOtro = Inversion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereKey($id)
            ->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'inversiones',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }
}
