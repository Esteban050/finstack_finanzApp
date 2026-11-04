<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\FuenteIngreso;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\ValoracionInversion;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * MOD-06 — Reglas de las inversiones (RF-037) y de sus movimientos (RF-038).
 *
 * Los montos se calculan con bcmath sobre cadenas (RNG-02). Las potencias con
 * exponente fraccionario, que bcmath no tiene, se calculan en punto flotante y
 * solo como factor: el monto resultante se redondea a 2 decimales (decisión 8,
 * SC-001).
 */
class InversionService
{
    /**
     * RN-038.4: fuente de ingreso de los rendimientos (decisión 2, SC-001): una
     * por Usuario, del tipo "Rendimientos financieros" del catálogo (RN-015.2).
     */
    public const FUENTE_RENDIMIENTOS = 'Rendimientos de inversiones';
    public const TIPO_FUENTE_RENDIMIENTOS = 'Rendimientos financieros';

    /** RN-038.4 / CA-038.1: los rendimientos son rentas de capital (decisión 1, SC-001). */
    public const CLASIFICACION_RENDIMIENTOS = 'RENTAS_CAPITAL';

    /** RN-039.4 */
    public const DIAS_VALORACION_VIGENTE = 35;

    /** Escala de las unidades (RN-037.3: hasta 8 decimales). */
    private const ESCALA_UNIDADES = 8;

    /** CA-037.1: el vencimiento es la apertura más el plazo en días. */
    public static function fechaVencimiento(CarbonImmutable $apertura, int $plazoDias): CarbonImmutable
    {
        return $apertura->addDays($plazoDias);
    }

    /**
     * CA-037.1 / RN-039.2: (1 + EA)^(días/365), con la tasa EA en porcentaje.
     */
    public static function factorCapitalizacion(string $tasaEaPorc, int $dias): string
    {
        $factor = (1 + ((float) $tasaEaPorc) / 100) ** ($dias / 365);

        return sprintf('%.15F', $factor);
    }

    /**
     * CA-037.1: rendimiento estimado al vencimiento = capital × ((1 + EA)^(días/365) − 1).
     */
    public static function rendimientoEstimado(string $capital, string $tasaEaPorc, int $dias): string
    {
        $rendimiento = bcmul($capital, bcsub(self::factorCapitalizacion($tasaEaPorc, $dias), '1', 15), 6);

        return IngresoService::redondear($rendimiento);
    }

    /** RN-037.5: un CDT o bono cuya fecha de vencimiento ya pasó está vencido. */
    public static function estadoSegunVencimiento(?CarbonImmutable $vencimiento, CarbonImmutable $hoy): string
    {
        return $vencimiento !== null && $vencimiento->lt($hoy) ? 'VENCIDA' : 'ACTIVA';
    }

    /** RN-039.3: la valoración más reciente es el valor actual. */
    public static function valorActual(Inversion $inversion): ?ValoracionInversion
    {
        return ValoracionInversion::where('inversion_id', $inversion->id)
            ->orderByDesc('fecha')
            ->first();
    }

    /**
     * RN-039.4: una inversión activa con más de 35 días sin valoración muestra
     * "Valoración desactualizada".
     */
    public static function valoracionDesactualizada(Inversion $inversion, ?ValoracionInversion $valoracion, CarbonImmutable $hoy): bool
    {
        if ($inversion->estado !== 'ACTIVA') {
            return false;
        }

        return $valoracion === null
            || CarbonImmutable::parse($valoracion->fecha)->addDays(self::DIAS_VALORACION_VIGENTE)->lt($hoy);
    }

    /**
     * RN-039.2 / CA-039.1: valor causado propuesto para CDT y bonos =
     * capital × (1 + tasa EA)^(días transcurridos/365), con el capital aportado
     * neto a la fecha. El Usuario lo acepta o lo reemplaza.
     */
    public static function valorCausado(Inversion $inversion, CarbonImmutable $fecha): array
    {
        $dias = (int) CarbonImmutable::parse($inversion->fecha_apertura)->diffInDays($fecha);
        $capital = RentabilidadService::capitalAportadoNeto($inversion, $fecha);

        return [
            'fecha' => $fecha->toDateString(),
            'capital' => $capital,
            'tasa_ea' => (string) $inversion->tasa_ea,
            'dias_transcurridos' => $dias,
            'valor_causado' => IngresoService::redondear(bcmul($capital, self::factorCapitalizacion((string) $inversion->tasa_ea, $dias), 6)),
        ];
    }

    /**
     * RN-037.5: "Vencida (la marca el Sistema al pasar la fecha de
     * vencimiento)". Pasa a Vencida toda inversión activa, de cualquier
     * Usuario, cuya fecha de vencimiento ya pasó. Lo ejecuta a diario
     * `scfp:marcar-inversiones-vencidas`.
     */
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

    /**
     * RN-037.3: unidades de una inversión con unidades, hoy o a una fecha: las
     * de los aportes (el inicial incluido) menos las de los retiros y la
     * liquidación.
     */
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
            return $movimiento->tipo === 'APORTE'
                ? bcadd($total, (string) $movimiento->unidades, self::ESCALA_UNIDADES)
                : bcsub($total, (string) $movimiento->unidades, self::ESCALA_UNIDADES);
        }, bcadd('0', '0', self::ESCALA_UNIDADES));
    }

    /**
     * RN-038.4: fuente "Rendimientos de inversiones" del Usuario. Se crea la
     * primera vez que registra un rendimiento; si ya tiene una fuente con ese
     * nombre (RN-020.1: único por Usuario), se usa esa.
     *
     * @throws ValidationException
     */
    public static function fuenteDeRendimientos(string $usuarioId): FuenteIngreso
    {
        $existente = FuenteIngreso::where('usuario_id', $usuarioId)
            ->where('nombre', self::FUENTE_RENDIMIENTOS)
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        $tipo = Categoria::where('tipo', 'INGRESO')
            ->where('es_predeterminada', true)
            ->whereNull('categoria_padre_id')
            ->where('nombre', self::TIPO_FUENTE_RENDIMIENTOS)
            ->first();

        if ($tipo === null) {
            throw ValidationException::withMessages([
                'ingreso' => 'El tipo de fuente "'.self::TIPO_FUENTE_RENDIMIENTOS.'" no está disponible en el catálogo (RF-015). Contacte al administrador.',
            ]);
        }

        return FuenteIngreso::create([
            'usuario_id' => $usuarioId,
            'tipo_fuente_id' => $tipo->id,
            'nombre' => self::FUENTE_RENDIMIENTOS,
            'clasificacion_tributaria' => self::CLASIFICACION_RENDIMIENTOS,
            'activa' => true,
        ]);
    }

    /**
     * RF-037: la inversión con su valor actual (RN-039.3), sus unidades
     * (RN-037.3), el indicador de RN-039.4 y, en CDT y bonos, el rendimiento
     * estimado al vencimiento (CA-037.1).
     */
    public static function presentar(Inversion $inversion, CarbonImmutable $hoy): array
    {
        $valoracion = self::valorActual($inversion);

        $rendimientoEstimado = null;
        if ($inversion->tieneTasa() && $inversion->fecha_vencimiento !== null) {
            $dias = (int) CarbonImmutable::parse($inversion->fecha_apertura)
                ->diffInDays(CarbonImmutable::parse($inversion->fecha_vencimiento));
            $rendimientoEstimado = self::rendimientoEstimado((string) $inversion->monto_inicial, (string) $inversion->tasa_ea, $dias);
        }

        return array_merge($inversion->toArray(), [
            'unidades_actuales' => self::unidadesActuales($inversion),
            'valor_actual' => $valoracion?->valor,
            'fecha_ultima_valoracion' => $valoracion?->fecha?->toDateString(),
            'valoracion_desactualizada' => self::valoracionDesactualizada($inversion, $valoracion, $hoy),
            'rendimiento_estimado' => $rendimientoEstimado,
        ]);
    }
}
