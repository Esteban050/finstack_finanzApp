<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Ingreso;
use App\Models\LiquidacionSeguridadSocial;
use App\Models\PagoSeguridadSocial;
use App\Models\ParametroNormativo;
use App\Models\PerfilTributario;
use App\Models\TarifaEscalonada;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * MOD-05 — Cálculo de aportes a seguridad social: independiente o contratista
 * (RF-033) y asalariado (RF-034), y reglas de los pagos por PILA (RF-035).
 *
 * Toda la aritmética de montos se hace con bcmath sobre cadenas (RNG-02).
 */
class SeguridadSocialService
{
    /** RNG-08: aviso obligatorio en todo cálculo de seguridad social. */
    public const AVISO_LEGAL = 'Valores estimados con fines informativos. No constituyen asesoría tributaria, contable ni financiera.';

    /** RN-033.3 */
    public const AVISO_BAJO_SMMLV = 'Con ingresos inferiores a 1 SMMLV no existe obligación de cotizar como independiente; el cálculo mostrado es voluntario sobre 1 SMMLV.';

    /** RF-033: ocupaciones del perfil (RF-008) que cotizan como independientes. */
    public const OCUPACIONES_INDEPENDIENTE = [
        'Independiente por cuenta propia',
        'Contratista por prestación de servicios',
    ];

    public const OCUPACION_ASALARIADO = 'Asalariado';

    /** RN-033.6 */
    public const OCUPACION_PENSIONADO = 'Pensionado';

    /** RN-033.5: cada aporte se redondea al múltiplo de $ 100 superior (Decreto 1990 de 2016). */
    private const MULTIPLO_APORTE = 100;

    /**
     * RN-035.3: categoría del catálogo (RF-015) del gasto que crea un pago PILA,
     * la misma que siembra CategoriaPredeterminadaSeeder.
     */
    public const CATEGORIA_GASTO_PILA = 'Seguridad social';
    public const SUBCATEGORIA_GASTO_PILA = 'Aporte PILA independiente';

    /** RN-035.3: medio de pago del gasto si el Usuario no indica otro. */
    public const MEDIO_PAGO_PILA = 'TRANSFERENCIA';

    /** RN-035.5: diferencia por concepto a partir de la cual se informa. */
    private const UMBRAL_DIFERENCIA = '1000';

    /** RN-033.7: primer día del periodo AAAA-MM. */
    public static function inicioDelPeriodo(string $periodo): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $periodo)->startOfMonth();
    }

    /**
     * RNG-04: cada cálculo usa los parámetros de la vigencia del hecho económico,
     * no los de la fecha de consulta.
     *
     * @throws ValidationException
     */
    public static function parametrosDe(int $anio): ParametroNormativo
    {
        $parametro = ParametroNormativo::where('anio_vigencia', $anio)
            ->where('es_activa', true)
            ->first();

        if ($parametro === null) {
            throw ValidationException::withMessages([
                'periodo' => "No existen parámetros normativos para la vigencia {$anio}. Contacte al administrador.",
            ]);
        }

        return $parametro;
    }

    /**
     * RN-008.6: el cálculo de un periodo pasado usa el perfil que regía entonces.
     * Si el perfil cambió a mitad de mes, se toma el más reciente de ese mes.
     */
    public static function perfilDelPeriodo(string $usuarioId, CarbonImmutable $inicioMes): ?PerfilTributario
    {
        return PerfilTributario::where('usuario_id', $usuarioId)
            ->where('vigente_desde', '<=', $inicioMes->endOfMonth()->toDateString())
            ->where(function ($query) use ($inicioMes) {
                $query->whereNull('vigente_hasta')
                    ->orWhere('vigente_hasta', '>=', $inicioMes->toDateString());
            })
            ->orderByDesc('vigente_desde')
            ->first();
    }

    public static function tieneOcupacion(?PerfilTributario $perfil, array $ocupaciones): bool
    {
        return array_intersect($ocupaciones, $perfil?->ocupaciones ?? []) !== [];
    }

    /**
     * RN-033.1: "Ingreso base del mes = suma de los ingresos brutos con
     * clasificación 'Rentas de trabajo' (no asalariado) del mes de cotización".
     *
     * Se toman los ingresos confirmados (RN-022.3) y se excluyen los de fuentes
     * cuyo tipo es "Salario" en el catálogo (RN-015.2): esos ya cotizan por
     * nómina (RF-034).
     */
    public static function ingresoBaseDelPeriodo(string $usuarioId, CarbonImmutable $inicioMes): string
    {
        $suma = Ingreso::query()
            ->where('usuario_id', $usuarioId)
            ->confirmados()
            ->where('clasificacion_tributaria', 'RENTAS_TRABAJO')
            ->whereBetween('fecha_recepcion', [$inicioMes->toDateString(), $inicioMes->endOfMonth()->toDateString()])
            ->whereDoesntHave('fuente.tipoFuente', self::deFuenteSalario())
            ->sum('monto_bruto_cop');

        return bcadd((string) $suma, '0', 2);
    }

    /** RN-034.5: solo un ingreso de fuente "Salario" lleva aportes de nómina. */
    public static function esIngresoDeSalario(Ingreso $ingreso): bool
    {
        return Ingreso::whereKey($ingreso->id)
            ->whereHas('fuente.tipoFuente', self::deFuenteSalario())
            ->exists();
    }

    /**
     * RF-033: IBC y aportes mensuales de un Independiente o Contratista.
     */
    public static function calcularIndependiente(string $ingresoBase, ParametroNormativo $parametro, ?PerfilTributario $perfil): array
    {
        $smmlv = (string) $parametro->valor_smmlv;

        // RN-033.2: IBC = ingreso base × 40 %, entre 1 y 25 SMMLV, al peso superior.
        $ibc = self::porcentaje($ingresoBase, (string) $parametro->porc_ibc_indep);
        $ibc = self::acotar(
            $ibc,
            bcmul($smmlv, (string) $parametro->ibc_min_smmlv, 2),
            bcmul($smmlv, (string) $parametro->ibc_max_smmlv, 2)
        );
        $ibc = self::redondearArriba($ibc, 1);

        // RN-033.6: un Pensionado no calcula aporte a pensión ni a FSP.
        $pensionado = self::tieneOcupacion($perfil, [self::OCUPACION_PENSIONADO]);
        $tarifaFsp = $pensionado ? '0' : self::tarifaFsp($ibc, $smmlv, $parametro);
        $tarifaArl = self::tarifaArl($perfil, $parametro);

        // RN-033.4 / RN-033.5: cada aporte al múltiplo de $ 100 superior.
        $aportes = [
            'salud' => self::redondearArriba(self::porcentaje($ibc, (string) $parametro->tarifa_salud_indep), self::MULTIPLO_APORTE),
            'pension' => $pensionado
                ? '0.00'
                : self::redondearArriba(self::porcentaje($ibc, (string) $parametro->tarifa_pension_indep), self::MULTIPLO_APORTE),
            'fsp' => self::redondearArriba(self::porcentaje($ibc, $tarifaFsp), self::MULTIPLO_APORTE),
            'arl' => $tarifaArl === null
                ? '0.00'
                : self::redondearArriba(self::porcentaje($ibc, $tarifaArl), self::MULTIPLO_APORTE),
        ];

        return [
            'ingreso_base' => bcadd($ingresoBase, '0', 2),
            'ibc' => $ibc,
            ...$aportes,
            // RN-033.5: total = suma de los aportes redondeados.
            'total' => array_reduce($aportes, fn ($total, $aporte) => bcadd($total, $aporte, 2), '0.00'),
            'avisos' => bccomp($ingresoBase, $smmlv, 2) < 0 ? [self::AVISO_BAJO_SMMLV] : [],
            'parametros_usados' => self::parametrosUsados($parametro, $ibc, [
                'porc_ibc' => (string) $parametro->porc_ibc_indep,
                'tarifa_salud' => (string) $parametro->tarifa_salud_indep,
                'tarifa_pension' => $pensionado ? '0' : (string) $parametro->tarifa_pension_indep,
                'tarifa_fsp' => $tarifaFsp,
                'clase_riesgo_arl' => $tarifaArl === null ? null : $perfil?->clase_riesgo_arl,
                'tarifa_arl' => $tarifaArl,
                'pensionado' => $pensionado,
            ]),
            'aviso_legal' => self::AVISO_LEGAL,
        ];
    }

    /**
     * RF-034: aportes que el empleador descuenta del salario de un Asalariado y
     * salario neto sin retención.
     *
     * RN-033.5 (múltiplos de $ 100) es una regla de la PILA del independiente:
     * acá los valores se redondean a 2 decimales (RNG-02), como en CA-034.1.
     */
    public static function calcularAsalariado(string $salario, bool $esIntegral, ParametroNormativo $parametro): array
    {
        $smmlv = (string) $parametro->valor_smmlv;

        // RN-034.2: IBC = salario (o 70 % del integral), entre 1 y 25 SMMLV.
        $base = $esIntegral ? self::porcentaje($salario, (string) $parametro->porc_ibc_integral) : $salario;
        $ibc = IngresoService::redondear(self::acotar(
            $base,
            bcmul($smmlv, (string) $parametro->ibc_min_smmlv, 2),
            bcmul($smmlv, (string) $parametro->ibc_max_smmlv, 2)
        ));

        // RN-034.3: salud 4 %, pensión 4 % y FSP desde 4 SMMLV. Sin ARL.
        $tarifaFsp = self::tarifaFsp($ibc, $smmlv, $parametro);

        $aportes = [
            'salud' => IngresoService::redondear(self::porcentaje($ibc, (string) $parametro->tarifa_salud_emp)),
            'pension' => IngresoService::redondear(self::porcentaje($ibc, (string) $parametro->tarifa_pension_emp)),
            'fsp' => IngresoService::redondear(self::porcentaje($ibc, $tarifaFsp)),
        ];

        $total = array_reduce($aportes, fn ($suma, $aporte) => bcadd($suma, $aporte, 2), '0.00');

        return [
            'salario' => bcadd($salario, '0', 2),
            'es_salario_integral' => $esIntegral,
            'ibc' => $ibc,
            ...$aportes,
            'total_aportes' => $total,
            // CA-034.1: "neto sin retención".
            'salario_neto_sin_retencion' => bcsub(bcadd($salario, '0', 2), $total, 2),
            'parametros_usados' => self::parametrosUsados($parametro, $ibc, [
                'porc_ibc' => $esIntegral ? (string) $parametro->porc_ibc_integral : '100.00',
                'tarifa_salud' => (string) $parametro->tarifa_salud_emp,
                'tarifa_pension' => (string) $parametro->tarifa_pension_emp,
                'tarifa_fsp' => $tarifaFsp,
            ]),
            'aviso_legal' => self::AVISO_LEGAL,
        ];
    }

    /**
     * RN-033.7: guarda el resultado como "Liquidación estimada" del periodo.
     * Si el periodo ya tenía una, se recalcula sobre la misma fila.
     *
     * @return array{0: ?array, 1: LiquidacionSeguridadSocial}
     */
    public static function guardarLiquidacion(string $usuarioId, string $periodo, string $origen, array $calculo, ParametroNormativo $parametro): array
    {
        $valores = [
            'origen_ingreso' => $origen,
            'ingreso_base' => $calculo['ingreso_base'],
            'ibc' => $calculo['ibc'],
            'salud' => $calculo['salud'],
            'pension' => $calculo['pension'],
            'fsp' => $calculo['fsp'],
            'arl' => $calculo['arl'],
            'total' => $calculo['total'],
            'parametro_normativo_id' => $parametro->id,
            'parametros_usados' => $calculo['parametros_usados'],
        ];

        // firstOrCreate inserta dentro de un savepoint: si otra petición creó la
        // fila del periodo entre la búsqueda y el INSERT, se toma esa y se recalcula.
        $liquidacion = LiquidacionSeguridadSocial::firstOrCreate(
            ['usuario_id' => $usuarioId, 'periodo' => $periodo],
            $valores
        );

        if ($liquidacion->wasRecentlyCreated) {
            return [null, $liquidacion->fresh()];
        }

        $anterior = $liquidacion->toArray();
        $liquidacion->fill($valores)->save();

        return [$anterior, $liquidacion->fresh()];
    }

    /**
     * RF-035: total de un pago, igual a la columna generada `total`: conceptos
     * más intereses de mora.
     */
    public static function totalPago(array $valores): string
    {
        return array_reduce(
            [...PagoSeguridadSocial::CONCEPTOS, 'intereses_mora'],
            fn ($total, $campo) => bcadd($total, (string) ($valores[$campo] ?? '0'), 2),
            '0.00'
        );
    }

    /**
     * CA-035.2: no puede haber dos pagos del mismo periodo, salvo una planilla
     * de corrección.
     */
    public static function periodoYaPagado(string $periodo, ?string $exceptoPagoId = null): bool
    {
        return PagoSeguridadSocial::where('periodo', $periodo)
            ->where('es_correccion', false)
            ->when($exceptoPagoId, fn ($query) => $query->whereKeyNot($exceptoPagoId))
            ->exists();
    }

    /**
     * RN-035.3 / CA-035.1: atributos del gasto de "Seguridad social" que
     * acompaña al pago, con su fecha y su monto total. Un solo gasto por el
     * total, en la subcategoría "Aporte PILA independiente".
     *
     * La naturaleza y la necesidad son las sugeridas por la subcategoría
     * (RN-026.2); si no trae sugerencia, el aporte es fijo y esencial. El aporte
     * obligatorio es ingreso no constitutivo (RN-035.4), no deducción.
     *
     * @throws ValidationException
     */
    public static function atributosGastoPila(PagoSeguridadSocial $pago, string $medioPago): array
    {
        $subcategoria = self::subcategoriaGastoPila();
        $total = self::totalPago($pago->only([...PagoSeguridadSocial::CONCEPTOS, 'intereses_mora']));

        $descripcion = "Pago PILA del periodo {$pago->periodo}"
            .($pago->numero_planilla ? " · planilla {$pago->numero_planilla}" : '')
            .($pago->es_correccion ? ' (corrección)' : '');

        return [
            'categoria_id' => $subcategoria->categoria_padre_id,
            'subcategoria_id' => $subcategoria->id,
            'fecha' => $pago->fecha_pago->toDateString(),
            'moneda' => 'COP',
            'monto_original' => $total,
            'tasa_cambio_usada' => '1.0000',
            'monto_cop' => $total,
            'naturaleza' => $subcategoria->naturaleza_sugerida ?? 'FIJO',
            'necesidad' => $subcategoria->necesidad_sugerida ?? 'ESENCIAL',
            'medio_pago' => $medioPago,
            'relevancia_tributaria' => 'NO_DEDUCIBLE',
            'subtipo_deduccion' => null,
            'descripcion' => $descripcion,
        ];
    }

    /**
     * RN-035.5: si lo pagado en el periodo difiere de la liquidación estimada en
     * más de $ 1.000 en algún concepto, se muestra la diferencia.
     *
     * RN-035.2: una planilla de corrección registra la diferencia frente al pago
     * original (decisión del equipo, SC-001), así que lo pagado en el periodo es
     * la suma de sus pagos, con y sin corrección.
     */
    public static function diferenciasConLiquidacion(PagoSeguridadSocial $pago): array
    {
        $liquidacion = LiquidacionSeguridadSocial::where('usuario_id', $pago->usuario_id)
            ->where('periodo', $pago->periodo)
            ->first();

        if ($liquidacion === null) {
            return [
                'liquidacion_id' => null,
                'hay_diferencias' => false,
                'diferencias' => [],
                'mensaje' => "El periodo {$pago->periodo} no tiene una liquidación estimada con la cual comparar.",
            ];
        }

        $pagadoPeriodo = PagoSeguridadSocial::where('usuario_id', $pago->usuario_id)
            ->where('periodo', $pago->periodo)
            ->selectRaw('SUM(salud) AS salud, SUM(pension) AS pension, SUM(fsp) AS fsp, SUM(arl) AS arl')
            ->first();

        $diferencias = [];

        foreach (PagoSeguridadSocial::CONCEPTOS as $concepto) {
            $pagado = bcadd((string) $pagadoPeriodo->{$concepto}, '0', 2);
            $estimado = (string) $liquidacion->{$concepto};
            $diferencia = bcsub($pagado, $estimado, 2);

            if (bccomp(ltrim($diferencia, '-'), self::UMBRAL_DIFERENCIA, 2) > 0) {
                $diferencias[] = [
                    'concepto' => $concepto,
                    'estimado' => $estimado,
                    'pagado' => $pagado,
                    'diferencia' => $diferencia,
                ];
            }
        }

        return [
            'liquidacion_id' => $liquidacion->id,
            'hay_diferencias' => $diferencias !== [],
            'diferencias' => $diferencias,
            'mensaje' => $diferencias === []
                ? 'Lo pagado en el periodo (pago y correcciones) coincide con la liquidación estimada (diferencias de hasta $ 1.000).'
                : 'Lo pagado en el periodo (pago y correcciones) difiere de la liquidación estimada en más de $ 1.000 en algún concepto.',
        ];
    }

    /**
     * RN-035.4: los aportes obligatorios de salud y pensión pagados se acumulan
     * como "Ingreso no constitutivo de renta" del año gravable (RF-043). El año
     * es el de la fecha de pago: el aporte cuenta en el año en que se paga. Las
     * correcciones suman porque registran la diferencia (RN-035.2).
     *
     * Solo cubre los pagos por PILA: los aportes descontados del salario
     * (RF-034) también son ingreso no constitutivo, pero el ingreso los guarda
     * en un solo valor con el FSP y su consolidado es de RF-043 (RN-043.1).
     */
    public static function resumenAnual(string $usuarioId, int $anio): array
    {
        $fila = PagoSeguridadSocial::where('usuario_id', $usuarioId)
            ->whereYear('fecha_pago', $anio)
            ->selectRaw('COUNT(*) AS cantidad, COALESCE(SUM(salud), 0) AS salud, COALESCE(SUM(pension), 0) AS pension')
            ->first();

        $salud = bcadd((string) $fila->salud, '0', 2);
        $pension = bcadd((string) $fila->pension, '0', 2);

        return [
            'anio' => $anio,
            'cantidad_pagos' => (int) $fila->cantidad,
            'salud' => $salud,
            'pension' => $pension,
            'ingreso_no_constitutivo_pila' => bcadd($salud, $pension, 2),
            'alcance' => 'Incluye solo los pagos por PILA (RF-035). Los aportes que el empleador descuenta del salario (RF-034) se suman en el consolidado del año gravable (RF-043).',
            'aviso_legal' => self::AVISO_LEGAL,
        ];
    }

    /**
     * RN-035.3: subcategoría "Seguridad social › Aporte PILA independiente" del
     * catálogo global.
     *
     * @throws ValidationException
     */
    private static function subcategoriaGastoPila(): Categoria
    {
        $subcategoria = Categoria::where('tipo', 'GASTO')
            ->where('es_predeterminada', true)
            ->where('activa', true)
            ->where('nombre', self::SUBCATEGORIA_GASTO_PILA)
            ->whereHas('padre', function ($query) {
                $query->whereNull('categoria_padre_id')
                    ->where('activa', true)
                    ->where('nombre', self::CATEGORIA_GASTO_PILA);
            })
            ->first();

        if ($subcategoria === null) {
            throw ValidationException::withMessages([
                'gasto' => 'La categoría "'.self::CATEGORIA_GASTO_PILA.' › '.self::SUBCATEGORIA_GASTO_PILA.'" no está disponible en el catálogo (RF-015). Contacte al administrador.',
            ]);
        }

        return $subcategoria;
    }

    /**
     * RN-015.2: fuentes cuyo tipo es "Salario" (o una subcategoría suya) en el
     * catálogo. Esos ingresos cotizan por nómina (RF-034), no como independiente.
     */
    private static function deFuenteSalario(): \Closure
    {
        return fn ($query) => $query->where(function ($tipo) {
            $tipo->where('nombre', 'Salario')
                ->orWhereHas('padre', fn ($padre) => $padre->where('nombre', 'Salario'));
        });
    }

    /**
     * RN-014.2: tarifa del FSP según el IBC expresado en SMMLV. Cada tramo
     * incluye su límite inferior y excluye el superior, así que 4,00 SMMLV ya
     * aporta (RN-034.3: "4 SMMLV o más") y por debajo de 4 la tarifa es 0 %
     * (RN-033.4).
     */
    private static function tarifaFsp(string $ibc, string $smmlv, ParametroNormativo $parametro): string
    {
        $enSmmlv = bcdiv($ibc, $smmlv, 4);

        $tramo = TarifaEscalonada::where('parametro_normativo_id', $parametro->id)
            ->where('tipo_tabla', 'FSP')
            ->where('rango_desde', '<=', $enSmmlv)
            ->where(function ($query) use ($enSmmlv) {
                $query->whereNull('rango_hasta')->orWhere('rango_hasta', '>', $enSmmlv);
            })
            ->orderByDesc('rango_desde')
            ->first();

        return $tramo === null ? '0' : (string) $tramo->tarifa_porc;
    }

    /**
     * RN-033.4 / RN-014.3: tarifa ARL de la clase de riesgo del perfil. "Solo se
     * incluye si la afiliación es obligatoria o el usuario la marcó como
     * voluntaria": un perfil con clase de riesgo registrada tiene afiliación,
     * obligatoria (`arl_obligatoria`) o voluntaria. Sin clase no hay ARL.
     */
    private static function tarifaArl(?PerfilTributario $perfil, ParametroNormativo $parametro): ?string
    {
        if ($perfil?->clase_riesgo_arl === null) {
            return null;
        }

        $tramo = TarifaEscalonada::where('parametro_normativo_id', $parametro->id)
            ->where('tipo_tabla', 'ARL')
            ->where('rango_desde', $perfil->clase_riesgo_arl)
            ->first();

        return $tramo === null ? null : (string) $tramo->tarifa_porc;
    }

    /**
     * CA-033.4: los parámetros usados y la vigencia acompañan el resultado.
     */
    private static function parametrosUsados(ParametroNormativo $parametro, string $ibc, array $tarifas): array
    {
        $smmlv = (string) $parametro->valor_smmlv;

        return [
            'anio_vigencia' => $parametro->anio_vigencia,
            'version' => $parametro->version,
            'acto_administrativo' => $parametro->acto_administrativo,
            'valor_smmlv' => $smmlv,
            'ibc_min_smmlv' => (string) $parametro->ibc_min_smmlv,
            'ibc_max_smmlv' => (string) $parametro->ibc_max_smmlv,
            'ibc_en_smmlv' => bcdiv($ibc, $smmlv, 2),
            ...$tarifas,
        ];
    }

    /** Valor × tarifa %, con 6 decimales de trabajo (RNG-02). */
    private static function porcentaje(string $valor, string $tarifaPorc): string
    {
        return bcdiv(bcmul($valor, $tarifaPorc, 6), '100', 6);
    }

    private static function acotar(string $valor, string $minimo, string $maximo): string
    {
        if (bccomp($valor, $minimo, 6) < 0) {
            return $minimo;
        }

        return bccomp($valor, $maximo, 6) > 0 ? $maximo : $valor;
    }

    /**
     * RN-033.2 (al peso superior) y RN-033.5 (al múltiplo de $ 100 superior).
     */
    private static function redondearArriba(string $valor, int $multiplo): string
    {
        $paso = (string) $multiplo;
        $veces = bcdiv($valor, $paso, 0);

        if (bccomp(bcmul($veces, $paso, 6), $valor, 6) < 0) {
            $veces = bcadd($veces, '1', 0);
        }

        return bcmul($veces, $paso, 2);
    }
}
