<?php

namespace App\Services;

use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\ParametroNormativo;
use App\Models\ValoracionInversion;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * MOD-06 — Rentabilidad de una inversión (RF-040) y del portafolio (RF-041), a
 * una fecha de corte.
 *
 * Sumas, ganancia y ROI se calculan con bcmath (RNG-02). La XIRR y la
 * rentabilidad real son tasas que se resuelven en punto flotante (decisión 8,
 * SC-001); se muestran con 2 decimales (RN-040.3).
 */
class RentabilidadService
{
    /** RN-040.2: con menos de 30 días la XIRR es "No calculable". */
    public const DIAS_MINIMOS_XIRR = 30;

    /** RN-040.2 */
    private const TOLERANCIA_XIRR = 1e-6;
    private const MAX_ITERACIONES_XIRR = 100;

    public const NO_CALCULABLE = 'No calculable';

    /** RN-040.1: flujos que salen del bolsillo del Usuario (negativos en la XIRR). */
    private const SALIDAS = ['APORTE', 'COMISION', 'RETENCION'];

    /**
     * RN-040.1: Capital aportado neto = Σ Aportes − Σ Retiros parciales, hasta
     * la fecha, en la moneda de la inversión.
     */
    public static function capitalAportadoNeto(Inversion $inversion, CarbonImmutable $fecha): string
    {
        $sumas = self::sumas(self::movimientosHasta($inversion, $fecha), 'monto');

        return bcsub($sumas['APORTE'], $sumas['RETIRO_PARCIAL'], 2);
    }

    /**
     * RN-040.1 a RN-040.3: indicadores de una inversión a la fecha de corte, en
     * su moneda (decisión 4, SC-001). Solo cuentan los movimientos hasta el
     * corte, y el valor actual es la valoración más reciente hasta el corte.
     */
    public static function indicadores(Inversion $inversion, CarbonImmutable $corte): array
    {
        $datos = self::datos($inversion, $corte, 'monto', null);
        $calculo = self::calcular($datos['sumas'], $datos['valor'], $datos['flujos']);

        $mensajes = $calculo['xirr'] === null ? [self::motivoXirr($datos['flujos'])] : [];

        // RN-040.1: rentabilidad real con la inflación anual de la vigencia (RNG-04).
        $real = null;
        $inflacion = null;
        if ($calculo['xirr'] !== null) {
            $parametro = ParametroNormativo::where('anio_vigencia', $corte->year)->where('es_activa', true)->first();

            if ($parametro === null) {
                $mensajes[] = "Rentabilidad real no calculable: no existen parámetros normativos para la vigencia {$corte->year}. Contacte al administrador.";
            } else {
                $inflacion = (string) $parametro->ipc_anual;
                $real = self::porcentaje(self::rentabilidadReal($calculo['xirr'], $inflacion));
            }
        }

        return [
            'fecha_corte' => $corte->toDateString(),
            'moneda' => $inversion->moneda,
            'total_aportes' => $datos['sumas']['APORTE'],
            'total_retiros' => bcadd($datos['sumas']['RETIRO_PARCIAL'], $datos['sumas']['LIQUIDACION_TOTAL'], 2),
            'total_rendimientos' => $datos['sumas']['RENDIMIENTO'],
            'total_comisiones' => $datos['sumas']['COMISION'],
            'total_retenciones' => $datos['sumas']['RETENCION'],
            'capital_aportado_neto' => $calculo['capital_aportado_neto'],
            'valor_actual' => $datos['valor'],
            'fecha_valor_actual' => $datos['valoracion']?->fecha?->toDateString(),
            'ganancia' => $calculo['ganancia'],
            'roi' => $calculo['roi'],
            'xirr' => $calculo['xirr'] === null ? self::NO_CALCULABLE : self::porcentaje($calculo['xirr']),
            'inflacion_vigencia' => $inflacion,
            'rentabilidad_real' => $real ?? self::NO_CALCULABLE,
            'resultado' => self::resultado($calculo['ganancia']),
            'mensajes' => $mensajes,
            'aviso_legal' => SeguridadSocialService::AVISO_LEGAL,
        ];
    }

    /**
     * RF-041: consolidado a la fecha de corte de las inversiones abiertas hasta
     * esa fecha y no liquidadas en ella (decisión 5, SC-001). Todo en COP: el
     * valor en USD con la TRM vigente en el corte (RN-041.4) y cada flujo con
     * la TRM de su fecha (decisión 4).
     *
     * @throws ValidationException
     */
    public static function portafolio(CarbonImmutable $corte): array
    {
        $inversiones = Inversion::query()
            ->where('fecha_apertura', '<=', $corte->toDateString())
            ->where(function ($query) use ($corte) {
                $query->whereNull('liquidada_en')->orWhere('liquidada_en', '>', $corte->toDateString());
            })
            ->orderBy('nombre')
            ->get();

        $trm = null;
        if ($inversiones->contains('moneda', TrmService::MONEDA)) {
            $trm = TrmService::vigenteEn($corte);

            if ($trm === null) {
                throw ValidationException::withMessages([
                    'fecha_corte' => "No hay TRM disponible para el {$corte->toDateString()}: no se pueden convertir las inversiones en USD (RN-041.4).",
                ]);
            }
        }

        $hoy = CarbonImmutable::today();
        $filas = [];
        $sumas = array_fill_keys(MovimientoInversion::TIPOS, '0.00');
        $flujos = [];
        $ganancia = '0.00';

        foreach ($inversiones as $inversion) {
            $valoracion = self::valoracionHasta($inversion, $corte);
            $valor = $valoracion === null ? '0.00' : (string) $valoracion->valor;
            $valorCop = $inversion->moneda === 'COP' ? $valor : IngresoService::convertirACop($valor, (string) $trm->valor);

            $datosCop = self::datos($inversion, $corte, 'monto_cop', $valorCop);
            $calculoCop = self::calcular($datosCop['sumas'], $valorCop, $datosCop['flujos']);

            foreach ($datosCop['sumas'] as $tipo => $suma) {
                $sumas[$tipo] = bcadd($sumas[$tipo], $suma, 2);
            }
            $flujos = array_merge($flujos, $datosCop['flujos']);
            $ganancia = bcadd($ganancia, $calculoCop['ganancia'], 2);

            // RN-041.3: ROI y XIRR de cada inversión en su moneda (decisión 4).
            $propios = self::datos($inversion, $corte, 'monto', $valor);
            $calculoPropio = self::calcular($propios['sumas'], $valor, $propios['flujos']);

            $filas[] = [
                'id' => $inversion->id,
                'nombre' => $inversion->nombre,
                'tipo' => $inversion->tipo,
                'nivel_riesgo' => $inversion->nivel_riesgo,
                'moneda' => $inversion->moneda,
                'valor_actual' => $valor,
                'valor_actual_cop' => $valorCop,
                'roi' => $calculoPropio['roi'],
                'xirr' => $calculoPropio['xirr'] === null ? self::NO_CALCULABLE : self::porcentaje($calculoPropio['xirr']),
                'fecha_ultima_valoracion' => $valoracion?->fecha?->toDateString(),
                'estado' => $inversion->estado,
                'valoracion_desactualizada' => InversionService::valoracionDesactualizada($inversion, InversionService::valorActual($inversion), $hoy),
                'resultado' => self::resultado($calculoPropio['ganancia']),
            ];
        }

        // CA-041.1: el total es la suma de los valores de la tabla de detalle.
        $valorTotal = array_reduce($filas, fn (string $total, array $fila) => bcadd($total, $fila['valor_actual_cop'], 2), '0.00');

        $participacion = self::porcentajes(array_column($filas, 'valor_actual_cop'));
        foreach ($filas as $i => $fila) {
            $filas[$i]['porcentaje_portafolio'] = $participacion[$i];
        }

        $xirr = self::xirr($flujos);

        return [
            'fecha_corte' => $corte->toDateString(),
            'moneda' => 'COP',
            'trm' => $trm === null ? null : TrmService::presentar($trm, $corte),
            'cantidad_inversiones' => count($filas),
            'valor_total' => $valorTotal,
            'capital_aportado_neto' => bcsub($sumas['APORTE'], $sumas['RETIRO_PARCIAL'], 2),
            'ganancia' => $ganancia,
            'roi' => bccomp($sumas['APORTE'], '0', 2) === 1 ? self::redondear(bcdiv(bcmul($ganancia, '100', 10), $sumas['APORTE'], 10)) : null,
            'xirr' => $xirr === null ? self::NO_CALCULABLE : self::porcentaje($xirr),
            'resultado' => self::resultado($ganancia),
            // RN-041.2: distribución del valor actual.
            'distribucion' => [
                'por_tipo' => self::distribucion($filas, 'tipo'),
                'por_riesgo' => self::distribucion($filas, 'nivel_riesgo'),
                'por_moneda' => self::distribucion($filas, 'moneda'),
            ],
            'detalle' => $filas,
            'aviso_legal' => SeguridadSocialService::AVISO_LEGAL,
        ];
    }

    /**
     * RN-040.2: TIR no periódica. Newton-Raphson desde 10 %; si diverge
     * (r ≤ −100 %) o no converge, bisección en un intervalo con cambio de
     * signo. Tolerancia 1×10⁻⁶ en la tasa y máximo 100 iteraciones por método.
     *
     * Null es "No calculable": menos de 30 días entre el primer y el último
     * flujo, flujos de un solo signo o sin convergencia.
     *
     * @param  array<int, array{0: CarbonImmutable, 1: float}>  $flujos
     */
    public static function xirr(array $flujos): ?float
    {
        $flujos = array_values(array_filter($flujos, fn (array $flujo) => $flujo[1] != 0.0));

        if (count($flujos) < 2) {
            return null;
        }

        $fechas = array_map(fn (array $flujo) => $flujo[0], $flujos);
        $inicio = min($fechas);

        if ((int) $inicio->diffInDays(max($fechas)) < self::DIAS_MINIMOS_XIRR) {
            return null;
        }

        $montos = array_column($flujos, 1);
        if (min($montos) >= 0 || max($montos) <= 0) {
            return null;
        }

        // RN-040.1: Σ Fᵢ / (1 + r)^((dᵢ − d₀)/365) = 0.
        $tiempos = array_map(fn (CarbonImmutable $fecha) => ((int) $inicio->diffInDays($fecha)) / 365, $fechas);

        $vpn = function (float $r) use ($montos, $tiempos): float {
            $total = 0.0;
            foreach ($montos as $i => $monto) {
                $total += $monto / (1 + $r) ** $tiempos[$i];
            }

            return $total;
        };

        $derivada = function (float $r) use ($montos, $tiempos): float {
            $total = 0.0;
            foreach ($montos as $i => $monto) {
                $total -= $tiempos[$i] * $monto / (1 + $r) ** ($tiempos[$i] + 1);
            }

            return $total;
        };

        $r = 0.1;
        for ($i = 0; $i < self::MAX_ITERACIONES_XIRR; $i++) {
            $pendiente = $derivada($r);

            if ($pendiente == 0.0 || ! is_finite($pendiente)) {
                break;
            }

            $siguiente = $r - $vpn($r) / $pendiente;

            if (! is_finite($siguiente) || $siguiente <= -1) {
                break;
            }

            if (abs($siguiente - $r) < self::TOLERANCIA_XIRR) {
                return $siguiente;
            }

            $r = $siguiente;
        }

        // Respaldo por bisección: se amplía el extremo superior hasta encontrar
        // un cambio de signo.
        $bajo = -0.999999;
        $alto = 1.0;
        $vpnBajo = $vpn($bajo);
        $vpnAlto = $vpn($alto);

        while ($vpnBajo * $vpnAlto > 0 && $alto < 1e6) {
            $alto *= 2;
            $vpnAlto = $vpn($alto);
        }

        if (! is_finite($vpnBajo) || ! is_finite($vpnAlto) || $vpnBajo * $vpnAlto > 0) {
            return null;
        }

        for ($i = 0; $i < self::MAX_ITERACIONES_XIRR; $i++) {
            $medio = ($bajo + $alto) / 2;

            if (($alto - $bajo) / 2 < self::TOLERANCIA_XIRR) {
                return $medio;
            }

            $vpnMedio = $vpn($medio);

            if ($vpnBajo * $vpnMedio <= 0) {
                $alto = $medio;
            } else {
                $bajo = $medio;
                $vpnBajo = $vpnMedio;
            }
        }

        return null;
    }

    /**
     * RN-040.1 / CA-040.3: Rentabilidad real anual = (1 + r) / (1 + inflación) − 1.
     * Con una XIRR del 10 % y una inflación del 5 %, 4,76 %.
     */
    public static function rentabilidadReal(float $xirr, string $inflacionPorc): float
    {
        return (1 + $xirr) / (1 + ((float) $inflacionPorc) / 100) - 1;
    }

    /** Movimientos de la inversión hasta la fecha, del más antiguo al más reciente. */
    private static function movimientosHasta(Inversion $inversion, CarbonImmutable $fecha)
    {
        return MovimientoInversion::where('inversion_id', $inversion->id)
            ->where('fecha', '<=', $fecha->toDateString())
            ->orderBy('fecha')
            ->get();
    }

    /** RN-039.3: la valoración más reciente hasta la fecha de corte. */
    private static function valoracionHasta(Inversion $inversion, CarbonImmutable $corte): ?ValoracionInversion
    {
        return ValoracionInversion::where('inversion_id', $inversion->id)
            ->where('fecha', '<=', $corte->toDateString())
            ->orderByDesc('fecha')
            ->first();
    }

    /**
     * Sumas por tipo, los flujos de la XIRR y el valor actual al corte, en el
     * campo de monto indicado (`monto` en la moneda de la inversión o
     * `monto_cop`). Con `$valor` se usa ese valor actual (ya convertido).
     */
    private static function datos(Inversion $inversion, CarbonImmutable $corte, string $campo, ?string $valor): array
    {
        $movimientos = self::movimientosHasta($inversion, $corte);
        $valoracion = self::valoracionHasta($inversion, $corte);
        $valor ??= $valoracion === null ? '0.00' : (string) $valoracion->valor;

        // RN-040.1: aportes, comisiones y retenciones negativos (la retención,
        // por decisión del equipo, SC-001); retiros, rendimientos y el valor
        // actual a la fecha de corte, positivos.
        $flujos = $movimientos->map(fn (MovimientoInversion $movimiento) => [
            CarbonImmutable::parse($movimiento->fecha),
            (in_array($movimiento->tipo, self::SALIDAS, true) ? -1 : 1) * (float) $movimiento->{$campo},
        ])->all();
        $flujos[] = [$corte, (float) $valor];

        return [
            'sumas' => self::sumas($movimientos, $campo),
            'valor' => $valor,
            'valoracion' => $valoracion,
            'flujos' => $flujos,
        ];
    }

    /** RN-040.1: Σ por tipo de movimiento. */
    private static function sumas($movimientos, string $campo): array
    {
        $sumas = array_fill_keys(MovimientoInversion::TIPOS, '0.00');

        foreach ($movimientos as $movimiento) {
            $sumas[$movimiento->tipo] = bcadd($sumas[$movimiento->tipo], (string) $movimiento->{$campo}, 2);
        }

        return $sumas;
    }

    /**
     * RN-040.1: capital aportado neto, ganancia, ROI y XIRR a partir de las
     * sumas, el valor actual y los flujos.
     */
    private static function calcular(array $sumas, string $valor, array $flujos): array
    {
        $retiros = bcadd($sumas['RETIRO_PARCIAL'], $sumas['LIQUIDACION_TOTAL'], 2);

        // Ganancia = Valor actual + Σ Retiros + Σ Rendimientos − Σ Aportes − Σ Comisiones − Σ Retenciones
        $ganancia = bcsub(
            bcadd(bcadd($valor, $retiros, 2), $sumas['RENDIMIENTO'], 2),
            bcadd(bcadd($sumas['APORTE'], $sumas['COMISION'], 2), $sumas['RETENCION'], 2),
            2
        );

        return [
            'capital_aportado_neto' => bcsub($sumas['APORTE'], $sumas['RETIRO_PARCIAL'], 2),
            'ganancia' => $ganancia,
            // ROI (%) = Ganancia total / Σ Aportes × 100
            'roi' => bccomp($sumas['APORTE'], '0', 2) === 1
                ? self::redondear(bcdiv(bcmul($ganancia, '100', 10), $sumas['APORTE'], 10))
                : null,
            'xirr' => self::xirr($flujos),
        ];
    }

    /** RN-040.2: por qué la XIRR no se pudo calcular. */
    private static function motivoXirr(array $flujos): string
    {
        $fechas = array_map(fn (array $flujo) => $flujo[0], $flujos);

        return (int) min($fechas)->diffInDays(max($fechas)) < self::DIAS_MINIMOS_XIRR
            ? 'XIRR no calculable: la inversión tiene menos de 30 días a la fecha de corte (RN-040.2). Se muestra solo el ROI.'
            : 'XIRR no calculable: el cálculo no converge con estos flujos (RN-040.2). Se muestra solo el ROI.';
    }

    /**
     * RN-041.2 / CA-041.2: valor y porcentaje por clave. Sin nivel de riesgo
     * declarado, la clave es SIN_DECLARAR.
     */
    private static function distribucion(array $filas, string $campo): array
    {
        $grupos = [];
        foreach ($filas as $fila) {
            $clave = $fila[$campo] ?? 'SIN_DECLARAR';
            $grupos[$clave] = bcadd($grupos[$clave] ?? '0.00', $fila['valor_actual_cop'], 2);
        }

        $porcentajes = self::porcentajes(array_values($grupos));

        return array_map(
            fn (string $clave, string $valor, string $porcentaje) => ['clave' => $clave, 'valor' => $valor, 'porcentaje' => $porcentaje],
            array_keys($grupos),
            array_values($grupos),
            $porcentajes
        );
    }

    /**
     * CA-041.2: porcentajes a 2 decimales que suman exactamente 100,00 por el
     * método del mayor residuo (redondear cada uno por separado puede dar 99,99).
     *
     * @param  array<int, string>  $valores
     * @return array<int, string>
     */
    private static function porcentajes(array $valores): array
    {
        $total = array_reduce($valores, fn (string $suma, string $valor) => bcadd($suma, $valor, 2), '0.00');

        if (bccomp($total, '0', 2) !== 1) {
            return array_map(fn () => '0.00', $valores);
        }

        // En centésimas de punto porcentual: 100,00 % = 10.000.
        $enteros = [];
        $residuos = [];
        foreach ($valores as $i => $valor) {
            $exacto = bcdiv(bcmul($valor, '10000', 10), $total, 10);
            $enteros[$i] = (int) bcadd($exacto, '0', 0);
            $residuos[$i] = bcsub($exacto, (string) $enteros[$i], 10);
        }

        $faltan = 10000 - array_sum($enteros);
        arsort($residuos, SORT_NUMERIC);
        foreach (array_keys($residuos) as $i) {
            if ($faltan <= 0) {
                break;
            }
            $enteros[$i]++;
            $faltan--;
        }

        return array_map(fn (int $centesimas) => bcdiv((string) $centesimas, '100', 2), $enteros);
    }

    /** RN-040.3: una tasa (0,1 = 10 %) en porcentaje con 2 decimales y signo. */
    private static function porcentaje(float $tasa): string
    {
        return self::redondear(sprintf('%.10F', $tasa * 100));
    }

    /** RN-040.3: ganancias y pérdidas con signo; el color no es el único indicador. */
    private static function resultado(string $ganancia): string
    {
        return match (bccomp($ganancia, '0', 2)) {
            1 => 'GANANCIA',
            -1 => 'PERDIDA',
            default => 'NEUTRO',
        };
    }

    /** Redondeo a 2 decimales, la mitad lejos de cero (admite negativos). */
    private static function redondear(string $valor): string
    {
        $redondeado = str_starts_with($valor, '-')
            ? bcsub($valor, '0.005', 2)
            : bcadd($valor, '0.005', 2);

        return bccomp($redondeado, '0', 2) === 0 ? '0.00' : $redondeado;
    }
}
