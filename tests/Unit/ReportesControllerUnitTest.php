<?php

namespace Tests\Unit;

use App\Http\Controllers\ReportesController;
use App\Http\Controllers\ResumenController;
use App\Models\Usuario;
use App\Services\Reportes\ComparativoPeriodosService;
use App\Services\Reportes\DashboardResumenService;
use App\Services\Reportes\FlujoCajaService;
use App\Services\Reportes\GastosDimensionService;
use App\Services\Reportes\IndicadoresSaludService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ReportesControllerUnitTest extends TestCase
{
    protected ResumenController $resumenController;
    protected ReportesController $reportesController;
    protected $dashboardServiceMock;
    protected $indicadoresServiceMock;
    protected $flujoCajaServiceMock;
    protected $gastosDimensionServiceMock;
    protected $comparativoServiceMock;
    protected Usuario $usuario;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboardServiceMock = Mockery::mock(DashboardResumenService::class);
        $this->indicadoresServiceMock = Mockery::mock(IndicadoresSaludService::class);
        $this->flujoCajaServiceMock = Mockery::mock(FlujoCajaService::class);
        $this->gastosDimensionServiceMock = Mockery::mock(GastosDimensionService::class);
        $this->comparativoServiceMock = Mockery::mock(ComparativoPeriodosService::class);

        $this->resumenController = new ResumenController($this->dashboardServiceMock);
        $this->reportesController = new ReportesController(
            $this->indicadoresServiceMock,
            $this->flujoCajaServiceMock,
            $this->gastosDimensionServiceMock,
            $this->comparativoServiceMock
        );

        $this->usuario = new Usuario([
            'nombres' => 'Usuario',
            'apellidos' => 'Prueba',
            'correo' => 'test@finanzapp.local',
        ]);
        $this->usuario->id = '11111111-1111-1111-1111-111111111111';

        $this->actingAs($this->usuario);
    }

    public function test_resumen_index_retorna_json_response(): void
    {
        $mockData = [
            'periodo' => ['periodo' => 'mes-actual'],
            'kpis' => [['clave' => 'ingresos_netos', 'valor' => '5000000.00']],
        ];

        $this->dashboardServiceMock
            ->shouldReceive('obtenerDashboard')
            ->once()
            ->andReturn($mockData);

        $request = Request::create('/resumen', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $this->resumenController->index($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertEquals('Dashboard consolidado del periodo.', $payload['mensaje']);
        $this->assertEquals($mockData, $payload['data']);
    }

    public function test_indicadores_retorna_json_response(): void
    {
        $mockData = [
            'mes' => '2026-10',
            'indicadores' => [['id' => 'tasa_ahorro', 'valor' => 15.5]],
        ];

        $this->indicadoresServiceMock
            ->shouldReceive('calcularIndicadores')
            ->once()
            ->with($this->usuario, '2026-10')
            ->andReturn($mockData);

        $request = Request::create('/reportes/indicadores', 'GET', ['mes' => '2026-10']);
        $request->headers->set('Accept', 'application/json');
        $response = $this->reportesController->indicadores($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertEquals('Indicadores de salud financiera calculados.', $payload['mensaje']);
        $this->assertEquals($mockData, $payload['data']);
    }

    public function test_flujo_caja_retorna_json_response(): void
    {
        $mockData = [
            'parametros' => ['agrupacion' => 'mensual'],
            'periodos' => [],
        ];

        $this->flujoCajaServiceMock
            ->shouldReceive('consultarFlujoCaja')
            ->once()
            ->with($this->usuario, '2026-01-01', '2026-06-30', 'mensual')
            ->andReturn($mockData);

        $request = Request::create('/reportes/flujo-de-caja', 'GET', [
            'fecha_desde' => '2026-01-01',
            'fecha_hasta' => '2026-06-30',
            'agrupacion' => 'mensual',
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = $this->reportesController->flujoCaja($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_gastos_dimension_retorna_json_response(): void
    {
        $mockData = [
            'columnas' => ['Efectivo', 'Tarjeta'],
            'filas' => [],
            'total_general' => '0.00',
        ];

        $this->gastosDimensionServiceMock
            ->shouldReceive('consultarGastosDimension')
            ->once()
            ->with($this->usuario, 'categoria', 'medio_pago', null, null)
            ->andReturn($mockData);

        $request = Request::create('/reportes/gastos', 'GET', [
            'dimension_fila' => 'categoria',
            'dimension_columna' => 'medio_pago',
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = $this->reportesController->gastosDimension($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertEquals('Análisis de gastos multidimensional generado.', $payload['mensaje']);
    }

    public function test_comparativo_retorna_json_response(): void
    {
        $mockData = [
            'resumen' => [],
            'tabla_categorias' => [],
        ];

        $this->comparativoServiceMock
            ->shouldReceive('comparar')
            ->once()
            ->with($this->usuario, '2026-09-01', '2026-09-30', '2026-10-01', '2026-10-31')
            ->andReturn($mockData);

        $request = Request::create('/reportes/comparativo', 'GET', [
            'desde_a' => '2026-09-01',
            'hasta_a' => '2026-09-30',
            'desde_b' => '2026-10-01',
            'hasta_b' => '2026-10-31',
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = $this->reportesController->comparativo($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertEquals('Comparativo entre periodos calculado.', $payload['mensaje']);
    }

    public function test_exportar_flujo_caja_xlsx_retorna_streamed_response(): void
    {
        $mockData = [
            'parametros' => [
                'rango_texto' => 'Del 01/01/2026 al 30/06/2026',
                'agrupacion' => 'mensual',
                'fecha_desde' => '2026-01-01',
                'fecha_hasta' => '2026-06-30',
            ],
            'catalogo_fuentes' => ['f1' => 'Salario'],
            'catalogo_categorias' => ['c1' => 'Alimentación'],
            'periodos' => [],
            'totales' => [
                'total_ingresos' => '1000000.00',
                'total_gastos' => '500000.00',
                'balance_neto' => '500000.00',
                'flujo_acumulado_final' => '500000.00',
            ],
        ];

        $this->flujoCajaServiceMock
            ->shouldReceive('consultarFlujoCaja')
            ->once()
            ->andReturn($mockData);

        $request = Request::create('/reportes/flujo-de-caja/exportar/xlsx', 'GET');
        $response = $this->reportesController->exportarFlujoCajaXlsx($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('flujo_de_caja_2026-01-01_2026-06-30_mensual.xlsx', $response->headers->get('Content-Disposition'));
    }

    public function test_exportar_gastos_dimension_xlsx_retorna_streamed_response(): void
    {
        $mockData = [
            'parametros' => [
                'dimension_fila_nombre' => 'Categoría',
                'dimension_columna_nombre' => 'Medio de pago',
                'periodo_texto' => 'Del 01/10/2026 al 31/10/2026',
            ],
            'columnas' => ['Efectivo'],
            'filas' => [],
            'totales_columnas' => ['Efectivo' => '0.00'],
            'total_general' => '0.00',
            'conteo_total_gastos' => 0,
            'top_comercios' => [],
        ];

        $this->gastosDimensionServiceMock
            ->shouldReceive('consultarGastosDimension')
            ->once()
            ->andReturn($mockData);

        $request = Request::create('/reportes/gastos/exportar/xlsx', 'GET');
        $response = $this->reportesController->exportarGastosDimensionXlsx($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('gastos_por_dimension', $response->headers->get('Content-Disposition'));
    }

    public function test_exportar_comparativo_xlsx_retorna_streamed_response(): void
    {
        $mockData = [
            'periodo_a' => ['texto' => 'Sep 2026'],
            'periodo_b' => ['texto' => 'Oct 2026'],
            'resumen' => [
                'ingresos' => ['monto_a' => '1000', 'monto_b' => '1200', 'variacion_pesos' => '200', 'variacion_porc' => 20],
                'gastos' => ['monto_a' => '800', 'monto_b' => '900', 'variacion_pesos' => '100', 'variacion_porc' => 12.5],
                'balance' => ['monto_a' => '200', 'monto_b' => '300', 'variacion_pesos' => '100', 'variacion_porc' => 50],
                'presupuesto' => ['periodo_a' => '80%', 'periodo_b' => '85%', 'variacion_pp_texto' => '+5 pp'],
            ],
            'tabla_categorias' => [
                ['nombre' => 'Comida', 'periodo_a' => '500', 'periodo_b' => '600', 'variacion_pesos' => '100', 'variacion_porc' => 20, 'variacion_porc_texto' => '+20,00%'],
            ],
        ];

        $this->comparativoServiceMock
            ->shouldReceive('comparar')
            ->once()
            ->andReturn($mockData);

        $request = Request::create('/reportes/comparativo/exportar/xlsx', 'GET');
        $response = $this->reportesController->exportarComparativoXlsx($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('comparativo_periodos.xlsx', $response->headers->get('Content-Disposition'));
    }

    public function test_exportar_indicadores_pdf_retorna_pdf_response(): void
    {
        $mockData = [
            'mes' => '2026-10',
            'mes_nombre' => 'Octubre 2026',
            'aviso_legal' => IndicadoresSaludService::AVISO_LEGAL,
            'indicadores' => [],
        ];

        $this->indicadoresServiceMock
            ->shouldReceive('calcularIndicadores')
            ->once()
            ->andReturn($mockData);

        $request = Request::create('/reportes/indicadores/pdf', 'GET');
        $response = $this->reportesController->exportarIndicadoresPdf($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('diagnostico_salud_financiera_2026-10.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_exportar_flujo_caja_pdf_retorna_pdf_response(): void
    {
        $mockData = [
            'parametros' => [
                'rango_texto' => 'Del 01/01/2026 al 30/06/2026',
                'agrupacion' => 'mensual',
                'fecha_desde' => '2026-01-01',
                'fecha_hasta' => '2026-06-30',
            ],
            'periodos' => [],
            'totales' => [],
            'nota_explicativa' => 'Nota test',
        ];

        $this->flujoCajaServiceMock
            ->shouldReceive('consultarFlujoCaja')
            ->once()
            ->andReturn($mockData);

        $request = Request::create('/reportes/flujo-de-caja/exportar/pdf', 'GET');
        $response = $this->reportesController->exportarFlujoCajaPdf($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('flujo_caja_2026-01-01_2026-06-30_mensual.pdf', $response->headers->get('Content-Disposition'));
    }
}
