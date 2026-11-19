<?php

namespace Tests\Unit;

use App\Services\Reportes\IndicadoresSaludService;
use Tests\TestCase;

class IndicadoresSaludServiceTest extends TestCase
{
    protected IndicadoresSaludService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IndicadoresSaludService();
    }

    public function test_constantes_y_aviso_legal_estandarizado_rng_08(): void
    {
        $this->assertStringContainsString('Valores estimados con fines informativos', IndicadoresSaludService::AVISO_LEGAL);
        $this->assertEquals(10.0, IndicadoresSaludService::META_AHORRO_DEFAULT);
        $this->assertEquals(50.0, IndicadoresSaludService::REGLA_50_30_20_DEFAULT['esenciales']);
        $this->assertEquals(30.0, IndicadoresSaludService::REGLA_50_30_20_DEFAULT['discrecionales']);
        $this->assertEquals(20.0, IndicadoresSaludService::REGLA_50_30_20_DEFAULT['ahorro']);
    }

    public function test_calculo_tasa_ahorro_formula(): void
    {
        // Ingresos: 5.000.000, Gastos: 4.000.000 -> Balance: 1.000.000
        // Tasa = (1.000.000 / 5.000.000) * 100 = 20.00%
        $ingresos = '5000000.00';
        $gastos = '4000000.00';
        $balance = bcsub($ingresos, $gastos, 2);
        $tasa = round((float) bcdiv(bcmul($balance, '100', 6), $ingresos, 4), 2);

        $this->assertEquals(20.0, $tasa);
        $this->assertTrue($tasa >= IndicadoresSaludService::META_AHORRO_DEFAULT);
    }

    public function test_calculo_tasa_ahorro_con_deficit(): void
    {
        // Ingresos: 3.000.000, Gastos: 3.500.000 -> Balance: -500.000
        // Tasa = (-500.000 / 3.000.000) * 100 = -16.67%
        $ingresos = '3000000.00';
        $gastos = '3500000.00';
        $balance = bcsub($ingresos, $gastos, 2);
        $tasa = round((float) bcdiv(bcmul($balance, '100', 6), $ingresos, 4), 2);

        $this->assertEquals(-16.67, $tasa);
        $this->assertTrue($tasa < 0);
    }

    public function test_calculo_gasto_diario_promedio(): void
    {
        // Gastos: 3.100.000 en mes de 31 días -> Gasto diario = 100.000
        $gastos = '3100000.00';
        $dias = 31;
        $promedio = round((float) bcdiv($gastos, (string) $dias, 4), 2);

        $this->assertEquals(100000.0, $promedio);
    }

    public function test_calculo_ticket_promedio(): void
    {
        // Gastos: 2.500.000 en 50 transacciones -> Ticket = 50.000
        $gastos = '2500000.00';
        $conteo = 50;
        $ticket = round((float) bcdiv($gastos, (string) $conteo, 4), 2);

        $this->assertEquals(50000.0, $ticket);
    }

    public function test_calculo_concentracion_gasto(): void
    {
        // Categoria max: 1.500.000 sobre 3.000.000 -> 50.0%
        $catMax = '1500000.00';
        $total = '3000000.00';
        $porc = round((float) bcdiv(bcmul($catMax, '100', 6), $total, 4), 1);

        $this->assertEquals(50.0, $porc);
    }
}
