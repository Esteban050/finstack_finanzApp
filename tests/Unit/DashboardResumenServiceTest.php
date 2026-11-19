<?php

namespace Tests\Unit;

use App\Services\Reportes\DashboardResumenService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DashboardResumenServiceTest extends TestCase
{
    protected DashboardResumenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardResumenService();
    }

    public function test_resolver_periodo_mes_actual_calcula_limites_y_periodo_anterior_equivalente(): void
    {
        $referencia = CarbonImmutable::parse('2026-10-15');
        $periodo = $this->service->resolverPeriodo('mes-actual', null, null, $referencia);

        $this->assertEquals('mes-actual', $periodo['periodo']);
        $this->assertEquals('2026-10-01', $periodo['desde']->format('Y-m-d'));
        $this->assertEquals('2026-10-31', $periodo['hasta']->format('Y-m-d'));
        $this->assertEquals('2026-09-01', $periodo['desde_anterior']->format('Y-m-d'));
        $this->assertEquals('2026-09-30', $periodo['hasta_anterior']->format('Y-m-d'));
        $this->assertEquals(31, $periodo['dias']);
    }

    public function test_resolver_periodo_mes_anterior(): void
    {
        $referencia = CarbonImmutable::parse('2026-10-15');
        $periodo = $this->service->resolverPeriodo('mes-anterior', null, null, $referencia);

        $this->assertEquals('mes-anterior', $periodo['periodo']);
        $this->assertEquals('2026-09-01', $periodo['desde']->format('Y-m-d'));
        $this->assertEquals('2026-09-30', $periodo['hasta']->format('Y-m-d'));
        $this->assertEquals('2026-08-01', $periodo['desde_anterior']->format('Y-m-d'));
        $this->assertEquals('2026-08-31', $periodo['hasta_anterior']->format('Y-m-d'));
    }

    public function test_resolver_periodo_trimestre(): void
    {
        $referencia = CarbonImmutable::parse('2026-10-15'); // T4 (Oct - Dic)
        $periodo = $this->service->resolverPeriodo('trimestre', null, null, $referencia);

        $this->assertEquals('trimestre', $periodo['periodo']);
        $this->assertEquals('2026-10-01', $periodo['desde']->format('Y-m-d'));
        $this->assertEquals('2026-12-31', $periodo['hasta']->format('Y-m-d'));
        $this->assertEquals('2026-07-01', $periodo['desde_anterior']->format('Y-m-d'));
        $this->assertEquals('2026-09-30', $periodo['hasta_anterior']->format('Y-m-d'));
    }

    public function test_resolver_periodo_personalizado(): void
    {
        $periodo = $this->service->resolverPeriodo('personalizado', '2026-10-01', '2026-10-10');

        $this->assertEquals('personalizado', $periodo['periodo']);
        $this->assertEquals('2026-10-01', $periodo['desde']->format('Y-m-d'));
        $this->assertEquals('2026-10-10', $periodo['hasta']->format('Y-m-d'));
        $this->assertEquals(10, $periodo['dias']);
        // Periodo anterior equivalente de 10 días inmediatamente previos
        $this->assertEquals('2026-09-30', $periodo['hasta_anterior']->format('Y-m-d'));
        $this->assertEquals('2026-09-21', $periodo['desde_anterior']->format('Y-m-d'));
    }

    public function test_calcular_variacion_positiva_y_negativa(): void
    {
        // Caso aumento (+ 25.00%)
        $varAumento = $this->service->calcularVariacion('1250000.00', '1000000.00');
        $this->assertEquals('250000.00', $varAumento['variacion_absoluta']);
        $this->assertEquals(25.0, $varAumento['variacion_porc']);
        $this->assertEquals('+', $varAumento['signo']);
        $this->assertEquals('subio', $varAumento['direccion']);

        // Caso disminución (- 20.00%)
        $varBaja = $this->service->calcularVariacion('800000.00', '1000000.00');
        $this->assertEquals('-200000.00', $varBaja['variacion_absoluta']);
        $this->assertEquals(-20.0, $varBaja['variacion_porc']);
        $this->assertEquals('-', $varBaja['signo']);
        $this->assertEquals('bajo', $varBaja['direccion']);

        // Caso igual
        $varIgual = $this->service->calcularVariacion('500000.00', '500000.00');
        $this->assertEquals('0.00', $varIgual['variacion_absoluta']);
        $this->assertEquals(0.0, $varIgual['variacion_porc']);
        $this->assertEquals('igual', $varIgual['direccion']);
    }

    public function test_calcular_variacion_con_periodo_anterior_cero(): void
    {
        $var = $this->service->calcularVariacion('500000.00', '0.00');
        $this->assertEquals('500000.00', $var['variacion_absoluta']);
        $this->assertNull($var['variacion_porc']);
        $this->assertEquals('+', $var['signo']);
    }
}
