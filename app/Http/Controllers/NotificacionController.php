<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use App\Services\NotificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * MOD-09 — RF-058: centro de notificaciones. Solo JSON y con datos crudos: el
 * texto de cada notificación lo arma el frontend con su tipo y `datos`.
 */
class NotificacionController extends Controller
{
    /** RNG-10: tamaños de página admitidos. */
    private const POR_PAGINA = [10, 20, 50, 100];

    /**
     * RF-058: bandeja, de la más nueva a la más vieja. Sin filtro de estado
     * muestra las no leídas y las leídas (las archivadas se piden aparte).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['nullable', Rule::in([Notificacion::NO_LEIDA, Notificacion::LEIDA, Notificacion::ARCHIVADA])],
            'prioridad' => ['nullable', Rule::in([Notificacion::INFORMATIVA, Notificacion::ADVERTENCIA, Notificacion::CRITICA])],
            'tipo' => ['nullable', 'string', 'max:40'],
            'por_pagina' => ['nullable', 'integer', Rule::in(self::POR_PAGINA)],
        ], [
            'estado.in' => 'El estado debe ser NO_LEIDA, LEIDA o ARCHIVADA.',
            'prioridad.in' => 'La prioridad debe ser INFORMATIVA, ADVERTENCIA o CRITICA.',
            'tipo.max' => 'El tipo admite máximo 40 caracteres.',
            'por_pagina.in' => 'Tamaños de página admitidos: 10, 20, 50 o 100.',
        ]);

        $pagina = Notificacion::query()
            ->when(
                $validated['estado'] ?? null,
                fn ($q, $estado) => $q->where('estado', $estado),
                fn ($q) => $q->whereIn('estado', [Notificacion::NO_LEIDA, Notificacion::LEIDA])
            )
            ->when($validated['prioridad'] ?? null, fn ($q, $prioridad) => $q->where('prioridad', $prioridad))
            ->when($validated['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->orderByDesc('creado_en')
            ->orderByDesc('id')
            ->paginate((int) ($validated['por_pagina'] ?? 20))
            ->withQueryString();

        return response()->json([
            'mensaje' => 'Notificaciones consultadas.',
            'data' => [
                'notificaciones' => collect($pagina->items())->map(fn (Notificacion $n) => $this->presentar($n))->all(),
                'no_leidas' => NotificacionService::noLeidas(Auth::id()),
                'meta' => [
                    'pagina_actual' => $pagina->currentPage(),
                    'por_pagina' => $pagina->perPage(),
                    'total' => $pagina->total(),
                    'ultima_pagina' => $pagina->lastPage(),
                ],
            ],
        ]);
    }

    /** RN-058.4: número sin leer para la campana. */
    public function contador(): JsonResponse
    {
        return response()->json([
            'mensaje' => 'Notificaciones sin leer.',
            'data' => ['no_leidas' => NotificacionService::noLeidas(Auth::id())],
        ]);
    }

    /** RN-058.3: marcar como leída. */
    public function leida(string $id): JsonResponse
    {
        return $this->cambiar($id, Notificacion::LEIDA, 'Notificación marcada como leída.');
    }

    /** RN-058.3: volver a no leída. */
    public function noLeida(string $id): JsonResponse
    {
        return $this->cambiar($id, Notificacion::NO_LEIDA, 'Notificación marcada como no leída.');
    }

    /** RN-058.3: archivar (se borra a los 180 días). */
    public function archivar(string $id): JsonResponse
    {
        return $this->cambiar($id, Notificacion::ARCHIVADA, 'Notificación archivada.');
    }

    /** RF-058: marcar todas como leídas. */
    public function marcarTodasLeidas(): JsonResponse
    {
        $cambiadas = NotificacionService::marcarTodasLeidas(Auth::id());

        return response()->json([
            'mensaje' => 'Notificaciones marcadas como leídas.',
            'data' => ['marcadas' => $cambiadas, 'no_leidas' => 0],
        ]);
    }

    /** RNG-01: el global scope responde 404 a una notificación ajena. */
    private function cambiar(string $id, string $estado, string $mensaje): JsonResponse
    {
        $notificacion = Notificacion::findOrFail($id);
        NotificacionService::cambiarEstado($notificacion, $estado);

        return response()->json([
            'mensaje' => $mensaje,
            'data' => [
                ...$this->presentar($notificacion->refresh()),
                'no_leidas' => NotificacionService::noLeidas(Auth::id()),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function presentar(Notificacion $n): array
    {
        return [
            'id' => $n->id,
            'tipo' => $n->tipo,
            'prioridad' => $n->prioridad,
            'estado' => $n->estado,
            'datos' => $n->datos,
            'referencia_tipo' => $n->referencia_tipo,
            'referencia_id' => $n->referencia_id,
            'creado_en' => $n->creado_en?->toIso8601String(),
            'leida_en' => $n->leida_en?->toIso8601String(),
            'archivada_en' => $n->archivada_en?->toIso8601String(),
            'correo_enviado' => $n->correo_enviado_en !== null,
        ];
    }
}
