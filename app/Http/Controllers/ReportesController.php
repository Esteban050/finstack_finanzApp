<?php

namespace App\Http\Controllers;

use App\Exports\ComparativoPeriodosExport;
use App\Exports\FlujoCajaExport;
use App\Exports\GastosDimensionExport;
use App\Services\Reportes\ComparativoPeriodosService;
use App\Services\Reportes\FlujoCajaService;
use App\Services\Reportes\GastosDimensionService;
use App\Services\Reportes\IndicadoresSaludService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MOD-11 — Reportes y Dashboards Dinámicos: Controlador de Reportes (Pantallas 07 a 10).
 *
 * Expone endpoints JSON y exportaciones en XLSX y PDF para:
 * - HU-H03: Indicadores de Salud Financiera (/reportes/indicadores)
 * - HU-H04: Flujo de Caja Histórico (/reportes/flujo-de-caja)
 * - HU-H05: Gastos por Dimensión y Top de Comercios (/reportes/gastos)
 * - HU-H06: Comparativo entre Periodos (/reportes/comparativo)
 */
class ReportesController extends Controller
{
    public function __construct(
        protected IndicadoresSaludService $indicadoresService,
        protected FlujoCajaService $flujoCajaService,
        protected GastosDimensionService $gastosDimensionService,
        protected ComparativoPeriodosService $comparativoService
    ) {}

    // =========================================================================
    // HU-H03: Indicadores de Salud Financiera (Pantalla 07)
    // =========================================================================

    /**
     * Consulta JSON de los 7 indicadores de salud financiera.
     */
    public function indicadores(Request $request): JsonResponse
    {
        $usuario = auth()->user();
        $mes = $request->query('mes'); // Formato YYYY-MM opcional

        $datos = $this->indicadoresService->calcularIndicadores($usuario, $mes);

        return response()->json([
            'mensaje' => 'Indicadores de salud financiera calculados.',
            'data' => $datos,
        ]);
    }

    /**
     * Exporta el diagnóstico de salud financiera a PDF con aviso legal (RNG-08).
     */
    public function exportarIndicadoresPdf(Request $request): Response
    {
        $usuario = auth()->user();
        $mes = $request->query('mes');

        $reporte = $this->indicadoresService->calcularIndicadores($usuario, $mes);
        $mesNombre = $reporte['mes'];

        if ($request->query('preview') === '1' || $request->query('html') === '1') {
            return response()->view('reportes.pdf.indicadores', compact('usuario', 'reporte'));
        }

        try {
            $pdf = Pdf::loadView('reportes.pdf.indicadores', compact('usuario', 'reporte'))
                ->setPaper('a4', 'portrait');

            return $pdf->download("diagnostico_salud_financiera_{$mesNombre}.pdf");
        } catch (\Throwable $e) {
            return response()->view('reportes.pdf.indicadores', compact('usuario', 'reporte'));
        }
    }

    // =========================================================================
    // HU-H04: Flujo de Caja Histórico (Pantalla 08)
    // =========================================================================

    /**
     * Consulta JSON del flujo de caja agrupado y sus gráficos.
     */
    public function flujoCaja(Request $request): JsonResponse
    {
        $usuario = auth()->user();
        $fechaDesde = $request->query('fecha_desde');
        $fechaHasta = $request->query('fecha_hasta');
        $agrupacion = $request->query('agrupacion', 'mensual');

        $datos = $this->flujoCajaService->consultarFlujoCaja($usuario, $fechaDesde, $fechaHasta, $agrupacion);

        return response()->json([
            'mensaje' => 'Reporte de flujo de caja generado.',
            'data' => $datos,
        ]);
    }

    /**
     * Exporta el reporte de flujo de caja a XLSX numérico (RN-069.3).
     */
    public function exportarFlujoCajaXlsx(Request $request): StreamedResponse
    {
        $usuario = auth()->user();
        $fechaDesde = $request->query('fecha_desde');
        $fechaHasta = $request->query('fecha_hasta');
        $agrupacion = $request->query('agrupacion', 'mensual');

        $datos = $this->flujoCajaService->consultarFlujoCaja($usuario, $fechaDesde, $fechaHasta, $agrupacion);
        $export = new FlujoCajaExport($datos, $usuario->name ?? $usuario->correo);

        $fDesde = $datos['parametros']['fecha_desde'];
        $fHasta = $datos['parametros']['fecha_hasta'];

        return $export->descargar("flujo_de_caja_{$fDesde}_{$fHasta}_{$agrupacion}.xlsx");
    }

    /**
     * Exporta el reporte de flujo de caja a PDF (orientación apaisada).
     */
    public function exportarFlujoCajaPdf(Request $request): Response
    {
        $usuario = auth()->user();
        $fechaDesde = $request->query('fecha_desde');
        $fechaHasta = $request->query('fecha_hasta');
        $agrupacion = $request->query('agrupacion', 'mensual');

        $reporte = $this->flujoCajaService->consultarFlujoCaja($usuario, $fechaDesde, $fechaHasta, $agrupacion);
        $fDesde = $reporte['parametros']['fecha_desde'];
        $fHasta = $reporte['parametros']['fecha_hasta'];

        if ($request->query('preview') === '1' || $request->query('html') === '1') {
            return response()->view('reportes.pdf.flujo-caja', compact('usuario', 'reporte'));
        }

        try {
            $pdf = Pdf::loadView('reportes.pdf.flujo-caja', compact('usuario', 'reporte'))
                ->setPaper('a4', 'landscape');

            return $pdf->download("flujo_caja_{$fDesde}_{$fHasta}_{$agrupacion}.pdf");
        } catch (\Throwable $e) {
            return response()->view('reportes.pdf.flujo-caja', compact('usuario', 'reporte'));
        }
    }

    // =========================================================================
    // HU-H05: Gastos por Dimensión y Top de Comercios (Pantalla 09)
    // =========================================================================

    /**
     * Consulta JSON de la matriz de gastos cruzada y top 10 comercios.
     */
    public function gastosDimension(Request $request): JsonResponse
    {
        $usuario = auth()->user();
        $dimFila = $request->query('dimension_fila', 'categoria');
        $dimCol = $request->query('dimension_columna', 'medio_pago');
        $fechaDesde = $request->query('fecha_desde');
        $fechaHasta = $request->query('fecha_hasta');

        $datos = $this->gastosDimensionService->consultarGastosDimension(
            $usuario,
            $dimFila,
            $dimCol,
            $fechaDesde,
            $fechaHasta
        );

        return response()->json([
            'mensaje' => 'Análisis de gastos multidimensional generado.',
            'data' => $datos,
        ]);
    }

    /**
     * Exporta la matriz bidimensional y ranking de comercios a XLSX.
     */
    public function exportarGastosDimensionXlsx(Request $request): StreamedResponse
    {
        $usuario = auth()->user();
        $dimFila = $request->query('dimension_fila', 'categoria');
        $dimCol = $request->query('dimension_columna', 'medio_pago');
        $fechaDesde = $request->query('fecha_desde');
        $fechaHasta = $request->query('fecha_hasta');

        $datos = $this->gastosDimensionService->consultarGastosDimension(
            $usuario,
            $dimFila,
            $dimCol,
            $fechaDesde,
            $fechaHasta
        );

        $export = new GastosDimensionExport($datos, $usuario->name ?? $usuario->correo);

        return $export->descargar("gastos_por_dimension_{$dimFila}_vs_{$dimCol}.xlsx");
    }

    // =========================================================================
    // HU-H06: Comparativo entre Periodos (Pantalla 10)
    // =========================================================================

    /**
     * Consulta JSON del comparativo entre Periodo A y Periodo B.
     */
    public function comparativo(Request $request): JsonResponse
    {
        $usuario = auth()->user();
        $desdeA = $request->query('desde_a');
        $hastaA = $request->query('hasta_a');
        $desdeB = $request->query('desde_b');
        $hastaB = $request->query('hasta_b');

        $datos = $this->comparativoService->comparar(
            $usuario,
            $desdeA,
            $hastaA,
            $desdeB,
            $hastaB
        );

        return response()->json([
            'mensaje' => 'Comparativo entre periodos calculado.',
            'data' => $datos,
        ]);
    }

    /**
     * Exporta la comparación entre periodos a XLSX.
     */
    public function exportarComparativoXlsx(Request $request): StreamedResponse
    {
        $usuario = auth()->user();
        $desdeA = $request->query('desde_a');
        $hastaA = $request->query('hasta_a');
        $desdeB = $request->query('desde_b');
        $hastaB = $request->query('hasta_b');

        $datos = $this->comparativoService->comparar(
            $usuario,
            $desdeA,
            $hastaA,
            $desdeB,
            $hastaB
        );

        $export = new ComparativoPeriodosExport($datos, $usuario->name ?? $usuario->correo);

        return $export->descargar("comparativo_periodos.xlsx");
    }
}
