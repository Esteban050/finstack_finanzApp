<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Reportes\ComparativoPeriodosService;
use App\Services\Reportes\DashboardResumenService;
use App\Services\Reportes\FlujoCajaService;
use App\Services\Reportes\GastosDimensionService;
use App\Services\Reportes\IndicadoresSaludService;
use Mockery;
use Tests\TestCase;

class ReportesModuloTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rutas_reportes_requieren_autenticacion(): void
    {
        $this->get('/resumen')->assertRedirect('/login');
        $this->get('/reportes/indicadores')->assertRedirect('/login');
        $this->get('/reportes/flujo-de-caja')->assertRedirect('/login');
        $this->get('/reportes/gastos')->assertRedirect('/login');
        $this->get('/reportes/comparativo')->assertRedirect('/login');
    }

    public function test_dashboard_redirige_a_resumen(): void
    {
        $usuario = new Usuario(['correo' => 'test@scfp.co']);
        $usuario->id = '11111111-1111-1111-1111-111111111111';

        $this->actingAs($usuario)
            ->withoutMiddleware([\App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class])
            ->get('/dashboard')
            ->assertRedirect(route('resumen.index'));
    }

    public function test_endpoint_resumen_responde_json_estructurado(): void
    {
        $mockDashboard = Mockery::mock(DashboardResumenService::class);
        $mockDashboard->shouldReceive('obtenerDashboard')
            ->once()
            ->andReturn([
                'periodo' => ['periodo' => 'mes-actual'],
                'kpis' => [['clave' => 'balance', 'valor' => '1000000.00']],
                'totales' => ['balance' => '1000000.00'],
                'proximos_15_dias' => [],
                'graficos' => [],
            ]);
        $this->app->instance(DashboardResumenService::class, $mockDashboard);

        $usuario = new Usuario(['correo' => 'test@scfp.co']);
        $usuario->id = '11111111-1111-1111-1111-111111111111';

        $response = $this->actingAs($usuario)
            ->withoutMiddleware([\App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class])
            ->getJson('/resumen');

        $response->assertStatus(200)
            ->assertJsonPath('mensaje', 'Dashboard consolidado del periodo.')
            ->assertJsonPath('data.periodo.periodo', 'mes-actual');
    }

    public function test_endpoint_indicadores_responde_json_estructurado(): void
    {
        $mockIndicadores = Mockery::mock(IndicadoresSaludService::class);
        $mockIndicadores->shouldReceive('calcularIndicadores')
            ->once()
            ->andReturn([
                'mes' => '2026-10',
                'indicadores' => [['id' => 'tasa_ahorro', 'valor' => 12.0]],
            ]);
        $this->app->instance(IndicadoresSaludService::class, $mockIndicadores);

        $usuario = new Usuario(['correo' => 'test@scfp.co']);
        $usuario->id = '11111111-1111-1111-1111-111111111111';

        $response = $this->actingAs($usuario)
            ->withoutMiddleware([\App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class])
            ->getJson('/reportes/indicadores?mes=2026-10');

        $response->assertStatus(200)
            ->assertJsonPath('mensaje', 'Indicadores de salud financiera calculados.')
            ->assertJsonPath('data.mes', '2026-10');
    }

    public function test_endpoint_flujo_caja_responde_json_estructurado(): void
    {
        $mockFlujo = Mockery::mock(FlujoCajaService::class);
        $mockFlujo->shouldReceive('consultarFlujoCaja')
            ->once()
            ->andReturn([
                'parametros' => ['agrupacion' => 'mensual'],
                'periodos' => [],
            ]);
        $this->app->instance(FlujoCajaService::class, $mockFlujo);

        $usuario = new Usuario(['correo' => 'test@scfp.co']);
        $usuario->id = '11111111-1111-1111-1111-111111111111';

        $response = $this->actingAs($usuario)
            ->withoutMiddleware([\App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class])
            ->getJson('/reportes/flujo-de-caja');

        $response->assertStatus(200)
            ->assertJsonPath('mensaje', 'Reporte de flujo de caja generado.');
    }

    public function test_endpoint_gastos_dimension_responde_json_estructurado(): void
    {
        $mockGastos = Mockery::mock(GastosDimensionService::class);
        $mockGastos->shouldReceive('consultarGastosDimension')
            ->once()
            ->andReturn([
                'columnas' => ['Efectivo'],
                'filas' => [],
            ]);
        $this->app->instance(GastosDimensionService::class, $mockGastos);

        $usuario = new Usuario(['correo' => 'test@scfp.co']);
        $usuario->id = '11111111-1111-1111-1111-111111111111';

        $response = $this->actingAs($usuario)
            ->withoutMiddleware([\App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class])
            ->getJson('/reportes/gastos');

        $response->assertStatus(200)
            ->assertJsonPath('mensaje', 'Análisis de gastos multidimensional generado.');
    }

    public function test_endpoint_comparativo_responde_json_estructurado(): void
    {
        $mockComp = Mockery::mock(ComparativoPeriodosService::class);
        $mockComp->shouldReceive('comparar')
            ->once()
            ->andReturn([
                'resumen' => [],
                'tabla_categorias' => [],
            ]);
        $this->app->instance(ComparativoPeriodosService::class, $mockComp);

        $usuario = new Usuario(['correo' => 'test@scfp.co']);
        $usuario->id = '11111111-1111-1111-1111-111111111111';

        $response = $this->actingAs($usuario)
            ->withoutMiddleware([\App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class])
            ->getJson('/reportes/comparativo');

        $response->assertStatus(200)
            ->assertJsonPath('mensaje', 'Comparativo entre periodos calculado.');
    }
}
