<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TasaUsura;
use App\Services\BitacoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MOD-08 Créditos RN-01 / RF-07 — Administración de la tasa de usura por
 * modalidad y vigencia (MOD-02). El Administrador carga cada certificación de
 * la Superintendencia Financiera; los créditos la usan para alertar.
 *
 * Solo responde JSON: esta entrega no incluye interfaz Blade.
 */
class TasaUsuraController extends Controller
{
    /** RNG-10: Opciones de paginación. */
    private const POR_PAGINA = [10, 20, 50, 100];

    /**
     * Créditos RF-07: histórico de la usura, de la vigencia más reciente a la
     * más antigua, opcionalmente de una modalidad (RNG-10).
     */
    public function index(Request $request): JsonResponse
    {
        $porPagina = (int) $request->query('por_pagina', 20);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 20;
        }

        $modalidad = $request->query('modalidad');

        $tasas = TasaUsura::with('registradoPor:id,nombres,apellidos')
            ->when(in_array($modalidad, TasaUsura::MODALIDADES, true), fn ($q) => $q->where('modalidad', $modalidad))
            ->orderByDesc('vigente_desde')
            ->paginate($porPagina)
            ->withQueryString();

        return response()->json([
            'mensaje' => 'Histórico de la tasa de usura consultado.',
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
     * Créditos RN-01 / RF-07: registra una certificación. Las vigencias de una
     * misma modalidad no se superponen.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modalidad' => ['required', Rule::in(TasaUsura::MODALIDADES)],
            'tasa_ea' => ['required', 'numeric', 'gt:0', 'max:200', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'vigente_desde' => ['required', 'date_format:Y-m-d'],
            'vigente_hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:vigente_desde'],
            'acto_administrativo' => ['required', 'string', 'min:5', 'max:120'],
        ], [
            'modalidad.required' => 'Indique la modalidad de crédito.',
            'modalidad.in' => 'La modalidad debe ser CONSUMO_ORDINARIO, MICROCREDITO o CONSUMO_BAJO_MONTO.',
            'tasa_ea.required' => 'Indique la tasa de usura en % EA.',
            'tasa_ea.numeric' => 'La tasa de usura debe ser un número (en % EA).',
            'tasa_ea.gt' => 'La tasa de usura debe ser mayor que cero.',
            'tasa_ea.max' => 'La tasa de usura admite máximo 200 % EA.',
            'tasa_ea.regex' => 'La tasa de usura admite hasta 4 decimales.',
            'vigente_desde.required' => 'Indique desde cuándo rige la tasa.',
            'vigente_desde.date_format' => 'La fecha de inicio debe tener el formato AAAA-MM-DD.',
            'vigente_hasta.required' => 'Indique hasta cuándo rige la tasa.',
            'vigente_hasta.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'vigente_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la de inicio.',
            'acto_administrativo.required' => 'Indique la resolución de la Superintendencia Financiera que certifica la tasa.',
            'acto_administrativo.min' => 'La resolución debe tener al menos 5 caracteres.',
            'acto_administrativo.max' => 'La resolución admite máximo 120 caracteres.',
        ]);

        $superpuesta = TasaUsura::where('modalidad', $validated['modalidad'])
            ->where('vigente_desde', '<=', $validated['vigente_hasta'])
            ->where('vigente_hasta', '>=', $validated['vigente_desde'])
            ->first();

        if ($superpuesta !== null) {
            throw ValidationException::withMessages([
                'vigente_desde' => 'La vigencia se cruza con la certificación del '.$superpuesta->vigente_desde->format('d/m/Y').' al '.$superpuesta->vigente_hasta->format('d/m/Y').' de la misma modalidad.',
            ]);
        }

        $tasa = TasaUsura::create([
            ...$validated,
            'acto_administrativo' => trim($validated['acto_administrativo']),
            'registrado_por' => Auth::id(),
        ]);

        BitacoraService::registrar(
            accion: 'REGISTRAR_TASA_USURA',
            entidadAfectada: 'tasas_usura',
            entidadId: $tasa->id,
            valorNuevo: $tasa->fresh()->toArray(),
            justificacion: 'Créditos RN-01 / RF-07: certificación de la tasa de usura.',
            usuarioId: Auth::id()
        );

        return response()->json([
            'mensaje' => 'Tasa de usura registrada.',
            'data' => $tasa->fresh(),
        ], 201);
    }
}
