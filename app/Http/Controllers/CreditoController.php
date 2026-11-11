<?php

namespace App\Http\Controllers;

use App\Models\Credito;
use App\Models\CuotaCredito;
use App\Models\PagoCredito;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Services\AlertaCreditoService;
use App\Services\BitacoraService;
use App\Services\CreditoService;
use App\Services\EndeudamientoService;
use App\Services\PagoCreditoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MOD-08 — Créditos: registro del crédito (RF-048) y su tabla de
 * amortización con cuota fija (RF-049). La tasa se recibe en EA, nominal o
 * mensual y se responde en sus tres expresiones (documento de créditos,
 * citado como "Créditos RF-xx": RF-05, RF-06, RF-10, RF-11 y RN-08).
 *
 * Solo responde JSON: esta entrega no incluye interfaz Blade.
 */
class CreditoController extends Controller
{
    /** RNG-10: Tamaños de página admitidos. */
    private const POR_PAGINA = [10, 20, 50, 100];

    /**
     * RF-049: Calcular cuota, tabla y deuda total sin guardar el crédito.
     */
    public function calcular(Request $request): JsonResponse
    {
        $validated = $this->validar($request, conIdentificacion: false);

        $calculo = CreditoService::calcular($validated);

        return response()->json([
            'mensaje' => 'Crédito calculado. No se guardó.',
            'data' => [
                ...$calculo,
                // Créditos sección 6: usura y capacidad de pago, como alertas.
                'alertas' => AlertaCreditoService::alertas($validated, $calculo['cuota_total'], Auth::id()),
            ],
        ]);
    }

    /**
     * Créditos RF-17: simulación inversa. Con una cuota máxima y las demás
     * condiciones, el monto máximo que se puede pedir y su plan. No guarda.
     */
    public function calcularMonto(Request $request): JsonResponse
    {
        $request->validate(
            ['cuota_maxima' => ['required', 'regex:/^\d{1,12}$/', 'not_regex:/^0+$/']],
            [
                'cuota_maxima.required' => 'Indique la cuota máxima que puede pagar.',
                'cuota_maxima.regex' => 'La cuota máxima va en pesos enteros, sin decimales ni separadores.',
                'cuota_maxima.not_regex' => 'La cuota máxima debe ser mayor que cero.',
            ]
        );

        $validated = $this->validarDatos($request->except('monto'), conIdentificacion: false, conMonto: false);
        $cuotaMaxima = (string) $request->input('cuota_maxima');

        $monto = CreditoService::montoMaximo($cuotaMaxima, $validated['tasa_periodica'], $validated['numero_cuotas'], $validated['sistema_amortizacion']);

        if (bccomp($monto, '0', 0) !== 1) {
            throw ValidationException::withMessages([
                'cuota_maxima' => 'La cuota máxima no alcanza para ningún monto con estas condiciones.',
            ]);
        }

        $datos = [...$validated, 'monto' => $monto];
        $calculo = CreditoService::calcular($datos);

        return response()->json([
            'mensaje' => 'Monto máximo calculado. No se guardó.',
            'data' => [
                'cuota_maxima' => $cuotaMaxima,
                'monto_maximo' => $monto,
                ...$calculo,
                'alertas' => AlertaCreditoService::alertas($datos, $calculo['cuota_total'], Auth::id()),
            ],
        ]);
    }

    /**
     * Créditos RF-18: compara de 2 a 3 escenarios lado a lado. Todos parten de
     * las mismas condiciones y cada uno solo cambia el plazo (o el número de
     * cuotas), la periodicidad o el sistema de amortización. No guarda.
     */
    public function comparar(Request $request): JsonResponse
    {
        $request->validate([
            'escenarios' => ['required', 'array', 'min:2', 'max:3'],
            'escenarios.*' => ['array'],
            'escenarios.*.nombre' => ['nullable', 'string', 'max:40'],
        ], [
            'escenarios.required' => 'Indique los escenarios a comparar.',
            'escenarios.array' => 'Los escenarios deben ser una lista.',
            'escenarios.min' => 'Compare al menos 2 escenarios.',
            'escenarios.max' => 'Se comparan máximo 3 escenarios (Créditos RF-18).',
            'escenarios.*.array' => 'Cada escenario debe ser un objeto con sus cambios.',
            'escenarios.*.nombre.max' => 'El nombre del escenario admite máximo 40 caracteres.',
        ]);

        $permitidos = ['nombre', 'plazo_meses', 'numero_cuotas', 'periodicidad', 'sistema_amortizacion'];
        $base = $request->except('escenarios');
        $resultados = [];

        foreach (array_values($request->input('escenarios')) as $n => $escenario) {
            $otros = array_diff(array_keys($escenario), $permitidos);
            if ($otros !== []) {
                throw ValidationException::withMessages([
                    "escenarios.{$n}" => 'Un escenario solo cambia el plazo (o el número de cuotas), la periodicidad o el sistema de amortización (Créditos RF-18). Sobra: '.implode(', ', $otros).'.',
                ]);
            }

            // El plazo del escenario reemplaza al de la base, venga en meses o en cuotas.
            $entrada = $base;
            if (array_key_exists('plazo_meses', $escenario) || array_key_exists('numero_cuotas', $escenario)) {
                unset($entrada['plazo_meses'], $entrada['numero_cuotas']);
            }
            $entrada = array_merge($entrada, array_diff_key($escenario, ['nombre' => true]));

            try {
                $datos = $this->validarDatos($entrada, conIdentificacion: false);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(
                    collect($e->errors())->mapWithKeys(fn ($mensajes, $campo) => ["escenarios.{$n}.{$campo}" => $mensajes])->all()
                );
            }

            $calculo = CreditoService::calcular($datos);

            $resultados[] = [
                'escenario' => $n + 1,
                'nombre' => $escenario['nombre'] ?? null,
                'periodicidad' => $datos['periodicidad'],
                'sistema_amortizacion' => $datos['sistema_amortizacion'],
                'plazo_meses' => $datos['plazo_meses'],
                'numero_cuotas' => $calculo['numero_cuotas'],
                'tasas' => $calculo['tasas'],
                'cuota' => $calculo['cuota'],
                'cuota_ultima' => $calculo['cuota_ultima'],
                'total_intereses' => $calculo['total_intereses'],
                'deuda_total' => $calculo['deuda_total'],
                'total_seguros_cargos' => $calculo['total_seguros_cargos'],
                'total_a_pagar' => $calculo['total_a_pagar'],
                'alertas' => AlertaCreditoService::alertas($datos, $calculo['cuota_total'], Auth::id()),
            ];
        }

        $referencia = $resultados[0]['total_intereses'];
        foreach ($resultados as &$resultado) {
            $resultado['diferencia_intereses_vs_1'] = bcsub($resultado['total_intereses'], $referencia, 0);
        }
        unset($resultado);

        $menor = array_reduce($resultados, fn ($m, $r) => $m === null || bccomp($r['total_a_pagar'], $m['total_a_pagar'], 0) === -1 ? $r : $m);

        return response()->json([
            'mensaje' => 'Escenarios comparados. No se guardó.',
            'data' => [
                'escenarios' => $resultados,
                'menor_costo' => $menor['escenario'],
            ],
        ]);
    }

    /**
     * RF-048: Créditos del Usuario, del más reciente al más antiguo (RNG-10),
     * cada uno con su cuota y su deuda total.
     */
    public function index(Request $request): JsonResponse
    {
        $porPagina = (int) $request->query('por_pagina', 20);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 20;
        }

        $creditos = Credito::query()
            ->with('cuotasVigentes')
            ->orderByDesc('creado_en')
            ->paginate($porPagina)
            ->withQueryString();

        return response()->json([
            'mensaje' => 'Créditos consultados.',
            'data' => [
                'creditos' => collect($creditos->items())->map(fn (Credito $c) => $this->presentar($c, conTabla: false, conAlertas: false))->all(),
                'meta' => [
                    'pagina_actual' => $creditos->currentPage(),
                    'por_pagina' => $creditos->perPage(),
                    'total' => $creditos->total(),
                    'ultima_pagina' => $creditos->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * RF-048 / Créditos RF-22: Registrar el crédito y guardar su plan de pagos
     * como versión 1.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validar($request);

        $credito = DB::transaction(function () use ($validated) {
            $credito = Credito::create([
                ...$validated,
                'usuario_id' => Auth::id(),
            ]);

            PagoCreditoService::crearPlan($credito->fresh(), 1);

            BitacoraService::registrar(
                accion: 'REGISTRAR_CREDITO',
                entidadAfectada: 'creditos',
                entidadId: $credito->id,
                valorNuevo: $credito->fresh()->toArray(),
                justificacion: 'RF-048: registro de un crédito.',
                usuarioId: Auth::id()
            );

            return $credito->fresh();
        });

        return response()->json([
            'mensaje' => 'Crédito registrado.',
            'data' => $this->presentar($credito, conTabla: false),
        ], 201);
    }

    /**
     * RF-049 / Créditos RF-21, RF-25, RF-41..43: el crédito con su plan
     * vigente (estado y lo pagado de cada cuota) y su estado de cuenta a hoy.
     */
    public function show(string $id): JsonResponse
    {
        $credito = $this->creditoPropio($id);
        $situacion = PagoCreditoService::situacion($credito, CarbonImmutable::today());

        return response()->json([
            'mensaje' => 'Crédito consultado.',
            'data' => [
                ...$this->presentar($credito, situacion: $situacion),
                'estado_cuenta' => PagoCreditoService::estadoCuenta($credito, $situacion),
            ],
        ]);
    }

    /**
     * RF-053 / RN-053.1 / RN-053.3: indicador de endeudamiento del mes actual
     * con sus dos partes (pagos del mes por crédito e ingresos netos de los
     * últimos meses completos). Sin semáforo ni umbrales: los pone el frontend.
     */
    public function endeudamiento(): JsonResponse
    {
        return response()->json([
            'mensaje' => 'Endeudamiento calculado.',
            'data' => EndeudamientoService::calcular(Auth::id()),
        ]);
    }

    /**
     * Créditos RF-37, RF-41, RF-42, RF-43: estado de cuenta a una fecha de
     * corte (por defecto hoy), con el saldo para cancelar a esa fecha. La
     * fecha no puede ser anterior al desembolso ni al último pago vigente.
     */
    public function estadoCuenta(Request $request, string $id): JsonResponse
    {
        $credito = $this->creditoPropio($id);
        $validated = $request->validate(
            ['fecha' => ['nullable', 'date_format:Y-m-d']],
            ['fecha.date_format' => 'La fecha de corte debe tener el formato AAAA-MM-DD.']
        );

        $fecha = CarbonImmutable::parse($validated['fecha'] ?? CarbonImmutable::today()->toDateString());
        $minima = $this->fechaMinimaDeCorte($credito);

        if ($fecha->lt($minima)) {
            throw ValidationException::withMessages([
                'fecha' => 'La fecha de corte no puede ser anterior al '.$minima->format('d/m/Y').' (desembolso o último pago vigente).',
            ]);
        }

        $situacion = PagoCreditoService::situacion($credito, $fecha);

        return response()->json([
            'mensaje' => 'Estado de cuenta consultado.',
            'data' => [
                ...PagoCreditoService::estadoCuenta($credito, $situacion),
                'cuotas_detalle' => $this->filasPlan($situacion['cuotas'], conEstado: true),
            ],
        ]);
    }

    /**
     * Créditos RF-37: paz y salvo en JSON, solo cuando el crédito quedó pagado
     * (saldo para cancelar en cero a hoy). Es una constancia de los pagos
     * registrados en el SCFP, no el documento que expide la entidad.
     */
    public function pazYSalvo(string $id): JsonResponse
    {
        $credito = $this->creditoPropio($id);
        $situacion = PagoCreditoService::situacion($credito, CarbonImmutable::today());
        $estado = PagoCreditoService::estadoCuenta($credito, $situacion);

        if ($estado['estado'] !== 'PAGADO') {
            throw ValidationException::withMessages([
                'credito' => 'El crédito aún tiene saldo ($ '.number_format((float) $estado['saldo_para_cancelar'], 0, ',', '.').'): el paz y salvo se genera cuando queda pagado.',
            ]);
        }

        $titular = Auth::user();
        $ultimoPago = $situacion['ultimo_pago'];

        BitacoraService::registrar(
            accion: 'GENERAR_PAZ_Y_SALVO_CREDITO',
            entidadAfectada: 'creditos',
            entidadId: $credito->id,
            justificacion: 'Créditos RF-37: paz y salvo del crédito pagado.',
            usuarioId: Auth::id()
        );

        return response()->json([
            'mensaje' => 'Paz y salvo generado.',
            'data' => [
                'constancia' => 'El crédito de tipo '.$credito->tipo_credito.' con '.($credito->banco ?? 'la entidad').' por $ '
                    .number_format((float) $credito->monto, 0, ',', '.').' no presenta saldo pendiente al '
                    .CarbonImmutable::today()->format('d/m/Y').'.',
                'fecha_expedicion' => CarbonImmutable::today()->toDateString(),
                'fecha_cancelacion' => $ultimoPago?->fecha->toDateString(),
                'titular' => [
                    'nombres' => $titular->nombres,
                    'apellidos' => $titular->apellidos,
                    'tipo_documento' => $titular->tipo_documento,
                    'numero_documento' => $titular->numero_documento,
                ],
                'credito' => [
                    'id' => $credito->id,
                    'tipo_credito' => $credito->tipo_credito,
                    'banco' => $credito->banco,
                    'monto' => (string) $credito->monto,
                    'fecha_inicio' => $credito->fecha_inicio->toDateString(),
                    'numero_cuotas' => $credito->numero_cuotas,
                    'periodicidad' => $credito->periodicidad,
                ],
                'estado' => $estado['estado'],
                'saldo_para_cancelar' => $estado['saldo_para_cancelar'],
                'total_pagado' => $estado['total_pagado'],
                'aviso' => 'Constancia generada con los pagos registrados en el SCFP. No reemplaza el paz y salvo que expide la entidad financiera.',
            ],
        ]);
    }

    /**
     * Créditos RF-26: las versiones del plan, de la primera a la vigente. Cada
     * versión muestra las cuotas que tenía en ese momento.
     */
    public function planes(string $id): JsonResponse
    {
        $credito = $this->creditoPropio($id);
        $cuotas = CuotaCredito::where('credito_id', $credito->id)->orderBy('numero')->get();

        $versiones = [];
        for ($v = 1; $v <= $credito->version_plan_vigente; $v++) {
            $filas = $cuotas->filter(fn (CuotaCredito $c) => $c->version_plan <= $v
                && ($c->reemplazada_en_version === null || $c->reemplazada_en_version > $v));

            $versiones[] = [
                'version' => $v,
                'vigente' => $v === $credito->version_plan_vigente,
                'numero_cuotas' => $filas->count(),
                'total_intereses' => PagoCreditoService::peso($filas->reduce(fn (string $t, CuotaCredito $c) => bcadd($t, (string) $c->interes, 2), '0')),
                'cuotas' => $filas->values()->map(fn (CuotaCredito $c) => $this->filaCuota($c))->all(),
            ];
        }

        return response()->json([
            'mensaje' => 'Versiones del plan consultadas.',
            'data' => $versiones,
        ]);
    }

    /**
     * RF-048: Editar el crédito. Créditos RF-08 / RF-28: con pagos vigentes
     * las condiciones quedan fijas y solo cambian el banco y el propósito; sin
     * pagos, el plan se regenera como versión nueva (RF-26).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $credito = $this->creditoPropio($id);
        $validated = $this->validar($request);

        $cambiaCondiciones = $this->cambiaCondiciones($credito, $validated);

        if ($cambiaCondiciones && PagoCredito::where('credito_id', $credito->id)->vigentes()->exists()) {
            throw ValidationException::withMessages([
                'credito' => 'El crédito ya tiene pagos: solo se pueden cambiar el banco y el propósito. Para corregir las condiciones, reverse los pagos.',
            ]);
        }

        DB::transaction(function () use ($credito, $validated, $cambiaCondiciones) {
            $anterior = $credito->toArray();

            $credito->update($validated);

            if ($cambiaCondiciones) {
                PagoCreditoService::reemplazarPlan($credito->fresh());
            }

            BitacoraService::registrar(
                accion: 'EDITAR_CREDITO',
                entidadAfectada: 'creditos',
                entidadId: $credito->id,
                valorAnterior: $anterior,
                valorNuevo: $credito->fresh()->toArray(),
                justificacion: 'RF-048: edición de un crédito.',
                usuarioId: Auth::id()
            );
        });

        $credito = $credito->fresh();

        return response()->json([
            'mensaje' => 'Crédito actualizado.',
            'data' => $this->presentar($credito, situacion: PagoCreditoService::situacion($credito, CarbonImmutable::today())),
        ]);
    }

    /**
     * RF-048: Eliminar el crédito. Es lógica (RNG-07). Con pagos vigentes no
     * se elimina: sus gastos en MOD-04 quedarían sin el pago que los explica.
     */
    public function destroy(string $id): JsonResponse
    {
        $credito = $this->creditoPropio($id);

        if (PagoCredito::where('credito_id', $credito->id)->vigentes()->exists()) {
            throw ValidationException::withMessages([
                'credito' => 'El crédito tiene pagos vigentes: reverse los pagos antes de eliminarlo.',
            ]);
        }

        DB::transaction(function () use ($credito) {
            $anterior = $credito->toArray();
            $credito->delete();

            BitacoraService::registrar(
                accion: 'ELIMINAR_CREDITO',
                entidadAfectada: 'creditos',
                entidadId: $credito->id,
                valorAnterior: $anterior,
                justificacion: 'RF-048: eliminación lógica de un crédito (RNG-07).',
                usuarioId: Auth::id()
            );
        });

        return response()->json(['mensaje' => 'Crédito eliminado.']);
    }

    /**
     * RF-048 / RF-049 / Créditos RF-24: datos del crédito con las tasas, la
     * cuota y los totales del plan vigente guardado; con la tabla solo cuando
     * llega la situación (detalle), y entonces con el estado de cada cuota.
     */
    private function presentar(Credito $credito, bool $conTabla = true, ?array $situacion = null, bool $conAlertas = true): array
    {
        $cuotas = $credito->relationLoaded('cuotasVigentes') ? $credito->cuotasVigentes : $credito->cuotasVigentes()->get();
        $sumar = fn (string $campo) => PagoCreditoService::peso(
            $cuotas->reduce(fn (string $t, CuotaCredito $c) => bcadd($t, (string) $c->{$campo}, 2), '0')
        );

        $tasas = CreditoService::tasas((string) $credito->tasa_ea, (string) $credito->tasa_periodica, $credito->periodicidad);
        $tasas['mora_ea_porc'] = $credito->tasa_mora_ea === null ? null : CreditoService::porcentaje((string) $credito->tasa_mora_ea);

        $calculo = [
            'version_plan' => $credito->version_plan_vigente,
            'numero_cuotas' => $cuotas->count(),
            'tasas' => $tasas,
            'cuota' => $cuotas->isEmpty() ? '0' : PagoCreditoService::peso($cuotas->first()->cuota),
            // Créditos RF-16: en el alemán la cuota es decreciente.
            'cuota_ultima' => $cuotas->isEmpty() ? '0' : PagoCreditoService::peso($cuotas->last()->cuota),
            'cuota_total' => $cuotas->isEmpty() ? '0' : $this->cuotaTotal($cuotas->first()),
            // Créditos RF-24: totales del plan vigente; seguros y cargos aparte (2.5).
            'total_intereses' => $sumar('interes'),
            'total_capital' => $sumar('capital'),
            'deuda_total' => $sumar('cuota'),
            'total_seguros_cargos' => $segurosCargos = bcadd($sumar('seguro'), $sumar('cargos'), 0),
            'total_a_pagar' => bcadd($sumar('cuota'), $segurosCargos, 0),
        ];

        if ($conTabla) {
            $calculo['tabla'] = $situacion !== null
                ? $this->filasPlan($situacion['cuotas'], conEstado: true)
                : $cuotas->map(fn (CuotaCredito $c) => $this->filaCuota($c))->all();
        }

        $datos = $credito->toArray();
        unset($datos['cuotas_vigentes']);

        $resultado = [...$datos, 'calculo' => $calculo];

        // Créditos sección 6: usura, capacidad de pago y financiación (solo en el detalle).
        if ($conAlertas) {
            $resultado['alertas'] = AlertaCreditoService::deCredito($credito, $calculo['cuota_total']);
        }

        return $resultado;
    }

    /** Créditos RF-21: cuota con seguros y cargos. */
    private function cuotaTotal(CuotaCredito $c): string
    {
        return PagoCreditoService::peso(bcadd(bcadd((string) $c->cuota, (string) $c->seguro, 2), (string) $c->cargos, 2));
    }

    /** Créditos RF-21: fila del plan con su estado y lo pagado (sin campos internos). */
    private function filasPlan(array $filas, bool $conEstado): array
    {
        return array_map(function (array $f) use ($conEstado) {
            unset($f['id'], $f['exigible']);

            return $conEstado ? $f : array_intersect_key($f, array_flip(['numero', 'fecha', 'saldo_inicial', 'cuota', 'interes', 'abono_capital', 'saldo_final']));
        }, $filas);
    }

    /** Créditos RF-21: fila de una cuota guardada, en pesos enteros. */
    private function filaCuota(CuotaCredito $c): array
    {
        return [
            'numero' => $c->numero,
            'version_plan' => $c->version_plan,
            'fecha' => $c->fecha_vencimiento->toDateString(),
            'saldo_inicial' => PagoCreditoService::peso($c->saldo_inicial),
            'cuota' => PagoCreditoService::peso($c->cuota),
            'interes' => PagoCreditoService::peso($c->interes),
            'abono_capital' => PagoCreditoService::peso($c->capital),
            'saldo_final' => PagoCreditoService::peso($c->saldo_final),
            'seguro' => PagoCreditoService::peso($c->seguro),
            'cargos' => PagoCreditoService::peso($c->cargos),
            'cuota_total' => $this->cuotaTotal($c),
        ];
    }

    /**
     * Créditos RF-08: ¿la edición cambia alguna condición financiera? Banco y
     * propósito no cuentan.
     */
    private function cambiaCondiciones(Credito $credito, array $validated): bool
    {
        $decimales = [
            'monto' => 2, 'tasa_ingresada' => 6, 'tasa_ea' => 18, 'tasa_periodica' => 18, 'tasa_mora_ea' => 18,
            'seguro_porc_mensual' => 6, 'cargo_fijo' => 2,
        ];

        foreach ($validated as $campo => $nuevo) {
            // Banco, propósito, valor del inmueble y VIS no cambian el plan.
            if (in_array($campo, ['banco', 'proposito', 'valor_inmueble', 'es_vis'], true)) {
                continue;
            }

            $actual = $credito->{$campo};
            if ($actual instanceof \DateTimeInterface) {
                $actual = $actual->format('Y-m-d');
            }

            if ($actual === null || $nuevo === null) {
                if ($actual !== $nuevo) {
                    return true;
                }

                continue;
            }

            $distinto = isset($decimales[$campo])
                ? bccomp((string) $actual, (string) $nuevo, $decimales[$campo]) !== 0
                : (string) $actual !== (string) $nuevo;

            if ($distinto) {
                return true;
            }
        }

        return false;
    }

    /** Créditos RF-41: el corte va desde el desembolso o el último pago vigente. */
    private function fechaMinimaDeCorte(Credito $credito): CarbonImmutable
    {
        $minima = CarbonImmutable::parse($credito->fecha_inicio->toDateString());
        $ultimo = PagoCredito::where('credito_id', $credito->id)->vigentes()->max('fecha');

        return $ultimo !== null && CarbonImmutable::parse($ultimo)->gt($minima) ? CarbonImmutable::parse($ultimo) : $minima;
    }

    /**
     * RF-048 / Créditos RF-05, RF-10, RF-11 y RN-02: condiciones del crédito.
     *
     * Devuelve los datos listos para guardar o calcular: la tasa ingresada con
     * su EA y su tasa periódica (18 decimales) y el número de cuotas. El plazo
     * llega en meses o directamente en cuotas, nunca los dos; en meses debe
     * dar un número entero de cuotas. Los límites salen de config/scfp.php.
     *
     * @throws ValidationException
     */
    private function validar(Request $request, bool $conIdentificacion = true): array
    {
        return $this->validarDatos($request->all(), $conIdentificacion);
    }

    /**
     * Las mismas reglas de validar() sobre un arreglo, para reutilizarlas en
     * cada escenario de la comparación (Créditos RF-18) y en la simulación
     * inversa, que no lleva monto (RF-17).
     *
     * @throws ValidationException
     */
    private function validarDatos(array $entrada, bool $conIdentificacion = true, bool $conMonto = true): array
    {
        $periodicidades = array_keys(CreditoService::PERIODICIDADES);

        $reglas = [
            'tipo_credito' => ['required', Rule::in(CreditoService::TIPOS_CREDITO)],
            'tasa' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,4}(\.\d{1,6})?$/'],
            'tipo_tasa' => ['required', Rule::in(CreditoService::TIPOS_TASA)],
            'capitalizacion' => ['nullable', 'required_if:tipo_tasa,NOMINAL', 'prohibited_unless:tipo_tasa,NOMINAL', Rule::in($periodicidades)],
            // Créditos RF-39: tasa de mora en % EA, opcional.
            'tasa_mora' => ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,4}(\.\d{1,6})?$/'],
            'plazo_meses' => ['nullable', 'required_without:numero_cuotas', 'prohibits:numero_cuotas', 'integer'],
            'numero_cuotas' => ['nullable', 'required_without:plazo_meses', 'integer', 'min:1', 'max:9999'],
            'periodicidad' => ['required', Rule::in($periodicidades)],
            // Créditos 2.2 / 2.4: opcional, por defecto cuota fija.
            'sistema_amortizacion' => ['nullable', Rule::in(CreditoService::SISTEMAS)],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            // Créditos RF-12 / RF-14: vencimiento de la cuota 1, opcional.
            'fecha_primer_pago' => ['nullable', 'date_format:Y-m-d'],
            // Créditos RF-02 / RF-16: seguro en % mensual del saldo y cargo fijo por cuota.
            'seguro_porc_mensual' => ['nullable', 'numeric', 'min:0', 'max:'.config('scfp.creditos.seguro_porc_mensual_max'), 'regex:/^\d{1,2}(\.\d{1,6})?$/'],
            'cargo_fijo' => ['nullable', 'regex:/^\d{1,12}$/'],
            // Créditos RN-03: solo vivienda.
            'valor_inmueble' => ['nullable', 'prohibited_unless:tipo_credito,VIVIENDA', 'regex:/^\d{1,12}$/', 'not_regex:/^0+$/'],
            'es_vis' => ['nullable', 'prohibited_unless:tipo_credito,VIVIENDA', 'boolean'],
        ];

        if ($conMonto) {
            $reglas['monto'] = ['required', 'numeric', 'gt:0', 'max:999999999999.99', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        }

        if ($conIdentificacion) {
            $reglas['banco'] = ['required', 'string', 'max:80'];
            $reglas['proposito'] = ['required', 'string', 'min:3', 'max:120'];
        }

        $validated = Validator::make($entrada, $reglas, $this->mensajes())->validate();

        $periodicidad = $validated['periodicidad'];
        [$numeroCuotas, $plazoMeses] = $this->cuotasYPlazo($validated, $periodicidad);

        // Créditos RF-12: el primer pago va después del desembolso y a más tardar dos periodos después.
        if (! empty($validated['fecha_primer_pago'])) {
            $primerPago = CarbonImmutable::parse($validated['fecha_primer_pago']);
            $limite = CreditoService::fechaVencimiento($validated['fecha_inicio'], $periodicidad, 2);

            if ($primerPago->lte(CarbonImmutable::parse($validated['fecha_inicio'])) || $primerPago->gt($limite)) {
                throw ValidationException::withMessages([
                    'fecha_primer_pago' => 'La fecha del primer pago debe ser posterior al desembolso y a más tardar dos periodos después (hasta el '.$limite->format('d/m/Y').').',
                ]);
            }
        }

        // Créditos RF-05: la tasa como se ingresó, más su EA y su tasa periódica.
        $tipoTasa = $validated['tipo_tasa'];
        $capitalizacion = $tipoTasa === 'NOMINAL' ? $validated['capitalizacion'] : null;
        $ea = CreditoService::tasaEa((string) $validated['tasa'], $tipoTasa, $capitalizacion);

        $eaMaxima = (string) config('scfp.creditos.tasa_ea_max');
        if (bccomp($ea, bcdiv($eaMaxima, '100', 18), 18) === 1) {
            throw ValidationException::withMessages([
                'tasa' => 'La tasa equivale a '.$this->decimalEs(CreditoService::porcentaje($ea))." % EA; el máximo admitido es {$this->decimalEs($eaMaxima)} % EA.",
            ]);
        }

        // Créditos RF-39: la mora también respeta el tope en EA.
        $moraEa = isset($validated['tasa_mora']) ? CreditoService::tasaEa((string) $validated['tasa_mora'], 'EA') : null;
        if ($moraEa !== null && bccomp($moraEa, bcdiv($eaMaxima, '100', 18), 18) === 1) {
            throw ValidationException::withMessages([
                'tasa_mora' => "La tasa de mora supera el máximo admitido de {$this->decimalEs($eaMaxima)} % EA.",
            ]);
        }

        $datos = [
            'tipo_credito' => $validated['tipo_credito'],
            'tipo_tasa' => $tipoTasa,
            'tasa_ingresada' => (string) $validated['tasa'],
            'capitalizacion' => $capitalizacion,
            'tasa_ea' => $ea,
            'tasa_periodica' => CreditoService::tasaPeriodica($ea, $periodicidad),
            'tasa_mora_ea' => $moraEa,
            'plazo_meses' => $plazoMeses,
            'periodicidad' => $periodicidad,
            'numero_cuotas' => $numeroCuotas,
            'sistema_amortizacion' => $validated['sistema_amortizacion'] ?? 'FRANCES',
            'fecha_inicio' => $validated['fecha_inicio'],
            'fecha_primer_pago' => $validated['fecha_primer_pago'] ?? null,
            'seguro_porc_mensual' => isset($validated['seguro_porc_mensual']) ? (string) $validated['seguro_porc_mensual'] : null,
            'cargo_fijo' => isset($validated['cargo_fijo']) ? (string) $validated['cargo_fijo'] : null,
            'valor_inmueble' => isset($validated['valor_inmueble']) ? (string) $validated['valor_inmueble'] : null,
            'es_vis' => isset($validated['es_vis']) ? (bool) $validated['es_vis'] : null,
        ];

        if ($conMonto) {
            $datos['monto'] = (string) $validated['monto'];
        }

        if ($conIdentificacion) {
            $datos['banco'] = trim($validated['banco']);
            $datos['proposito'] = trim($validated['proposito']);
        }

        return $datos;
    }

    /**
     * Créditos RF-11 y RN-02: número de cuotas y plazo en meses (nulo si
     * llegó en cuotas). El plazo, o los meses que cubren las cuotas, debe
     * quedar dentro de los límites; vivienda tiene los suyos.
     *
     * @return array{0: int, 1: int|null}
     *
     * @throws ValidationException
     */
    private function cuotasYPlazo(array $validated, string $periodicidad): array
    {
        if (isset($validated['plazo_meses'])) {
            $campo = 'plazo_meses';
            $plazoMeses = (int) $validated['plazo_meses'];
            $numeroCuotas = CreditoService::numeroCuotas($plazoMeses, $periodicidad);

            if ($numeroCuotas === null) {
                throw ValidationException::withMessages([
                    'plazo_meses' => 'Con periodicidad '.mb_strtolower($periodicidad).' el plazo debe ser múltiplo de '.CreditoService::multiploMeses($periodicidad).' meses.',
                ]);
            }

            $meses = (string) $plazoMeses;
        } else {
            $campo = 'numero_cuotas';
            $plazoMeses = null;
            $numeroCuotas = (int) $validated['numero_cuotas'];
            $meses = CreditoService::mesesEquivalentes($numeroCuotas, $periodicidad);
        }

        $limites = config('scfp.creditos');
        $esVivienda = $validated['tipo_credito'] === 'VIVIENDA';
        $min = (string) ($esVivienda ? $limites['vivienda_plazo_min_meses'] : $limites['plazo_min_meses']);
        $max = (string) ($esVivienda ? $limites['vivienda_plazo_max_meses'] : $limites['plazo_max_meses']);

        if (bccomp($meses, $min, 4) === -1 || bccomp($meses, $max, 4) === 1) {
            $mensaje = $esVivienda
                ? "Los créditos de vivienda tienen un plazo entre {$min} y {$max} meses (Ley 546 de 1999)."
                : "El plazo debe estar entre {$min} y {$max} meses.";

            if ($campo === 'numero_cuotas') {
                $mensaje = "Las {$numeroCuotas} cuotas equivalen a ".$this->decimalEs(rtrim(rtrim($meses, '0'), '.')).' meses. '.$mensaje;
            }

            throw ValidationException::withMessages([$campo => $mensaje]);
        }

        return [$numeroCuotas, $plazoMeses];
    }

    /** Número con coma decimal para los mensajes: 22.5 → 22,5. */
    private function decimalEs(string $valor): string
    {
        return str_replace('.', ',', $valor);
    }

    /**
     * RNG-01: El global scope ya devolvió 404 para recursos ajenos. Aquí solo se
     * distingue el caso que la regla manda registrar en bitácora (RF-019).
     */
    private function creditoPropio(string $id): Credito
    {
        $credito = Credito::find($id);

        if ($credito !== null) {
            return $credito;
        }

        $esDeOtro = Credito::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereKey($id)
            ->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'creditos',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }

    private function mensajes(): array
    {
        return [
            'banco.required' => 'Indique el nombre del banco.',
            'banco.max' => 'El nombre del banco admite máximo 80 caracteres.',
            'proposito.required' => 'Indique el propósito del préstamo.',
            'proposito.min' => 'El propósito debe tener al menos 3 caracteres.',
            'proposito.max' => 'El propósito admite máximo 120 caracteres.',
            'monto.required' => 'Indique el monto del préstamo.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.gt' => 'El monto debe ser mayor que cero.',
            'monto.max' => 'El monto admite hasta 12 dígitos enteros.',
            'monto.regex' => 'El monto admite hasta 12 dígitos enteros y 2 decimales.',
            'tipo_credito.required' => 'Indique el tipo de crédito.',
            'tipo_credito.in' => 'El tipo de crédito debe ser libre inversión, libranza, vehículo, vivienda, tarjeta de crédito u otro.',
            'tasa.required' => 'Indique la tasa de interés.',
            'tasa.numeric' => 'La tasa de interés debe ser un número.',
            'tasa.min' => 'La tasa de interés no puede ser negativa.',
            'tasa.regex' => 'La tasa de interés admite hasta 4 dígitos enteros y 6 decimales, sin signo.',
            'tasa_mora.numeric' => 'La tasa de mora debe ser un número (en % EA).',
            'tasa_mora.min' => 'La tasa de mora no puede ser negativa.',
            'tasa_mora.regex' => 'La tasa de mora admite hasta 4 dígitos enteros y 6 decimales, sin signo.',
            'tipo_tasa.required' => 'Indique si la tasa es efectiva anual (EA), nominal o mensual.',
            'tipo_tasa.in' => 'El tipo de tasa debe ser EA, NOMINAL o MENSUAL.',
            'capitalizacion.required_if' => 'Indique cada cuánto capitaliza la tasa nominal (por ejemplo, MENSUAL para NMV).',
            'capitalizacion.prohibited_unless' => 'La capitalización solo aplica a una tasa nominal.',
            'capitalizacion.in' => 'La capitalización debe ser semanal, quincenal, mensual, bimestral, trimestral, semestral o anual.',
            'plazo_meses.required_without' => 'Indique el plazo en meses o el número de cuotas.',
            'plazo_meses.prohibits' => 'Indique el plazo en meses o el número de cuotas, no ambos.',
            'plazo_meses.integer' => 'El plazo debe ser un número entero de meses.',
            'numero_cuotas.required_without' => 'Indique el plazo en meses o el número de cuotas.',
            'numero_cuotas.integer' => 'El número de cuotas debe ser un entero.',
            'numero_cuotas.min' => 'El crédito debe tener al menos una cuota.',
            'numero_cuotas.max' => 'El número de cuotas admite máximo 9999.',
            'periodicidad.required' => 'Indique cada cuánto se paga la cuota.',
            'periodicidad.in' => 'La periodicidad debe ser semanal, quincenal, mensual, bimestral, trimestral, semestral o anual.',
            'sistema_amortizacion.in' => 'El sistema de amortización debe ser FRANCES (cuota fija) o ALEMAN (abono constante a capital).',
            'fecha_primer_pago.date_format' => 'La fecha del primer pago debe tener el formato AAAA-MM-DD.',
            'seguro_porc_mensual.numeric' => 'El seguro debe ser un porcentaje mensual sobre el saldo.',
            'seguro_porc_mensual.min' => 'El seguro no puede ser negativo.',
            'seguro_porc_mensual.max' => 'El seguro admite máximo '.config('scfp.creditos.seguro_porc_mensual_max').' % mensual sobre el saldo.',
            'seguro_porc_mensual.regex' => 'El seguro admite hasta 6 decimales.',
            'cargo_fijo.regex' => 'El cargo fijo por cuota va en pesos enteros, sin decimales ni separadores.',
            'valor_inmueble.prohibited_unless' => 'El valor del inmueble solo aplica a créditos de vivienda.',
            'valor_inmueble.regex' => 'El valor del inmueble va en pesos enteros, sin decimales ni separadores.',
            'valor_inmueble.not_regex' => 'El valor del inmueble debe ser mayor que cero.',
            'es_vis.prohibited_unless' => 'Indicar si es VIS solo aplica a créditos de vivienda.',
            'es_vis.boolean' => 'Indique si la vivienda es VIS con true o false.',
            'fecha_inicio.required' => 'Indique la fecha de desembolso del crédito.',
            'fecha_inicio.date_format' => 'La fecha de desembolso debe tener el formato AAAA-MM-DD.',
        ];
    }
}
