<?php

namespace App\Http\Controllers;

use App\Models\EntidadFinanciera;
use App\Models\FicCatalogo;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\UsuarioPerfilTributario;
use App\Models\ValoracionInversion;
use App\Services\BitacoraService;
use App\Services\CalculoImpuestoInversionService;
use App\Services\IngresoService;
use App\Services\InversionService;
use App\Services\RentabilidadService;
use App\Services\TrmService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * MOD-06 — RF-037 / v1.1: Registro de inversiones en los 6 tipos normativos:
 * CDT, ACCION_ETF, FIC, CUENTA_ALTO_RENDIMIENTO, FPV, CESANTIAS.
 */
class InversionController extends Controller
{
    private const POR_PAGINA = [10, 20, 50, 100];

    public function index(Request $request): View|JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['nullable', Rule::in(Inversion::ESTADOS)],
            'tipo' => ['nullable', Rule::in(Inversion::TIPOS)],
        ], [
            'estado.in' => 'El estado no es válido.',
            'tipo.in' => 'El tipo de inversión no es válido.',
        ]);

        $porPagina = (int) $request->query('por_pagina', 20);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 20;
        }

        $inversiones = Inversion::query()
            ->with(['entidadFinanciera', 'detalleCdt', 'detalleCuenta', 'detalleRentaVariable', 'detalleFic.catalogo', 'detalleFpv', 'detalleCesantias'])
            ->when($validated['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($validated['tipo'] ?? null, fn ($query, $tipo) => $query->where('tipo', $tipo))
            ->orderByDesc('fecha_apertura')
            ->orderByDesc('creado_en')
            ->paginate($porPagina)
            ->withQueryString();

        $hoy = CarbonImmutable::today();
        $presentadas = collect($inversiones->items())->map(fn (Inversion $inversion) => InversionService::presentar($inversion, $hoy));

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Inversiones consultadas.',
                'data' => [
                    'inversiones' => $presentadas,
                    'meta' => [
                        'pagina_actual' => $inversiones->currentPage(),
                        'por_pagina' => $inversiones->perPage(),
                        'total' => $inversiones->total(),
                        'ultima_pagina' => $inversiones->lastPage(),
                    ],
                ],
            ]);
        }

        $entidades = EntidadFinanciera::orderBy('nombre')->get();
        $fics = FicCatalogo::with('composicion')->where('activo', true)->get();
        $perfil = UsuarioPerfilTributario::where('usuario_id', Auth::id())
            ->where('anio_gravable', $hoy->year)
            ->first();

        return view('inversiones.index', [
            'inversiones' => $inversiones,
            'inversionesPresentadas' => $presentadas,
            'tipos' => Inversion::TIPOS,
            'estados' => Inversion::ESTADOS,
            'entidades' => $entidades,
            'fics' => $fics,
            'perfilTributario' => $perfil,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->has('nivel_riesgo')) {
            $nivel = $request->input('nivel_riesgo');
            $request->merge(['nivel_riesgo' => ! empty($nivel) ? strtoupper(trim($nivel)) : null]);
        }

        if ($request->has('tasa_ea') && is_string($request->input('tasa_ea'))) {
            $tasaEa = str_replace(',', '.', trim($request->input('tasa_ea')));
            $request->merge(['tasa_ea' => $tasaEa !== '' ? $tasaEa : null]);
        }

        $tipo = $request->input('tipo');

        if ($tipo === 'ACCION_ETF' && ! $request->filled('valor_dividendo_por_unidad')) {
            $request->merge(['valor_dividendo_por_unidad' => '0.00']);
        }

        // RF-INV-06: Auto-cálculo de 2 de 3 valores en productos por unidades (ACCION_ETF, FIC, FPV)
        if (in_array($tipo, ['ACCION_ETF', 'FIC', 'FPV'], true)) {
            $montoIn = $request->input('monto_inicial');
            $unidadesIn = $request->input('unidades');
            $valorUnidadIn = $request->input('valor_unidad') ?? $request->input('precio_unitario_compra');

            $tieneMonto = ! empty($montoIn) && is_numeric($montoIn) && (float) $montoIn > 0;
            $tieneUnidades = ! empty($unidadesIn) && is_numeric($unidadesIn) && (float) $unidadesIn > 0;
            $tieneValorUnidad = ! empty($valorUnidadIn) && is_numeric($valorUnidadIn) && (float) $valorUnidadIn > 0;

            if ($tieneMonto && $tieneValorUnidad && ! $tieneUnidades) {
                $unidadesCalc = bcdiv((string) $montoIn, (string) $valorUnidadIn, 8);
                $request->merge(['unidades' => $unidadesCalc]);
            } elseif ($tieneMonto && $tieneUnidades && ! $tieneValorUnidad) {
                $valorUnidadCalc = bcdiv((string) $montoIn, (string) $unidadesIn, 8);
                $request->merge([
                    'valor_unidad' => $valorUnidadCalc,
                    'precio_unitario_compra' => $valorUnidadCalc,
                ]);
            } elseif ($tieneUnidades && $tieneValorUnidad && ! $tieneMonto) {
                $montoCalc = IngresoService::redondear(bcmul((string) $unidadesIn, (string) $valorUnidadIn, 8));
                $request->merge(['monto_inicial' => $montoCalc]);
            } elseif ($tieneMonto && $tieneUnidades && $tieneValorUnidad) {
                // Verificar consistencia con tolerancia de $1 COP
                $esperado = bcmul((string) $unidadesIn, (string) $valorUnidadIn, 4);
                $diferencia = abs((float) bcsub((string) $montoIn, $esperado, 4));
                if ($diferencia > 1.0) {
                    throw ValidationException::withMessages([
                        'monto_inicial' => 'Inconsistencia en los valores ingresados: Monto ($' . number_format((float) $montoIn, 2) . ') no coincide con Unidades × Valor Unidad ($' . number_format((float) $esperado, 2) . '). Deje uno de los campos vacío para auto-calcularlo.',
                    ]);
                }
            }
        }

        $validated = $request->validate($this->reglas($request), $this->mensajes());

        $hoy = CarbonImmutable::today();
        $apertura = CarbonImmutable::parse($validated['fecha_apertura']);
        $montoInicial = (string) $validated['monto_inicial'];

        // Conversión TRM
        $tasa = TrmService::tasaPara($validated['moneda'], $validated['tasa_cambio_usada'] ?? null, $apertura->toDateString());
        $montoCop = IngresoService::convertirACop($montoInicial, $tasa);

        $monedaDividendo = $validated['moneda_dividendo'] ?? 'COP';
        $trmDividendo = null;
        if ($tipo === 'ACCION_ETF' && strtoupper($monedaDividendo) === 'USD') {
            $trmDividendo = TrmService::tasaPara('USD', $validated['trm_dividendo'] ?? null, $apertura->toDateString());
        }

        if (! IngresoService::montoEnRango($montoCop)) {
            throw ValidationException::withMessages([
                'monto_inicial' => 'El monto convertido a pesos supera el máximo permitido de $ 999.999.999.999,99.',
            ]);
        }

        // RF-PER-01 / RF-PER-03: Actualizar perfil tributario si se proporcionan campos
        if ($request->filled('ingreso_anual_estimado') || $request->filled('ingreso_laboral_promedio_6m') || $request->filled('tarifa_marginal_estimada')) {
            UsuarioPerfilTributario::updateOrCreate(
                [
                    'usuario_id' => Auth::id(),
                    'anio_gravable' => $apertura->year,
                ],
                [
                    'es_declarante' => $request->boolean('es_declarante', false),
                    'es_residente' => $request->boolean('es_residente', true),
                    'obligado_contabilidad' => $request->boolean('obligado_contabilidad', false),
                    'ingreso_anual_estimado' => (string) ($request->input('ingreso_anual_estimado') ?? '0'),
                    'ingreso_laboral_promedio_6m' => (string) ($request->input('ingreso_laboral_promedio_6m') ?? '0'),
                    'tarifa_marginal_estimada' => (string) ($request->input('tarifa_marginal_estimada') ?? '0'),
                ]
            );
        }

        // FPV: Validar tope de aportes (9.8.2)
        $datosFpv = [];
        if ($tipo === 'FPV') {
            $perfil = UsuarioPerfilTributario::where('usuario_id', Auth::id())
                ->where('anio_gravable', $apertura->year)
                ->first();
            $ingresoAnual = (string) ($perfil?->ingreso_anual_estimado ?? $request->input('ingreso_anual_estimado') ?? '0');

            // Sumar aportes FPV previos del año
            $aportesFpvAnio = MovimientoInversion::where('usuario_id', Auth::id())
                ->where('tipo', 'APORTE')
                ->whereYear('fecha', $apertura->year)
                ->whereHas('inversion', fn ($q) => $q->where('tipo', 'FPV'))
                ->sum('monto_cop');

            $datosFpv = CalculoImpuestoInversionService::validarTopeAporteFpv(
                montoAporte: $montoCop,
                ingresoAnual: $ingresoAnual,
                aportesAfcAnio: '0.00',
                aportesFpvAnio: (string) $aportesFpvAnio,
                fecha: $apertura->toDateString()
            );
        }

        // Vencimiento para CDT
        $vencimiento = null;
        if ($tipo === 'CDT') {
            $vencimiento = isset($validated['fecha_vencimiento'])
                ? CarbonImmutable::parse($validated['fecha_vencimiento'])
                : InversionService::fechaVencimiento($apertura, (int) $validated['plazo_dias']);
        }

        $inversion = DB::transaction(function () use ($validated, $tipo, $hoy, $apertura, $montoInicial, $tasa, $montoCop, $vencimiento, $datosFpv, $request, $monedaDividendo, $trmDividendo) {
            $unidadesInit = (!empty($validated['unidades']) && (float) $validated['unidades'] > 0)
                ? (string) $validated['unidades']
                : null;
            $precioUnitarioRaw = $validated['valor_unidad'] ?? $validated['precio_unitario_compra'] ?? null;
            $precioUnitarioInit = (!empty($precioUnitarioRaw) && (float) $precioUnitarioRaw > 0)
                ? (string) $precioUnitarioRaw
                : null;

            $inversion = Inversion::create([
                'usuario_id' => Auth::id(),
                'nombre' => $validated['nombre'],
                'tipo' => $tipo,
                'entidad' => $validated['entidad'] ?? null,
                'entidad_id' => $validated['entidad_id'] ?? null,
                'moneda' => $validated['moneda'],
                'fecha_apertura' => $apertura->toDateString(),
                'monto_inicial' => $montoInicial,
                'nivel_riesgo' => $validated['nivel_riesgo'] ?? Inversion::riesgoSugerido($tipo),
                'tasa_ea' => in_array($tipo, ['CDT', 'CUENTA_ALTO_RENDIMIENTO'], true) ? (string) ($validated['tasa_ea'] ?? '0') : null,
                'plazo_dias' => isset($validated['plazo_dias']) ? (int) $validated['plazo_dias'] : null,
                'periodicidad_intereses' => match ($validated['periodicidad_pago'] ?? null) {
                    'VENCIMIENTO', 'AL_VENCIMIENTO' => 'AL_VENCIMIENTO',
                    default => $validated['periodicidad_pago'] ?? null,
                },
                'fecha_vencimiento' => $vencimiento?->toDateString(),
                'simbolo' => $tipo === 'ACCION_ETF' ? ($validated['simbolo'] ?? null) : null,
                'unidades_iniciales' => in_array($tipo, ['ACCION_ETF', 'FIC', 'FPV'], true) ? $unidadesInit : null,
                'precio_unitario_compra' => in_array($tipo, ['ACCION_ETF', 'FIC', 'FPV'], true) ? $precioUnitarioInit : null,
                'rentabilidad_esperada_porc' => isset($validated['rentabilidad_esperada_porc']) ? (string) $validated['rentabilidad_esperada_porc'] : null,
                'plazo_meses_esperado' => isset($validated['plazo_meses_esperado']) ? (int) $validated['plazo_meses_esperado'] : null,
                'estado' => $vencimiento !== null && $vencimiento->lt($hoy) ? 'VENCIDA' : 'ACTIVA',
            ]);

            // Crear el registro 1:1 especializado según tipo
            match ($tipo) {
                'CDT' => $inversion->detalleCdt()->create([
                    'capital' => $montoInicial,
                    'tasa_valor' => (string) $validated['tasa_ea'],
                    'tipo_tasa' => $validated['tipo_tasa'] ?? 'EA',
                    'periodicidad_nominal' => $validated['periodicidad_nominal'] ?? null,
                    'plazo_dias' => (int) $validated['plazo_dias'],
                    'fecha_vencimiento' => $vencimiento->toDateString(),
                    'periodicidad_pago' => $validated['periodicidad_pago'] ?? 'VENCIMIENTO',
                    'es_cdat' => $request->boolean('es_cdat', false),
                    'base_dias' => (int) ($validated['base_dias'] ?? 365),
                ]),
                'CUENTA_ALTO_RENDIMIENTO' => $inversion->detalleCuenta()->create([
                    'saldo_inicial' => $montoInicial,
                    'tasa_ea' => (string) $validated['tasa_ea'],
                    'es_cuenta_gmf_exenta' => $request->boolean('es_cuenta_gmf_exenta', false),
                    'base_dias' => (int) ($validated['base_dias'] ?? 365),
                    'periodicidad_abono' => $validated['periodicidad_abono'] ?? 'MENSUAL',
                ]),
                'ACCION_ETF' => $inversion->detalleRentaVariable()->create([
                    'simbolo' => $validated['simbolo'],
                    'nombre_instrumento' => $validated['nombre_instrumento'] ?? $validated['nombre'],
                    'tipo' => $validated['subtipo_renta_variable'] ?? 'ACCION',
                    'mercado' => $validated['mercado'] ?? 'BVC',
                    'esta_inscrito_bvc' => $request->boolean('esta_inscrito_bvc', true),
                    'porcentaje_circulacion_vendido_anio' => (string) ($validated['porcentaje_circulacion_vendido_anio'] ?? '0.00'),
                    'unidades_total' => (string) $validated['unidades'],
                    'costo_promedio_unitario' => (string) ($validated['valor_unidad'] ?? $validated['precio_unitario_compra']),
                    'fecha_primera_compra' => $apertura->toDateString(),
                    'tipo_reparto' => $validated['tipo_reparto'] ?? 'DISTRIBUTIVO',
                    'valor_actual_unidad' => (string) ($validated['valor_unidad'] ?? $validated['precio_unitario_compra']),
                    'valor_dividendo_por_unidad' => (string) ($validated['valor_dividendo_por_unidad'] ?? '0.00'),
                    'moneda_dividendo' => $monedaDividendo,
                    'trm_dividendo' => $trmDividendo,
                    'trm_compra' => $tasa,
                ]),
                'FIC' => $inversion->detalleFic()->create([
                    'fic_catalogo_id' => $validated['fic_catalogo_id'],
                    'unidades' => (string) $validated['unidades'],
                    'valor_unidad_compra' => (string) ($validated['valor_unidad'] ?? $validated['precio_unitario_compra']),
                    'valor_unidad_actual' => (string) ($validated['valor_unidad'] ?? $validated['precio_unitario_compra']),
                    'pacto_permanencia_dias' => isset($validated['pacto_permanencia_dias']) ? (int) $validated['pacto_permanencia_dias'] : null,
                    'comision_administracion_ea' => (string) ($validated['comision_administracion_ea'] ?? '0.00'),
                ]),
                'FPV' => $inversion->detalleFpv()->create([
                    'administradora' => $validated['entidad'] ?? 'AFP Skandia',
                    'tipo_vinculacion' => $validated['tipo_vinculacion'] ?? 'DIRECTO',
                    'saldo_actual' => $montoInicial,
                    'unidades' => isset($validated['unidades']) ? (string) $validated['unidades'] : null,
                    'valor_unidad' => isset($validated['valor_unidad']) ? (string) $validated['valor_unidad'] : null,
                ]),
                'CESANTIAS' => $inversion->detalleCesantias()->create([
                    'fondo' => $validated['entidad'] ?? 'Porvenir Cesantías',
                    'regimen' => $validated['regimen'] ?? 'ANUALIZADO',
                    'saldo' => $montoInicial,
                    'tasa_interes_esperada' => (string) ($validated['tasa_interes_esperada'] ?? '0.00'),
                    'plazo_dias' => (int) ($validated['plazo_dias'] ?? 365),
                    'salario_promedio_6m_override' => isset($validated['ingreso_laboral_promedio_6m']) ? (string) $validated['ingreso_laboral_promedio_6m'] : null,
                ]),
            };

            // Primer movimiento (Aporte inicial)
            $movimientoInicial = MovimientoInversion::create([
                'usuario_id' => Auth::id(),
                'inversion_id' => $inversion->id,
                'tipo' => 'APORTE',
                'fecha' => $apertura->toDateString(),
                'monto' => $montoInicial,
                'tasa_cambio_usada' => $tasa,
                'monto_cop' => $montoCop,
                'monto_bruto' => $montoInicial,
                'valor_unidad' => in_array($tipo, ['ACCION_ETF', 'FIC', 'FPV'], true) ? $precioUnitarioInit : null,
                'trm' => $tasa,
                'comision' => '0.00',
                'retencion_aplicada' => '0.00',
                'gmf_aplicado' => '0.00',
                'impuesto_origen' => '0.00',
                'unidades' => in_array($tipo, ['ACCION_ETF', 'FIC', 'FPV'], true) ? $unidadesInit : null,
                'nota' => 'Aporte inicial de la inversión',
                'es_inicial' => true,
            ]);

            // Valoración inicial
            ValoracionInversion::create([
                'usuario_id' => Auth::id(),
                'inversion_id' => $inversion->id,
                'fecha' => $apertura->toDateString(),
                'valor' => $montoInicial,
                'precio_unitario' => in_array($tipo, ['ACCION_ETF', 'FIC', 'FPV'], true) ? $precioUnitarioInit : null,
                'unidades' => in_array($tipo, ['ACCION_ETF', 'FIC', 'FPV'], true) ? $unidadesInit : null,
                'origen' => 'INICIAL',
            ]);

            // Registrar puentes tributarios (FPV primer aporte dentro de tope)
            InversionService::registrarPuentesTributarios($movimientoInicial, $inversion, $datosFpv);

            BitacoraService::registrar(
                accion: 'CREAR_INVERSION',
                entidadAfectada: 'inversiones',
                entidadId: $inversion->id,
                valorNuevo: $inversion->fresh()->toArray(),
                justificacion: "Registro de inversión tipo {$tipo} con aporte inicial normalizado (v1.1).",
                usuarioId: Auth::id()
            );

            return $inversion->fresh();
        });

        $avisoFpv = '';
        if ($tipo === 'FPV' && ! empty($datosFpv['excede_tope'])) {
            $avisoFpv = ' Aviso: el aporte superó el cupo anual exento de $' . number_format((float) $datosFpv['cupo_disponible'], 2) . '. Solo la parte dentro del tope ($' . number_format((float) $datosFpv['parte_dentro_tope'], 2) . ') suma como deducible.';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Inversión registrada con éxito.' . $avisoFpv,
                'data' => InversionService::presentar($inversion, $hoy),
            ], 201);
        }

        return redirect()->route('inversiones.show', $inversion->id)
            ->with('status', 'Inversión registrada con éxito. El monto inicial se registró como primer aporte.' . $avisoFpv);
    }

    public function show(Request $request, string $id): View|JsonResponse
    {
        $inversion = $this->inversionPropia($id);
        $hoy = CarbonImmutable::today();

        InversionService::reconciliarValoraciones($inversion);

        $movimientos = MovimientoInversion::where('inversion_id', $inversion->id)
            ->orderByDesc('fecha')
            ->orderByDesc('creado_en')
            ->get();

        $valoraciones = ValoracionInversion::where('inversion_id', $inversion->id)
            ->orderByDesc('fecha')
            ->get();

        $valoracionesCronologicas = ValoracionInversion::where('inversion_id', $inversion->id)
            ->orderBy('fecha', 'asc')
            ->orderBy('creado_en', 'asc')
            ->get();

        $indicadores = RentabilidadService::indicadores($inversion, $hoy);
        $presentacion = InversionService::presentar($inversion, $hoy);

        $valorCausadoPropuesto = $inversion->tipo === 'CDT'
            ? InversionService::valorCausado($inversion, $hoy)
            : null;

        $deduciblesGenerados = $inversion->valoresDeducibles()->orderByDesc('creado_en')->get();
        $ingresosGenerados = $inversion->ingresosInversion()->orderByDesc('creado_en')->get();

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Inversión consultada.',
                'data' => array_merge($presentacion, [
                    'movimientos' => $movimientos,
                    'valoraciones' => $valoraciones,
                    'valoraciones_cronologicas' => $valoracionesCronologicas,
                    'indicadores' => $indicadores,
                    'deducibles_generados' => $deduciblesGenerados,
                    'ingresos_generados' => $ingresosGenerados,
                ]),
            ]);
        }

        $fics = FicCatalogo::with('composicion')->where('activo', true)->get();
        $perfil = UsuarioPerfilTributario::where('usuario_id', Auth::id())
            ->where('anio_gravable', $hoy->year)
            ->first();

        return view('inversiones.show', [
            'inversion' => $inversion,
            'presentacion' => $presentacion,
            'movimientos' => $movimientos,
            'valoraciones' => $valoraciones,
            'valoracionesCronologicas' => $valoracionesCronologicas,
            'indicadores' => $indicadores,
            'valorCausadoPropuesto' => $valorCausadoPropuesto,
            'deduciblesGenerados' => $deduciblesGenerados,
            'ingresosGenerados' => $ingresosGenerados,
            'fics' => $fics,
            'perfilTributario' => $perfil,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $inversion = $this->inversionPropia($id);

        if ($request->has('nivel_riesgo')) {
            $nivel = $request->input('nivel_riesgo');
            $request->merge(['nivel_riesgo' => ! empty($nivel) ? strtoupper(trim($nivel)) : null]);
        }

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'min:3', 'max:60'],
            'entidad' => ['sometimes', 'nullable', 'string', 'max:80'],
            'nivel_riesgo' => ['sometimes', 'nullable', Rule::in(Inversion::NIVELES_RIESGO)],
            'simbolo' => ['sometimes', 'nullable', 'string', 'max:15'],
            'rentabilidad_esperada_porc' => ['sometimes', 'nullable', 'numeric', 'between:-100,10000'],
            'plazo_meses_esperado' => ['sometimes', 'nullable', 'integer', 'between:1,360'],
            'fecha_vencimiento' => ['sometimes', 'required', 'date', 'after:' . $inversion->fecha_apertura->toDateString()],
            'valor_actual_unidad' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ], $this->mensajes());

        $anterior = $inversion->toArray();

        DB::transaction(function () use ($inversion, $validated, $anterior) {
            $inversion->fill($validated);

            if (array_key_exists('fecha_vencimiento', $validated) && ! $inversion->estaLiquidada()) {
                $inversion->estado = InversionService::estadoSegunVencimiento(
                    CarbonImmutable::parse($validated['fecha_vencimiento']),
                    CarbonImmutable::today()
                );
            }

            $inversion->save();

            // Si se actualiza el valor por unidad en renta variable o FIC
            if (! empty($validated['valor_actual_unidad'])) {
                if ($inversion->tipo === 'ACCION_ETF' && $inversion->detalleRentaVariable) {
                    $inversion->detalleRentaVariable->update([
                        'valor_actual_unidad' => (string) $validated['valor_actual_unidad'],
                    ]);
                } elseif ($inversion->tipo === 'FIC' && $inversion->detalleFic) {
                    $inversion->detalleFic->update([
                        'valor_unidad_actual' => (string) $validated['valor_actual_unidad'],
                    ]);
                }
            }

            BitacoraService::registrar(
                accion: 'EDITAR_INVERSION',
                entidadAfectada: 'inversiones',
                entidadId: $inversion->id,
                valorAnterior: $anterior,
                valorNuevo: $inversion->fresh()->toArray(),
                justificacion: 'RF-037: edición de los datos de la inversión.',
                usuarioId: Auth::id()
            );
        });

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Inversión actualizada.',
                'data' => InversionService::presentar($inversion->fresh(), CarbonImmutable::today()),
            ]);
        }

        return redirect()->route('inversiones.show', $inversion->id)
            ->with('status', 'Inversión actualizada con éxito.');
    }

    public function destroy(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $inversion = $this->inversionPropia($id);

        $tieneRendimientos = MovimientoInversion::where('inversion_id', $inversion->id)
            ->whereIn('tipo', ['RENDIMIENTO', 'ABONO_INTERES', 'DIVIDENDO'])
            ->exists();

        if ($tieneRendimientos) {
            throw ValidationException::withMessages([
                'inversion' => 'La inversión tiene rendimientos o abonos registrados como ingresos: no se puede eliminar directamente. Márquela como Cerrada.',
            ]);
        }

        DB::transaction(function () use ($inversion) {
            $anterior = $inversion->toArray();
            $inversion->delete();

            BitacoraService::registrar(
                accion: 'ELIMINAR_INVERSION',
                entidadAfectada: 'inversiones',
                entidadId: $inversion->id,
                valorAnterior: $anterior,
                justificacion: 'RNG-07: eliminación lógica de la inversión.',
                usuarioId: Auth::id()
            );
        });

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => 'Inversión eliminada.']);
        }

        return redirect()->route('inversiones.index')
            ->with('status', 'Inversión eliminada con éxito.');
    }

    private function inversionPropia(string $id): Inversion
    {
        $inversion = Inversion::find($id);

        if ($inversion !== null) {
            return $inversion;
        }

        $esDeOtro = Inversion::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->whereKey($id)
            ->exists();

        if ($esDeOtro) {
            BitacoraService::registrar(
                accion: 'ACCESO_DENEGADO_RECURSO_AJENO',
                entidadAfectada: 'inversiones',
                entidadId: $id,
                justificacion: 'RNG-01: intento de acceso a un recurso de otro usuario.',
                usuarioId: Auth::id()
            );
        }

        abort(404);
    }

    private function reglas(Request $request): array
    {
        $tipo = $request->input('tipo');

        if ($tipo === 'ACCION_ETF' && ! $request->filled('valor_dividendo_por_unidad')) {
            $request->merge(['valor_dividendo_por_unidad' => '0.00']);
        }

        return [
            'nombre' => ['required', 'string', 'min:3', 'max:60'],
            'tipo' => ['required', Rule::in(Inversion::TIPOS)],
            'entidad' => ['nullable', 'string', 'max:80'],
            'entidad_id' => ['nullable', 'uuid', 'exists:entidad_financiera,id'],
            'moneda' => ['required', Rule::in(Inversion::MONEDAS)],
            'fecha_apertura' => ['required', 'date', 'before_or_equal:today'],
            'monto_inicial' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'nivel_riesgo' => ['nullable', Rule::in(Inversion::NIVELES_RIESGO)],
            'tasa_cambio_usada' => ['nullable', 'numeric', 'gt:0'],

            // Campos específicos CDT
            'tasa_ea' => [Rule::requiredIf(in_array($tipo, ['CDT', 'CUENTA_ALTO_RENDIMIENTO'], true)), 'nullable', 'numeric', 'between:0,100'],
            'tipo_tasa' => ['nullable', Rule::in(['EA', 'NOMINAL'])],
            'plazo_dias' => [Rule::requiredIf($tipo === 'CDT'), 'nullable', 'integer', 'between:1,3650'],
            'periodicidad_pago' => ['nullable', Rule::in(['VENCIMIENTO', 'AL_VENCIMIENTO', 'MENSUAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL'])],
            'fecha_vencimiento' => ['nullable', 'date', 'after:fecha_apertura'],
            'base_dias' => ['nullable', Rule::in([360, 365])],

            // Campos específicos Acciones / ETF
            'simbolo' => [Rule::requiredIf($tipo === 'ACCION_ETF'), 'nullable', 'string', 'max:15'],
            'unidades' => [Rule::requiredIf(in_array($tipo, ['ACCION_ETF', 'FIC'], true)), 'nullable', 'numeric', 'gt:0'],
            'valor_unidad' => [Rule::requiredIf(in_array($tipo, ['ACCION_ETF', 'FIC'], true)), 'nullable', 'numeric', 'gt:0'],
            'subtipo_renta_variable' => ['nullable', Rule::in(['ACCION', 'ETF'])],
            'mercado' => ['nullable', Rule::in(['BVC', 'EXTERIOR'])],
            'tipo_reparto' => ['nullable', Rule::in(['DISTRIBUTIVO', 'ACUMULATIVO', 'NO_APLICA'])],
            'valor_dividendo_por_unidad' => [Rule::requiredIf($tipo === 'ACCION_ETF'), 'nullable', 'numeric', 'gte:0'],
            'moneda_dividendo' => ['nullable', Rule::in(['COP', 'USD'])],
            'trm_dividendo' => ['nullable', 'numeric', 'gt:0'],

            // Campos específicos FIC
            'fic_catalogo_id' => [Rule::requiredIf($tipo === 'FIC'), 'nullable', 'uuid', 'exists:fic_catalogo,id'],
            'pacto_permanencia_dias' => ['nullable', 'integer', 'gte:0'],

            // Campos específicos FPV
            'tipo_vinculacion' => ['nullable', Rule::in(['DIRECTO', 'VIA_EMPLEADOR'])],

            // Campos específicos Cesantías
            'regimen' => ['nullable', Rule::in(['ANUALIZADO', 'TRADICIONAL'])],
            'tasa_interes_esperada' => ['nullable', 'numeric', 'between:0,100'],

            // Campos de perfil tributario opcionales
            'es_declarante' => ['nullable', 'boolean'],
            'es_residente' => ['nullable', 'boolean'],
            'obligado_contabilidad' => ['nullable', 'boolean'],
            'ingreso_anual_estimado' => ['nullable', 'numeric', 'gte:0'],
            'ingreso_laboral_promedio_6m' => ['nullable', 'numeric', 'gte:0'],
            'tarifa_marginal_estimada' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    private function mensajes(): array
    {
        return [
            'nombre.required' => 'Indique el nombre de la inversión.',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max' => 'El nombre admite máximo 60 caracteres.',
            'tipo.required' => 'Indique el tipo de inversión.',
            'tipo.in' => 'El tipo de inversión debe ser uno de los 6 productos oficiales.',
            'moneda.required' => 'Indique la moneda (COP o USD).',
            'fecha_apertura.required' => 'Indique la fecha de apertura.',
            'fecha_apertura.before_or_equal' => 'La fecha de apertura no puede ser futura.',
            'monto_inicial.required' => 'Indique el monto inicial de la inversión.',
            'monto_inicial.gt' => 'El monto inicial debe ser mayor que cero.',
            'tasa_ea.required' => 'La tasa de interés es obligatoria para este producto.',
            'plazo_dias.required' => 'El plazo en días es obligatorio para el CDT.',
            'simbolo.required' => 'El símbolo mnemotécnico o ticker es obligatorio para Acciones / ETF.',
            'valor_dividendo_por_unidad.required' => 'Indique el dividendo por acción decretado (digite 0 si no reparte dividendos).',
            'unidades.required' => 'La cantidad de unidades o títulos es obligatoria.',
            'valor_unidad.required' => 'El valor o precio por unidad es obligatorio.',
            'fic_catalogo_id.required' => 'Seleccione un fondo del catálogo de FICs.',
        ];
    }
}
