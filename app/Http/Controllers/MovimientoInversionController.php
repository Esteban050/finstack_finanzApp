<?php

namespace App\Http\Controllers;

use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\UsuarioPerfilTributario;
use App\Models\ValoracionInversion;
use App\Models\ValorDeducibleRegistro;
use App\Services\BitacoraService;
use App\Services\CalculoImpuestoInversionService;
use App\Services\IngresoService;
use App\Services\InversionService;
use App\Services\ParametroTributarioService;
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

/**
 * MOD-06 — RF-038 / v1.1: Movimientos/Transacciones de inversiones.
 * Gestiona COMPRA, VENTA, APORTE, RETIRO, ABONO_INTERES, DIVIDENDO, COMISION, REDENCION
 * aplicando las deducciones en el momento (retención, GMF) y registrando los puentes tributarios.
 */
class MovimientoInversionController extends Controller
{
    private const POR_PAGINA = [10, 20, 50, 100];

    public function index(Request $request, string $id): JsonResponse
    {
        $inversion = $this->inversionPropia($id);

        $porPagina = (int) $request->query('por_pagina', 20);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 20;
        }

        $movimientos = MovimientoInversion::where('inversion_id', $inversion->id)
            ->orderByDesc('fecha')
            ->orderByDesc('creado_en')
            ->paginate($porPagina)
            ->withQueryString();

        return response()->json([
            'mensaje' => 'Movimientos consultados.',
            'data' => [
                'movimientos' => $movimientos->items(),
                'meta' => [
                    'pagina_actual' => $movimientos->currentPage(),
                    'por_pagina' => $movimientos->perPage(),
                    'total' => $movimientos->total(),
                    'ultima_pagina' => $movimientos->lastPage(),
                ],
            ],
        ]);
    }

    public function store(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $inversion = $this->inversionPropia($id);

        if ($inversion->estaLiquidada()) {
            throw ValidationException::withMessages([
                'inversion' => 'La inversión está cerrada o liquidada: no admite transacciones nuevas.',
            ]);
        }

        if (! $request->filled('monto') && $request->filled('unidades') && $request->filled('valor_unidad')) {
            $montoCalc = IngresoService::redondear(bcmul((string) $request->input('unidades'), (string) $request->input('valor_unidad'), 4));
            $request->merge(['monto' => $montoCalc]);
        }

        $validated = $request->validate($this->reglas($inversion), $this->mensajes());

        $tipo = $validated['tipo'];
        if (! $inversion->permiteMovimiento($tipo)) {
            throw ValidationException::withMessages([
                'tipo' => "La inversión de tipo «{$inversion->tipo}» no admite transacciones de tipo «{$tipo}».",
            ]);
        }

        $fecha = $validated['fecha'];
        $monto = (string) ($validated['monto'] ?? '0');
        $unidades = isset($validated['unidades']) ? (string) $validated['unidades'] : null;
        $valorUnidad = isset($validated['valor_unidad']) ? (string) $validated['valor_unidad'] : null;
        $comision = (string) ($validated['comision'] ?? '0.00');
        $impuestoOrigen = (string) ($validated['impuesto_origen'] ?? '0.00');

        // TRM para transacciones
        $tasa = TrmService::tasaPara($inversion->moneda, $validated['tasa_cambio_usada'] ?? null, $fecha);

        // Variables de liquidación tributaria
        $retencionAplicada = '0.00';
        $gmfAplicado = '0.00';
        $montoBruto = $monto;
        $datosPuente = [];

        // Perfil tributario para tarifas marginales
        $perfil = UsuarioPerfilTributario::where('usuario_id', Auth::id())
            ->where('anio_gravable', CarbonImmutable::parse($fecha)->year)
            ->first();
        $tarifaMarginal = $perfil ? (string) $perfil->tarifa_marginal_estimada : '0.00';

        // ----------------------------------------------------
        // Lógica específica por producto y tipo de transacción
        // ----------------------------------------------------

        // 1. COMPRA en ACCION_ETF
        if ($inversion->tipo === 'ACCION_ETF' && in_array($tipo, ['COMPRA', 'APORTE'], true)) {
            $detalle = $inversion->detalleRentaVariable;
            if ($detalle && $unidades !== null && $valorUnidad !== null) {
                $montoBruto = IngresoService::redondear(bcmul($unidades, $valorUnidad, 4));
                $monto = bcadd($montoBruto, $comision, 2);
            }
        }

        // 2. VENTA en ACCION_ETF
        if ($inversion->tipo === 'ACCION_ETF' && $tipo === 'VENTA') {
            $detalle = $inversion->detalleRentaVariable;
            if (! $detalle) {
                throw new \RuntimeException('No se encontró el detalle de renta variable.');
            }

            // RF-INV-05: Validar que no se vendan más unidades de las disponibles
            if (bccomp($unidades, (string) $detalle->unidades_total, 8) === 1) {
                throw ValidationException::withMessages([
                    'unidades' => "No puede vender más unidades de las disponibles. Saldo actual: {$detalle->unidades_total} unidades.",
                ]);
            }

            $liquidacionVenta = CalculoImpuestoInversionService::calcularVentaAcciones(
                unidadesVendidas: $unidades,
                precioVenta: $valorUnidad,
                costoPromedioUnitario: (string) $detalle->costo_promedio_unitario,
                comisionVenta: $comision,
                estaInscritoBvc: (bool) $detalle->esta_inscrito_bvc,
                porcentajeCirculacionVendidoAnio: (string) ($detalle->porcentaje_circulacion_vendido_anio ?? '0.00'),
                fechaPrimeraCompra: $detalle->fecha_primera_compra ? $detalle->fecha_primera_compra->toDateString() : $fecha,
                fechaVenta: $fecha,
                tarifaMarginalUsuario: $tarifaMarginal,
                trmVenta: $tasa,
                trmCompra: (string) ($detalle->trm_compra ?? '1.0000')
            );

            if ($inversion->moneda === 'USD') {
                $montoBruto = $liquidacionVenta['ingreso_bruto_original'];
                $monto = $liquidacionVenta['neto_antes_impuesto_original'];
            } else {
                $montoBruto = $liquidacionVenta['ingreso_bruto_cop'];
                $monto = $liquidacionVenta['neto_antes_impuesto'];
            }

            $datosPuente = [
                'utilidad_cop' => $liquidacionVenta['utilidad_cop'],
                'utilidad_original' => $liquidacionVenta['utilidad_original'] ?? $liquidacionVenta['utilidad_cop'],
                'clase_fiscal' => $liquidacionVenta['clase_fiscal'],
                'anios_tenencia' => $liquidacionVenta['anios_tenencia'] ?? 0,
                'impuesto_estimado' => $liquidacionVenta['impuesto_renta_estimado'],
            ];
        }

        // 3. DIVIDENDO en ACCION_ETF
        if ($inversion->tipo === 'ACCION_ETF' && $tipo === 'DIVIDENDO') {
            $detalle = $inversion->detalleRentaVariable;
            $tipoReparto = $detalle?->tipo_reparto ?? 'DISTRIBUTIVO';

            $valDivUnit = $valorUnidad ?? ($detalle?->valor_dividendo_por_unidad ? (string) $detalle->valor_dividendo_por_unidad : '0');
            $unidadesBase = $unidades ?? ($detalle?->unidades_total ? (string) $detalle->unidades_total : '0');

            // Moneda y TRM del dividendo
            $monedaDiv = strtoupper((string) ($request->input('moneda') ?? $detalle?->moneda_dividendo ?? $inversion->moneda ?? 'COP'));
            $trmDiv = $request->input('tasa_cambio_usada') ?? $request->input('trm') ?? $detalle?->trm_dividendo;

            if ($monedaDiv === 'USD') {
                $trmDiv = TrmService::tasaPara('USD', $trmDiv, $fecha);
            } else {
                $trmDiv = '1.0000';
            }

            $liquidacionDiv = CalculoImpuestoInversionService::calcularDividendo(
                unidades: $unidadesBase,
                valorDividendoPorUnidad: $valDivUnit,
                tipoReparto: $tipoReparto,
                impuestoOrigen: $impuestoOrigen,
                fecha: $fecha,
                mercado: $detalle?->mercado ?? 'BVC',
                subtipo: $detalle?->tipo ?? 'ACCION',
                moneda: $monedaDiv,
                trm: $trmDiv
            );

            if ($inversion->moneda === 'USD') {
                $montoBruto = $liquidacionDiv['bruto_original'];
                $retencionAplicada = $liquidacionDiv['retencion_aplicada_original'];
                $impuestoOrigen = $liquidacionDiv['impuesto_origen_original'];
                $monto = $liquidacionDiv['neto_original'];
                $tasa = $trmDiv;
            } else {
                $montoBruto = $liquidacionDiv['bruto_cop'];
                $retencionAplicada = $liquidacionDiv['retencion_aplicada'];
                $impuestoOrigen = $liquidacionDiv['impuesto_origen'];
                $monto = $liquidacionDiv['neto_cop'];
                $tasa = '1.0000';
            }
        }

        // 4. ABONO_INTERES en CUENTA_ALTO_RENDIMIENTO o CDT
        if ($tipo === 'ABONO_INTERES') {
            if ($inversion->tipo === 'CUENTA_ALTO_RENDIMIENTO') {
                $detalle = $inversion->detalleCuenta;
                $tasaEa = (string) ($detalle?->tasa_ea ?? $inversion->tasa_ea ?? '0');
                $dias = (int) ($validated['dias'] ?? 30);

                // Si no se suministra monto bruto directo, se calcula
                if (bccomp($monto, '0', 2) === 0) {
                    $valoracionActual = InversionService::valorActual($inversion);
                    $saldoBase = $valoracionActual ? (string) $valoracionActual->valor : (string) ($detalle?->saldo_inicial ?? $inversion->monto_inicial);
                    $liqAbono = CalculoImpuestoInversionService::calcularAbonoCuenta($saldoBase, $tasaEa, $dias, $detalle?->base_dias ?? 365, $fecha);
                    $montoBruto = $liqAbono['bruto'];
                    $retencionAplicada = $liqAbono['retencion_total'];
                    $monto = $liqAbono['neto'];
                } else {
                    $baseDiariaUvt = ParametroTributarioService::obtenerValor('RETEFUENTE_CUENTA_AHORRO_BASE_DIARIA_UVT', $fecha);
                    $uvt = ParametroTributarioService::obtenerUvt($fecha);
                    $topeMinimo = IngresoService::redondear(bcmul(bcmul((string) $dias, $baseDiariaUvt, 6), $uvt, 4));

                    if (bccomp($monto, $topeMinimo, 2) < 0) {
                        $retencionAplicada = '0.00';
                    } else {
                        $tarifaRet = ParametroTributarioService::obtenerValor('RETEFUENTE_REND_FINANCIEROS', $fecha);
                        $retencionAplicada = IngresoService::redondear(bcmul($monto, bcdiv($tarifaRet, '100', 6), 4));
                    }
                    $montoBruto = $monto;
                    $monto = bcsub($montoBruto, $retencionAplicada, 2);
                }
            } elseif ($inversion->tipo === 'CDT') {
                $tarifaRet = ParametroTributarioService::obtenerValor('RETEFUENTE_RENTA_FIJA', $fecha);
                $montoBruto = $monto;
                $retencionAplicada = IngresoService::redondear(bcmul($montoBruto, bcdiv($tarifaRet, '100', 6), 4));
                $monto = bcsub($montoBruto, $retencionAplicada, 2);
            }
        }

        // 5. RETIRO en CUENTA_ALTO_RENDIMIENTO (GMF y tope 350 UVT)
        if ($inversion->tipo === 'CUENTA_ALTO_RENDIMIENTO' && in_array($tipo, ['RETIRO', 'RETIRO_PARCIAL'], true)) {
            $detalle = $inversion->detalleCuenta;
            $esExenta = (bool) ($detalle?->es_cuenta_gmf_exenta ?? false);

            // Validar saldo disponible
            $valoracion = InversionService::valorActual($inversion);
            $saldoDisponible = $valoracion ? (string) $valoracion->valor : (string) ($detalle?->saldo_inicial ?? $inversion->monto_inicial);
            if (bccomp($monto, $saldoDisponible, 2) === 1) {
                throw ValidationException::withMessages([
                    'monto' => "No puede retirar más del saldo disponible. Saldo actual: $" . number_format((float) $saldoDisponible, 2),
                ]);
            }

            // Calcular retiros acumulados del mes calendario actual para esta inversión
            $fechaCarbon = CarbonImmutable::parse($fecha);
            $retirosMesPrevios = MovimientoInversion::where('inversion_id', $inversion->id)
                ->whereIn('tipo', ['RETIRO', 'RETIRO_PARCIAL'])
                ->whereYear('fecha', $fechaCarbon->year)
                ->whereMonth('fecha', $fechaCarbon->month)
                ->sum('monto_bruto');

            $liqRetiro = CalculoImpuestoInversionService::calcularRetiroCuenta(
                montoRetiro: $monto,
                esCuentaGmfExenta: $esExenta,
                retirosMesAcumuladosPrevios: (string) $retirosMesPrevios,
                fecha: $fecha
            );

            $gmfAplicado = $liqRetiro['gmf_aplicado'];
            $montoBruto = $monto;
            $monto = $liqRetiro['neto_desembolsado'];
        }

        // 6. REDENCION en FIC
        if ($inversion->tipo === 'FIC' && in_array($tipo, ['REDENCION', 'RETIRO', 'RETIRO_PARCIAL'], true)) {
            $detalle = $inversion->detalleFic;
            if (! $detalle) {
                throw new \RuntimeException('No se encontró el detalle de FIC.');
            }

            $valoracion = InversionService::valorActual($inversion);
            $valorActualFondo = $valoracion ? (string) $valoracion->valor : '0.00';
            $totalAportesNetos = RentabilidadService::capitalAportadoNeto($inversion, CarbonImmutable::parse($fecha));

            // Suma de utilidad ya redimida en movimientos anteriores
            $utilidadYaRedimida = DB::table('ingreso_inversion')
                ->where('inversion_id', $inversion->id)
                ->where('tipo', 'RENDIMIENTO')
                ->sum('valor_bruto');

            $tarifaPonderada = $detalle->catalogo ? $detalle->catalogo->tarifaPonderada() : '6.10';

            $liqRedencion = CalculoImpuestoInversionService::calcularRedencionFic(
                montoRedencion: $monto,
                valorActualFondo: $valorActualFondo,
                totalAportesNetos: $totalAportesNetos,
                utilidadYaRedimida: (string) $utilidadYaRedimida,
                tarifaPonderada: $tarifaPonderada,
                comisionSalida: $comision
            );

            $montoBruto = $monto;
            $retencionAplicada = $liqRedencion['retencion_aplicada'];
            $monto = $liqRedencion['neto'];
            $datosPuente = [
                'parte_utilidad' => $liqRedencion['parte_utilidad'],
            ];

            // Descontar unidades del FIC
            $valUnidadAct = $detalle->valor_unidad_actual ?? '1.00000000';
            if (bccomp((string) $valUnidadAct, '0', 8) > 0) {
                $unidadesRedimidas = bcdiv($montoBruto, (string) $valUnidadAct, 8);
                $unidades = $unidadesRedimidas;
            }
        }

        // 7. APORTE / RETIRO en FPV
        if ($inversion->tipo === 'FPV') {
            if (in_array($tipo, ['APORTE', 'COMPRA'], true)) {
                $ingresoAnual = (string) ($perfil?->ingreso_anual_estimado ?? '0');
                $aportesPrevios = MovimientoInversion::where('usuario_id', Auth::id())
                    ->where('tipo', 'APORTE')
                    ->whereYear('fecha', CarbonImmutable::parse($fecha)->year)
                    ->whereHas('inversion', fn ($q) => $q->where('tipo', 'FPV'))
                    ->sum('monto_cop');

                $datosPuente = CalculoImpuestoInversionService::validarTopeAporteFpv(
                    montoAporte: $monto,
                    ingresoAnual: $ingresoAnual,
                    aportesAfcAnio: '0.00',
                    aportesFpvAnio: (string) $aportesPrevios,
                    fecha: $fecha
                );
                $montoBruto = $monto;
            } elseif (in_array($tipo, ['RETIRO', 'RETIRO_PARCIAL'], true)) {
                $cumplePermanencia = $request->boolean('cumple_permanencia', false);

                // Calcular aportes con y sin beneficio disponibles en este FPV
                $totalAportesFpv = MovimientoInversion::where('inversion_id', $inversion->id)
                    ->whereIn('tipo', ['APORTE', 'COMPRA'])
                    ->sum('monto_cop');

                $beneficioRegistrado = ValorDeducibleRegistro::where('inversion_id', $inversion->id)
                    ->where('concepto', 'APORTE_FPV')
                    ->sum('valor');

                $reversionesPrevias = abs((float) ValorDeducibleRegistro::where('inversion_id', $inversion->id)
                    ->where('concepto', 'AJUSTE_FPV_RETIRO')
                    ->sum('valor'));

                $retirosPrevios = MovimientoInversion::where('inversion_id', $inversion->id)
                    ->whereIn('tipo', ['RETIRO', 'RETIRO_PARCIAL'])
                    ->sum('monto_bruto');

                $aportesSinBeneficioOriginales = bcsub((string) $totalAportesFpv, (string) $beneficioRegistrado, 2);
                if (bccomp($aportesSinBeneficioOriginales, '0', 2) < 0) {
                    $aportesSinBeneficioOriginales = '0.00';
                }

                $sinBeneficioConsumido = bcsub((string) $retirosPrevios, (string) $reversionesPrevias, 2);
                if (bccomp($sinBeneficioConsumido, '0', 2) < 0) {
                    $sinBeneficioConsumido = '0.00';
                }

                $aportesSinBeneficioDisponibles = bcsub((string) $aportesSinBeneficioOriginales, (string) $sinBeneficioConsumido, 2);
                if (bccomp($aportesSinBeneficioDisponibles, '0', 2) < 0) {
                    $aportesSinBeneficioDisponibles = '0.00';
                }

                // Si no se digitó manualmente la proporción de rendimientos, se calcula a partir de la valorización actual vs costo base
                $proporcionRendimiento = (string) ($validated['proporcion_rendimiento'] ?? '0.00');
                if (bccomp($proporcionRendimiento, '0', 4) === 0) {
                    $valoracionActual = InversionService::valorActual($inversion);
                    $valorMercado = $valoracionActual ? (string) $valoracionActual->valor : (string) ($inversion->detalleFpv?->saldo_actual ?? $inversion->monto_inicial);
                    $costoBase = (string) ($inversion->detalleFpv?->saldo_actual ?? $inversion->monto_inicial);
                    if (bccomp($valorMercado, $costoBase, 2) > 0 && bccomp($valorMercado, '0', 2) > 0) {
                        $gananciaTotal = bcsub($valorMercado, $costoBase, 2);
                        $proporcionRendimiento = bcdiv($gananciaTotal, $valorMercado, 8);
                    }
                }

                $liqRetiroFpv = CalculoImpuestoInversionService::calcularRetiroFpv(
                    montoRetiro: $monto,
                    cumplePermanenciaODestino: $cumplePermanencia,
                    proporcionRendimiento: $proporcionRendimiento,
                    fecha: $fecha,
                    aportesSinBeneficioDisponibles: $aportesSinBeneficioDisponibles
                );

                $montoBruto = $monto;
                $retencionAplicada = $liqRetiroFpv['retencion_aplicada'];
                $monto = $liqRetiroFpv['neto'];
                $datosPuente = [
                    'cumple_permanencia' => $cumplePermanencia,
                    'aportes_retirados' => $liqRetiroFpv['aportes_retirados'],
                ];
            }
        }

        // 8. RETIRO en CESANTIAS
        if ($inversion->tipo === 'CESANTIAS' && in_array($tipo, ['RETIRO', 'RETIRO_PARCIAL'], true)) {
            $ingresoPromedio6m = (string) ($perfil?->ingreso_laboral_promedio_6m ?? $inversion->detalleCesantias?->salario_promedio_6m_override ?? '0');
            $liqCesantias = CalculoImpuestoInversionService::calcularRetiroCesantias(
                montoRetirado: $monto,
                ingresoLaboralPromedio6m: $ingresoPromedio6m,
                fecha: $fecha
            );

            $montoBruto = $monto;
            $retencionAplicada = '0.00';
            $datosPuente = [
                'parte_exenta' => $liqCesantias['parte_exenta'],
            ];
        }

        $montoCop = IngresoService::convertirACop($monto, $tasa);

        [$movimiento] = DB::transaction(function () use (
            $inversion, $tipo, $fecha, $monto, $tasa, $montoCop, $montoBruto,
            $valorUnidad, $unidades, $comision, $retencionAplicada, $gmfAplicado,
            $impuestoOrigen, $validated, $datosPuente
        ) {
            $movimiento = MovimientoInversion::create([
                'usuario_id' => Auth::id(),
                'inversion_id' => $inversion->id,
                'tipo' => $tipo,
                'fecha' => $fecha,
                'monto' => $monto,
                'tasa_cambio_usada' => $tasa,
                'monto_cop' => $montoCop,
                'monto_bruto' => $montoBruto,
                'valor_unidad' => $valorUnidad,
                'trm' => $tasa,
                'comision' => $comision,
                'retencion_aplicada' => $retencionAplicada,
                'gmf_aplicado' => $gmfAplicado,
                'impuesto_origen' => $impuestoOrigen,
                'unidades' => $unidades,
                'nota' => $validated['nota'] ?? null,
                'es_inicial' => false,
            ]);

            // Actualizar inventario / detalle según tipo
            if ($inversion->tipo === 'ACCION_ETF' && $inversion->detalleRentaVariable) {
                $det = $inversion->detalleRentaVariable;
                if (in_array($tipo, ['COMPRA', 'APORTE'], true) && $unidades !== null && $valorUnidad !== null) {
                    $recalculo = CalculoImpuestoInversionService::recalcularCostoPromedio(
                        unidadesPrevias: (string) $det->unidades_total,
                        costoPromedioPrevio: (string) $det->costo_promedio_unitario,
                        unidadesNuevas: $unidades,
                        precioNuevo: $valorUnidad,
                        comision: $comision,
                        trm: $tasa
                    );
                    $det->update([
                        'unidades_total' => $recalculo['unidades_total'],
                        'costo_promedio_unitario' => $recalculo['costo_promedio_unitario'],
                        'valor_actual_unidad' => $valorUnidad,
                    ]);
                } elseif ($tipo === 'VENTA' && $unidades !== null) {
                    $nuevasUnidades = bcsub((string) $det->unidades_total, $unidades, 8);
                    $det->update([
                        'unidades_total' => $nuevasUnidades,
                        'valor_actual_unidad' => $valorUnidad ?? $det->valor_actual_unidad,
                    ]);
                    if (bccomp($nuevasUnidades, '0', 8) <= 0) {
                        $inversion->update(['estado' => 'CERRADA']);
                    }
                }
            } elseif ($inversion->tipo === 'FIC' && $inversion->detalleFic) {
                $det = $inversion->detalleFic;
                if (in_array($tipo, ['APORTE', 'COMPRA'], true) && $unidades !== null) {
                    $det->update([
                        'unidades' => bcadd((string) $det->unidades, $unidades, 8),
                        'valor_unidad_actual' => $valorUnidad ?? $det->valor_unidad_actual,
                    ]);
                } elseif (in_array($tipo, ['REDENCION', 'RETIRO', 'RETIRO_PARCIAL'], true) && $unidades !== null) {
                    $nuevasUnidades = bcsub((string) $det->unidades, $unidades, 8);
                    $det->update([
                        'unidades' => $nuevasUnidades,
                    ]);
                    if (bccomp($nuevasUnidades, '0', 8) <= 0) {
                        $inversion->update(['estado' => 'CERRADA']);
                    }
                }
            } elseif ($inversion->tipo === 'CDT' && $tipo === 'REDENCION') {
                $inversion->update(['estado' => 'LIQUIDADA']);
            } elseif ($inversion->tipo === 'CUENTA_ALTO_RENDIMIENTO' && $inversion->detalleCuenta) {
                $det = $inversion->detalleCuenta;
                if (in_array($tipo, ['APORTE', 'ABONO_INTERES'], true)) {
                    $det->update(['saldo_inicial' => bcadd((string) $det->saldo_inicial, $monto, 2)]);
                } elseif (in_array($tipo, ['RETIRO', 'RETIRO_PARCIAL'], true)) {
                    $det->update(['saldo_inicial' => bcsub((string) $det->saldo_inicial, $montoBruto, 2)]);
                }
            } elseif ($inversion->tipo === 'CESANTIAS' && $inversion->detalleCesantias) {
                $det = $inversion->detalleCesantias;
                if ($tipo === 'APORTE') {
                    $det->update(['saldo' => bcadd((string) $det->saldo, $monto, 2)]);
                } elseif (in_array($tipo, ['RETIRO', 'RETIRO_PARCIAL'], true)) {
                    $det->update(['saldo' => bcsub((string) $det->saldo, $montoBruto, 2)]);
                }
            } elseif ($inversion->tipo === 'FPV' && $inversion->detalleFpv) {
                $det = $inversion->detalleFpv;
                $ultimaVal = ValoracionInversion::where('inversion_id', $inversion->id)->orderByDesc('fecha')->first();
                $valorUnidad = $ultimaVal?->precio_unitario ?? $det->valor_unidad ?? $movimiento->valor_unidad;

                if (in_array($tipo, ['APORTE', 'COMPRA'], true)) {
                    if ($unidades === null && $valorUnidad !== null && bccomp((string) $valorUnidad, '0', 8) > 0) {
                        $unidades = bcdiv($monto, (string) $valorUnidad, 8);
                        $movimiento->update([
                            'unidades' => $unidades,
                            'valor_unidad' => $valorUnidad,
                        ]);
                    }
                    $nuevasUnidades = $unidades !== null ? bcadd((string) ($det->unidades ?? '0'), $unidades, 8) : null;
                    $det->update([
                        'saldo_actual' => bcadd((string) $det->saldo_actual, $monto, 2),
                        'unidades' => $nuevasUnidades,
                    ]);
                } elseif (in_array($tipo, ['RETIRO', 'RETIRO_PARCIAL'], true)) {
                    if ($unidades === null && $valorUnidad !== null && bccomp((string) $valorUnidad, '0', 8) > 0) {
                        $unidades = bcdiv($montoBruto, (string) $valorUnidad, 8);
                        $movimiento->update([
                            'unidades' => $unidades,
                            'valor_unidad' => $valorUnidad,
                        ]);
                    }
                    $nuevasUnidades = $unidades !== null ? bcsub((string) ($det->unidades ?? '0'), $unidades, 8) : null;
                    $det->update([
                        'saldo_actual' => bcsub((string) $det->saldo_actual, $montoBruto, 2),
                        'unidades' => $nuevasUnidades,
                    ]);
                    if ($nuevasUnidades !== null && bccomp($nuevasUnidades, '0', 8) <= 0) {
                        $inversion->update(['estado' => 'CERRADA']);
                    }
                }
            }

            // Sincronizar valoración
            InversionService::sincronizarValoracionTrasMovimiento($inversion, $movimiento);

            // Registrar en tablas puente hacia Ingresos
            InversionService::registrarPuentesTributarios($movimiento, $inversion, $datosPuente);

            BitacoraService::registrar(
                accion: 'REGISTRAR_MOVIMIENTO',
                entidadAfectada: 'movimientos_inversion',
                entidadId: $movimiento->id,
                valorNuevo: $movimiento->fresh()->toArray(),
                justificacion: "Transacción {$tipo} con retención $" . number_format((float) $retencionAplicada, 2) . " y GMF $" . number_format((float) $gmfAplicado, 2),
                usuarioId: Auth::id()
            );

            return [$movimiento];
        });

        if ($request->expectsJson()) {
            return response()->json([
                'mensaje' => 'Transacción registrada con éxito.',
                'data' => [
                    'movimiento' => $movimiento,
                    'retencion_aplicada' => $retencionAplicada,
                    'gmf_aplicado' => $gmfAplicado,
                    'monto_desembolsado' => $monto,
                ],
            ], 201);
        }

        return redirect()->route('inversiones.show', $inversion->id)
            ->with('status', "Transacción de {$tipo} registrada con éxito.");
    }

    public function destroy(Request $request, string $id, string $movimientoId): JsonResponse|RedirectResponse
    {
        $inversion = $this->inversionPropia($id);

        $movimiento = MovimientoInversion::where('inversion_id', $inversion->id)
            ->whereKey($movimientoId)
            ->firstOrFail();

        if ($movimiento->es_inicial) {
            throw ValidationException::withMessages([
                'movimiento' => 'El aporte inicial no se puede eliminar. Para eliminar la inversión, hágalo desde la pantalla principal.',
            ]);
        }

        DB::transaction(function () use ($movimiento, $inversion) {
            $anterior = $movimiento->toArray();

            // Revertir inventario si era VENTA o REDENCION
            if ($inversion->tipo === 'ACCION_ETF' && $inversion->detalleRentaVariable) {
                $det = $inversion->detalleRentaVariable;
                if ($movimiento->tipo === 'VENTA' && $movimiento->unidades) {
                    $det->increment('unidades_total', (float) $movimiento->unidades);
                    if ($inversion->estado === 'CERRADA') {
                        $inversion->update(['estado' => 'ACTIVA']);
                    }
                } elseif (in_array($movimiento->tipo, ['COMPRA', 'APORTE'], true) && $movimiento->unidades) {
                    $nuevas = bcsub((string) $det->unidades_total, (string) $movimiento->unidades, 8);
                    $det->update(['unidades_total' => bccomp($nuevas, '0', 8) > 0 ? $nuevas : '0.00000000']);
                }
            } elseif ($inversion->tipo === 'FIC' && $inversion->detalleFic) {
                $det = $inversion->detalleFic;
                if (in_array($movimiento->tipo, ['REDENCION', 'RETIRO', 'RETIRO_PARCIAL'], true) && $movimiento->unidades) {
                    $det->increment('unidades', (float) $movimiento->unidades);
                    if ($inversion->estado === 'CERRADA') {
                        $inversion->update(['estado' => 'ACTIVA']);
                    }
                }
            } elseif ($inversion->tipo === 'CDT' && $movimiento->tipo === 'REDENCION') {
                if (in_array($inversion->estado, ['LIQUIDADA', 'CERRADA'], true)) {
                    $inversion->update(['estado' => 'ACTIVA']);
                }
            }

            // Eliminar registros puente asociados
            $movimiento->valoresDeducibles()->delete();
            $movimiento->ingresosInversion()->delete();

            if ($movimiento->ingreso_id) {
                \App\Models\Ingreso::where('id', $movimiento->ingreso_id)->delete();
            }

            $movimiento->delete();

            BitacoraService::registrar(
                accion: 'ELIMINAR_MOVIMIENTO',
                entidadAfectada: 'movimientos_inversion',
                entidadId: $movimiento->id,
                valorAnterior: $anterior,
                justificacion: 'Eliminación lógica de transacción.',
                usuarioId: Auth::id()
            );
        });

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => 'Movimiento eliminado.']);
        }

        return redirect()->route('inversiones.show', $inversion->id)
            ->with('status', 'Transacción eliminada con éxito.');
    }

    private function inversionPropia(string $id): Inversion
    {
        $inversion = Inversion::find($id);

        if ($inversion !== null) {
            return $inversion;
        }

        abort(404);
    }

    private function reglas(Inversion $inversion): array
    {
        return [
            'tipo' => ['required', Rule::in(Inversion::MOVIMIENTOS_PERMITIDOS[$inversion->tipo] ?? MovimientoInversion::TIPOS)],
            'fecha' => ['required', 'date', 'after_or_equal:' . $inversion->fecha_apertura->toDateString(), 'before_or_equal:today'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'unidades' => ['nullable', 'numeric', 'gt:0'],
            'valor_unidad' => ['nullable', 'numeric', 'gt:0'],
            'comision' => ['nullable', 'numeric', 'gte:0'],
            'impuesto_origen' => ['nullable', 'numeric', 'gte:0'],
            'moneda' => ['nullable', Rule::in(['COP', 'USD'])],
            'trm' => ['nullable', 'numeric', 'gt:0'],
            'tasa_cambio_usada' => ['nullable', 'numeric', 'gt:0'],
            'nota' => ['nullable', 'string', 'max:255'],
            'cumple_permanencia' => ['nullable', 'boolean'],
            'proporcion_rendimiento' => ['nullable', 'numeric', 'between:0,1'],
            'dias' => ['nullable', 'integer', 'between:1,365'],
        ];
    }

    private function mensajes(): array
    {
        return [
            'tipo.required' => 'Indique el tipo de transacción.',
            'tipo.in' => 'El tipo de transacción no está permitido para este producto.',
            'fecha.required' => 'Indique la fecha de la transacción.',
            'fecha.after_or_equal' => 'La fecha no puede ser anterior a la apertura de la inversión.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
            'monto.required' => 'Indique el monto de la transacción.',
            'monto.gt' => 'El monto debe ser mayor que cero.',
        ];
    }
}
