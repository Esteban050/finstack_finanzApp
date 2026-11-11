<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\CuotaCredito;
use App\Models\Scopes\DelUsuarioAutenticado;
use App\Models\TasaUsura;
use Carbon\CarbonImmutable;

/**
 * MOD-08 — Alertas de un crédito (documento de créditos, sección 6): usura
 * (RN-01), capacidad de pago (RN-03 vivienda, RN-07 libranza) y financiación
 * de vivienda (RN-03).
 *
 * Son alertas, no bloqueos (decisión del usuario): en una app personal el
 * usuario anota su crédito real, aunque supere un límite. Cada alerta lleva
 * un código estable, un nivel (ADVERTENCIA o INFO) y un mensaje.
 */
class AlertaCreditoService
{
    /** Créditos RN-01: modalidad con la que se comparan los créditos que no son de vivienda. */
    public const MODALIDAD_CONSUMO = 'CONSUMO_ORDINARIO';

    /**
     * Alertas de unas condiciones de crédito ya validadas.
     *
     * @param  array<string, mixed>  $datos  tipo_credito, monto, tasa_ea, tasa_mora_ea, fecha_inicio, valor_inmueble, es_vis
     * @param  string  $cuotaTotal  primera cuota con seguros y cargos
     * @return array<int, array<string, mixed>>
     */
    public static function alertas(array $datos, string $cuotaTotal, string $usuarioId, ?string $creditoId = null): array
    {
        return [
            ...self::usura($datos),
            ...self::capacidadDePago($datos, $cuotaTotal, $usuarioId, $creditoId),
            ...self::financiacionVivienda($datos),
        ];
    }

    /** Las alertas de un crédito registrado, con la primera cuota de su plan vigente. */
    public static function deCredito(Credito $credito, string $cuotaTotal): array
    {
        return self::alertas([
            'tipo_credito' => $credito->tipo_credito,
            'monto' => (string) $credito->monto,
            'tasa_ea' => (string) $credito->tasa_ea,
            'tasa_mora_ea' => $credito->tasa_mora_ea === null ? null : (string) $credito->tasa_mora_ea,
            'fecha_inicio' => $credito->fecha_inicio->toDateString(),
            'valor_inmueble' => $credito->valor_inmueble === null ? null : (string) $credito->valor_inmueble,
            'es_vis' => $credito->es_vis,
        ], $cuotaTotal, $credito->usuario_id, $credito->id);
    }

    /**
     * Créditos RN-01 / RF-07 / RF-39: la tasa corriente y la de mora frente a
     * la usura vigente en la fecha de desembolso. Vivienda no tiene usura.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function usura(array $datos): array
    {
        if ($datos['tipo_credito'] === 'VIVIENDA') {
            return [];
        }

        $fecha = CarbonImmutable::parse($datos['fecha_inicio']);
        $usura = TasaUsura::vigenteEn(self::MODALIDAD_CONSUMO, $fecha);

        if ($usura === null) {
            return [[
                'codigo' => 'USURA_SIN_CERTIFICAR',
                'nivel' => 'INFO',
                'mensaje' => 'No hay tasa de usura cargada para el '.$fecha->format('d/m/Y').': no se pudo validar la tasa (Créditos RN-01).',
            ]];
        }

        $limite = bcdiv((string) $usura->tasa_ea, '100', 18);
        $usuraPorc = CreditoService::porcentaje($limite);
        $alertas = [];

        $tasas = [
            'TASA_SUPERA_USURA' => ['tasa' => $datos['tasa_ea'], 'nombre' => 'La tasa'],
            'MORA_SUPERA_USURA' => ['tasa' => $datos['tasa_mora_ea'] ?? null, 'nombre' => 'La tasa de mora'],
        ];

        foreach ($tasas as $codigo => $tasa) {
            if ($tasa['tasa'] === null || bccomp((string) $tasa['tasa'], $limite, 18) !== 1) {
                continue;
            }

            $tasaPorc = CreditoService::porcentaje((string) $tasa['tasa']);
            $alertas[] = [
                'codigo' => $codigo,
                'nivel' => 'ADVERTENCIA',
                'mensaje' => "{$tasa['nombre']} ({$tasaPorc} % EA) supera la tasa de usura vigente ({$usuraPorc} % EA, {$usura->acto_administrativo}). Cobrar por encima de la usura es ilegal (Créditos RN-01).",
                'tasa_ea_porc' => $tasaPorc,
                'usura_ea_porc' => $usuraPorc,
                'vigencia' => ['desde' => $usura->vigente_desde->toDateString(), 'hasta' => $usura->vigente_hasta->toDateString()],
            ];
        }

        return $alertas;
    }

    /**
     * Créditos RN-03 / RN-07: capacidad de pago frente al promedio de los
     * ingresos netos confirmados. Vivienda: la primera cuota no supera el 40 %.
     * Libranza: la cuota y las de sus otras libranzas no superan el 50 %.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function capacidadDePago(array $datos, string $cuotaTotal, string $usuarioId, ?string $creditoId = null): array
    {
        $tipo = $datos['tipo_credito'];
        if (! in_array($tipo, ['VIVIENDA', 'LIBRANZA'], true)) {
            return [];
        }

        $config = config('scfp.creditos');
        $ingreso = self::ingresoMensualPromedio($usuarioId);
        $meses = (int) $config['meses_promedio_ingresos'];

        if (bccomp($ingreso, '0', 0) !== 1) {
            return [[
                'codigo' => 'SIN_INGRESOS',
                'nivel' => 'INFO',
                'mensaje' => "No hay ingresos confirmados en los últimos {$meses} meses: no se pudo validar la capacidad de pago (Créditos ".($tipo === 'VIVIENDA' ? 'RN-03' : 'RN-07').').',
            ]];
        }

        if ($tipo === 'VIVIENDA') {
            $porc = (string) $config['vivienda_cuota_max_porc_ingresos'];
            $comprometido = $cuotaTotal;
            $codigo = 'CUOTA_SUPERA_INGRESOS';
            $texto = "La primera cuota de vivienda supera el {$porc} % de sus ingresos";
            $norma = 'RN-03, Decreto 1077 de 2015';
        } else {
            $porc = (string) $config['libranza_descuentos_max_porc_ingresos'];
            $comprometido = bcadd($cuotaTotal, self::cuotasOtrasLibranzas($usuarioId, $creditoId), 0);
            $codigo = 'LIBRANZA_SUPERA_LIMITE';
            $texto = "Las cuotas de libranza superan el {$porc} % de sus ingresos netos";
            $norma = 'RN-07, Ley 1527 de 2012';
        }

        $limite = CreditoService::alPeso(bcdiv(bcmul($ingreso, $porc, 10), '100', 10));

        if (bccomp($comprometido, $limite, 0) !== 1) {
            return [];
        }

        return [[
            'codigo' => $codigo,
            'nivel' => 'ADVERTENCIA',
            'mensaje' => "{$texto} (promedio de {$meses} meses: $ ".self::formato($ingreso).'; límite $ '.self::formato($limite).") (Créditos {$norma}).",
            'ingreso_mensual_promedio' => $ingreso,
            'limite' => $limite,
            'comprometido' => $comprometido,
            'porcentaje_ingresos' => bcdiv(bcmul($comprometido, '100', 10), $ingreso, 2),
        ]];
    }

    /**
     * Créditos RN-03: vivienda financia hasta el 70 % del valor del inmueble
     * (80 % si es VIS). Solo si se indicó el valor.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function financiacionVivienda(array $datos): array
    {
        if ($datos['tipo_credito'] !== 'VIVIENDA' || empty($datos['valor_inmueble'])) {
            return [];
        }

        $config = config('scfp.creditos');
        $porc = (string) (($datos['es_vis'] ?? false) ? $config['vivienda_vis_financiacion_max_porc'] : $config['vivienda_financiacion_max_porc']);
        $limite = CreditoService::alPeso(bcdiv(bcmul((string) $datos['valor_inmueble'], $porc, 10), '100', 10));

        if (bccomp((string) $datos['monto'], $limite, 2) !== 1) {
            return [];
        }

        return [[
            'codigo' => 'FINANCIACION_SUPERA_LIMITE',
            'nivel' => 'ADVERTENCIA',
            'mensaje' => "El monto supera el {$porc} % del valor del inmueble".(($datos['es_vis'] ?? false) ? ' (VIS)' : '').': máximo $ '.self::formato($limite).' (Créditos RN-03, Decreto 1077 de 2015).',
            'limite' => $limite,
            'porcentaje_financiado' => bcdiv(bcmul((string) $datos['monto'], '100', 10), (string) $datos['valor_inmueble'], 2),
        ]];
    }

    /**
     * Créditos RN-03 / RN-07: promedio mensual de los ingresos netos
     * confirmados de los últimos meses calendario completos (MOD-03). Es el
     * mismo promedio del indicador de endeudamiento (RN-053.1).
     */
    public static function ingresoMensualPromedio(string $usuarioId): string
    {
        return EndeudamientoService::ingresosNetosPorMes($usuarioId, CarbonImmutable::today())['promedio'];
    }

    /**
     * Créditos RN-07: la próxima cuota programada (con seguros y cargos) de
     * las otras libranzas del usuario que aún tienen cuotas por vencer.
     */
    private static function cuotasOtrasLibranzas(string $usuarioId, ?string $excluir): string
    {
        $creditos = Credito::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $usuarioId)
            ->where('tipo_credito', 'LIBRANZA')
            ->when($excluir, fn ($q) => $q->whereKeyNot($excluir))
            ->pluck('id');

        $total = '0';
        foreach ($creditos as $id) {
            $proxima = CuotaCredito::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->where('credito_id', $id)
                ->whereNull('reemplazada_en_version')
                ->where('fecha_vencimiento', '>=', CarbonImmutable::today()->toDateString())
                ->orderBy('numero')
                ->first();

            if ($proxima !== null) {
                $total = bcadd($total, bcadd(bcadd((string) $proxima->cuota, (string) $proxima->seguro, 2), (string) $proxima->cargos, 2), 0);
            }
        }

        return $total;
    }

    /** $ 1.234.567 para los mensajes. */
    private static function formato(string $pesos): string
    {
        return number_format((float) $pesos, 0, ',', '.');
    }
}
