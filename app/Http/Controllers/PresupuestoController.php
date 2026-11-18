<?php

namespace App\Http\Controllers;

use App\Models\LineaPresupuesto;
use App\Services\AlertaService;
use App\Services\PresupuestoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MOD-09 — Presupuestos: configurar el presupuesto mensual por categoría
 * (RF-055) y consultar su ejecución con la proyección al cierre (RF-056).
 *
 * Solo JSON y con datos crudos: el estado de cada línea (RN-056.2), los
 * colores y los textos los maneja el frontend.
 */
class PresupuestoController extends Controller
{
    /**
     * RF-056: ejecución del presupuesto de un mes (?mes=AAAA-MM, por defecto el
     * actual). El mes actual se genera desde el anterior si este pide
     * repetirse (RN-055.1).
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate(
            ['mes' => ['nullable', 'date_format:Y-m']],
            ['mes.date_format' => 'El mes va en formato AAAA-MM.']
        );

        $hoy = CarbonImmutable::today();
        $mes = $request->filled('mes')
            ? PresupuestoService::parsearMes($request->input('mes'))
            : PresupuestoService::mesActual($hoy);

        $presupuesto = $mes->equalTo(PresupuestoService::mesActual($hoy))
            ? PresupuestoService::asegurarMes(Auth::id(), $hoy)
            : PresupuestoService::delMes(Auth::id(), $mes);

        if ($presupuesto === null) {
            return $this->sinPresupuesto($mes);
        }

        return response()->json([
            'mensaje' => 'Ejecución del presupuesto calculada.',
            'data' => PresupuestoService::ejecucion($presupuesto, $hoy),
        ]);
    }

    /**
     * RF-056: detalle de una línea, con la serie diaria del gasto acumulado y
     * sus últimos gastos.
     */
    public function linea(string $mes, string $lineaId): JsonResponse
    {
        $presupuesto = PresupuestoService::delMes(Auth::id(), PresupuestoService::parsearMes($mes));
        $linea = $presupuesto
            ? LineaPresupuesto::where('presupuesto_id', $presupuesto->id)->find($lineaId)
            : null;

        if ($linea === null) {
            return response()->json(['mensaje' => 'La línea no existe en el presupuesto de ese mes.'], 404);
        }

        return response()->json([
            'mensaje' => 'Detalle de la línea calculado.',
            'data' => PresupuestoService::detalleLinea($presupuesto, $linea),
        ]);
    }

    /**
     * RF-055 / RN-055.3: datos para configurar el presupuesto del mes: lo
     * gastado, el promedio de 3 meses y el sugerido por categoría.
     */
    public function base(string $mes): JsonResponse
    {
        return response()->json([
            'mensaje' => 'Base del presupuesto calculada.',
            'data' => PresupuestoService::base(Auth::id(), PresupuestoService::parsearMes($mes)),
        ]);
    }

    /**
     * RF-055: crea o reemplaza el presupuesto del mes. Solo el mes actual y los
     * siguientes (hasta `meses_futuros_max`).
     */
    public function guardar(Request $request, string $mes): JsonResponse
    {
        $mesPresupuesto = $this->mesEditable($mes);

        $validated = $request->validate([
            'repetir_cada_mes' => ['required', 'boolean'],
            'trasladar_saldo' => ['required', 'boolean'],
            'lineas' => ['required', 'array', 'min:1', 'max:'.config('scfp.presupuestos.lineas_max')],
            'lineas.*' => ['array'],
            'lineas.*.categoria_id' => ['required', 'uuid'],
            'lineas.*.modo_asignacion' => ['required', Rule::in([LineaPresupuesto::PESOS, LineaPresupuesto::PORC_INGRESO])],
            'lineas.*.monto_asignado' => ['nullable', 'required_if:lineas.*.modo_asignacion,'.LineaPresupuesto::PESOS, 'regex:/^\d{1,12}$/', 'not_regex:/^0+$/'],
            'lineas.*.porcentaje_ingreso' => ['nullable', 'required_if:lineas.*.modo_asignacion,'.LineaPresupuesto::PORC_INGRESO, 'numeric', 'decimal:0,2', 'gt:0', 'max:100'],
        ], [
            'repetir_cada_mes.required' => 'Indique si el presupuesto se repite cada mes.',
            'repetir_cada_mes.boolean' => 'repetir_cada_mes debe ser verdadero o falso.',
            'trasladar_saldo.required' => 'Indique si el saldo no usado pasa al mes siguiente.',
            'trasladar_saldo.boolean' => 'trasladar_saldo debe ser verdadero o falso.',
            'lineas.required' => 'Agregue al menos una línea al presupuesto.',
            'lineas.array' => 'Las líneas deben ser una lista.',
            'lineas.min' => 'Agregue al menos una línea al presupuesto.',
            'lineas.max' => 'El presupuesto admite máximo :max líneas.',
            'lineas.*.array' => 'Cada línea debe ser un objeto.',
            'lineas.*.categoria_id.required' => 'Indique la categoría de la línea.',
            'lineas.*.categoria_id.uuid' => 'La categoría de la línea no es válida.',
            'lineas.*.modo_asignacion.required' => 'Indique si la línea se asigna en pesos o en % del ingreso.',
            'lineas.*.modo_asignacion.in' => 'El modo de asignación debe ser PESOS o PORC_INGRESO.',
            'lineas.*.monto_asignado.required_if' => 'Indique el monto asignado de la línea.',
            'lineas.*.monto_asignado.regex' => 'El monto asignado va en pesos enteros, sin decimales ni separadores.',
            'lineas.*.monto_asignado.not_regex' => 'El monto asignado debe ser mayor que $ 0.',
            'lineas.*.porcentaje_ingreso.required_if' => 'Indique el porcentaje del ingreso de la línea.',
            'lineas.*.porcentaje_ingreso.numeric' => 'El porcentaje del ingreso debe ser un número.',
            'lineas.*.porcentaje_ingreso.decimal' => 'El porcentaje del ingreso admite máximo 2 decimales.',
            'lineas.*.porcentaje_ingreso.gt' => 'El porcentaje del ingreso debe ser mayor que 0.',
            'lineas.*.porcentaje_ingreso.max' => 'El porcentaje del ingreso no puede pasar de 100.',
        ]);

        $resultado = PresupuestoService::guardar(
            Auth::id(),
            $mesPresupuesto,
            (bool) $validated['repetir_cada_mes'],
            (bool) $validated['trasladar_saldo'],
            array_values($validated['lineas'])
        );

        // RF-057: bajar un monto asignado puede cruzar un umbral del mes en curso.
        if ($mesPresupuesto->equalTo(PresupuestoService::mesActual())) {
            AlertaService::programar(Auth::id());
        }

        return response()->json([
            'mensaje' => $resultado['creado'] ? 'Presupuesto creado.' : 'Presupuesto actualizado.',
            'data' => [
                ...PresupuestoService::ejecucion($resultado['presupuesto']),
                'advertencias' => $resultado['advertencias'],
            ],
        ], $resultado['creado'] ? 201 : 200);
    }

    /** RF-055: elimina el presupuesto del mes actual o de uno futuro. */
    public function destroy(string $mes): JsonResponse
    {
        $mesPresupuesto = $this->mesEditable($mes);
        $presupuesto = PresupuestoService::delMes(Auth::id(), $mesPresupuesto);

        if ($presupuesto === null) {
            return $this->sinPresupuesto($mesPresupuesto);
        }

        PresupuestoService::eliminar($presupuesto);

        return response()->json(['mensaje' => 'Presupuesto eliminado.']);
    }

    /**
     * Solo se configura el mes actual y los siguientes: un mes cerrado es
     * historia (su ejecución ya no cambia con el plan).
     */
    private function mesEditable(string $mes): CarbonImmutable
    {
        $mesPresupuesto = PresupuestoService::parsearMes($mes);
        $actual = PresupuestoService::mesActual();
        $maximo = $actual->addMonthsNoOverflow((int) config('scfp.presupuestos.meses_futuros_max'));

        if ($mesPresupuesto->lessThan($actual)) {
            throw ValidationException::withMessages([
                'mes' => 'Solo se puede configurar el presupuesto del mes actual o de los siguientes.',
            ]);
        }

        if ($mesPresupuesto->greaterThan($maximo)) {
            throw ValidationException::withMessages([
                'mes' => 'Solo se puede configurar hasta '.config('scfp.presupuestos.meses_futuros_max').' meses adelante.',
            ]);
        }

        return $mesPresupuesto;
    }

    private function sinPresupuesto(CarbonImmutable $mes): JsonResponse
    {
        return response()->json([
            'mensaje' => 'No hay presupuesto para '.$mes->format('Y-m').'.',
        ], 404);
    }
}
