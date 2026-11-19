<?php

namespace Tests\Unit;

use App\Services\Reportes\ComparativoPeriodosService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComparativoPeriodosServiceTest extends TestCase
{
    protected ComparativoPeriodosService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ComparativoPeriodosService();
    }

    public function test_validar_duracion_equivalente_con_diferencia_menor_o_igual_a_3_dias_rn_070_3(): void
    {
        // Periodo A: Septiembre (30 días: 2026-09-01 al 2026-09-30)
        // Periodo B: Octubre (31 días: 2026-10-01 al 2026-10-31)
        // Diferencia: 1 día <= 3 días (Tolerancia admitida)
        $desdeA = CarbonImmutable::parse('2026-09-01');
        $hastaA = CarbonImmutable::parse('2026-09-30');
        $desdeB = CarbonImmutable::parse('2026-10-01');
        $hastaB = CarbonImmutable::parse('2026-10-31');

        $resultado = $this->service->validarDuracionEquivalente($desdeA, $hastaA, $desdeB, $hastaB);

        $this->assertEquals(30, $resultado['dias_a']);
        $this->assertEquals(31, $resultado['dias_b']);
        $this->assertEquals(1, $resultado['diferencia']);
    }

    public function test_validar_duracion_equivalente_rechaza_diferencia_mayor_a_3_dias(): void
    {
        // Periodo A: 10 días (2026-10-01 al 2026-10-10)
        // Periodo B: 20 días (2026-10-01 al 2026-10-20)
        // Diferencia: 10 días > 3 días
        $desdeA = CarbonImmutable::parse('2026-10-01');
        $hastaA = CarbonImmutable::parse('2026-10-10');
        $desdeB = CarbonImmutable::parse('2026-10-01');
        $hastaB = CarbonImmutable::parse('2026-10-20');

        $this->expectException(ValidationException::class);
        $this->service->validarDuracionEquivalente($desdeA, $hastaA, $desdeB, $hastaB);
    }

    public function test_validar_duracion_equivalente_rechaza_fechas_invertidas(): void
    {
        $desdeA = CarbonImmutable::parse('2026-10-15');
        $hastaA = CarbonImmutable::parse('2026-10-10'); // Invertida
        $desdeB = CarbonImmutable::parse('2026-11-01');
        $hastaB = CarbonImmutable::parse('2026-11-10');

        $this->expectException(ValidationException::class);
        $this->service->validarDuracionEquivalente($desdeA, $hastaA, $desdeB, $hastaB);
    }

    public function test_manejo_division_por_cero_muestra_na_ca_070_2(): void
    {
        // Si Periodo A es $0 y Periodo B es $200.000 -> Variación % es "N/A"
        $var = $this->service->calcularVariacion('0.00', '200000.00');

        $this->assertEquals('200000.00', $var['variacion_pesos']);
        $this->assertNull($var['variacion_porc']);
        $this->assertEquals('N/A', $var['variacion_porc_texto']);
        $this->assertEquals('+', $var['signo']);
        $this->assertEquals('aumento', $var['direccion']);
    }

    public function test_calculo_variaciones_aumento_y_disminucion(): void
    {
        // A: 1.000.000, B: 1.200.000 -> +200.000 (+20,00%)
        $varAumento = $this->service->calcularVariacion('1000000.00', '1200000.00');
        $this->assertEquals('200000.00', $varAumento['variacion_pesos']);
        $this->assertEquals(20.0, $varAumento['variacion_porc']);
        $this->assertEquals('+20,00%', $varAumento['variacion_porc_texto']);
        $this->assertEquals('+', $varAumento['signo']);

        // A: 1.000.000, B: 800.000 -> -200.000 (-20,00%)
        $varDisminucion = $this->service->calcularVariacion('1000000.00', '800000.00');
        $this->assertEquals('-200000.00', $varDisminucion['variacion_pesos']);
        $this->assertEquals(-20.0, $varDisminucion['variacion_porc']);
        $this->assertEquals('-20,00%', $varDisminucion['variacion_porc_texto']);
        $this->assertEquals('-', $varDisminucion['signo']);
    }

    public function test_ordenamiento_por_variacion_en_pesos_rn_070_2(): void
    {
        $categorias = [
            ['nombre' => 'Alimentación', 'variacion_pesos' => '-50000.00'],
            ['nombre' => 'Transporte', 'variacion_pesos' => '150000.00'],
            ['nombre' => 'Servicios', 'variacion_pesos' => '300000.00'],
            ['nombre' => 'Entretenimiento', 'variacion_pesos' => '-100000.00'],
        ];

        // Ordenar por variación en pesos descendente (mayor aumento a mayor disminución)
        usort($categorias, fn ($a, $b) => bccomp($b['variacion_pesos'], $a['variacion_pesos'], 2));

        $this->assertEquals('Servicios', $categorias[0]['nombre']);
        $this->assertEquals('Transporte', $categorias[1]['nombre']);
        $this->assertEquals('Alimentación', $categorias[2]['nombre']);
        $this->assertEquals('Entretenimiento', $categorias[3]['nombre']);
    }
}
