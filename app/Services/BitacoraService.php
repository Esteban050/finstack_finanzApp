<?php

namespace App\Services;

use App\Models\BitacoraAuditoria;
use Illuminate\Support\Facades\Request;

class BitacoraService
{
    /**
     * Registrar un evento en la bitácora de auditoría.
     */
    public static function registrar(
        string $accion,
        string $entidadAfectada,
        ?string $entidadId = null,
        ?array $valorAnterior = null,
        ?array $valorNuevo = null,
        ?string $justificacion = null,
        ?string $usuarioId = null
    ): BitacoraAuditoria {
        return BitacoraAuditoria::create([
            'usuario_id' => $usuarioId ?? auth()->id(),
            'accion' => $accion,
            'entidad_afectada' => $entidadAfectada,
            'entidad_id' => $entidadId,
            'valor_anterior' => $valorAnterior,
            'valor_nuevo' => $valorNuevo,
            'justificacion' => $justificacion,
            'ip_origen' => Request::ip(),
            'fecha_evento' => now(),
        ]);
    }
}
