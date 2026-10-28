<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TasaCambio;
use App\Services\BitacoraService;
use App\Services\TrmService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * RF-017 — Administración de la TRM (MOD-02).
 *
 * Solo responde JSON: esta entrega no incluye interfaz Blade.
 */
class TrmController extends Controller
{
    /** RNG-10: Opciones de paginación. */
    private const POR_PAGINA = [10, 20, 50, 100];

    /**
     * RF-017: Histórico de la TRM, de la más reciente a la más antigua (RNG-10).
     */
    public function index(Request $request): JsonResponse
    {
        $porPagina = (int) $request->query('por_pagina', 20);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 20;
        }

        $tasas = TasaCambio::with('registradoPor:id,nombres,apellidos')
            ->where('moneda', TrmService::MONEDA)
            ->orderByDesc('vigencia_desde')
            ->paginate($porPagina)
            ->withQueryString();

        return response()->json([
            'mensaje' => 'Histórico de la TRM consultado.',
            'data' => [
                'tasas' => $tasas->items(),
                'meta' => [
                    'pagina_actual' => $tasas->currentPage(),
                    'por_pagina' => $tasas->perPage(),
                    'total' => $tasas->total(),
                    'ultima_pagina' => $tasas->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * RN-017.3: El Administrador registra o corrige la TRM de una fecha, con una
     * justificación que queda en la bitácora. La corrección no cambia los
     * registros que ya usaron la TRM anterior (CA-017.2).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // La TRM de mañana se publica hoy, así que se admite un día adelante.
            'fecha' => ['required', 'date', 'before_or_equal:tomorrow'],
            'valor' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,8}(\.\d{1,4})?$/'],
            'justificacion' => ['required', 'string', 'min:20', 'max:500'],
        ], [
            'fecha.required' => 'Indique la fecha de la TRM.',
            'fecha.date' => 'La fecha no tiene un formato válido (AAAA-MM-DD).',
            'fecha.before_or_equal' => 'Solo se puede registrar la TRM hasta el día de mañana.',
            'valor.required' => 'Indique el valor de la TRM.',
            'valor.numeric' => 'El valor de la TRM debe ser un número.',
            'valor.gt' => 'El valor de la TRM debe ser mayor que cero.',
            'valor.regex' => 'El valor de la TRM admite hasta 8 dígitos enteros y 4 decimales.',
            'justificacion.required' => 'La justificación es obligatoria (RN-017.3).',
            'justificacion.min' => 'La justificación debe tener al menos 20 caracteres.',
            'justificacion.max' => 'La justificación no puede superar los 500 caracteres.',
        ]);

        $fecha = CarbonImmutable::parse($validated['fecha'])->toDateString();

        [$anterior, $trm] = TrmService::registrarManual(
            $fecha,
            (string) $validated['valor'],
            $validated['justificacion'],
            Auth::id()
        );

        BitacoraService::registrar(
            accion: $anterior === null ? 'REGISTRAR_TRM_MANUAL' : 'CORREGIR_TRM',
            entidadAfectada: 'tasas_cambio',
            entidadId: $trm->id,
            valorAnterior: $anterior,
            valorNuevo: $trm->toArray(),
            justificacion: trim($validated['justificacion']),
            usuarioId: Auth::id()
        );

        return response()->json([
            'mensaje' => $anterior === null
                ? 'TRM registrada manualmente.'
                : 'TRM corregida. Los registros que ya usaron la TRM anterior no cambian.',
            'data' => $trm,
        ], $anterior === null ? 201 : 200);
    }

    /**
     * RN-017.1: Forzar ahora la consulta a la fuente oficial. Un fallo cuenta
     * como intento del día, igual que los del comando programado.
     */
    public function sincronizar(): JsonResponse
    {
        $hoy = CarbonImmutable::today();

        try {
            $trm = TrmService::sincronizar($hoy);
        } catch (\Throwable $e) {
            $intento = TrmService::registrarFallo($e, $hoy);

            return response()->json([
                'mensaje' => 'No se pudo obtener la TRM de la fuente oficial (intento '.$intento.' de '.TrmService::INTENTOS_DIARIOS.' del día).',
            ], 503);
        }

        BitacoraService::registrar(
            accion: 'SINCRONIZAR_TRM',
            entidadAfectada: 'tasas_cambio',
            entidadId: $trm->id,
            valorNuevo: $trm->toArray(),
            justificacion: 'RN-017.1: sincronización solicitada por el Administrador.',
            usuarioId: Auth::id()
        );

        return response()->json([
            'mensaje' => 'TRM sincronizada con la fuente oficial.',
            'data' => TrmService::presentar($trm, $hoy),
        ]);
    }
}
