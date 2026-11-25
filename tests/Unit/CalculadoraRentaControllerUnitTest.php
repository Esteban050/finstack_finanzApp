<?php

namespace Tests\Unit;

use App\Http\Controllers\DeclaracionRentaController;
use App\Services\Renta\CalculadoraRentaService;
use App\Services\Renta\CalendarioVencimientoService;
use App\Services\Renta\ConsolidadoRentaService;
use App\Services\Renta\EstimacionRentaService;
use App\Services\Renta\EvaluacionObligacionService;
use App\Services\Renta\InformacionComplementariaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use Tests\Support\PreparaVigenciasCalculadora;
use Tests\TestCase;

class CalculadoraRentaControllerUnitTest extends TestCase
{
    use PreparaVigenciasCalculadora;
    protected DeclaracionRentaController $controller;
    protected CalculadoraRentaService $calculadoraService;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararVigenciasCalculadora();

        $consolidadoService = Mockery::mock(ConsolidadoRentaService::class);
        $complementariaService = Mockery::mock(InformacionComplementariaService::class);
        $evaluacionService = Mockery::mock(EvaluacionObligacionService::class);
        $estimacionService = Mockery::mock(EstimacionRentaService::class);
        $estimacionService->shouldReceive('estimar')->andReturn([
            'paso7' => ['impuesto_redondeado_cop' => 0.0],
            'paso8' => ['saldo_valor_mostrado_cop' => 1212000.0, 'saldo_label' => 'Saldo a favor'],
        ]);
        $this->actingAs(new \App\Models\Usuario);
        $vencimientoService = Mockery::mock(CalendarioVencimientoService::class);
        $this->calculadoraService = new CalculadoraRentaService();

        $this->controller = new DeclaracionRentaController(
            $consolidadoService,
            $complementariaService,
            $evaluacionService,
            $estimacionService,
            $vencimientoService,
            $this->calculadoraService
        );
    }

    public function test_calcular_simulacion_retorna_json_response_estructurada(): void
    {
        $request = Request::create('/renta/2025/calculadora/calcular', 'POST', [
            'patrimonio_bruto_cop' => 150000000,
            'ingresos_brutos_cop' => 85000000,
            'consumos_tarjeta_cop' => 10000000,
            'compras_totales_cop' => 15000000,
            'consignaciones_cop' => 90000000,
            'salud_pension_cop' => 6800000,
            'retenciones_fuente_cop' => 2000000,
        ]);

        $response = $this->controller->calcularSimulacion($request, 2025);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->getData(true);

        $this->assertEquals(2025, $data['anio_gravable']);
        $this->assertTrue($data['obligacion']['esta_obligado']);
        $this->assertEquals('OBLIGADO', $data['obligacion']['veredicto']);
        $this->assertCount(2, $data['obligacion']['conceptos_que_obligan']); // ingresos (85M) y consignaciones (90M)
        
        $codigosSuperados = array_column($data['obligacion']['conceptos_que_obligan'], 'codigo');
        $this->assertContains('ingresos', $codigosSuperados);
        $this->assertContains('consignaciones', $codigosSuperados);

        $this->assertArrayHasKey('liquidacion', $data);
        $this->assertGreaterThan(0, $data['liquidacion']['impuesto_cop']);
        $this->assertArrayHasKey('saldo_neto_cop', $data['liquidacion']);
    }

    public function test_calcular_simulacion_rechaza_un_anio_sin_vigencia(): void
    {
        $request = Request::create('/renta/2030/calculadora/calcular', 'POST');
        $response = $this->controller->calcularSimulacion($request, 2030);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('2030', $response->getData(true)['message']);
    }

    public function test_calcular_simulacion_no_obliga_si_montos_son_menores_a_topes(): void
    {
        $request = Request::create('/renta/2025/calculadora/calcular', 'POST', [
            'patrimonio_bruto_cop' => 40000000,
            'ingresos_brutos_cop' => 30000000,
            'consumos_tarjeta_cop' => 5000000,
            'compras_totales_cop' => 10000000,
            'consignaciones_cop' => 12000000,
        ]);

        $response = $this->controller->calcularSimulacion($request, 2025);
        $data = $response->getData(true);

        $this->assertFalse($data['obligacion']['esta_obligado']);
        $this->assertEquals('NO_OBLIGADO', $data['obligacion']['veredicto']);
        $this->assertCount(0, $data['obligacion']['conceptos_que_obligan']);
    }
}
