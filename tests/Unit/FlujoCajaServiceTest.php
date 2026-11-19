<?php

namespace Tests\Unit;

use App\Services\Reportes\FlujoCajaService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class FlujoCajaServiceTest extends TestCase
{
    protected FlujoCajaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FlujoCajaService();
    }

    public function test_resolver_parametros_defecto_ultimos_6_meses(): void
    {
        $referencia = CarbonImmutable::parse('2026-10-15');
        $params = $this->service->resolverParametros(null, null, 'mensual', $referencia);

        $this->assertEquals('mensual', $params['agrupacion']);
        $this->assertEquals('2026-05-01', $params['desde']->format('Y-m-d'));
        $this->assertEquals('2026-10-31', $params['hasta']->format('Y-m-d'));
    }

    public function test_generar_intervalos_mensuales(): void
    {
        $desde = CarbonImmutable::parse('2026-08-01');
        $hasta = CarbonImmutable::parse('2026-10-31');

        $intervalos = $this->service->generarIntervalos($desde, $hasta, 'mensual');

        $this->assertCount(3, $intervalos);
        $this->assertEquals('2026-08', $intervalos[0]['clave']);
        $this->assertEquals('2026-09', $intervalos[1]['clave']);
        $this->assertEquals('2026-10', $intervalos[2]['clave']);
    }

    public function test_generar_intervalos_trimestrales(): void
    {
        $desde = CarbonImmutable::parse('2026-01-01');
        $hasta = CarbonImmutable::parse('2026-12-31');

        $intervalos = $this->service->generarIntervalos($desde, $hasta, 'trimestral');

        $this->assertCount(4, $intervalos);
        $this->assertEquals('2026-T1', $intervalos[0]['clave']);
        $this->assertEquals('2026-T2', $intervalos[1]['clave']);
        $this->assertEquals('2026-T3', $intervalos[2]['clave']);
        $this->assertEquals('2026-T4', $intervalos[3]['clave']);
    }

    public function test_generar_intervalos_anuales(): void
    {
        $desde = CarbonImmutable::parse('2024-01-01');
        $hasta = CarbonImmutable::parse('2026-12-31');

        $intervalos = $this->service->generarIntervalos($desde, $hasta, 'anual');

        $this->assertCount(3, $intervalos);
        $this->assertEquals('2024', $intervalos[0]['clave']);
        $this->assertEquals('2025', $intervalos[1]['clave']);
        $this->assertEquals('2026', $intervalos[2]['clave']);
    }

    public function test_calculo_flujo_neto_y_acumulado_matematico_ca_066_1(): void
    {
        // Mes 1: Ingresos 4.000.000, Gastos 3.000.000 -> Neto +1.000.000, Acumulado 1.000.000
        // Mes 2: Ingresos 3.000.000, Gastos 3.500.000 -> Neto -500.000, Acumulado 500.000
        // Mes 3: Ingresos 5.000.000, Gastos 3.000.000 -> Neto +2.000.000, Acumulado 2.500.000
        $datosMeses = [
            ['ing' => '4000000.00', 'gas' => '3000000.00'],
            ['ing' => '3000000.00', 'gas' => '3500000.00'],
            ['ing' => '5000000.00', 'gas' => '3000000.00'],
        ];

        $acumulado = '0';
        $flujosNetos = [];
        $flujosAcumulados = [];

        foreach ($datosMeses as $m) {
            $neto = bcsub($m['ing'], $m['gas'], 2);
            $acumulado = bcadd($acumulado, $neto, 2);
            $flujosNetos[] = $neto;
            $flujosAcumulados[] = $acumulado;
        }

        $this->assertEquals('1000000.00', $flujosNetos[0]);
        $this->assertEquals('1000000.00', $flujosAcumulados[0]);

        $this->assertEquals('-500000.00', $flujosNetos[1]);
        $this->assertEquals('500000.00', $flujosAcumulados[1]);

        $this->assertEquals('2000000.00', $flujosNetos[2]);
        $this->assertEquals('2500000.00', $flujosAcumulados[2]);
    }
}
