<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\FuenteIngreso;
use App\Models\Ingreso;
use App\Models\IngresoInversion;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\ValoracionInversion;
use App\Models\ValorDeducibleRegistro;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * MOD-06 — Reglas de las inversiones (RF-037) y de sus transacciones (RF-038).
 * Soporta el ciclo de vida de los 6 productos tributarios y puente con el módulo de Ingresos.
 */
class InversionService
{
    public const FUENTE_RENDIMIENTOS = 'Rendimientos de inversiones';
    public const TIPO_FUENTE_RENDIMIENTOS = 'Rendimientos financieros';
    public const CLASIFICACION_RENDIMIENTOS = 'RENTAS_CAPITAL';

    public const DIAS_VALORACION_VIGENTE = 35;
    private const ESCALA_UNIDADES = 8;

    public static function fechaVencimiento(CarbonImmutable $apertura, int $plazoDias): CarbonImmutable
    {
        return $apertura->addDays($plazoDias);
    }

    public static function factorCapitalizacion(string $tasaEaPorc, int $dias, int $baseDias = 365): string
    {
        $factor = (1 + ((float) $tasaEaPorc) / 100) ** ($dias / $baseDias);
        return sprintf('%.15F', $factor);
    }

    public static function rendimientoEstimado(string $capital, string $tasaEaPorc, int $dias, int $baseDias = 365): string
    {
        $rendimiento = bcmul($capital, bcsub(self::factorCapitalizacion($tasaEaPorc, $dias, $baseDias), '1', 15), 6);
        return IngresoService::redondear($rendimiento);
    }

    public static function estadoSegunVencimiento(?CarbonImmutable $vencimiento, CarbonImmutable $hoy): string
    {
        return $vencimiento !== null && $vencimiento->lt($hoy) ? 'VENCIDA' : 'ACTIVA';
    }

    public static function valorActual(Inversion $inversion): ?ValoracionInversion
    {
        if ($inversion->estaLiquidada()) {
            return null;
        }

        return ValoracionInversion::where('inversion_id', $inversion->id)
            ->orderByDesc('fecha')
            ->orderByDesc('creado_en')
            ->first();
    }

    public static function valoracionDesactualizada(Inversion $inversion, ?ValoracionInversion $valoracion, CarbonImmutable $hoy): bool
    {
        if ($inversion->estado !== 'ACTIVA') {
            return false;
        }

        return $valoracion === null
            || CarbonImmutable::parse($valoracion->fecha)->addDays(self::DIAS_VALORACION_VIGENTE)->lt($hoy);
    }

    public static function valorCausado(Inversion $inversion, CarbonImmutable $fecha): array
    {
        $dias = (int) CarbonImmutable::parse($inversion->fecha_apertura)->diffInDays($fecha);
        $capital = RentabilidadService::capitalAportadoNeto($inversion, $fecha);
        $tasaEa = (string) ($inversion->detalleCdt?->tasa_valor ?? $inversion->tasa_ea ?? '0');
        $baseDias = (int) ($inversion->detalleCdt?->base_dias ?? 365);

        return [
            'fecha' => $fecha->toDateString(),
            'capital' => $capital,
            'tasa_ea' => $tasaEa,
            'dias_transcurridos' => $dias,
            'valor_causado' => IngresoService::redondear(bcmul($capital, self::factorCapitalizacion($tasaEa, $dias, $baseDias), 6)),
        ];
    }

    public static function marcarVencidas(CarbonImmutable $hoy): int
    {
        $vencidas = Inversion::withoutGlobalScopes()
            ->whereNull('eliminado_en')
            ->where('estado', 'ACTIVA')
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', $hoy->toDateString())
            ->get();

        foreach ($vencidas as $inversion) {
            $anterior = $inversion->toArray();
            $inversion->update(['estado' => 'VENCIDA']);

            BitacoraService::registrar(
                accion: 'VENCER_INVERSION',
                entidadAfectada: 'inversiones',
                entidadId: $inversion->id,
                valorAnterior: $anterior,
                valorNuevo: $inversion->fresh()->toArray(),
                justificacion: 'RN-037.5: el Sistema marca la inversión como Vencida al pasar su fecha de vencimiento.',
                usuarioId: $inversion->usuario_id
            );
        }

        return $vencidas->count();
    }

    public static function unidadesActuales(Inversion $inversion, ?CarbonImmutable $fecha = null): ?string
    {
        if (! $inversion->tieneUnidades()) {
            return null;
        }

        $movimientos = MovimientoInversion::where('inversion_id', $inversion->id)
            ->whereNotNull('unidades')
            ->when($fecha, fn ($query) => $query->where('fecha', '<=', $fecha->toDateString()))
            ->get(['tipo', 'unidades']);

        return $movimientos->reduce(function (string $total, MovimientoInversion $movimiento) {
            if (in_array($movimiento->tipo, ['APORTE', 'COMPRA'], true)) {
                return bcadd($total, (string) $movimiento->unidades, self::ESCALA_UNIDADES);
            }
            if (in_array($movimiento->tipo, ['VENTA', 'REDENCION', 'RETIRO', 'RETIRO_PARCIAL'], true)) {
                return bcsub($total, (string) $movimiento->unidades, self::ESCALA_UNIDADES);
            }
            return $total;
        }, bcadd('0', '0', self::ESCALA_UNIDADES));
    }

    /**
     * RF-VAL-02: Mostrar valorización no realizada = (valor actual - costo) * unidades
     */
    public static function valoracionNoRealizada(Inversion $inversion): ?array
    {
        if ($inversion->tipo === 'ACCION_ETF') {
            $detalle = $inversion->detalleRentaVariable;
            if (! $detalle || bccomp((string) $detalle->unidades_total, '0', 8) <= 0) {
                return null;
            }
            $precioActual = $detalle->valor_actual_unidad ?? $detalle->costo_promedio_unitario;
            $diferenciaUnit = bcsub((string) $precioActual, (string) $detalle->costo_promedio_unitario, 8);
            $valor = IngresoService::redondear(bcmul($diferenciaUnit, (string) $detalle->unidades_total, 4));
            $porcentaje = bccomp((string) $detalle->costo_promedio_unitario, '0', 8) > 0
                ? bcdiv(bcmul($diferenciaUnit, '100', 4), (string) $detalle->costo_promedio_unitario, 2)
                : '0.00';

            return [
                'valor' => $valor,
                'porcentaje' => $porcentaje,
                'unidades' => (string) $detalle->unidades_total,
                'costo_unitario' => (string) $detalle->costo_promedio_unitario,
                'precio_actual' => (string) $precioActual,
            ];
        }

        return null;
    }

    /**
     * RF-VAL-05: Proyección automática de rendimiento para cuenta de alto rendimiento.
     */
    public static function proyeccionCuenta(Inversion $inversion, int $dias = 30): ?array
    {
        if ($inversion->tipo !== 'CUENTA_ALTO_RENDIMIENTO') {
            return null;
        }

        $detalle = $inversion->detalleCuenta;
        $tasaEa = (string) ($detalle?->tasa_ea ?? $inversion->tasa_ea ?? '0');
        $valoracion = self::valorActual($inversion);
        $saldo = $valoracion ? (string) $valoracion->valor : (string) ($detalle?->saldo_inicial ?? $inversion->monto_inicial);

        return CalculoImpuestoInversionService::calcularAbonoCuenta($saldo, $tasaEa, $dias, $detalle?->base_dias ?? 365);
    }

    /**
     * Sincronizar automáticamente la valoración tras una transacción.
     */
    public static function sincronizarValoracionTrasMovimiento(Inversion $inversion, MovimientoInversion $movimiento): ?ValoracionInversion
    {
        $tiposSincronizables = ['APORTE', 'COMPRA', 'RETIRO', 'RETIRO_PARCIAL', 'VENTA', 'REDENCION', 'ABONO_INTERES', 'DIVIDENDO'];
        if (! in_array($movimiento->tipo, $tiposSincronizables, true) || $movimiento->es_inicial) {
            return null;
        }

        $fecha = CarbonImmutable::parse($movimiento->fecha);

        if ($inversion->estaLiquidada()) {
            ValoracionInversion::where('inversion_id', $inversion->id)
                ->where('fecha', '>=', $fecha->toDateString())
                ->update(['valor' => '0.00']);

            return ValoracionInversion::updateOrCreate(
                ['inversion_id' => $inversion->id, 'fecha' => $fecha->toDateString()],
                [
                    'usuario_id' => $inversion->usuario_id,
                    'valor' => '0.00',
                    'precio_unitario' => null,
                    'unidades' => null,
                    'origen' => 'MANUAL',
                ]
            );
        }

        if ($inversion->tipo === 'ACCION_ETF') {
            $unidadesActuales = self::unidadesActuales($inversion, $fecha);

            $precioUnitario = null;
            if ($movimiento->tipo !== 'DIVIDENDO' && $movimiento->valor_unidad !== null && bccomp((string) $movimiento->valor_unidad, '0', 8) === 1) {
                $precioUnitario = (string) $movimiento->valor_unidad;
            } elseif ($movimiento->tipo !== 'DIVIDENDO' && $movimiento->unidades !== null && bccomp((string) $movimiento->unidades, '0', 8) === 1) {
                $precioUnitario = bcdiv((string) $movimiento->monto, (string) $movimiento->unidades, 8);
            } else {
                $ultimaVal = ValoracionInversion::where('inversion_id', $inversion->id)
                    ->where('fecha', '<=', $fecha->toDateString())
                    ->whereNotNull('precio_unitario')
                    ->orderByDesc('fecha')
                    ->first();
                $precioUnitario = $ultimaVal?->precio_unitario ?? $inversion->precio_unitario_compra ?? $inversion->detalleRentaVariable?->valor_actual_unidad ?? $inversion->detalleRentaVariable?->costo_promedio_unitario ?? '1.00000000';
            }

            $nuevoValor = IngresoService::redondear(bcmul($unidadesActuales, (string) $precioUnitario, 10));

            return ValoracionInversion::updateOrCreate(
                ['inversion_id' => $inversion->id, 'fecha' => $fecha->toDateString()],
                [
                    'usuario_id' => $inversion->usuario_id,
                    'valor' => $nuevoValor,
                    'precio_unitario' => (string) $precioUnitario,
                    'unidades' => $unidadesActuales,
                    'origen' => 'MANUAL',
                ]
            );
        }

        // Inversiones sin unidades (cuentas de ahorro, FPV, cesantías, CDT)
        $ultimaVal = ValoracionInversion::where('inversion_id', $inversion->id)
            ->where('fecha', '<=', $fecha->toDateString())
            ->orderByDesc('fecha')
            ->first();

        $valorBase = $ultimaVal !== null ? (string) $ultimaVal->valor : (string) $inversion->monto_inicial;
        $montoEfecto = $movimiento->monto_bruto ?? $movimiento->monto;

        if (in_array($movimiento->tipo, ['APORTE', 'COMPRA', 'ABONO_INTERES', 'DIVIDENDO'], true)) {
            $nuevoValor = bcadd($valorBase, (string) $movimiento->monto, 2);
        } else {
            $nuevoValor = bcsub($valorBase, (string) $montoEfecto, 2);
        }

        if (bccomp($nuevoValor, '0', 2) < 0) {
            $nuevoValor = '0.00';
        }

        $valActualizada = ValoracionInversion::updateOrCreate(
            ['inversion_id' => $inversion->id, 'fecha' => $fecha->toDateString()],
            [
                'usuario_id' => $inversion->usuario_id,
                'valor' => $nuevoValor,
                'precio_unitario' => null,
                'unidades' => null,
                'origen' => 'MANUAL',
            ]
        );

        // Si existen valoraciones posteriores a la fecha del movimiento, sincronizar su saldo restante
        $valPosteriores = ValoracionInversion::where('inversion_id', $inversion->id)
            ->where('fecha', '>', $fecha->toDateString())
            ->get();

        foreach ($valPosteriores as $valPost) {
            if ($inversion->estaLiquidada()) {
                $valPost->update(['valor' => '0.00']);
            } else {
                $nuevoPost = in_array($movimiento->tipo, ['APORTE', 'COMPRA', 'ABONO_INTERES', 'DIVIDENDO'], true)
                    ? bcadd((string) $valPost->valor, (string) $movimiento->monto, 2)
                    : bcsub((string) $valPost->valor, (string) $montoEfecto, 2);
                if (bccomp($nuevoPost, '0', 2) < 0) {
                    $nuevoPost = '0.00';
                }
                $valPost->update(['valor' => $nuevoPost]);
            }
        }

        return $valActualizada;
    }

    public static function reconciliarValoraciones(Inversion $inversion): void
    {
        if ($inversion->estaLiquidada()) {
            return;
        }

        if ($inversion->tipo === 'ACCION_ETF') {
            $unidadesActuales = self::unidadesActuales($inversion);
            $ultimaVal = ValoracionInversion::where('inversion_id', $inversion->id)
                ->orderByDesc('fecha')
                ->first();

            if ($ultimaVal !== null && $ultimaVal->precio_unitario !== null) {
                if (bccomp((string) $ultimaVal->unidades, (string) $unidadesActuales, 8) !== 0) {
                    $nuevoValor = IngresoService::redondear(bcmul((string) $unidadesActuales, (string) $ultimaVal->precio_unitario, 10));
                    $ultimaVal->update([
                        'unidades' => $unidadesActuales,
                        'valor' => $nuevoValor,
                    ]);
                }
            }
        }
    }

    /**
     * 9.3 / 9.5: Registro en tablas puente hacia Ingresos (ingreso_inversion y valor_deducible_registro).
     */
    public static function registrarPuentesTributarios(MovimientoInversion $movimiento, Inversion $inversion, array $datos = []): void
    {
        $anio = CarbonImmutable::parse($movimiento->fecha)->year;

        // 1. Aportes FPV -> valor_deducible_registro
        if ($inversion->tipo === 'FPV' && in_array($movimiento->tipo, ['APORTE', 'COMPRA'], true)) {
            $parteDentroTope = $datos['parte_dentro_tope'] ?? $movimiento->monto_cop;
            if (bccomp((string) $parteDentroTope, '0', 2) > 0) {
                ValorDeducibleRegistro::create([
                    'usuario_id' => $movimiento->usuario_id,
                    'inversion_id' => $inversion->id,
                    'transaccion_id' => $movimiento->id,
                    'anio' => $anio,
                    'concepto' => 'APORTE_FPV',
                    'valor' => (string) $parteDentroTope,
                ]);
            }
        }

        // 2. Retiro anticipado FPV -> reversión deducible (ajuste negativo)
        if ($inversion->tipo === 'FPV' && in_array($movimiento->tipo, ['RETIRO', 'RETIRO_PARCIAL'], true) && ! ($datos['cumple_permanencia'] ?? true)) {
            $aportesRetirados = $datos['aportes_retirados'] ?? $movimiento->monto_cop;
            if (bccomp((string) $aportesRetirados, '0', 2) > 0) {
                ValorDeducibleRegistro::create([
                    'usuario_id' => $movimiento->usuario_id,
                    'inversion_id' => $inversion->id,
                    'transaccion_id' => $movimiento->id,
                    'anio' => $anio,
                    'concepto' => 'AJUSTE_FPV_RETIRO',
                    'valor' => '-' . bcadd((string) $aportesRetirados, '0', 2),
                ]);
            }

            // Registrar rendimientos retirados en la tabla de ingresos de inversión
            $rendimientosRetirados = $datos['rendimientos_retirados'] ?? '0.00';
            if (bccomp((string) $rendimientosRetirados, '0', 2) > 0) {
                $retRendimientos = $datos['retencion_rendimientos'] ?? '0.00';
                IngresoInversion::create([
                    'usuario_id' => $movimiento->usuario_id,
                    'inversion_id' => $inversion->id,
                    'transaccion_id' => $movimiento->id,
                    'anio' => $anio,
                    'tipo' => 'RENDIMIENTO',
                    'valor_bruto' => (string) $rendimientosRetirados,
                    'retencion' => (string) $retRendimientos,
                ]);
            }
        }

        // 3. Cesantías exentas -> valor_deducible_registro
        if ($inversion->tipo === 'CESANTIAS' && in_array($movimiento->tipo, ['RETIRO', 'RETIRO_PARCIAL'], true)) {
            $parteExenta = $datos['parte_exenta'] ?? '0.00';
            if (bccomp((string) $parteExenta, '0', 2) > 0) {
                ValorDeducibleRegistro::create([
                    'usuario_id' => $movimiento->usuario_id,
                    'inversion_id' => $inversion->id,
                    'transaccion_id' => $movimiento->id,
                    'anio' => $anio,
                    'concepto' => 'CESANTIAS_EXENTA',
                    'valor' => (string) $parteExenta,
                ]);
            }
        }

        // 4. Venta de Acciones/ETF -> Registro oficial en módulo de Ingresos (según tenencia) e IngresoInversion
        if ($inversion->tipo === 'ACCION_ETF' && $movimiento->tipo === 'VENTA') {
            $utilidadCop = $datos['utilidad_cop'] ?? '0.00';
            $utilidadOriginal = $datos['utilidad_original'] ?? $utilidadCop;
            $claseFiscal = $datos['clase_fiscal'] ?? 'RENTA_ORDINARIA';
            $aniosTenencia = (int) ($datos['anios_tenencia'] ?? 0);

            if (bccomp((string) $utilidadCop, '0', 2) > 0) {
                // Determinar clasificación tributaria en módulo de Ingresos:
                // Si tenencia >= 2 años -> GANANCIA_OCASIONAL (Arts. 300 y 314 E.T.)
                // Si tenencia < 2 años -> RENTAS_CAPITAL (Art. 241 E.T., Cédula General)
                $clasificacion = ($claseFiscal === 'GANANCIA_OCASIONAL' || $aniosTenencia >= 2)
                    ? 'GANANCIA_OCASIONAL'
                    : 'RENTAS_CAPITAL';

                $nombreFuente = ($clasificacion === 'GANANCIA_OCASIONAL')
                    ? 'Ganancia ocasional por venta de inversiones'
                    : 'Utilidad por venta de acciones / ETF';

                $fuente = self::fuenteDeRendimientos(
                    usuarioId: $movimiento->usuario_id,
                    nombreFuente: $nombreFuente,
                    tipoFuente: 'Rendimientos Financieros',
                    clasificacion: $clasificacion
                );

                $tasa = (string) ($movimiento->tasa_cambio_usada ?: '1.0000');
                $montoOriginal = ($inversion->moneda === 'USD') ? (string) $utilidadOriginal : (string) $utilidadCop;

                $ingreso = Ingreso::create([
                    'usuario_id' => $movimiento->usuario_id,
                    'fuente_id' => $fuente->id,
                    'fecha_recepcion' => $movimiento->fecha,
                    'clasificacion_tributaria' => $clasificacion,
                    'moneda' => $inversion->moneda,
                    'monto_original' => $montoOriginal,
                    'tasa_cambio_usada' => $tasa,
                    'monto_bruto_cop' => (string) $utilidadCop,
                    'retencion_fuente_cop' => '0.00',
                    'aportes_descontados_cop' => '0.00',
                    'origen' => 'MANUAL',
                    'estado' => 'CONFIRMADO',
                    'descripcion' => "Utilidad en venta de «{$inversion->nombre}» ({$movimiento->unidades} títulos - " . ($clasificacion === 'GANANCIA_OCASIONAL' ? 'Ganancia Ocasional ≥ 2 años' : 'Rentas de Capital < 2 años') . ")",
                ]);

                $movimiento->update(['ingreso_id' => $ingreso->id]);

                IngresoInversion::create([
                    'usuario_id' => $movimiento->usuario_id,
                    'inversion_id' => $inversion->id,
                    'transaccion_id' => $movimiento->id,
                    'anio' => $anio,
                    'tipo' => 'UTILIDAD_VENTA',
                    'valor_bruto' => (string) $utilidadCop,
                    'retencion' => '0.00',
                ]);

                if ($claseFiscal === 'NO_GRAVADA') {
                    ValorDeducibleRegistro::create([
                        'usuario_id' => $movimiento->usuario_id,
                        'inversion_id' => $inversion->id,
                        'transaccion_id' => $movimiento->id,
                        'anio' => $anio,
                        'concepto' => 'UTILIDAD_NO_GRAVADA_BVC',
                        'valor' => (string) $utilidadCop,
                    ]);
                }
            }
        }

        // 5. Dividendos -> Registro oficial en módulo de Ingresos (valor neto) y deducción (retención 30%)
        if ($inversion->tipo === 'ACCION_ETF' && $movimiento->tipo === 'DIVIDENDO') {
            $bruto = $movimiento->monto_bruto ?? $movimiento->monto;
            $retencion = $movimiento->retencion_aplicada ?? '0.00';
            $neto = bcsub((string) $bruto, (string) $retencion, 2);
            if (bccomp((string) $neto, '0', 2) <= 0) {
                $neto = (string) $movimiento->monto;
            }

            // Crear ingreso oficial en el módulo de Ingresos (con el valor neto)
            $fuente = self::fuenteDeRendimientos(
                usuarioId: $movimiento->usuario_id,
                nombreFuente: 'Dividendos de inversiones',
                tipoFuente: 'Dividendos',
                clasificacion: 'DIVIDENDOS'
            );

            $tasa = (string) ($movimiento->tasa_cambio_usada ?: '1.0000');
            $netoCop = IngresoService::convertirACop((string) $neto, $tasa);

            $ingreso = Ingreso::create([
                'usuario_id' => $movimiento->usuario_id,
                'fuente_id' => $fuente->id,
                'fecha_recepcion' => $movimiento->fecha,
                'clasificacion_tributaria' => 'DIVIDENDOS',
                'moneda' => $inversion->moneda,
                'monto_original' => (string) $neto,
                'tasa_cambio_usada' => $tasa,
                'monto_bruto_cop' => $netoCop,
                'retencion_fuente_cop' => '0.00',
                'aportes_descontados_cop' => '0.00',
                'origen' => 'MANUAL',
                'estado' => 'CONFIRMADO',
                'descripcion' => "Dividendo neto recibido de «{$inversion->nombre}»",
            ]);

            $brutoCop = IngresoService::convertirACop((string) $bruto, $tasa);
            $retencionCop = IngresoService::convertirACop((string) $retencion, $tasa);

            $movimiento->update(['ingreso_id' => $ingreso->id]);

            IngresoInversion::create([
                'usuario_id' => $movimiento->usuario_id,
                'inversion_id' => $inversion->id,
                'transaccion_id' => $movimiento->id,
                'anio' => $anio,
                'tipo' => 'DIVIDENDO',
                'valor_bruto' => (string) $brutoCop,
                'retencion' => (string) $retencionCop,
            ]);

            if (bccomp((string) $retencionCop, '0', 2) > 0) {
                ValorDeducibleRegistro::create([
                    'usuario_id' => $movimiento->usuario_id,
                    'inversion_id' => $inversion->id,
                    'transaccion_id' => $movimiento->id,
                    'anio' => $anio,
                    'concepto' => 'RETENCION_DIVIDENDOS',
                    'valor' => (string) $retencionCop,
                ]);
            }
        }

        // 6. Abono de Interés / Rendimientos -> Registro oficial en módulo de Ingresos (valor neto)
        if (in_array($movimiento->tipo, ['ABONO_INTERES', 'RENDIMIENTO'], true)) {
            $bruto = $movimiento->monto_bruto ?? $movimiento->monto;
            $retencion = $movimiento->retencion_aplicada ?? '0.00';
            $neto = bcsub((string) $bruto, (string) $retencion, 2);
            if (bccomp((string) $neto, '0', 2) <= 0) {
                $neto = (string) $movimiento->monto;
            }

            if (bccomp((string) $neto, '0', 2) > 0) {
                $fuente = self::fuenteDeRendimientos(
                    usuarioId: $movimiento->usuario_id,
                    nombreFuente: self::FUENTE_RENDIMIENTOS,
                    tipoFuente: self::TIPO_FUENTE_RENDIMIENTOS,
                    clasificacion: self::CLASIFICACION_RENDIMIENTOS
                );

                $tasa = (string) ($movimiento->tasa_cambio_usada ?: '1.0000');
                $netoCop = IngresoService::convertirACop((string) $neto, $tasa);

                $ingreso = Ingreso::create([
                    'usuario_id' => $movimiento->usuario_id,
                    'fuente_id' => $fuente->id,
                    'fecha_recepcion' => $movimiento->fecha,
                    'clasificacion_tributaria' => self::CLASIFICACION_RENDIMIENTOS,
                    'moneda' => $inversion->moneda,
                    'monto_original' => (string) $neto,
                    'tasa_cambio_usada' => $tasa,
                    'monto_bruto_cop' => $netoCop,
                    'retencion_fuente_cop' => '0.00',
                    'aportes_descontados_cop' => '0.00',
                    'origen' => 'MANUAL',
                    'estado' => 'CONFIRMADO',
                    'descripcion' => "Rendimiento/Interés neto de «{$inversion->nombre}»",
                ]);

                $movimiento->update(['ingreso_id' => $ingreso->id]);
            }

            if (bccomp((string) $bruto, '0', 2) > 0) {
                $brutoCop = IngresoService::convertirACop((string) $bruto, $tasa);
                $retencionCop = IngresoService::convertirACop((string) $retencion, $tasa);

                IngresoInversion::create([
                    'usuario_id' => $movimiento->usuario_id,
                    'inversion_id' => $inversion->id,
                    'transaccion_id' => $movimiento->id,
                    'anio' => $anio,
                    'tipo' => 'RENDIMIENTO',
                    'valor_bruto' => (string) $brutoCop,
                    'retencion' => (string) $retencionCop,
                ]);

                if ($inversion->tipo === 'CDT' && bccomp((string) $retencionCop, '0', 2) > 0) {
                    ValorDeducibleRegistro::create([
                        'usuario_id' => $movimiento->usuario_id,
                        'inversion_id' => $inversion->id,
                        'transaccion_id' => $movimiento->id,
                        'anio' => $anio,
                        'concepto' => 'RETEFUENTE_RENTA_FIJA',
                        'valor' => (string) $retencionCop,
                    ]);
                }
            }
        }

        // 6b. Redención de CDT con rendimientos liquidados al vencimiento
        if ($inversion->tipo === 'CDT' && $movimiento->tipo === 'REDENCION' && !empty($datos['parte_utilidad'])) {
            $rendimientoBruto = $datos['parte_utilidad'];
            $retencion = $movimiento->retencion_aplicada ?? '0.00';
            $neto = bcsub((string) $rendimientoBruto, (string) $retencion, 2);

            $fuente = self::fuenteDeRendimientos(
                usuarioId: $movimiento->usuario_id,
                nombreFuente: 'Rendimientos de Renta Fija (CDT)',
                tipoFuente: self::TIPO_FUENTE_RENDIMIENTOS,
                clasificacion: self::CLASIFICACION_RENDIMIENTOS
            );

            $tasa = (string) ($movimiento->tasa_cambio_usada ?: '1.0000');
            $netoCop = IngresoService::convertirACop((string) $neto, $tasa);

            $ingreso = Ingreso::create([
                'usuario_id' => $movimiento->usuario_id,
                'fuente_id' => $fuente->id,
                'fecha_recepcion' => $movimiento->fecha,
                'clasificacion_tributaria' => self::CLASIFICACION_RENDIMIENTOS,
                'moneda' => $inversion->moneda,
                'monto_original' => (string) $neto,
                'tasa_cambio_usada' => $tasa,
                'monto_bruto_cop' => $netoCop,
                'retencion_fuente_cop' => '0.00',
                'aportes_descontados_cop' => '0.00',
                'origen' => 'MANUAL',
                'estado' => 'CONFIRMADO',
                'descripcion' => "Rendimiento neto por liquidación al vencimiento de CDT «{$inversion->nombre}»",
            ]);

            $movimiento->update(['ingreso_id' => $ingreso->id]);

            IngresoInversion::create([
                'usuario_id' => $movimiento->usuario_id,
                'inversion_id' => $inversion->id,
                'transaccion_id' => $movimiento->id,
                'anio' => $anio,
                'tipo' => 'RENDIMIENTO',
                'valor_bruto' => (string) $rendimientoBruto,
                'retencion' => (string) $retencion,
            ]);

            if (bccomp((string) $retencion, '0', 2) > 0) {
                ValorDeducibleRegistro::create([
                    'usuario_id' => $movimiento->usuario_id,
                    'inversion_id' => $inversion->id,
                    'transaccion_id' => $movimiento->id,
                    'anio' => $anio,
                    'concepto' => 'RETEFUENTE_RENTA_FIJA',
                    'valor' => (string) $retencion,
                ]);
            }
        }

        // 7. Redención en FIC con parte imputada a utilidad
        if ($inversion->tipo === 'FIC' && in_array($movimiento->tipo, ['REDENCION', 'RETIRO', 'RETIRO_PARCIAL'], true)) {
            $parteUtilidad = $datos['parte_utilidad'] ?? '0.00';
            if (bccomp((string) $parteUtilidad, '0', 2) > 0) {
                $retencion = $movimiento->retencion_aplicada ?? '0.00';
                $neto = bcsub((string) $parteUtilidad, (string) $retencion, 2);
                if (bccomp((string) $neto, '0', 2) <= 0) {
                    $neto = (string) $parteUtilidad;
                }

                $fuente = self::fuenteDeRendimientos(
                    usuarioId: $movimiento->usuario_id,
                    nombreFuente: 'Rendimientos de fondos de inversión (FIC)',
                    tipoFuente: 'Rendimientos Financieros',
                    clasificacion: 'RENTAS_CAPITAL'
                );

                $tasa = (string) ($movimiento->tasa_cambio_usada ?: '1.0000');
                $netoCop = IngresoService::convertirACop((string) $neto, $tasa);

                $ingreso = Ingreso::create([
                    'usuario_id' => $movimiento->usuario_id,
                    'fuente_id' => $fuente->id,
                    'fecha_recepcion' => $movimiento->fecha,
                    'clasificacion_tributaria' => 'RENTAS_CAPITAL',
                    'moneda' => $inversion->moneda,
                    'monto_original' => (string) $neto,
                    'tasa_cambio_usada' => $tasa,
                    'monto_bruto_cop' => $netoCop,
                    'retencion_fuente_cop' => '0.00',
                    'aportes_descontados_cop' => '0.00',
                    'origen' => 'MANUAL',
                    'estado' => 'CONFIRMADO',
                    'descripcion' => "Rendimiento neto por redención en FIC «{$inversion->nombre}» ({$movimiento->unidades} unidades)",
                ]);

                $movimiento->update(['ingreso_id' => $ingreso->id]);

                IngresoInversion::create([
                    'usuario_id' => $movimiento->usuario_id,
                    'inversion_id' => $inversion->id,
                    'transaccion_id' => $movimiento->id,
                    'anio' => $anio,
                    'tipo' => 'RENDIMIENTO',
                    'valor_bruto' => (string) $parteUtilidad,
                    'retencion' => (string) $retencion,
                ]);

                if (bccomp((string) $retencion, '0', 2) > 0) {
                    ValorDeducibleRegistro::create([
                        'usuario_id' => $movimiento->usuario_id,
                        'inversion_id' => $inversion->id,
                        'transaccion_id' => $movimiento->id,
                        'anio' => $anio,
                        'concepto' => 'RETENCION_FIC',
                        'valor' => (string) $retencion,
                    ]);
                }
            }
        }
    }

    public static function fuenteDeRendimientos(
        string $usuarioId,
        string $nombreFuente = self::FUENTE_RENDIMIENTOS,
        string $tipoFuente = self::TIPO_FUENTE_RENDIMIENTOS,
        string $clasificacion = self::CLASIFICACION_RENDIMIENTOS
    ): FuenteIngreso {
        $existente = FuenteIngreso::where('usuario_id', $usuarioId)
            ->where('nombre', $nombreFuente)
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        $tipo = Categoria::where('tipo', 'INGRESO')
            ->where('es_predeterminada', true)
            ->whereNull('categoria_padre_id')
            ->where('nombre', $tipoFuente)
            ->first();

        if ($tipo === null) {
            $tipo = Categoria::where('tipo', 'INGRESO')
                ->where('es_predeterminada', true)
                ->whereNull('categoria_padre_id')
                ->first();
        }

        if ($tipo === null) {
            throw ValidationException::withMessages([
                'ingreso' => 'El tipo de fuente "'.self::TIPO_FUENTE_RENDIMIENTOS.'" no está disponible en el catálogo (RF-015). Contacte al administrador.',
            ]);
        }

        return FuenteIngreso::create([
            'usuario_id' => $usuarioId,
            'tipo_fuente_id' => $tipo->id,
            'nombre' => $nombreFuente,
            'clasificacion_tributaria' => $clasificacion,
            'activa' => true,
        ]);
    }

    public static function presentar(Inversion $inversion, CarbonImmutable $hoy): array
    {
        $valoracion = self::valorActual($inversion);
        $rendimientoEstimado = null;
        $interesesCobradosCdt = '0.00';
        $rendimientoTotalCdt = null;

        if ($inversion->tipo === 'CDT') {
            $detalle = $inversion->detalleCdt;
            $tasaEa = $detalle?->tasa_valor ?? $inversion->tasa_ea;
            $plazoDias = $detalle?->plazo_dias ?? $inversion->plazo_dias;
            $capital = $detalle?->capital ?? $inversion->monto_inicial;
            $baseDias = $detalle?->base_dias ?? 365;
            if ($tasaEa !== null && $plazoDias !== null) {
                $rendimientoTotalCdt = self::rendimientoEstimado((string) $capital, (string) $tasaEa, (int) $plazoDias, (int) $baseDias);
                $interesesCobradosCdt = (string) MovimientoInversion::where('inversion_id', $inversion->id)
                    ->where('tipo', 'ABONO_INTERES')
                    ->sum('monto_bruto');
                $restante = bcsub($rendimientoTotalCdt, $interesesCobradosCdt, 2);
                $rendimientoEstimado = bccomp($restante, '0', 2) > 0 ? $restante : '0.00';
            }
        }

        $proyeccionCuenta = null;
        if ($inversion->tipo === 'CUENTA_ALTO_RENDIMIENTO') {
            $proyeccionCuenta = self::proyeccionCuenta($inversion);
        }

        $valoracionNoRealizada = self::valoracionNoRealizada($inversion);

        return array_merge($inversion->toArray(), [
            'unidades_actuales' => self::unidadesActuales($inversion),
            'valor_actual' => $valoracion?->valor,
            'fecha_ultima_valoracion' => $valoracion?->fecha?->toDateString(),
            'valoracion_desactualizada' => self::valoracionDesactualizada($inversion, $valoracion, $hoy),
            'rendimiento_estimado' => $rendimientoEstimado,
            'rendimiento_bruto_total_cdt' => $rendimientoTotalCdt,
            'intereses_cobrados_cdt' => $interesesCobradosCdt,
            'proyeccion_cuenta' => $proyeccionCuenta,
            'valoracion_no_realizada' => $valoracionNoRealizada,
            'detalle_especifico' => $inversion->detalle()?->toArray(),
            'entidad_relacion' => $inversion->entidadFinanciera?->toArray(),
        ]);
    }
}
