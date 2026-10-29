<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use App\Models\LiquidacionSeguridadSocial;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Services\AlertaSeguridadSocialService;
use App\Services\BitacoraService;
use App\Services\IngresoService;
use App\Services\SeguridadSocialService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MOD-05 — Seguridad social: liquidación del independiente o contratista
 * (RF-033), aportes del asalariado (RF-034) y fechas límite de pago (RF-036).
 *
 * Solo responde JSON: esta entrega no incluye interfaz Blade. El aislamiento de
 * datos (RNG-01) lo da el global scope DelUsuarioAutenticado de los modelos.
 */
class SeguridadSocialController extends Controller
{
    /** RNG-10: Tamaños de página admitidos. */
    private const POR_PAGINA = [10, 20, 50, 100];

    /**
     * RN-033.7: Liquidaciones estimadas guardadas, de la más reciente a la más
     * antigua (RNG-10).
     */
    public function liquidaciones(Request $request): JsonResponse
    {
        $porPagina = (int) $request->query('por_pagina', 20);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 20;
        }

        $liquidaciones = LiquidacionSeguridadSocial::query()
            ->orderByDesc('periodo')
            ->paginate($porPagina)
            ->withQueryString();

        return response()->json([
            'mensaje' => 'Liquidaciones consultadas.',
            'data' => [
                'liquidaciones' => $liquidaciones->items(),
                'meta' => [
                    'pagina_actual' => $liquidaciones->currentPage(),
                    'por_pagina' => $liquidaciones->perPage(),
                    'total' => $liquidaciones->total(),
                    'ultima_pagina' => $liquidaciones->lastPage(),
                ],
                'aviso_legal' => SeguridadSocialService::AVISO_LEGAL,
            ],
        ]);
    }

    /**
     * RN-033.7 / CA-033.4: Liquidación estimada de un periodo, con el desglose y
     * los parámetros usados.
     */
    public function liquidacion(string $periodo): JsonResponse
    {
        $liquidacion = LiquidacionSeguridadSocial::where('periodo', $periodo)->first();

        if ($liquidacion === null) {
            return response()->json([
                'mensaje' => "No hay una liquidación estimada para el periodo {$periodo}.",
            ], 404);
        }

        return response()->json([
            'mensaje' => 'Liquidación consultada.',
            'data' => array_merge($liquidacion->toArray(), [
                'aviso_legal' => SeguridadSocialService::AVISO_LEGAL,
            ]),
        ]);
    }

    /**
     * RF-033: Calcular la liquidación del periodo con los ingresos de rentas de
     * trabajo registrados (RN-033.1) y guardarla como "Liquidación estimada"
     * (RN-033.7). Si el periodo ya tenía una, se recalcula.
     */
    public function liquidar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'periodo' => $this->reglaPeriodo(),
        ], $this->mensajes());

        [$inicio, $parametro, $perfil] = $this->contextoIndependiente($validated['periodo']);

        $ingresoBase = SeguridadSocialService::ingresoBaseDelPeriodo(Auth::id(), $inicio);
        $calculo = SeguridadSocialService::calcularIndependiente($ingresoBase, $parametro, $perfil);

        [$anterior, $liquidacion] = DB::transaction(function () use ($validated, $calculo, $parametro) {
            [$anterior, $liquidacion] = SeguridadSocialService::guardarLiquidacion(
                Auth::id(),
                $validated['periodo'],
                LiquidacionSeguridadSocial::ORIGEN_REGISTRADO,
                $calculo,
                $parametro
            );

            BitacoraService::registrar(
                accion: $anterior === null ? 'CALCULAR_LIQUIDACION_SEGURIDAD_SOCIAL' : 'RECALCULAR_LIQUIDACION_SEGURIDAD_SOCIAL',
                entidadAfectada: 'liquidaciones_seguridad_social',
                entidadId: $liquidacion->id,
                valorAnterior: $anterior,
                valorNuevo: $liquidacion->toArray(),
                justificacion: 'RN-033.7: liquidación estimada del periodo '.$validated['periodo'].'.',
                usuarioId: Auth::id()
            );

            return [$anterior, $liquidacion];
        });

        return response()->json([
            'mensaje' => $anterior === null
                ? 'Liquidación estimada calculada y guardada.'
                : 'Liquidación estimada recalculada.',
            'data' => array_merge([
                'id' => $liquidacion->id,
                'periodo' => $liquidacion->periodo,
                'origen_ingreso' => $liquidacion->origen_ingreso,
            ], $calculo),
        ], $anterior === null ? 201 : 200);
    }

    /**
     * RN-033.1: Simular la liquidación con un ingreso base ingresado a mano.
     * Es una simulación: no reemplaza la liquidación estimada guardada.
     */
    public function simular(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'periodo' => $this->reglaPeriodo(),
            'ingreso_base' => $this->reglaMonto(permiteCero: true),
        ], $this->mensajes());

        [, $parametro, $perfil] = $this->contextoIndependiente($validated['periodo']);

        $calculo = SeguridadSocialService::calcularIndependiente((string) $validated['ingreso_base'], $parametro, $perfil);

        return response()->json([
            'mensaje' => 'Simulación calculada. No se guardó como liquidación estimada.',
            'data' => array_merge([
                'periodo' => $validated['periodo'],
                'origen_ingreso' => LiquidacionSeguridadSocial::ORIGEN_MANUAL,
            ], $calculo),
        ]);
    }

    /**
     * RF-034: Aportes que el empleador descuenta del salario y salario neto. El
     * salario integral sale del perfil del periodo (RN-034.1).
     */
    public function calcularAsalariado(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'salario' => $this->reglaMonto(),
            // Periodo del que se toman los parámetros (RNG-04). Por defecto, el actual.
            'periodo' => array_merge(['nullable'], array_slice($this->reglaPeriodo(), 1)),
        ], $this->mensajes());

        $periodo = $validated['periodo'] ?? CarbonImmutable::today()->format('Y-m');
        [, $parametro, $perfil] = $this->contextoAsalariado($periodo);

        $calculo = SeguridadSocialService::calcularAsalariado(
            (string) $validated['salario'],
            (bool) $perfil->es_salario_integral,
            $parametro
        );

        return response()->json([
            'mensaje' => 'Aportes del asalariado calculados.',
            'data' => array_merge(['periodo' => $periodo], $calculo),
        ]);
    }

    /**
     * RN-034.5: "El usuario puede aplicar el resultado al ingreso registrado
     * (RF-021) para llenar el campo 'aportes descontados'".
     *
     * El salario es el monto bruto del ingreso y los parámetros, los de su fecha
     * de recepción (RNG-04).
     */
    public function aplicarAsalariado(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ingreso_id' => ['required', 'uuid'],
        ], $this->mensajes());

        $ingreso = $this->ingresoPropio($validated['ingreso_id']);

        if ($ingreso->clasificacion_tributaria !== 'RENTAS_TRABAJO') {
            throw ValidationException::withMessages([
                'ingreso_id' => 'Los aportes del asalariado solo se aplican a un ingreso de rentas de trabajo.',
            ]);
        }

        // RN-034.5: unos honorarios ya cuentan en el ingreso base del independiente
        // (RN-033.1); aplicarles aportes de nómina los descontaría dos veces.
        if (! SeguridadSocialService::esIngresoDeSalario($ingreso)) {
            throw ValidationException::withMessages([
                'ingreso_id' => 'Los aportes del asalariado solo se aplican a un ingreso de una fuente de tipo "Salario".',
            ]);
        }

        $periodo = $ingreso->fecha_recepcion->format('Y-m');
        [, $parametro, $perfil] = $this->contextoAsalariado($periodo);

        $calculo = SeguridadSocialService::calcularAsalariado(
            (string) $ingreso->monto_bruto_cop,
            (bool) $perfil->es_salario_integral,
            $parametro
        );

        // RN-021.2: el neto (bruto − retención − aportes) no puede quedar negativo.
        $neto = IngresoService::calcularNeto(
            (string) $ingreso->monto_bruto_cop,
            (string) $ingreso->retencion_fuente_cop,
            $calculo['total_aportes']
        );

        if (bccomp($neto, '0', 2) === -1) {
            throw ValidationException::withMessages([
                'ingreso_id' => 'La suma de la retención y los aportes calculados supera el monto bruto del ingreso.',
            ]);
        }

        $anterior = $ingreso->toArray();

        DB::transaction(function () use ($ingreso, $calculo, $anterior) {
            $ingreso->update(['aportes_descontados_cop' => $calculo['total_aportes']]);

            BitacoraService::registrar(
                accion: 'APLICAR_APORTES_ASALARIADO',
                entidadAfectada: 'ingresos',
                entidadId: $ingreso->id,
                valorAnterior: $anterior,
                valorNuevo: $ingreso->fresh()->toArray(),
                justificacion: 'RN-034.5: aportes del asalariado aplicados a "aportes descontados".',
                usuarioId: Auth::id()
            );
        });

        return response()->json([
            'mensaje' => 'Aportes aplicados al ingreso.',
            'data' => [
                'ingreso' => $ingreso->fresh(),
                'calculo' => array_merge(['periodo' => $periodo], $calculo),
            ],
        ]);
    }

    /**
     * RF-036: Fechas límite de pago según el calendario (RF-016) y los dos
     * últimos dígitos del documento, con el estado de cada periodo. Las alertas
     * activas son los periodos por vencer (RN-036.1) o vencidos (RN-036.2).
     */
    public function alertas(): JsonResponse
    {
        $perfil = Auth::user()->perfilTributarioActivo;

        if (! SeguridadSocialService::tieneOcupacion($perfil, SeguridadSocialService::OCUPACIONES_INDEPENDIENTE)) {
            throw ValidationException::withMessages([
                'perfil' => 'Las alertas de pago son para Independientes y Contratistas (RF-036). Su perfil no registra esas ocupaciones.',
            ]);
        }

        if ($perfil->ultimos_digitos_nit === null) {
            throw ValidationException::withMessages([
                'perfil' => 'Registre en su perfil los dos últimos dígitos de su documento: con ellos se busca su fecha límite en el calendario (RF-036).',
            ]);
        }

        $periodos = AlertaSeguridadSocialService::estadoDePeriodos(
            Auth::id(),
            $perfil->ultimos_digitos_nit,
            CarbonImmutable::today()
        );

        $alertas = collect($periodos)
            ->whereIn('estado', [AlertaSeguridadSocialService::ESTADO_POR_VENCER, AlertaSeguridadSocialService::ESTADO_VENCIDO])
            ->map(fn (array $periodo) => array_merge($periodo, [
                'advertencia' => $periodo['estado'] === AlertaSeguridadSocialService::ESTADO_VENCIDO
                    ? AlertaSeguridadSocialService::ADVERTENCIA_MORA
                    : null,
            ]))
            ->values();

        return response()->json([
            'mensaje' => 'Fechas límite de pago consultadas.',
            'data' => [
                'ultimos_digitos' => $perfil->ultimos_digitos_nit,
                'periodos' => $periodos,
                'alertas' => $alertas,
            ],
        ]);
    }

    /**
     * RF-033: periodo, parámetros de su vigencia (RNG-04) y perfil que regía
     * entonces (RN-008.6), que debe ser de Independiente o Contratista.
     *
     * @throws ValidationException
     */
    private function contextoIndependiente(string $periodo): array
    {
        $inicio = SeguridadSocialService::inicioDelPeriodo($periodo);
        $parametro = SeguridadSocialService::parametrosDe($inicio->year);
        $perfil = SeguridadSocialService::perfilDelPeriodo(Auth::id(), $inicio);

        if (! SeguridadSocialService::tieneOcupacion($perfil, SeguridadSocialService::OCUPACIONES_INDEPENDIENTE)) {
            throw ValidationException::withMessages([
                'periodo' => "Su perfil tributario del periodo {$periodo} no registra la ocupación Independiente por cuenta propia ni Contratista por prestación de servicios (RF-033).",
            ]);
        }

        return [$inicio, $parametro, $perfil];
    }

    /**
     * RF-034: lo mismo para un Asalariado.
     *
     * @throws ValidationException
     */
    private function contextoAsalariado(string $periodo): array
    {
        $inicio = SeguridadSocialService::inicioDelPeriodo($periodo);
        $parametro = SeguridadSocialService::parametrosDe($inicio->year);
        $perfil = SeguridadSocialService::perfilDelPeriodo(Auth::id(), $inicio);

        if (! SeguridadSocialService::tieneOcupacion($perfil, [SeguridadSocialService::OCUPACION_ASALARIADO])) {
            throw ValidationException::withMessages([
                'periodo' => "Su perfil tributario del periodo {$periodo} no registra la ocupación Asalariado (RF-034).",
            ]);
        }

        return [$inicio, $parametro, $perfil];
    }

    /**
     * RNG-01: El global scope ya devolvió 404 para recursos ajenos. Aquí solo se
     * distingue el caso que la regla manda registrar en bitácora (RF-019).
     */
    private function ingresoPropio(string $id): Ingreso
    {
        $ingreso = Ingreso::find($id);

        if ($ingreso !== null) {
            return $ingreso;
        }

        $esDeOtro = Ingreso::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereKey($id)
            ->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'ingresos',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }

    /**
     * RN-033.7: periodo AAAA-MM, que no puede ser posterior al mes actual.
     */
    private function reglaPeriodo(): array
    {
        return [
            'required',
            'string',
            'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
            function (string $atributo, mixed $valor, \Closure $falla) {
                if (is_string($valor)
                    && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)
                    && SeguridadSocialService::inicioDelPeriodo($valor)->gt(CarbonImmutable::today()->startOfMonth())) {
                    $falla('El periodo no puede ser posterior al mes actual.');
                }
            },
        ];
    }

    /** RNG-09: mismo rango que los montos del dominio. */
    private function reglaMonto(bool $permiteCero = false): array
    {
        return [
            'required',
            'numeric',
            $permiteCero ? 'min:0' : 'gt:0',
            'max:999999999999.99',
            'regex:/^\d{1,12}(\.\d{1,2})?$/',
        ];
    }

    private function mensajes(): array
    {
        return [
            'periodo.required' => 'Indique el periodo de cotización (AAAA-MM).',
            'periodo.regex' => 'El periodo debe tener el formato AAAA-MM.',
            'ingreso_base.required' => 'Indique el ingreso base a simular.',
            'ingreso_base.numeric' => 'El ingreso base debe ser un número.',
            'ingreso_base.min' => 'El ingreso base no puede ser negativo.',
            'ingreso_base.max' => 'El ingreso base no puede superar $ 999.999.999.999,99.',
            'ingreso_base.regex' => 'El ingreso base admite hasta 12 dígitos enteros y 2 decimales.',
            'salario.required' => 'Indique el salario mensual.',
            'salario.numeric' => 'El salario debe ser un número.',
            'salario.gt' => 'El salario debe ser mayor que cero.',
            'salario.max' => 'El salario no puede superar $ 999.999.999.999,99.',
            'salario.regex' => 'El salario admite hasta 12 dígitos enteros y 2 decimales.',
            'ingreso_id.required' => 'Indique el ingreso al que se aplican los aportes.',
            'ingreso_id.uuid' => 'El ingreso indicado no es válido.',
        ];
    }
}
