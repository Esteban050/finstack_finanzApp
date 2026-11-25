<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\EsquemaCostoPresunto;
use App\Models\Ingreso;
use App\Models\LiquidacionSeguridadSocial;
use App\Models\PagoSeguridadSocial;
use App\Models\ParametroNormativo;
use App\Models\PerfilTributario;
use App\Models\Scopes\DelUsuarioAutenticado;
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

    /** RF-03, RF-05, RF-07: Alerta del tope legal de 25 SMMLV */
    public const AVISO_TOPE_25_SMMLV = 'Has alcanzado el tope legal máximo de cotización de 25 SMMLV ($ %s). Tu base de cotización (IBC) se limita a este valor, por lo que el excedente de $ %s queda exento de aportes a salud, pensión y ARL.';

    public const AVISO_CUPO_COPAL_LABORAL = 'Tu contrato laboral cubre el tope máximo legal de 25 SMMLV ($ %s). Tus actividades independientes tienen un cupo disponible de $ 0 y están 100%% EXENTAS de cotizar en este mes.';

    public const AVISO_FSP_20_SMMLV = 'El IBC consolidado total supera los 20 SMMLV ($ %s), aplicando la tarifa máxima del 2,00%% del Fondo de Solidaridad Pensional (FSP).';

    public const AVISO_PARAFISCALES_SIN_TOPE = 'Los aportes parafiscales (Caja de Compensación, SENA e ICBF) se liquidan sobre el 100%% de la nómina salarial devengada (o 70%% en salario integral), omitiendo el límite de 25 SMMLV (RF-11).';

    public const AVISO_NO_OBLIGADO = 'Tus ingresos del mes son inferiores a 1 salario mínimo, así que no estás obligado a pagar seguridad social como independiente.';

    public const AVISO_IBC_MINIMO = 'Después de los descuentos, la base de cotización queda por debajo de 1 salario mínimo; por eso se cotiza sobre el salario mínimo.';

    public const OCUPACION_RENTISTA = 'Rentista de capital';

    /**
     * Porcentaje legal/UGPP de presunción de costos para Rentistas de capital (28,08%).
     */
    public const PORCENTAJE_PRESUNCION_COSTOS_RENTISTA = '28.08';

    public const PORCENTAJE_PRESUNCION_COSTOS_RENTISTA_HASTA_ABRIL_2026 = '27.50';

    public const PORCENTAJE_PRESUNCION_COSTOS_RENTISTA_DESDE_MAYO_2026 = '28.08';

    /**
     * Retorna el porcentaje de presunción de costos aplicable a rentistas de capital para un periodo dado.
     */
    public static function porcentajePresuncionRentista(?string $periodo = null): string
    {
        return EsquemaCostoPresunto::porcentajePara(EsquemaCostoPresunto::ACTIVIDAD_CAPITAL, $periodo);
    }

    /** RF-033: ocupaciones del perfil (RF-008) que cotizan como independientes. */
    public const OCUPACIONES_INDEPENDIENTE = [
        'Independiente por cuenta propia',
        'Contratista por prestación de servicios',
        'Rentista de capital',
    ];

    public const OCUPACION_ASALARIADO = 'Asalariado';

    /** RN-033.6 */
    public const OCUPACION_PENSIONADO = 'Pensionado';

    /** CST art. 132: mínimo del salario integral, en SMMLV (10 + 30 % prestacional). */
    public const SMMLV_SALARIO_INTEGRAL_MINIMO = '13';

    /** RN-033.5: cada aporte se redondea al múltiplo de $ 100 superior (Decreto 1990 de 2016). */
    public const MULTIPLO_APORTE = 100;

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

    /**
     * H24: último periodo que se puede consultar, liquidar o pagar: el mes siguiente al actual (la PILA se
     * paga mes vencido, así que en noviembre ya se prepara diciembre). La misma regla en todos los caminos.
     */
    public static function periodoMaximo(?CarbonImmutable $hoy = null): CarbonImmutable
    {
        return ($hoy ?? CarbonImmutable::today())->startOfMonth()->addMonthNoOverflow();
    }

    public static function mensajePeriodoMaximo(?CarbonImmutable $hoy = null): string
    {
        return 'El periodo no puede ser posterior a '.self::periodoMaximo($hoy)->format('Y-m').' (el mes siguiente al actual).';
    }

    /** Regla de validación (closure) con el límite único y el mensaje único del periodo. */
    public static function validarPeriodoMaximo(): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $falla) {
            if (is_string($valor)
                && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)
                && self::inicioDelPeriodo($valor)->gt(self::periodoMaximo())) {
                $falla(self::mensajePeriodoMaximo());
            }
        };
    }

    /**
     * Mes vencido: el periodo que se cotiza se paga con los ingresos del mes
     * anterior (cotizar noviembre usa los ingresos de octubre).
     */
    public static function mesDeIngresos(CarbonImmutable $inicioPeriodo): CarbonImmutable
    {
        return $inicioPeriodo->startOfMonth()->subMonthNoOverflow();
    }

    /**
     * Ingresos confirmados que respaldan el periodo a cotizar: los del mes
     * anterior (mes vencido), de la modalidad elegida. Independiente/contratista
     * usa rentas de trabajo y rentista de capital usa únicamente rentas de
     * capital; nunca se mezclan ambas cédulas.
     */
    public static function ingresosParaSeguridadSocial(
        string $usuarioId,
        CarbonImmutable $inicioPeriodo,
        string $tipoCotizante,
        string $smmlv
    ): array {
        $clasificacion = strtoupper($tipoCotizante) === 'RENTISTA_CAPITAL'
            ? 'RENTAS_CAPITAL'
            : 'RENTAS_TRABAJO';
        $inicioMes = self::mesDeIngresos($inicioPeriodo);

        $ingresos = Ingreso::query()
            ->where('usuario_id', $usuarioId)
            ->confirmados()
            ->where('clasificacion_tributaria', $clasificacion)
            ->whereBetween('fecha_recepcion', [$inicioMes->toDateString(), $inicioMes->endOfMonth()->toDateString()])
            ->whereDoesntHave('fuente.tipoFuente', self::deFuenteSalario())
            ->with('fuente:id,nombre')
            ->orderBy('fecha_recepcion')
            ->get();

        $total = bcadd((string) $ingresos->sum('monto_bruto_cop'), '0', 2);
        $esRentista = strtoupper($tipoCotizante) === 'RENTISTA_CAPITAL';
        $periodoCotizado = $inicioPeriodo->format('Y-m');
        $porcentajeRentista = $esRentista ? self::porcentajePresuncionRentista($periodoCotizado) : null;
        $deduccion = '0.00';

        if ($esRentista) {
            $deduccion = self::redondearArriba(self::porcentaje($total, $porcentajeRentista), 1);
            $neto = bcsub($total, $deduccion, 2);
            if (bccomp($neto, '0', 2) < 0) {
                $neto = '0.00';
            }
            $obligado = self::estaObligadoACotizar($neto, $smmlv);
        } else {
            $obligado = self::estaObligadoACotizar($total, $smmlv);
        }

        return [
            'clasificacion' => $clasificacion,
            'mes_ingresos' => $inicioMes->format('Y-m'),
            'total' => $total,
            'cantidad' => $ingresos->count(),
            'smmlv' => bcadd($smmlv, '0', 2),
            'obligado' => $obligado,
            'supera_smmlv' => $obligado,
            'porcentaje_presuncion' => $porcentajeRentista,
            'deduccion_presuncion' => $deduccion,
            'ingresos' => $ingresos->map(fn ($ingreso) => [
                'id' => $ingreso->id,
                'fecha' => $ingreso->fecha_recepcion->format('d/m/Y'),
                'fuente' => $ingreso->fuente?->nombre ?? 'Sin fuente',
                'monto' => (string) $ingreso->monto_bruto_cop,
            ])->values()->all(),
        ];
    }

    /**
     * El independiente o rentista de capital está obligado a cotizar cuando sus
     * ingresos del mes son de al menos 1 SMMLV. Por debajo no hay obligación.
     */
    public static function estaObligadoACotizar(string $ingresos, string $smmlv): bool
    {
        return bccomp($ingresos, $smmlv, 2) >= 0;
    }

    /**
     * Resultado para quien no está obligado a cotizar: conserva los datos de
     * ingresos del cálculo, pero sin base ni aportes a pagar.
     */
    public static function sinObligacion(array $calculo): array
    {
        foreach (['ibc', 'salud', 'pension', 'fsp', 'arl', 'total'] as $campo) {
            $calculo[$campo] = '0.00';
        }
        $calculo['aplica_fsp'] = false;
        $calculo['obligado'] = false;
        $calculo['avisos'] = [self::AVISO_NO_OBLIGADO];

        return $calculo;
    }

    /**
     * Consulta los ingresos del periodo cotizado y calcula los aportes sugeridos
     * para el pago PILA, según sea prestación de servicios o rentista de capital.
     *
     * Si es rentista de capital, permite descontar los gastos asociados de los ingresos
     * brutos antes de calcular el 40 % de IBC y aportes.
     *
     * H24: como PILA se paga mes vencido, el periodo cotizado M se liquida con los ingresos
     * confirmados de M-1 (la misma regla de ingresosParaSeguridadSocial y de la liquidación).
     */
    public static function consultarIngresosPeriodoCotizado(
        string $usuarioId,
        string $periodo,
        string $tipoCotizante = 'PRESTACION_SERVICIOS',
        string $gastos = '0'
    ): array {
        $inicioPeriodo = self::inicioDelPeriodo($periodo);
        $inicioMes = self::mesDeIngresos($inicioPeriodo);
        $finMes = $inicioMes->endOfMonth();
        $mesIngresos = $inicioMes->format('Y-m');

        $esRentista = in_array(strtoupper($tipoCotizante), ['RENTISTA_CAPITAL', 'RENTISTA'], true);

        $query = Ingreso::query()
            ->where('usuario_id', $usuarioId)
            ->confirmados()
            ->whereBetween('fecha_recepcion', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->whereDoesntHave('fuente.tipoFuente', self::deFuenteSalario());

        $clasificacion = $esRentista ? 'RENTAS_CAPITAL' : 'RENTAS_TRABAJO';
        $query->where('clasificacion_tributaria', $clasificacion);

        $ingresos = $query->with('fuente:id,nombre')
            ->orderBy('fecha_recepcion')
            ->get();

        $suma = $ingresos->sum('monto_bruto_cop');
        $totalIngresos = bcadd((string) $suma, '0', 2);
        $tieneIngresos = bccomp($totalIngresos, '0', 2) > 0;
        $parametroPeriodo = self::parametrosDe($inicioPeriodo->year);

        if (! $tieneIngresos) {
            $clase = $esRentista ? 'rentas de capital o trabajo' : 'rentas de trabajo';
            $msg = "Para cotizar {$periodo} se toman los ingresos de {$mesIngresos} (mes vencido), y no hay ingresos confirmados de {$clase} en ese mes. Puedes registrarlos en el módulo de Ingresos o diligenciar los valores de aportes manualmente.";

            return [
                'periodo' => $periodo,
                'tipo_cotizante' => $esRentista ? 'RENTISTA_CAPITAL' : 'PRESTACION_SERVICIOS',
                'tiene_ingresos' => false,
                'cantidad_ingresos' => 0,
                'total_ingresos' => '0.00',
                'total_ingresos_brutos' => '0.00',
                'smmlv' => (string) $parametroPeriodo->valor_smmlv,
                'obligacion_por_ingresos' => false,
                'gastos_asociados' => bcadd($gastos ?: '0', '0', 2),
                'ingreso_neto' => '0.00',
                'ingresos' => [],
                'calculo' => null,
                'liquidacion' => null,
                'mensaje' => $msg,
            ];
        }

        $parametro = $parametroPeriodo;
        $perfil = self::perfilDelPeriodo($usuarioId, $inicioPeriodo);

        $liquidacion = LiquidacionSeguridadSocial::where('usuario_id', $usuarioId)
            ->where('periodo', $periodo)
            ->first();

        if ($esRentista) {
            $calculo = self::calcularRentistaCapital($totalIngresos, $gastos, $parametro, $perfil);
            $superaSmmlv = self::estaObligadoACotizar($calculo['ingreso_neto'], (string) $parametroPeriodo->valor_smmlv);
        } else {
            $calculo = self::calcularIndependiente($totalIngresos, $parametro, $perfil);
            $superaSmmlv = self::estaObligadoACotizar($totalIngresos, (string) $parametroPeriodo->valor_smmlv);
        }

        $listaIngresos = $ingresos->map(fn ($ing) => [
            'id' => $ing->id,
            'fecha' => $ing->fecha_recepcion->format('d/m/Y'),
            'fuente' => $ing->fuente?->nombre ?? 'Sin fuente',
            'monto' => (string) $ing->monto_bruto_cop,
            'descripcion' => $ing->descripcion ?? '',
            'clasificacion' => $ing->clasificacion_tributaria,
        ])->values()->all();

        $tipoMensaje = $esRentista ? 'rentas de capital o trabajo' : 'rentas de trabajo';

        return [
            'periodo' => $periodo,
            'tipo_cotizante' => $esRentista ? 'RENTISTA_CAPITAL' : 'PRESTACION_SERVICIOS',
            'tiene_ingresos' => true,
            'cantidad_ingresos' => $ingresos->count(),
            'total_ingresos' => $totalIngresos,
            'total_ingresos_brutos' => $totalIngresos,
            'smmlv' => (string) $parametro->valor_smmlv,
            'obligacion_por_ingresos' => $superaSmmlv,
            'gastos_asociados' => $esRentista ? bcadd($gastos ?: '0', '0', 2) : '0.00',
            'ingreso_neto' => $esRentista ? ($calculo['ingreso_neto'] ?? $totalIngresos) : $totalIngresos,
            'ingresos' => $listaIngresos,
            'calculo' => [
                'tipo_cotizante' => $calculo['tipo_cotizante'] ?? 'PRESTACION_SERVICIOS',
                'ingresos_brutos' => $totalIngresos,
                'gastos_asociados' => $calculo['gastos_asociados'] ?? '0.00',
                'ingreso_neto' => $calculo['ingreso_neto'] ?? $totalIngresos,
                'ingreso_base' => $calculo['ingreso_base'],
                'ibc' => $calculo['ibc'],
                'salud' => $calculo['salud'],
                'pension' => $calculo['pension'],
                'fsp' => $calculo['fsp'],
                'arl' => $calculo['arl'],
                'total' => $calculo['total'],
                'porcentaje_salud' => $calculo['parametros_usados']['tarifa_salud'] ?? null,
                'porcentaje_pension' => $calculo['parametros_usados']['tarifa_pension'] ?? null,
                'porcentaje_fsp' => $calculo['parametros_usados']['tarifa_fsp'] ?? null,
                'porcentaje_arl' => $calculo['parametros_usados']['tarifa_arl'] ?? null,
                'avisos' => $calculo['avisos'] ?? [],
            ],
            'liquidacion' => $liquidacion ? [
                'id' => $liquidacion->id,
                'salud' => (string) $liquidacion->salud,
                'pension' => (string) $liquidacion->pension,
                'fsp' => (string) $liquidacion->fsp,
                'arl' => (string) $liquidacion->arl,
                'total' => (string) $liquidacion->total,
            ] : null,
            'mensaje' => "Para cotizar {$periodo} se toman los ingresos de {$mesIngresos} (mes vencido): se encontraron {$ingresos->count()} ingreso(s) de {$tipoMensaje}.",
        ];
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
        $ibcSinAcotar = self::porcentaje($ingresoBase, (string) $parametro->porc_ibc_indep);
        $ibcMinimo = bcmul($smmlv, (string) $parametro->ibc_min_smmlv, 2);
        $ibcMaximo = bcmul($smmlv, (string) $parametro->ibc_max_smmlv, 2);

        $superoTope25 = bccomp($ibcSinAcotar, $ibcMaximo, 2) > 0;
        $excedenteTope = $superoTope25 ? bcsub($ibcSinAcotar, $ibcMaximo, 2) : '0.00';

        $ibc = self::acotar(
            $ibcSinAcotar,
            $ibcMinimo,
            $ibcMaximo
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

        $avisos = match (true) {
            bccomp($ingresoBase, $smmlv, 2) < 0 => [self::AVISO_BAJO_SMMLV],
            bccomp($ibcSinAcotar, $ibcMinimo, 2) < 0 => [self::AVISO_IBC_MINIMO],
            default => [],
        };

        if ($superoTope25) {
            $avisos[] = sprintf(
                self::AVISO_TOPE_25_SMMLV,
                number_format((float) $ibcMaximo, 0, ',', '.'),
                number_format((float) $excedenteTope, 0, ',', '.')
            );
        }

        return [
            'tipo_cotizante' => 'INDEPENDIENTE',
            'monto_contrato' => bcadd($ingresoBase, '0', 2),
            'ingreso_base' => bcadd($ingresoBase, '0', 2),
            'ibc' => $ibc,
            'ibc_max_smmlv' => $ibcMaximo,
            'supero_tope_25_smmlv' => $superoTope25,
            'tope_25_smmlv_alcanzado' => $superoTope25,
            'excedente_desafectado_tope' => $excedenteTope,
            'porcentaje_salud' => (string) $parametro->tarifa_salud_indep,
            'porcentaje_pension' => $pensionado ? '0.00' : (string) $parametro->tarifa_pension_indep,
            'porcentaje_fsp' => $tarifaFsp,
            'porcentaje_arl' => $tarifaArl ?? '0.00',
            'aplica_fsp' => bccomp($tarifaFsp, '0', 2) > 0,
            ...$aportes,
            // RN-033.5: total = suma de los aportes redondeados.
            'total' => array_reduce($aportes, fn ($total, $aporte) => bcadd($total, $aporte, 2), '0.00'),
            'avisos' => $avisos,
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
     * Cálculo de aportes a seguridad social para Rentista de Capital.
     *
     * A diferencia del contratista o independiente por prestación de servicios, el
     * rentista de capital deduce la presunción de costos (28,08%) o gastos procedentes
     * antes de calcular el IBC (40% de la base neta).
     *
     * Base neta = max(0, Ingresos brutos − Presunción de costos / Gastos)
     * IBC = 40 % de la base neta, acotado entre 1 y 25 SMMLV al peso superior.
     */
    public static function calcularRentistaCapital(
        string $ingresosBrutos,
        string $gastos,
        ParametroNormativo $parametro,
        ?PerfilTributario $perfil,
        ?string $porcentajePresuncion = null
    ): array {
        $brutos = bcadd($ingresosBrutos, '0', 2);
        $usarPresuncion = $porcentajePresuncion !== null;

        if ($usarPresuncion) {
            $deduccionCostos = self::porcentaje($brutos, $porcentajePresuncion);
            $deduccionCostos = self::redondearArriba($deduccionCostos, 1);
        } else {
            $deduccionCostos = bcadd($gastos ?: '0', '0', 2);
        }

        $baseNeta = bcsub($brutos, $deduccionCostos, 2);
        if (bccomp($baseNeta, '0', 2) < 0) {
            $baseNeta = '0.00';
        }

        $calculo = self::calcularIndependiente($baseNeta, $parametro, $perfil);
        $smmlv = (string) $parametro->valor_smmlv;

        return [
            'tipo_cotizante' => 'RENTISTA_CAPITAL',
            'monto_contrato' => $brutos,
            'ingresos_brutos' => $brutos,
            'porcentaje_presuncion_costos' => $usarPresuncion ? $porcentajePresuncion : null,
            'presuncion_costos' => $usarPresuncion ? $deduccionCostos : '0.00',
            'gastos_asociados' => $deduccionCostos,
            'ingreso_neto' => $baseNeta,
            'ingreso_base' => $baseNeta,
            'ibc' => $calculo['ibc'],
            'ibc_max_smmlv' => $calculo['ibc_max_smmlv'] ?? bcmul($smmlv, (string) $parametro->ibc_max_smmlv, 2),
            'supero_tope_25_smmlv' => $calculo['supero_tope_25_smmlv'] ?? false,
            'tope_25_smmlv_alcanzado' => $calculo['tope_25_smmlv_alcanzado'] ?? false,
            'excedente_desafectado_tope' => $calculo['excedente_desafectado_tope'] ?? '0.00',
            'porcentaje_salud' => $calculo['porcentaje_salud'],
            'salud' => $calculo['salud'],
            'porcentaje_pension' => $calculo['porcentaje_pension'],
            'pension' => $calculo['pension'],
            'porcentaje_fsp' => $calculo['porcentaje_fsp'],
            'fsp' => $calculo['fsp'],
            'porcentaje_arl' => $calculo['porcentaje_arl'],
            'arl' => $calculo['arl'],
            'total' => $calculo['total'],
            'aplica_fsp' => $calculo['aplica_fsp'],
            // Para rentista de capital, la obligación de cotizar depende del ingreso neto tras la deducción
            // de costos (o gastos). Si la base neta es inferior a 1 SMMLV, no existe obligación
            // (cálculo voluntario sobre 1 SMMLV). Si la base neta es >= 1 SMMLV pero su 40% queda por debajo
            // de 1 SMMLV, se cotiza sobre 1 SMMLV.
            'avisos' => (function () use ($baseNeta, $smmlv, $parametro, $calculo) {
                $avisos = match (true) {
                    bccomp($baseNeta, $smmlv, 2) < 0 => [self::AVISO_BAJO_SMMLV],
                    bccomp(self::porcentaje($baseNeta, (string) $parametro->porc_ibc_indep), bcmul($smmlv, (string) $parametro->ibc_min_smmlv, 2), 2) < 0 => [self::AVISO_IBC_MINIMO],
                    default => [],
                };
                if ($calculo['supero_tope_25_smmlv'] ?? false) {
                    $avisos[] = sprintf(
                        self::AVISO_TOPE_25_SMMLV,
                        number_format((float) ($calculo['ibc_max_smmlv'] ?? bcmul($smmlv, (string) $parametro->ibc_max_smmlv, 2)), 0, ',', '.'),
                        number_format((float) ($calculo['excedente_desafectado_tope'] ?? '0'), 0, ',', '.')
                    );
                }

                return $avisos;
            })(),
            'parametros_usados' => array_merge($calculo['parametros_usados'], [
                'tipo_cotizante' => 'Rentista de capital',
                'ingresos_brutos' => $brutos,
                'porcentaje_presuncion_costos' => $usarPresuncion ? $porcentajePresuncion : null,
                'presuncion_costos' => $usarPresuncion ? $deduccionCostos : '0.00',
                'gastos_asociados' => $deduccionCostos,
                'ingreso_neto' => $baseNeta,
            ]),
            'aviso_legal' => self::AVISO_LEGAL,
        ];
    }

    /**
     * RF-02 & RF-04: Cálculo de IBC individual por actividad económica para independientes.
     */
    public static function calcularActividadIndependiente(
        string $ingresoBruto,
        string $codigoActividad,
        ?string $costosReales = null,
        bool $usarCostosReales = false,
        ?ParametroNormativo $parametro = null,
        ?string $periodo = null
    ): array {
        $brutos = bcadd($ingresoBruto, '0', 2);

        if ($usarCostosReales && $costosReales !== null && bccomp($costosReales, '0', 2) > 0) {
            $deduccionCostos = bcadd($costosReales, '0', 2);
            $porcentajePresuncion = null;
            $modalidadCosto = 'REALES';
        } else {
            $porcentajePresuncion = EsquemaCostoPresunto::porcentajePara($codigoActividad, $periodo);
            $deduccionCostos = self::porcentaje($brutos, $porcentajePresuncion);
            $deduccionCostos = self::redondearArriba($deduccionCostos, 1);
            $modalidadCosto = 'PRESUNCION_UGPP';
        }

        $baseNeta = bcsub($brutos, $deduccionCostos, 2);
        if (bccomp($baseNeta, '0', 2) < 0) {
            $baseNeta = '0.00';
        }

        // RF-04: IBC preliminar de la actividad = 40 % sobre el ingreso neto
        $porcIbc = $parametro ? (string) $parametro->porc_ibc_indep : '40.00';
        $ibcPreliminar = self::porcentaje($baseNeta, $porcIbc);
        $ibcPreliminar = self::redondearArriba($ibcPreliminar, 1);

        $esServicios = in_array(strtoupper($codigoActividad), [
            EsquemaCostoPresunto::ACTIVIDAD_SERVICIOS,
            'PRESTACION_SERVICIOS',
            'SERVICIOS',
            'HONORARIOS',
        ], true);

        return [
            'codigo_actividad' => $codigoActividad,
            'ingreso_bruto' => $brutos,
            'modalidad_costo' => $modalidadCosto,
            'porcentaje_presuncion' => $porcentajePresuncion,
            'costos_aplicados' => $deduccionCostos,
            'ingreso_neto' => $baseNeta,
            'porc_ibc' => $porcIbc,
            'ibc_preliminar' => $ibcPreliminar,
            'es_servicios' => $esServicios,
        ];
    }

    /**
     * RF-01 a RF-11: Motor de cálculo y consolidación multi-ingreso con control
     * del tope de 25 SMMLV ($43.772.625), prelación laboral, deducción UGPP,
     * ARL diferenciado y parafiscales sin tope.
     */
    public static function consolidarMultiIngreso(
        array $datos,
        ?ParametroNormativo $parametro = null,
        ?PerfilTributario $perfil = null
    ): array {
        $periodo = $datos['periodo'] ?? CarbonImmutable::today()->format('Y-m');
        $inicio = self::inicioDelPeriodo($periodo);
        $parametro = $parametro ?? self::parametrosDe($inicio->year);

        $smmlv = (string) $parametro->valor_smmlv;
        $tope25 = bcmul($smmlv, (string) $parametro->ibc_max_smmlv, 2);
        $piso1 = bcmul($smmlv, (string) $parametro->ibc_min_smmlv, 2);
        $umbral20 = bcmul($smmlv, '20.00', 2);

        // 1. COMPONENTE LABORAL (RF-03)
        $salarioRaw = self::limpiarMonto((string) ($datos['salario'] ?? '0'));
        $esIntegral = (bool) ($datos['es_salario_integral'] ?? false);
        $tieneLaboral = bccomp($salarioRaw, '0', 2) > 0;
        $retencionFuente = self::limpiarMonto((string) ($datos['retencion_fuente'] ?? '0'));

        $claseArlLaboral = (int) ($datos['clase_riesgo_arl_laboral'] ?? ($datos['clase_riesgo_arl'] ?? ($perfil?->clase_riesgo_arl ?? 1)));
        $claseArlServicios = (int) ($datos['clase_riesgo_arl_independiente'] ?? ($datos['clase_riesgo_arl'] ?? ($perfil?->clase_riesgo_arl ?? 1)));
        $exoneradoParafiscales = isset($datos['exonerado_parafiscales'])
            ? filter_var($datos['exonerado_parafiscales'], FILTER_VALIDATE_BOOLEAN)
            : true;

        if ($tieneLaboral) {
            self::validarSalarioIntegral($salarioRaw, $esIntegral, $parametro);
            $baseLaboral = $esIntegral ? self::porcentaje($salarioRaw, (string) $parametro->porc_ibc_integral) : $salarioRaw;
            if (bccomp($baseLaboral, $tope25, 2) > 0) {
                $ibcLaboral = $tope25;
                $excedenteLaboral = bcsub($baseLaboral, $tope25, 2);
                $topeLaboralAlcanzado = true;
            } else {
                $ibcLaboral = bccomp($baseLaboral, $piso1, 2) < 0 ? $piso1 : $baseLaboral;
                $excedenteLaboral = '0.00';
                $topeLaboralAlcanzado = false;
            }
        } else {
            $baseLaboral = '0.00';
            $ibcLaboral = '0.00';
            $excedenteLaboral = '0.00';
            $topeLaboralAlcanzado = false;
        }

        // 2. CUPO DISPONIBLE PARA INDEPENDIENTES (RF-07)
        // Cupo Disponible = max(0, IBC Máximo - IBC Asalariado)
        $cupoDisponible = bcsub($tope25, $ibcLaboral, 2);
        if (bccomp($cupoDisponible, '0', 2) < 0) {
            $cupoDisponible = '0.00';
        }

        // 3. COMPONENTE INDEPENDIENTE Y RENTISTA (RF-04, RF-06)
        $actividadesIn = $datos['actividades'] ?? $datos['actividades_independientes'] ?? [];
        $actividadesCalculadas = [];
        $totalIbcServiciosPreliminar = '0.00';
        $totalIbcCapitalPreliminar = '0.00';
        $totalIngresoBrutoIndep = '0.00';
        $totalCostosDeducidosIndep = '0.00';
        $totalIngresoNetoIndep = '0.00';

        // Soporte de compatibilidad con inputs planos tradicionales
        if (empty($actividadesIn) && isset($datos['monto_contrato'])) {
            $montoTrad = self::limpiarMonto((string) $datos['monto_contrato']);
            if (bccomp($montoTrad, '0', 2) > 0) {
                $tipoCot = strtoupper((string) ($datos['tipo_cotizante'] ?? 'INDEPENDIENTE'));
                $esRent = in_array($tipoCot, ['RENTISTA_CAPITAL', 'RENTISTA'], true);
                $actividadesIn[] = [
                    'codigo_actividad' => $esRent ? EsquemaCostoPresunto::ACTIVIDAD_CAPITAL : EsquemaCostoPresunto::ACTIVIDAD_SERVICIOS,
                    'ingreso_bruto' => $montoTrad,
                    'costos_reales' => $datos['gastos'] ?? '0',
                    'usar_costos_reales' => ! empty($datos['gastos']) && bccomp((string) $datos['gastos'], '0', 2) > 0,
                ];
            }
        }

        foreach ($actividadesIn as $act) {
            $montoAct = self::limpiarMonto((string) ($act['ingreso_bruto'] ?? ($act['monto_bruto'] ?? '0')));
            if (bccomp($montoAct, '0', 2) <= 0) {
                continue;
            }
            $costosAct = self::limpiarMonto((string) ($act['costos_reales'] ?? '0'));
            $usarReales = filter_var($act['usar_costos_reales'] ?? false, FILTER_VALIDATE_BOOLEAN)
                || (bccomp($costosAct, '0', 2) > 0);
            $codAct = $act['codigo_actividad'] ?? ($act['tipo'] ?? EsquemaCostoPresunto::ACTIVIDAD_SERVICIOS);

            $calcAct = self::calcularActividadIndependiente($montoAct, $codAct, $costosAct, $usarReales, $parametro, $periodo);
            $actividadesCalculadas[] = $calcAct;

            $totalIngresoBrutoIndep = bcadd($totalIngresoBrutoIndep, $calcAct['ingreso_bruto'], 2);
            $totalCostosDeducidosIndep = bcadd($totalCostosDeducidosIndep, $calcAct['costos_aplicados'], 2);
            $totalIngresoNetoIndep = bcadd($totalIngresoNetoIndep, $calcAct['ingreso_neto'], 2);

            if ($calcAct['es_servicios']) {
                $totalIbcServiciosPreliminar = bcadd($totalIbcServiciosPreliminar, $calcAct['ibc_preliminar'], 2);
            } else {
                $totalIbcCapitalPreliminar = bcadd($totalIbcCapitalPreliminar, $calcAct['ibc_preliminar'], 2);
            }
        }

        $sumaIbcIndepPreliminar = bcadd($totalIbcServiciosPreliminar, $totalIbcCapitalPreliminar, 2);
        $tieneIndependiente = bccomp($sumaIbcIndepPreliminar, '0', 2) > 0;

        $claseArlServicios = (int) ($datos['clase_riesgo_arl_servicios'] ?? ($perfil?->clase_riesgo_arl ?? 1));

        if ($tieneIndependiente) {
            if (bccomp($cupoDisponible, '0', 2) === 0) {
                $ibcIndepCotizado = '0.00';
                $excedenteIndep = $sumaIbcIndepPreliminar;
                $topeIndepAlcanzado = true;
                $ibcServiciosCotizado = '0.00';
                $ibcCapitalCotizado = '0.00';
            } elseif (bccomp($sumaIbcIndepPreliminar, $cupoDisponible, 2) > 0) {
                $ibcIndepCotizado = $cupoDisponible;
                $excedenteIndep = bcsub($sumaIbcIndepPreliminar, $cupoDisponible, 2);
                $topeIndepAlcanzado = true;

                // RF-10: Asignar cupo preferencial a servicios para cálculo de ARL
                if (bccomp($totalIbcServiciosPreliminar, $cupoDisponible, 2) >= 0) {
                    $ibcServiciosCotizado = $cupoDisponible;
                    $ibcCapitalCotizado = '0.00';
                } else {
                    $ibcServiciosCotizado = $totalIbcServiciosPreliminar;
                    $ibcCapitalCotizado = bcsub($cupoDisponible, $ibcServiciosCotizado, 2);
                }
            } else {
                $ibcIndepCotizado = $sumaIbcIndepPreliminar;
                $excedenteIndep = '0.00';
                $topeIndepAlcanzado = false;
                $ibcServiciosCotizado = $totalIbcServiciosPreliminar;
                $ibcCapitalCotizado = $totalIbcCapitalPreliminar;

                // Piso legal de 1 SMMLV si solo es independiente
                if (! $tieneLaboral && bccomp($ibcIndepCotizado, $piso1, 2) < 0) {
                    $ibcIndepCotizado = $piso1;
                }
            }
        } else {
            $ibcIndepCotizado = '0.00';
            $excedenteIndep = '0.00';
            $topeIndepAlcanzado = false;
            $ibcServiciosCotizado = '0.00';
            $ibcCapitalCotizado = '0.00';
        }

        // 4. CONSOLIDACIÓN GLOBAL DE 25 SMMLV (RF-05)
        $ibcConsolidadoTotal = bcadd($ibcLaboral, $ibcIndepCotizado, 2);
        $excedenteTotalExento = bcadd($excedenteLaboral, $excedenteIndep, 2);
        $superoTope25 = bccomp($excedenteTotalExento, '0', 2) > 0 || bccomp($ibcConsolidadoTotal, $tope25, 2) >= 0;

        // 5. FONDO DE SOLIDARIDAD PENSIONAL (RF-09)
        // Evaluar si el consolidado total supera 20 SMMLV
        $supera20Smmlv = bccomp($ibcConsolidadoTotal, $umbral20, 2) > 0;
        // RN-033.6: un Pensionado no paga pensión (propia ni patronal) ni FSP.
        $pensionado = self::tieneOcupacion($perfil, [self::OCUPACION_PENSIONADO]);
        $tarifaFsp = $pensionado
            ? '0'
            : ($supera20Smmlv ? '2.00' : self::tarifaFsp($ibcConsolidadoTotal, $smmlv, $parametro));

        // 6. LIQUIDACIÓN LABORAL (TIPO E)
        if ($tieneLaboral) {
            $saludEmp = self::redondearArriba(self::porcentaje($ibcLaboral, (string) $parametro->tarifa_salud_emp), 1);
            $pensionEmp = $pensionado ? '0.00' : self::redondearArriba(self::porcentaje($ibcLaboral, (string) $parametro->tarifa_pension_emp), 1);
            $fspLaboral = self::redondearArriba(self::porcentaje($ibcLaboral, $tarifaFsp), 1);
            $totalAportesEmpleado = bcadd(bcadd($saludEmp, $pensionEmp, 2), $fspLaboral, 2);
            $salarioNetoEstimado = bcsub(bcsub($salarioRaw, $totalAportesEmpleado, 2), $retencionFuente, 2);
            if (bccomp($salarioNetoEstimado, '0', 2) < 0) {
                $salarioNetoEstimado = '0.00';
            }

            // Aportes Empleador
            $tarifaSaludPatronal = (string) ($parametro->tarifa_salud_patronal ?? '8.50');
            $tarifaPensionPatronal = (string) ($parametro->tarifa_pension_patronal ?? '12.00');
            $tarifaArlLab = self::tarifaArlPorClase($claseArlLaboral);

            $saludPatronal = self::redondearArriba(self::porcentaje($ibcLaboral, $tarifaSaludPatronal), 1);
            $pensionPatronal = $pensionado ? '0.00' : self::redondearArriba(self::porcentaje($ibcLaboral, $tarifaPensionPatronal), 1);
            $arlPatronal = self::redondearArriba(self::porcentaje($ibcLaboral, $tarifaArlLab), 1);

            // Parafiscales (RF-11: sobre nómina 100% u 70% integral OMITIENDO TOPE)
            $baseParafiscales = $baseLaboral;
            $tarifaCcf = (string) ($parametro->tarifa_parafiscales_ccf ?? '4.00');
            $tarifaSena = (string) ($parametro->tarifa_parafiscales_sena ?? '2.00');
            $tarifaIcbf = (string) ($parametro->tarifa_parafiscales_icbf ?? '3.00');

            $parafiscalCcf = self::redondearArriba(self::porcentaje($baseParafiscales, $tarifaCcf), 1);
            $parafiscalSena = $exoneradoParafiscales ? '0.00' : self::redondearArriba(self::porcentaje($baseParafiscales, $tarifaSena), 1);
            $parafiscalIcbf = $exoneradoParafiscales ? '0.00' : self::redondearArriba(self::porcentaje($baseParafiscales, $tarifaIcbf), 1);
            $totalParafiscales = bcadd(bcadd($parafiscalCcf, $parafiscalSena, 2), $parafiscalIcbf, 2);

            $totalAportesEmpleador = bcadd(bcadd(bcadd($saludPatronal, $pensionPatronal, 2), $arlPatronal, 2), $totalParafiscales, 2);
        } else {
            $saludEmp = '0.00';
            $pensionEmp = '0.00';
            $fspLaboral = '0.00';
            $totalAportesEmpleado = '0.00';
            $salarioNetoEstimado = '0.00';
            $tarifaSaludPatronal = '8.50';
            $tarifaPensionPatronal = '12.00';
            $tarifaArlLab = '0.000';
            $saludPatronal = '0.00';
            $pensionPatronal = '0.00';
            $arlPatronal = '0.00';
            $baseParafiscales = '0.00';
            $tarifaCcf = (string) ($parametro->tarifa_parafiscales_ccf ?? '4.00');
            $tarifaSena = (string) ($parametro->tarifa_parafiscales_sena ?? '2.00');
            $tarifaIcbf = (string) ($parametro->tarifa_parafiscales_icbf ?? '3.00');
            $parafiscalCcf = '0.00';
            $parafiscalSena = '0.00';
            $parafiscalIcbf = '0.00';
            $totalParafiscales = '0.00';
            $totalAportesEmpleador = '0.00';
        }

        // 7. LIQUIDACIÓN INDEPENDIENTE (TIPO I)
        if ($tieneIndependiente && bccomp($ibcIndepCotizado, '0', 2) > 0) {
            $saludIndep = self::redondearArriba(self::porcentaje($ibcIndepCotizado, (string) $parametro->tarifa_salud_indep), 1);
            $pensionIndep = $pensionado ? '0.00' : self::redondearArriba(self::porcentaje($ibcIndepCotizado, (string) $parametro->tarifa_pension_indep), 1);
            $fspIndep = self::redondearArriba(self::porcentaje($ibcIndepCotizado, $tarifaFsp), 1);

            // RF-10: ARL ÚNICAMENTE sobre la porción de prestación de servicios cotizada
            $tarifaArlServ = self::tarifaArlPorClase($claseArlServicios);
            $arlIndep = self::redondearArriba(self::porcentaje($ibcServiciosCotizado, $tarifaArlServ), 1);

            $totalAportesIndep = bcadd(bcadd(bcadd($saludIndep, $pensionIndep, 2), $fspIndep, 2), $arlIndep, 2);
        } else {
            $saludIndep = '0.00';
            $pensionIndep = '0.00';
            $fspIndep = '0.00';
            $tarifaArlServ = '0.000';
            $arlIndep = '0.00';
            $totalAportesIndep = '0.00';
        }

        // 8. AVISOS NORMATIVOS
        $avisos = [];
        if ($superoTope25) {
            $avisos[] = sprintf(
                self::AVISO_TOPE_25_SMMLV,
                number_format((float) $tope25, 0, ',', '.'),
                number_format((float) $excedenteTotalExento, 0, ',', '.')
            );
        }
        if ($topeLaboralAlcanzado && $tieneIndependiente) {
            $avisos[] = sprintf(
                self::AVISO_CUPO_COPAL_LABORAL,
                number_format((float) $tope25, 0, ',', '.')
            );
        }
        if ($tieneLaboral && $topeIndepAlcanzado && ! $topeLaboralAlcanzado) {
            $avisos[] = sprintf(
                'Tu contrato laboral consumió $ %s de IBC. Conforme a la prelación legal, tu cupo disponible para cotización independiente en este mes es de $ %s; el excedente independiente de $ %s queda legalmente EXENTO.',
                number_format((float) $ibcLaboral, 0, ',', '.'),
                number_format((float) $cupoDisponible, 0, ',', '.'),
                number_format((float) $excedenteIndep, 0, ',', '.')
            );
        }
        if ($supera20Smmlv && ! $pensionado) {
            $avisos[] = sprintf(
                self::AVISO_FSP_20_SMMLV,
                number_format((float) $umbral20, 0, ',', '.')
            );
        }
        if ($tieneLaboral) {
            $avisos[] = self::AVISO_PARAFISCALES_SIN_TOPE;
        }

        $laboral = [
            'tiene_laboral' => $tieneLaboral,
            'salario' => $salarioRaw,
            'es_salario_integral' => $esIntegral,
            'base_salarial' => $baseLaboral,
            'ibc_laboral' => $ibcLaboral,
            'excedente_exento' => $excedenteLaboral,
            'tope_alcanzado' => $topeLaboralAlcanzado,
            'retencion_fuente' => $retencionFuente,
            'salario_neto_estimado' => $salarioNetoEstimado,
            'trabajador' => [
                'tarifa_salud' => (string) $parametro->tarifa_salud_emp,
                'salud' => $saludEmp,
                'tarifa_pension' => (string) $parametro->tarifa_pension_emp,
                'pension' => $pensionEmp,
                'tarifa_fsp' => $tarifaFsp,
                'fsp' => $fspLaboral,
                'total_descuentos' => $totalAportesEmpleado,
            ],
            'empleador' => [
                'tarifa_salud' => $tarifaSaludPatronal,
                'salud' => $saludPatronal,
                'tarifa_pension' => $tarifaPensionPatronal,
                'pension' => $pensionPatronal,
                'clase_riesgo_arl' => $claseArlLaboral,
                'tarifa_arl' => $tarifaArlLab,
                'arl' => $arlPatronal,
                'subtotal_seguridad_social' => bcadd(bcadd($saludPatronal, $pensionPatronal, 2), $arlPatronal, 2),
                'parafiscales' => [
                    'base_nomina' => $baseParafiscales,
                    'omite_tope_25_smmlv' => true,
                    'exonerado_sena_icbf' => $exoneradoParafiscales,
                    'tarifa_ccf' => $tarifaCcf,
                    'ccf' => $parafiscalCcf,
                    'tarifa_sena' => $tarifaSena,
                    'sena' => $parafiscalSena,
                    'tarifa_icbf' => $tarifaIcbf,
                    'icbf' => $parafiscalIcbf,
                    'total' => $totalParafiscales,
                ],
                'total_costo' => $totalAportesEmpleador,
            ],
            'costo_total_nomina' => bcadd($salarioRaw, $totalAportesEmpleador, 2),
        ];

        $independiente = [
            'tiene_independiente' => $tieneIndependiente,
            'actividades' => $actividadesCalculadas,
            'total_ingresos_brutos' => $totalIngresoBrutoIndep,
            'total_costos_deducidos' => $totalCostosDeducidosIndep,
            'total_ingreso_neto' => $totalIngresoNetoIndep,
            'suma_ibc_preliminar' => $sumaIbcIndepPreliminar,
            'ibc_servicios_preliminar' => $totalIbcServiciosPreliminar,
            'ibc_capital_preliminar' => $totalIbcCapitalPreliminar,
            'ibc_cotizado' => $ibcIndepCotizado,
            'ibc_servicios_cotizado' => $ibcServiciosCotizado,
            'ibc_capital_cotizado' => $ibcCapitalCotizado,
            'excedente_exento' => $excedenteIndep,
            'tope_alcanzado' => $topeIndepAlcanzado,
            'aportes' => [
                'tarifa_salud' => (string) $parametro->tarifa_salud_indep,
                'salud' => $saludIndep,
                'tarifa_pension' => (string) $parametro->tarifa_pension_indep,
                'pension' => $pensionIndep,
                'tarifa_fsp' => $tarifaFsp,
                'fsp' => $fspIndep,
                'clase_riesgo_arl_servicios' => $claseArlServicios,
                'tarifa_arl' => $tarifaArlServ,
                'base_arl_servicios' => $ibcServiciosCotizado,
                'excluye_capital_de_arl' => true,
                'arl' => $arlIndep,
                'total' => $totalAportesIndep,
            ],
        ];

        return [
            'periodo' => $periodo,
            'anio_vigencia' => $parametro->anio_vigencia,
            'smmlv' => $smmlv,
            'ibc_min_smmlv' => $piso1,
            'ibc_max_smmlv' => $tope25,
            'supero_tope_25_smmlv' => $superoTope25,
            'excedente_total_exento' => $excedenteTotalExento,
            'ibc_consolidado_total' => $ibcConsolidadoTotal,
            'cupo_disponible_independiente' => $cupoDisponible,
            'ibc_laboral' => $ibcLaboral,
            'ibc_independiente_preliminar' => $sumaIbcIndepPreliminar,
            'ibc_independiente_aprobado' => $ibcIndepCotizado,
            'excedente_desafectado_tope' => $excedenteTotalExento,
            'tarifa_fsp_aplicada' => $tarifaFsp,
            'aplica_fsp_20_smmlv' => $supera20Smmlv && ! $pensionado,
            'laboral' => $laboral,
            'liquidacion_laboral_tipo_e' => $laboral,
            'independiente' => $independiente,
            'liquidacion_independiente_tipo_i' => [
                'total_independiente' => $totalAportesIndep,
                'ibc_arl_servicios' => $ibcServiciosCotizado,
                'arl' => $arlIndep,
                ...$independiente['aportes'],
            ],
            // Resumen Global
            'resumen' => [
                'ibc_consolidado_total' => $ibcConsolidadoTotal,
                'excedente_total_exento' => $excedenteTotalExento,
                'total_seguridad_social_cotizante' => bcadd($totalAportesEmpleado, $totalAportesIndep, 2),
                'total_seguridad_social_empleador' => $tieneLaboral ? bcadd(bcadd($saludPatronal, $pensionPatronal, 2), $arlPatronal, 2) : '0.00',
                'total_parafiscales' => $totalParafiscales,
                'total_general_aportes' => bcadd(bcadd($totalAportesEmpleado, $totalAportesIndep, 2), $totalAportesEmpleador, 2),
            ],
            'avisos' => $avisos,
            'aviso_legal' => self::AVISO_LEGAL,
        ];
    }

    /**
     * RF-034: aportes que el empleador descuenta del salario de un Asalariado y
     * salario neto, con la retención informada si la hay (RN-034.4).
     *
     * RN-033.5 (múltiplos de $ 100) es una regla de la PILA del independiente:
     * acá los valores se redondean a 2 decimales (RNG-02), como en CA-034.1.
     */
    /**
     * CST art. 132: el salario integral no puede ser inferior a 10 SMMLV más el
     * 30 % de factor prestacional, es decir 13 SMMLV. Un valor menor no es un
     * salario integral válido y se rechaza.
     */
    public static function validarSalarioIntegral(string $salario, bool $esIntegral, ParametroNormativo $parametro): void
    {
        if (! $esIntegral) {
            return;
        }

        $minimo = bcmul((string) $parametro->valor_smmlv, self::SMMLV_SALARIO_INTEGRAL_MINIMO, 2);
        if (bccomp($salario, $minimo, 2) < 0) {
            throw ValidationException::withMessages([
                'salario' => 'El salario integral no puede ser inferior a 13 SMMLV ($ '
                    .number_format((float) $minimo, 0, ',', '.')
                    .'): 10 salarios mínimos más el 30 % de factor prestacional. Si tu salario es menor, no es integral: márcalo como "No" en tu perfil.',
            ]);
        }
    }

    public static function calcularAsalariado(
        string $salario,
        bool $esIntegral,
        ParametroNormativo $parametro,
        ?string $retencion = null,
        bool $exoneradoParafiscales = true,
        int $claseRiesgoArl = 1,
        bool $pensionado = false
    ): array {
        self::validarSalarioIntegral($salario, $esIntegral, $parametro);

        $smmlv = (string) $parametro->valor_smmlv;
        $tope25 = bcmul($smmlv, (string) $parametro->ibc_max_smmlv, 2);

        // RN-034.2 / RF-03: IBC = salario ordinario 100 % (o 70 % del integral), acotado a 25 SMMLV.
        $base = $esIntegral ? self::porcentaje($salario, (string) $parametro->porc_ibc_integral) : $salario;

        $excedenteTope = '0.00';
        $topeAlcanzado = false;
        if (bccomp($base, $tope25, 2) > 0) {
            $ibc = IngresoService::redondear($tope25);
            $excedenteTope = bcsub($base, $tope25, 2);
            $topeAlcanzado = true;
        } else {
            $ibc = IngresoService::redondear(self::acotar(
                $base,
                bcmul($smmlv, (string) $parametro->ibc_min_smmlv, 2),
                $tope25
            ));
        }

        // RN-034.3: salud 4 %, pensión 4 % y FSP desde 4 SMMLV.
        // RN-033.6: un Pensionado no aporta a pensión ni a FSP (Ley 100 art. 17), ni él ni el empleador.
        $tarifaFsp = $pensionado ? '0' : self::tarifaFsp($ibc, $smmlv, $parametro);

        $aportes = [
            'salud' => IngresoService::redondear(self::porcentaje($ibc, (string) $parametro->tarifa_salud_emp)),
            'pension' => $pensionado
                ? '0.00'
                : IngresoService::redondear(self::porcentaje($ibc, (string) $parametro->tarifa_pension_emp)),
            'fsp' => IngresoService::redondear(self::porcentaje($ibc, $tarifaFsp)),
        ];

        $total = array_reduce($aportes, fn ($suma, $aporte) => bcadd($suma, $aporte, 2), '0.00');
        $netoSinRetencion = bcsub(bcadd($salario, '0', 2), $total, 2);

        // RN-034.4: la retención en la fuente la informa el usuario y es opcional.
        $retencion = bcadd($retencion ?? '0', '0', 2);

        // RF-01, RF-08, RF-09, RF-10: Aportes patronales del empleador
        $tarifaSaludPatronal = (string) ($parametro->tarifa_salud_patronal ?? '8.50');
        $tarifaPensionPatronal = $pensionado ? '0.00' : (string) ($parametro->tarifa_pension_patronal ?? '12.00');
        $tarifaArlPatronal = self::tarifaArlPorClase($claseRiesgoArl);

        $saludPatronal = IngresoService::redondear(self::porcentaje($ibc, $tarifaSaludPatronal));
        $pensionPatronal = $pensionado
            ? '0.00'
            : IngresoService::redondear(self::porcentaje($ibc, $tarifaPensionPatronal));
        $arlPatronal = IngresoService::redondear(self::porcentaje($ibc, $tarifaArlPatronal));

        // RF-11: Parafiscales calculados sobre nómina 100 % (o 70 % en integral), OMITIENDO el tope de 25 SMMLV
        $baseParafiscales = $base;
        $tarifaCcf = (string) ($parametro->tarifa_parafiscales_ccf ?? '4.00');
        $tarifaSena = (string) ($parametro->tarifa_parafiscales_sena ?? '2.00');
        $tarifaIcbf = (string) ($parametro->tarifa_parafiscales_icbf ?? '3.00');

        $ccf = IngresoService::redondear(self::porcentaje($baseParafiscales, $tarifaCcf));
        $sena = $exoneradoParafiscales ? '0.00' : IngresoService::redondear(self::porcentaje($baseParafiscales, $tarifaSena));
        $icbf = $exoneradoParafiscales ? '0.00' : IngresoService::redondear(self::porcentaje($baseParafiscales, $tarifaIcbf));
        $totalParafiscales = bcadd(bcadd($ccf, $sena, 2), $icbf, 2);
        $totalEmpleador = bcadd(bcadd(bcadd($saludPatronal, $pensionPatronal, 2), $arlPatronal, 2), $totalParafiscales, 2);

        $avisos = [];
        if ($topeAlcanzado) {
            $avisos[] = sprintf(
                self::AVISO_TOPE_25_SMMLV,
                number_format((float) $tope25, 0, ',', '.'),
                number_format((float) $excedenteTope, 0, ',', '.')
            );
        }

        return [
            'tipo_cotizante' => 'ASALARIADO',
            'salario' => bcadd($salario, '0', 2),
            'es_salario_integral' => $esIntegral,
            'base_salarial' => bcadd($base, '0', 2),
            'ibc' => $ibc,
            'ibc_max_smmlv' => $tope25,
            'tope_25_smmlv_alcanzado' => $topeAlcanzado,
            'supero_tope_25_smmlv' => $topeAlcanzado,
            'excedente_desafectado_tope' => $excedenteTope,
            'porcentaje_salud' => (string) $parametro->tarifa_salud_emp,
            'porcentaje_pension' => $pensionado ? '0.00' : (string) $parametro->tarifa_pension_emp,
            'porcentaje_fsp' => $tarifaFsp,
            'aplica_fsp' => bccomp($tarifaFsp, '0', 2) > 0,
            'pensionado' => $pensionado,
            ...$aportes,
            'total_aportes' => $total,
            // CA-034.1: "neto sin retención".
            'salario_neto_sin_retencion' => $netoSinRetencion,
            'retencion_fuente' => $retencion,
            // RN-034.4: salario − salud − pensión − FSP − retención informada.
            'salario_neto_estimado' => bcsub($netoSinRetencion, $retencion, 2),
            'desglose_patronal' => [
                'base_parafiscales' => $baseParafiscales,
                'salud_patronal' => $saludPatronal,
                'pension_patronal' => $pensionPatronal,
                'arl_patronal' => $arlPatronal,
                'parafiscales_ccf' => $ccf,
                'parafiscales_sena' => $sena,
                'parafiscales_icbf' => $icbf,
                'total_parafiscales' => $totalParafiscales,
                'total_empleador' => $totalEmpleador,
            ],
            'empleador' => [
                'porcentaje_salud' => $tarifaSaludPatronal,
                'salud' => $saludPatronal,
                'porcentaje_pension' => $tarifaPensionPatronal,
                'pension' => $pensionPatronal,
                'clase_riesgo_arl' => $claseRiesgoArl,
                'porcentaje_arl' => $tarifaArlPatronal,
                'arl' => $arlPatronal,
                'subtotal_seguridad_social' => bcadd(bcadd($saludPatronal, $pensionPatronal, 2), $arlPatronal, 2),
                'parafiscales' => [
                    'base_calculo' => $baseParafiscales,
                    'omite_tope_25_smmlv' => true,
                    'exonerado_sena_icbf' => $exoneradoParafiscales,
                    'tarifa_ccf' => $tarifaCcf,
                    'ccf' => $ccf,
                    'tarifa_sena' => $tarifaSena,
                    'sena' => $sena,
                    'tarifa_icbf' => $tarifaIcbf,
                    'icbf' => $icbf,
                    'total' => $totalParafiscales,
                ],
                'total_costo_empleador' => $totalEmpleador,
            ],
            'costo_total_empresa' => bcadd($salario, $totalEmpleador, 2),
            'avisos' => $avisos,
            'parametros_usados' => self::parametrosUsados($parametro, $ibc, [
                'porc_ibc' => $esIntegral ? (string) $parametro->porc_ibc_integral : '100.00',
                'tarifa_salud' => (string) $parametro->tarifa_salud_emp,
                'tarifa_pension' => $pensionado ? '0' : (string) $parametro->tarifa_pension_emp,
                'tarifa_fsp' => $tarifaFsp,
                'tarifa_salud_patronal' => $tarifaSaludPatronal,
                'tarifa_pension_patronal' => $tarifaPensionPatronal,
                'pensionado' => $pensionado,
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
        $fila = PagoSeguridadSocial::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
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
    public static function deFuenteSalario(): \Closure
    {
        return fn ($query) => $query->where(function ($tipo) {
            $tipo->where('nombre', 'Salario')
                ->orWhereHas('padre', fn ($padre) => $padre->where('nombre', 'Salario'));
        });
    }

    /**
     * RN-014.2: tarifa del FSP según el IBC expresado en SMMLV. Los límites
     * inferiores de 4 y 16 SMMLV son inclusivos ("igual o superior"), así que
     * 4,00 SMMLV ya aporta (RN-034.3) y por debajo de 4 la tarifa es 0 %
     * (RN-033.4). Entre 16 y 20 SMMLV el límite superior de cada tramo también
     * es inclusivo: exactamente 17, 18, 19 o 20 SMMLV queda en el tramo inferior.
     */
    public static function tarifaFsp(string $ibc, string $smmlv, ParametroNormativo $parametro): string
    {
        $comparacion = bccomp($ibc, bcmul($smmlv, '20', 2), 2);
        if ($comparacion > 0) {
            return (string) ($parametro->tarifa_fsp ?? '2.00');
        }
        if (bccomp($ibc, bcmul($smmlv, '4', 2), 2) < 0) {
            return '0';
        }

        $enSmmlv = bcdiv($ibc, $smmlv, 12);
        // Los 4 y 16 SMMLV inician el aporte base y el adicional, respectivamente.
        $incluyeDesde = bccomp($enSmmlv, '4', 12) === 0 || bccomp($enSmmlv, '16', 12) === 0;
        $tramo = $parametro->tarifasEscalonadas()
            ->where('tipo_tabla', 'FSP')
            ->where('rango_desde', $incluyeDesde ? '<=' : '<', $enSmmlv)
            ->where(fn ($rango) => $rango->whereNull('rango_hasta')->orWhere('rango_hasta', '>=', $enSmmlv))
            ->orderByDesc('rango_desde')
            ->first();

        if ($tramo === null) {
            throw ValidationException::withMessages([
                'periodo' => "Falta configurar el tramo del Fondo de Solidaridad Pensional para la vigencia {$parametro->anio_vigencia}.",
            ]);
        }

        return (string) $tramo->tarifa_porc;
    }

    /**
     * RF-10: Retorna la tarifa porcentual reglamentaria para cada clase de riesgo ARL (I a V).
     */
    public static function tarifaArlPorClase(int|string|null $clase): string
    {
        return match ((int) $clase) {
            1 => '0.522',
            2 => '1.044',
            3 => '2.436',
            4 => '4.350',
            5 => '6.960',
            default => '0.000',
        };
    }

    /**
     * Limpia un valor monetario recibido por query o post (con o sin separadores de miles).
     */
    public static function limpiarMonto(?string $monto): string
    {
        if ($monto === null) {
            return '0.00';
        }

        $monto = trim($monto);
        if ($monto === '') {
            return '0.00';
        }

        // Remover espacios y caracteres excepto digitos, puntos y comas
        $monto = preg_replace('/[^\d.,]/', '', $monto);

        // Si contiene coma y punto, normalizar según la posición
        if (str_contains($monto, ',') && str_contains($monto, '.')) {
            if (strrpos($monto, ',') > strrpos($monto, '.')) {
                $monto = str_replace('.', '', $monto);
                $monto = str_replace(',', '.', $monto);
            } else {
                $monto = str_replace(',', '', $monto);
            }
        } elseif (str_contains($monto, ',')) {
            $partes = explode(',', $monto);
            if (count($partes) === 2 && strlen($partes[1]) <= 2) {
                $monto = $partes[0].'.'.$partes[1];
            } else {
                $monto = str_replace(',', '', $monto);
            }
        }

        return is_numeric($monto) ? bcadd($monto, '0', 2) : '0.00';
    }

    /**
     * RN-033.4 / RN-014.3: tarifa ARL de la clase de riesgo del perfil. "Solo se
     * incluye si la afiliación es obligatoria o el usuario la marcó como
     * voluntaria": un perfil con clase de riesgo registrada tiene afiliación,
     * obligatoria (`arl_obligatoria`) o voluntaria. Sin clase no hay ARL.
     */
    private static function tarifaArl(?PerfilTributario $perfil, ParametroNormativo $parametro): ?string
    {
        if ($perfil?->clase_riesgo_arl === null || (int) $perfil->clase_riesgo_arl <= 0) {
            return null;
        }

        $tramo = TarifaEscalonada::where('parametro_normativo_id', $parametro->id)
            ->where('tipo_tabla', 'ARL')
            ->where('rango_desde', $perfil->clase_riesgo_arl)
            ->first();

        if ($tramo !== null) {
            return (string) $tramo->tarifa_porc;
        }

        $tarifa = self::tarifaArlPorClase($perfil->clase_riesgo_arl);

        return bccomp($tarifa, '0.000', 3) > 0 ? $tarifa : null;
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
