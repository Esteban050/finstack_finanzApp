<?php

namespace App\Http\Controllers;

use App\Services\TrmService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RF-017 — Consulta de la TRM y conversión COP ⇄ USD para el Usuario.
 *
 * Solo responde JSON: esta entrega no incluye interfaz Blade.
 */
class TasaCambioController extends Controller
{
    /**
     * RF-017 / RN-017.2: TRM vigente en una fecha (hoy, si no se indica), con su
     * vigencia, su fuente y la fecha de su última actualización.
     */
    public function trm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => ['nullable', 'date'],
        ], $this->mensajes());

        $fecha = CarbonImmutable::parse($validated['fecha'] ?? 'today');
        $trm = TrmService::vigenteEn($fecha);

        if ($trm === null) {
            return $this->sinTrm($fecha);
        }

        return response()->json([
            'mensaje' => 'TRM consultada.',
            'data' => TrmService::presentar($trm, $fecha),
        ]);
    }

    /**
     * RF-017: Convertir un monto entre COP y USD con la TRM vigente en la fecha
     * indicada (hoy, si no se indica).
     */
    public function convertir(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // RNG-09: mismo rango que los montos del dominio.
            'monto' => ['required', 'numeric', 'gt:0', 'max:999999999999.99', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            // RN-017.4: monedas soportadas en la v1.0.
            'de' => ['required', 'string', 'in:COP,USD,cop,usd'],
            'a' => ['required', 'string', 'in:COP,USD,cop,usd', 'different:de'],
            'fecha' => ['nullable', 'date'],
        ], $this->mensajes());

        $fecha = CarbonImmutable::parse($validated['fecha'] ?? 'today');
        $trm = TrmService::vigenteEn($fecha);

        if ($trm === null) {
            return $this->sinTrm($fecha);
        }

        $de = strtoupper($validated['de']);
        $a = strtoupper($validated['a']);

        return response()->json([
            'mensaje' => 'Conversión calculada.',
            'data' => [
                'monto' => (string) $validated['monto'],
                'de' => $de,
                'a' => $a,
                'resultado' => TrmService::convertir((string) $validated['monto'], $de, $a, (string) $trm->valor),
                'trm' => TrmService::presentar($trm, $fecha),
            ],
        ]);
    }

    /**
     * RN-017.2: Ni la fuente oficial ni la base local tienen una TRM para esa
     * fecha o una anterior. Es una falta del servicio, no un error del Usuario.
     */
    private function sinTrm(CarbonImmutable $fecha): JsonResponse
    {
        return response()->json([
            'mensaje' => "No hay TRM disponible para el {$fecha->toDateString()}. La fuente oficial no respondió y no hay una TRM anterior registrada.",
        ], 503);
    }

    private function mensajes(): array
    {
        return [
            'fecha.date' => 'La fecha no tiene un formato válido (AAAA-MM-DD).',
            'monto.required' => 'Indique el monto a convertir.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.gt' => 'El monto debe ser mayor que cero.',
            'monto.max' => 'El monto no puede superar $ 999.999.999.999,99.',
            'monto.regex' => 'El monto admite hasta 12 dígitos enteros y 2 decimales.',
            'de.required' => 'Indique la moneda de origen.',
            'de.in' => 'La moneda de origen debe ser COP o USD.',
            'a.required' => 'Indique la moneda de destino.',
            'a.in' => 'La moneda de destino debe ser COP o USD.',
            'a.different' => 'La moneda de destino debe ser distinta de la de origen.',
        ];
    }
}
