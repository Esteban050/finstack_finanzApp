<?php

namespace App\Http\Controllers;

use App\Models\PreferenciaNotificacion;
use App\Services\ReglaAlertaService;
use App\Services\ResumenCorreoService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * MOD-09 — RF-057 / RF-059: reglas de alerta del Usuario y su resumen por
 * correo (Mi perfil › Alertas y notificaciones). Solo JSON.
 */
class ReglaAlertaController extends Controller
{
    /** RF-057 / RN-059.3: reglas vigentes y preferencias del resumen. */
    public function index(Request $request): JsonResponse|View
    {
        if (!$request->expectsJson()) {
            return view('planning.index', ['workspacePage' => 'alerts']);
        }
        return response()->json([
            'mensaje' => 'Reglas de alerta consultadas.',
            'data' => $this->respuesta(),
        ]);
    }

    /**
     * RF-057: guarda los tipos enviados (los campos que no vengan no
     * cambian). RN-059.3: y, si viene, el resumen por correo.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reglas' => ['required_without:resumen', 'array', 'min:1'],
            'reglas.*' => ['array'],
            'reglas.*.tipo' => ['required', 'distinct', Rule::in(ReglaAlertaService::tipos())],
            'reglas.*.activa' => ['sometimes', 'boolean'],
            'reglas.*.enviar_correo' => ['sometimes', 'boolean'],
            'reglas.*.parametros' => ['sometimes', 'array'],
            'resumen' => ['sometimes', 'array'],
            'resumen.frecuencia' => ['sometimes', Rule::in(PreferenciaNotificacion::FRECUENCIAS)],
            'resumen.bloques' => ['sometimes', 'array', 'min:1'],
            'resumen.bloques.*' => ['distinct', Rule::in(PreferenciaNotificacion::BLOQUES)],
        ], [
            'reglas.required_without' => 'Envíe al menos una regla o el resumen por correo.',
            'reglas.array' => 'Las reglas deben ser una lista.',
            'reglas.min' => 'Envíe al menos una regla.',
            'reglas.*.array' => 'Cada regla debe ser un objeto.',
            'reglas.*.tipo.required' => 'Indique el tipo de la regla.',
            'reglas.*.tipo.distinct' => 'Cada tipo de regla va una sola vez.',
            'reglas.*.tipo.in' => 'El tipo de regla no existe.',
            'reglas.*.activa.boolean' => 'activa debe ser verdadero o falso.',
            'reglas.*.enviar_correo.boolean' => 'enviar_correo debe ser verdadero o falso.',
            'reglas.*.parametros.array' => 'Los parámetros de la regla deben ser un objeto.',
            'resumen.array' => 'El resumen debe ser un objeto.',
            'resumen.frecuencia.in' => 'La frecuencia del resumen debe ser NINGUNA, SEMANAL o MENSUAL.',
            'resumen.bloques.array' => 'Los bloques del resumen deben ser una lista.',
            'resumen.bloques.min' => 'Elija al menos un bloque para el resumen.',
            'resumen.bloques.*.distinct' => 'Cada bloque va una sola vez.',
            'resumen.bloques.*.in' => 'Bloques admitidos: INGRESOS_GASTOS, PRESUPUESTO, ALERTAS y VENCIMIENTOS.',
        ]);

        if (isset($validated['reglas'])) {
            ReglaAlertaService::guardar(Auth::id(), array_values($validated['reglas']));
        }

        if (isset($validated['resumen'])) {
            ResumenCorreoService::guardarPreferencias(
                Auth::id(),
                $validated['resumen']['frecuencia'] ?? null,
                $validated['resumen']['bloques'] ?? null
            );
        }

        return response()->json([
            'mensaje' => 'Reglas de alerta guardadas.',
            'data' => $this->respuesta(),
        ]);
    }

    /** RF-057: vuelve a los valores sugeridos (el resumen queda apagado). */
    public function restablecer(): JsonResponse
    {
        ReglaAlertaService::restablecer(Auth::id());
        ResumenCorreoService::restablecer(Auth::id());

        return response()->json([
            'mensaje' => 'Reglas de alerta restablecidas a los valores sugeridos.',
            'data' => $this->respuesta(),
        ]);
    }

    /** @return array{reglas: array<int, array<string, mixed>>, resumen: array<string, mixed>} */
    private function respuesta(): array
    {
        return [
            'reglas' => array_values(ReglaAlertaService::vigentes(Auth::id())),
            'resumen' => ResumenCorreoService::preferencias(Auth::id()),
        ];
    }
}
