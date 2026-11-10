<?php

namespace App\Services\Renta;

use App\Models\DeclaracionRenta;
use App\Models\Usuario;
use Carbon\CarbonImmutable;

class EvaluacionObligacionService
{
    public function __construct(
        protected InformacionComplementariaService $complementariaService,
        protected ConsolidadoRentaService $consolidadoService
    ) {}

    /**
     * Evalúa los 6 criterios normativos y determina la obligación de declarar renta (RF-045).
     */
    public function evaluar(Usuario $usuario, int $anioGravable): array
    {
        $declaracion = $this->complementariaService->obtenerDeclaracion($usuario, $anioGravable);
        $params = ConsolidadoRentaService::obtenerParametros($anioGravable);
        $uvt = $params['uvt'];

        // Determinar si es preliminar (año en curso antes del 31 de diciembre)
        $ahora = CarbonImmutable::now();
        $esAnioEnCurso = ($ahora->year === $anioGravable && $ahora->isBefore(CarbonImmutable::create($anioGravable, 12, 31, 23, 59, 59)));
        $mesesTranscurridos = max(1, $ahora->month);

        // Topes en UVT y COP
        $topeC1Cop = $params['tope_patrimonio_uvt'] * $uvt; // 4.500 UVT
        $topeC2Cop = $params['tope_ingresos_uvt'] * $uvt;   // 1.400 UVT
        $topeC3Cop = $params['tope_tarjetas_uvt'] * $uvt;   // 1.400 UVT
        $topeC4Cop = $params['tope_compras_uvt'] * $uvt;    // 1.400 UVT
        $topeC5Cop = $params['tope_consignaciones_uvt'] * $uvt; // 1.400 UVT

        // Valores de evaluación (consolidado + ajuste manual)
        $valC1 = max(0.0, (float) $declaracion->c1_patrimonio_bruto_cop + (float) $declaracion->c1_ajuste_manual_cop);
        $valC2 = max(0.0, (float) $declaracion->c2_ingresos_brutos_cop + (float) $declaracion->c2_ajuste_manual_cop);
        $declaracion->c1_valor_final_cop = $valC1;
        $declaracion->c2_valor_final_cop = $valC2;
        $valC3 = (float) $declaracion->c3_consumos_tarjeta_cop;
        $valC4 = (float) $declaracion->c4_compras_totales_cop;

        // C5: max(Piso Inversiones, Consignaciones declaradas)
        $pisoInv = (float) $declaracion->c5_piso_inversiones_cop;
        $consignacionesDecl = (float) ($declaracion->c5_consignaciones_declaradas_cop ?? 0.0);
        $valC5 = max($pisoInv, $consignacionesDecl);

        // C6: responsable de IVA
        $valC6 = (bool) $declaracion->c6_responsable_iva;

        // Proyección si es año en curso
        $valC2Proyectado = $esAnioEnCurso ? round(($valC2 / $mesesTranscurridos) * 12) : $valC2;

        // 1. Evaluación C1: Patrimonio > 4.500 UVT
        $cumpleC1 = $valC1 > $topeC1Cop;
        $margenC1 = max(0.0, $topeC1Cop - $valC1);
        $cercaC1 = (! $cumpleC1 && $topeC1Cop > 0 && ($valC1 / $topeC1Cop) >= 0.90);

        // 2. Evaluación C2: Ingresos >= 1.400 UVT (ÚNICO criterio con >=)
        $cumpleC2 = $valC2 >= $topeC2Cop;
        $margenC2 = max(0.0, $topeC2Cop - $valC2);
        $cercaC2 = (! $cumpleC2 && $topeC2Cop > 0 && ($valC2 / $topeC2Cop) >= 0.90);

        // 3. Evaluación C3: Consumos tarjeta > 1.400 UVT
        $cumpleC3 = $valC3 > $topeC3Cop;
        $margenC3 = max(0.0, $topeC3Cop - $valC3);
        $cercaC3 = (! $cumpleC3 && $topeC3Cop > 0 && ($valC3 / $topeC3Cop) >= 0.90);

        // 4. Evaluación C4: Compras totales > 1.400 UVT
        $cumpleC4 = $valC4 > $topeC4Cop;
        $margenC4 = max(0.0, $topeC4Cop - $valC4);
        $cercaC4 = (! $cumpleC4 && $topeC4Cop > 0 && ($valC4 / $topeC4Cop) >= 0.90);

        // 5. Evaluación C5: Consignaciones/inversiones > 1.400 UVT
        $cumpleC5 = $valC5 > $topeC5Cop;
        $margenC5 = max(0.0, $topeC5Cop - $valC5);
        $cercaC5 = (! $cumpleC5 && $topeC5Cop > 0 && ($valC5 / $topeC5Cop) >= 0.90);

        // 6. Evaluación C6: Responsable de IVA
        $cumpleC6 = $valC6;

        // Veredicto Global (RN-045.2)
        $algunoCumple = ($cumpleC1 || $cumpleC2 || $cumpleC3 || $cumpleC4 || $cumpleC5 || $cumpleC6);

        if ($algunoCumple) {
            $veredicto = 'OBLIGADO';
            $veredictoLabel = 'OBLIGADO A DECLARAR';
        } elseif (! $declaracion->c5_confirmado) {
            $veredicto = 'INCOMPLETO';
            $veredictoLabel = 'INCOMPLETO';
        } else {
            $veredicto = 'NO_OBLIGADO';
            $veredictoLabel = 'NO OBLIGADO A DECLARAR';
        }

        // Actualizar estado en el modelo
        $declaracion->estado_evaluacion = $veredicto;
        $declaracion->es_preliminar = $esAnioEnCurso;
        $declaracion->save();

        $criterios = [
            'c1' => [
                'id' => 'c1',
                'nombre' => 'Patrimonio bruto al 31 de diciembre',
                'norma' => 'Art. 592 num. 1 y Art. 594-3 E.T.',
                'tope_uvt' => $params['tope_patrimonio_uvt'],
                'tope_cop' => $topeC1Cop,
                'operador' => '>',
                'valor_cop' => $valC1,
                'valor_uvt' => ConsolidadoRentaService::aUvt($valC1, $uvt),
                'cumple' => $cumpleC1,
                'margen_disponible_cop' => $margenC1,
                'margen_disponible_uvt' => ConsolidadoRentaService::aUvt($margenC1, $uvt),
                'cerca' => $cercaC1,
            ],
            'c2' => [
                'id' => 'c2',
                'nombre' => 'Ingresos brutos del año gravable',
                'norma' => 'Art. 592 num. 1, Art. 593 y Art. 594-3 E.T.',
                'tope_uvt' => $params['tope_ingresos_uvt'],
                'tope_cop' => $topeC2Cop,
                'operador' => '>=',
                'valor_cop' => $valC2,
                'valor_uvt' => ConsolidadoRentaService::aUvt($valC2, $uvt),
                'cumple' => $cumpleC2,
                'margen_disponible_cop' => $margenC2,
                'margen_disponible_uvt' => ConsolidadoRentaService::aUvt($margenC2, $uvt),
                'cerca' => $cercaC2,
                'proyectado_cop' => $valC2Proyectado,
            ],
            'c3' => [
                'id' => 'c3',
                'nombre' => 'Consumos mediante tarjeta de crédito',
                'norma' => 'Art. 594-3 num. 1 E.T.',
                'tope_uvt' => $params['tope_tarjetas_uvt'],
                'tope_cop' => $topeC3Cop,
                'operador' => '>',
                'valor_cop' => $valC3,
                'valor_uvt' => ConsolidadoRentaService::aUvt($valC3, $uvt),
                'cumple' => $cumpleC3,
                'margen_disponible_cop' => $margenC3,
                'margen_disponible_uvt' => ConsolidadoRentaService::aUvt($margenC3, $uvt),
                'cerca' => $cercaC3,
            ],
            'c4' => [
                'id' => 'c4',
                'nombre' => 'Compras y consumos totales del año',
                'norma' => 'Art. 594-3 num. 2 E.T.',
                'tope_uvt' => $params['tope_compras_uvt'],
                'tope_cop' => $topeC4Cop,
                'operador' => '>',
                'valor_cop' => $valC4,
                'valor_uvt' => ConsolidadoRentaService::aUvt($valC4, $uvt),
                'cumple' => $cumpleC4,
                'margen_disponible_cop' => $margenC4,
                'margen_disponible_uvt' => ConsolidadoRentaService::aUvt($margenC4, $uvt),
                'cerca' => $cercaC4,
            ],
            'c5' => [
                'id' => 'c5',
                'nombre' => 'Consignaciones bancarias, depósitos o inversiones',
                'norma' => 'Art. 594-3 num. 3 E.T.',
                'tope_uvt' => $params['tope_consignaciones_uvt'],
                'tope_cop' => $topeC5Cop,
                'operador' => '>',
                'valor_cop' => $valC5,
                'valor_uvt' => ConsolidadoRentaService::aUvt($valC5, $uvt),
                'cumple' => $cumpleC5,
                'margen_disponible_cop' => $margenC5,
                'margen_disponible_uvt' => ConsolidadoRentaService::aUvt($margenC5, $uvt),
                'cerca' => $cercaC5,
                'piso_inversiones_cop' => $pisoInv,
                'consignaciones_declaradas_cop' => $consignacionesDecl,
                'confirmado' => $declaracion->c5_confirmado,
            ],
            'c6' => [
                'id' => 'c6',
                'nombre' => 'Responsable del Impuesto sobre las Ventas (IVA)',
                'norma' => 'Art. 592 num. 2 E.T.',
                'tope_uvt' => null,
                'tope_cop' => null,
                'operador' => '== true',
                'valor_cop' => null,
                'valor_uvt' => null,
                'cumple' => $cumpleC6,
                'margen_disponible_cop' => null,
                'margen_disponible_uvt' => null,
                'cerca' => false,
            ],
        ];

        return [
            'declaracion' => $declaracion,
            'anio_gravable' => $anioGravable,
            'parametros' => $params,
            'veredicto' => $veredicto,
            'veredicto_label' => $veredictoLabel,
            'es_preliminar' => $esAnioEnCurso,
            'alguno_cumple' => $algunoCumple,
            'c5_confirmado' => $declaracion->c5_confirmado,
            'criterios' => $criterios,
            'resumen' => [
                'criterios_cumplidos' => count(array_filter($criterios, fn ($c) => $c['cumple'])),
                'criterios_cerca' => count(array_filter($criterios, fn ($c) => $c['cerca'])),
            ],
        ];
    }
}

