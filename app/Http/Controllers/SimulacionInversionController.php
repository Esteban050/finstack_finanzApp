<?php

namespace App\Http\Controllers;

use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\SimulacionInversion;
use App\Services\BitacoraService;
use App\Services\SimulacionInversionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MOD-06 — RF-042: Simulación y comparación de hasta 4 alternativas de
 * inversión hipotéticas. Las simulaciones guardadas no son inversiones: no
 * afectan el patrimonio ni los reportes (RN-042.4).
 *
 * Solo responde JSON: esta entrega no incluye interfaz Blade.
 */
class SimulacionInversionController extends Controller
{
    /** RNG-10: Tamaños de página admitidos. */
    private const POR_PAGINA = [10, 20, 50, 100];

    /**
     * RN-042.3 / CA-042.2: Comparar las alternativas sin guardarlas.
     */
    public function calcular(Request $request): JsonResponse
    {
        $validated = $request->validate($this->reglasAlternativas(), $this->mensajes());
        $this->validarAlternativas($validated['alternativas']);

        return response()->json([
            'mensaje' => 'Simulación calculada. No se guardó.',
            'data' => SimulacionInversionService::comparar($validated['alternativas'], CarbonImmutable::today()),
        ]);
    }

    /**
     * RN-042.4: Simulaciones guardadas, de la más reciente a la más antigua (RNG-10).
     */
    public function index(Request $request): JsonResponse
    {
        $porPagina = (int) $request->query('por_pagina', 20);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 20;
        }

        $simulaciones = SimulacionInversion::query()
            ->orderByDesc('actualizado_en')
            ->paginate($porPagina)
            ->withQueryString();

        return response()->json([
            'mensaje' => 'Simulaciones consultadas.',
            'data' => [
                'simulaciones' => $simulaciones->items(),
                'meta' => [
                    'pagina_actual' => $simulaciones->currentPage(),
                    'por_pagina' => $simulaciones->perPage(),
                    'total' => $simulaciones->total(),
                    'ultima_pagina' => $simulaciones->lastPage(),
                    'maximo' => SimulacionInversionService::MAX_GUARDADAS,
                ],
            ],
        ]);
    }

    /**
     * RN-042.4: Guardar la simulación con un nombre, hasta 20 por Usuario.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            array_merge(['nombre' => $this->reglaNombre()], $this->reglasAlternativas()),
            $this->mensajes()
        );
        $this->validarAlternativas($validated['alternativas']);

        if (SimulacionInversion::count() >= SimulacionInversionService::MAX_GUARDADAS) {
            throw ValidationException::withMessages([
                'nombre' => 'Puede guardar máximo '.SimulacionInversionService::MAX_GUARDADAS.' simulaciones (RN-042.4). Elimine alguna para guardar esta.',
            ]);
        }

        $simulacion = DB::transaction(function () use ($validated) {
            $simulacion = SimulacionInversion::create([
                'usuario_id' => Auth::id(),
                'nombre' => $validated['nombre'],
                'alternativas' => array_values($validated['alternativas']),
            ]);

            BitacoraService::registrar(
                accion: 'GUARDAR_SIMULACION_INVERSION',
                entidadAfectada: 'simulaciones_inversion',
                entidadId: $simulacion->id,
                valorNuevo: $simulacion->toArray(),
                justificacion: 'RN-042.4: simulación de inversión guardada.',
                usuarioId: Auth::id()
            );

            return $simulacion->fresh();
        });

        return response()->json([
            'mensaje' => 'Simulación guardada.',
            'data' => $this->presentar($simulacion),
        ], 201);
    }

    /**
     * RN-042.4: La simulación guardada, con los resultados recalculados.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'mensaje' => 'Simulación consultada.',
            'data' => $this->presentar($this->simulacionPropia($id)),
        ]);
    }

    /**
     * RN-042.4: Cambiar el nombre o las alternativas de la simulación.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $simulacion = $this->simulacionPropia($id);

        // Si llegan alternativas se validan completas, como al guardar.
        $reglas = ['nombre' => array_merge(['sometimes'], $this->reglaNombre($simulacion->id))];
        if ($request->has('alternativas')) {
            $reglas = array_merge($reglas, $this->reglasAlternativas());
        }

        $validated = $request->validate($reglas, $this->mensajes());

        if (isset($validated['alternativas'])) {
            $this->validarAlternativas($validated['alternativas']);
            $validated['alternativas'] = array_values($validated['alternativas']);
        }

        DB::transaction(function () use ($simulacion, $validated) {
            $anterior = $simulacion->toArray();
            $simulacion->update($validated);

            BitacoraService::registrar(
                accion: 'EDITAR_SIMULACION_INVERSION',
                entidadAfectada: 'simulaciones_inversion',
                entidadId: $simulacion->id,
                valorAnterior: $anterior,
                valorNuevo: $simulacion->fresh()->toArray(),
                justificacion: 'RN-042.4: edición de una simulación guardada.',
                usuarioId: Auth::id()
            );
        });

        return response()->json([
            'mensaje' => 'Simulación actualizada.',
            'data' => $this->presentar($simulacion->fresh()),
        ]);
    }

    /**
     * RN-042.4: Eliminar la simulación. Es física: una simulación no es un
     * registro financiero (RNG-07) y no afecta el patrimonio ni los reportes.
     */
    public function destroy(string $id): JsonResponse
    {
        $simulacion = $this->simulacionPropia($id);

        DB::transaction(function () use ($simulacion) {
            $anterior = $simulacion->toArray();
            $simulacion->delete();

            BitacoraService::registrar(
                accion: 'ELIMINAR_SIMULACION_INVERSION',
                entidadAfectada: 'simulaciones_inversion',
                entidadId: $simulacion->id,
                valorAnterior: $anterior,
                justificacion: 'RN-042.4: eliminación de una simulación guardada.',
                usuarioId: Auth::id()
            );
        });

        return response()->json(['mensaje' => 'Simulación eliminada.']);
    }

    private function presentar(SimulacionInversion $simulacion): array
    {
        return array_merge($simulacion->toArray(), [
            'resultados' => SimulacionInversionService::comparar($simulacion->alternativas, CarbonImmutable::today()),
        ]);
    }

    /**
     * RN-042.1: el aporte periódico exige su periodicidad, y cada alternativa
     * tiene que invertir algo (monto inicial o aporte).
     *
     * @throws ValidationException
     */
    private function validarAlternativas(array $alternativas): void
    {
        $errores = [];

        foreach (array_values($alternativas) as $i => $alternativa) {
            $aporte = (string) ($alternativa['aporte_periodico'] ?? '0');

            if (bccomp($aporte, '0', 2) === 1 && empty($alternativa['periodicidad_aporte'])) {
                $errores["alternativas.{$i}.periodicidad_aporte"] = 'Indique la periodicidad del aporte periódico (RN-042.1).';
            }

            if (bccomp((string) $alternativa['monto_inicial'], '0', 2) !== 1 && bccomp($aporte, '0', 2) !== 1) {
                $errores["alternativas.{$i}.monto_inicial"] = 'La alternativa debe tener un monto inicial o un aporte periódico mayor que cero.';
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * RNG-01: El global scope ya devolvió 404 para recursos ajenos. Aquí solo se
     * distingue el caso que la regla manda registrar en bitácora (RF-019).
     */
    private function simulacionPropia(string $id): SimulacionInversion
    {
        $simulacion = SimulacionInversion::find($id);

        if ($simulacion !== null) {
            return $simulacion;
        }

        $esDeOtro = SimulacionInversion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereKey($id)
            ->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'simulaciones_inversion',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }

    /** RN-042.4: nombre de 3 a 60 caracteres, único por Usuario. */
    private function reglaNombre(?string $exceptoId = null): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:60',
            Rule::unique('simulaciones_inversion', 'nombre')
                ->where('usuario_id', Auth::id())
                ->ignore($exceptoId),
        ];
    }

    /** RN-042.1: datos por alternativa, de 1 a 4 alternativas. */
    private function reglasAlternativas(): array
    {
        $monto = ['numeric', 'min:0', 'max:999999999999.99', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        $porcentaje = 'regex:/^-?\d{1,3}(\.\d{1,4})?$/';

        return [
            'alternativas' => ['required', 'array', 'min:1', 'max:'.SimulacionInversionService::MAX_ALTERNATIVAS],
            'alternativas.*.nombre' => ['required', 'string', 'max:40'],
            'alternativas.*.monto_inicial' => ['required', ...$monto],
            'alternativas.*.aporte_periodico' => ['nullable', ...$monto],
            'alternativas.*.periodicidad_aporte' => ['nullable', Rule::in(array_keys(SimulacionInversionService::PERIODICIDADES))],
            'alternativas.*.tasa_ea' => ['required', 'numeric', 'between:-50,100', $porcentaje],
            'alternativas.*.plazo_meses' => ['required', 'integer', 'between:1,480'],
            'alternativas.*.retencion_porc' => ['nullable', 'numeric', 'between:0,100', $porcentaje],
            'alternativas.*.comision_anual_porc' => ['nullable', 'numeric', 'between:0,10', $porcentaje],
        ];
    }

    private function mensajes(): array
    {
        return [
            'nombre.required' => 'Indique el nombre de la simulación.',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max' => 'El nombre admite máximo 60 caracteres.',
            'nombre.unique' => 'Ya tiene una simulación con ese nombre.',
            'alternativas.required' => 'Indique al menos una alternativa.',
            'alternativas.array' => 'Las alternativas deben enviarse como una lista.',
            'alternativas.min' => 'Indique al menos una alternativa.',
            'alternativas.max' => 'Se pueden comparar hasta '.SimulacionInversionService::MAX_ALTERNATIVAS.' alternativas.',
            'alternativas.*.nombre.required' => 'Indique el nombre de la alternativa.',
            'alternativas.*.nombre.max' => 'El nombre de la alternativa admite máximo 40 caracteres.',
            'alternativas.*.monto_inicial.required' => 'Indique el monto inicial de la alternativa.',
            'alternativas.*.monto_inicial.min' => 'El monto inicial no puede ser negativo.',
            'alternativas.*.monto_inicial.regex' => 'El monto inicial admite hasta 12 dígitos enteros y 2 decimales.',
            'alternativas.*.aporte_periodico.min' => 'El aporte periódico no puede ser negativo.',
            'alternativas.*.periodicidad_aporte.in' => 'La periodicidad del aporte debe ser mensual, trimestral, semestral o anual.',
            'alternativas.*.tasa_ea.required' => 'Indique la tasa EA esperada.',
            'alternativas.*.tasa_ea.between' => 'La tasa EA esperada debe estar entre −50 % y 100 % (RN-042.1).',
            'alternativas.*.plazo_meses.required' => 'Indique el plazo en meses.',
            'alternativas.*.plazo_meses.integer' => 'El plazo debe ser un número entero de meses.',
            'alternativas.*.plazo_meses.between' => 'El plazo debe estar entre 1 y 480 meses (RN-042.1).',
            'alternativas.*.retencion_porc.between' => 'La retención o impuesto debe estar entre 0 % y 100 % (RN-042.1).',
            'alternativas.*.comision_anual_porc.between' => 'La comisión anual debe estar entre 0 % y 10 % (RN-042.1).',
        ];
    }
}
