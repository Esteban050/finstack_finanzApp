<?php

namespace Tests\Unit;

use App\Services\Renta\CalculadoraRentaService;
use Tests\Support\PreparaVigenciasCalculadora;
use Tests\TestCase;

class CalculadoraRentaServiceTest extends TestCase
{
    use PreparaVigenciasCalculadora;
    protected CalculadoraRentaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararVigenciasCalculadora();
        $this->service = new CalculadoraRentaService();
    }

    public function test_no_obligado_cuando_todos_los_montos_estan_en_cero_o_bajos(): void
    {
        $datos = [
            'patrimonio_bruto_cop' => 50000000,
            'ingresos_brutos_cop' => 20000000,
            'consumos_tarjeta_cop' => 5000000,
            'compras_totales_cop' => 10000000,
            'consignaciones_cop' => 15000000,
        ];

        $resultado = $this->service->evaluarObligacion($datos, 2025);

        $this->assertFalse($resultado['esta_obligado']);
        $this->assertEquals('NO_OBLIGADO', $resultado['veredicto']);
        $this->assertCount(0, $resultado['conceptos_que_obligan']);
        $this->assertEquals(0, $resultado['criterios_cumplidos_conteo']);
    }

    public function test_obligado_unicamente_por_patrimonio_mayor_a_4500_uvt(): void
    {
        // 4500 * 49.799 = 224.095.500
        $datos = [
            'patrimonio_bruto_cop' => 224095501, // Supera por 1 peso
            'ingresos_brutos_cop' => 0,
            'consumos_tarjeta_cop' => 0,
            'compras_totales_cop' => 0,
            'consignaciones_cop' => 0,
        ];

        $resultado = $this->service->evaluarObligacion($datos, 2025);

        $this->assertTrue($resultado['esta_obligado']);
        $this->assertEquals('OBLIGADO', $resultado['veredicto']);
        $this->assertCount(1, $resultado['conceptos_que_obligan']);
        $this->assertEquals('patrimonio', $resultado['conceptos_que_obligan'][0]['codigo']);
        $this->assertEquals(1.0, $resultado['conceptos_que_obligan'][0]['exceso_cop']);
    }

    public function test_obligado_por_ingresos_con_operador_mayor_o_igual_a_1400_uvt(): void
    {
        // 1400 * 49.799 = 69.718.600
        $datos = [
            'patrimonio_bruto_cop' => 0,
            'ingresos_brutos_cop' => 69718600, // Exactamente igual al tope
            'consumos_tarjeta_cop' => 0,
            'compras_totales_cop' => 0,
            'consignaciones_cop' => 0,
        ];

        $resultado = $this->service->evaluarObligacion($datos, 2025);

        $this->assertTrue($resultado['esta_obligado']);
        $this->assertCount(1, $resultado['conceptos_que_obligan']);
        $this->assertEquals('ingresos', $resultado['conceptos_que_obligan'][0]['codigo']);
    }

    public function test_obligado_por_consumos_con_tarjeta_de_credito_mayor_a_1400_uvt(): void
    {
        // 1400 * 49.799 = 69.718.600
        $datos = [
            'patrimonio_bruto_cop' => 0,
            'ingresos_brutos_cop' => 0,
            'consumos_tarjeta_cop' => 70000000,
            'compras_totales_cop' => 0,
            'consignaciones_cop' => 0,
        ];

        $resultado = $this->service->evaluarObligacion($datos, 2025);

        $this->assertTrue($resultado['esta_obligado']);
        $this->assertCount(1, $resultado['conceptos_que_obligan']);
        $this->assertEquals('tarjetas', $resultado['conceptos_que_obligan'][0]['codigo']);
    }

    public function test_obligado_por_compras_totales_mayor_a_1400_uvt(): void
    {
        $datos = [
            'patrimonio_bruto_cop' => 0,
            'ingresos_brutos_cop' => 0,
            'consumos_tarjeta_cop' => 0,
            'compras_totales_cop' => 80000000,
            'consignaciones_cop' => 0,
        ];

        $resultado = $this->service->evaluarObligacion($datos, 2025);

        $this->assertTrue($resultado['esta_obligado']);
        $this->assertCount(1, $resultado['conceptos_que_obligan']);
        $this->assertEquals('compras', $resultado['conceptos_que_obligan'][0]['codigo']);
    }

    public function test_obligado_por_consignaciones_bancarias_mayor_a_1400_uvt(): void
    {
        $datos = [
            'patrimonio_bruto_cop' => 0,
            'ingresos_brutos_cop' => 0,
            'consumos_tarjeta_cop' => 0,
            'compras_totales_cop' => 0,
            'consignaciones_cop' => 75000000,
        ];

        $resultado = $this->service->evaluarObligacion($datos, 2025);

        $this->assertTrue($resultado['esta_obligado']);
        $this->assertCount(1, $resultado['conceptos_que_obligan']);
        $this->assertEquals('consignaciones', $resultado['conceptos_que_obligan'][0]['codigo']);
    }

    public function test_identifica_multiples_conceptos_que_obligan(): void
    {
        // 1400 UVT = 69.718.600 en 2025
        $datos = [
            'patrimonio_bruto_cop' => 100000000,
            'ingresos_brutos_cop' => 75000000,  // Supera
            'consumos_tarjeta_cop' => 70000000, // Supera
            'compras_totales_cop' => 50000000,
            'consignaciones_cop' => 85000000,   // Supera
        ];

        $resultado = $this->service->evaluarObligacion($datos, 2025);

        $this->assertTrue($resultado['esta_obligado']);
        $this->assertEquals(3, $resultado['criterios_cumplidos_conteo']);
        
        $codigosSuperados = array_column($resultado['conceptos_que_obligan'], 'codigo');
        $this->assertContains('ingresos', $codigosSuperados);
        $this->assertContains('tarjetas', $codigosSuperados);
        $this->assertContains('consignaciones', $codigosSuperados);
        $this->assertNotContains('patrimonio', $codigosSuperados);
        $this->assertNotContains('compras', $codigosSuperados);
    }

    public function test_conceptos_depuracion_son_totalmente_opcionales(): void
    {
        // Solo ingresa ingresos brutos, sin especificar salud, pensión, FPV ni retenciones
        $datos = [
            'ingresos_brutos_cop' => 100000000,
        ];

        $resultado = $this->service->calcularLiquidacion($datos, 2025);

        $this->assertEquals(100000000, $resultado['ingresos_brutos_cop']);
        $this->assertEquals(0, $resultado['incrngo_total_cop']);
        $this->assertEquals(100000000, $resultado['renta_liquida_cop']);
        // 25% exento laboral aplicado automáticamente: 25.000.000 (menor a 790 UVT = 39.341.210 y menor a 40% = 40.000.000)
        $this->assertEquals(25000000, $resultado['exenta_25_laboral_cop']);
        $this->assertEquals(75000000, $resultado['renta_liquida_gravable_cop']);
        $this->assertGreaterThan(0, $resultado['impuesto_cop']);
        $this->assertEquals('SALDO_A_PAGAR', $resultado['tipo_resultado']);
    }

    public function test_liquidacion_con_ingreso_bajo_arroja_impuesto_cero(): void
    {
        // 1090 UVT * 49.799 = 54.280.910
        // Con RLG <= 1090 UVT, impuesto es 0
        $datos = [
            'ingresos_brutos_cop' => 50000000,
        ];

        $resultado = $this->service->calcularLiquidacion($datos, 2025);

        $this->assertEquals(0.0, $resultado['impuesto_cop']);
        $this->assertEquals('0%', $resultado['tarifa_marginal']);
        $this->assertEquals('CERO', $resultado['tipo_resultado']);
    }

    public function test_liquidacion_con_retenciones_mayores_al_impuesto_genera_saldo_a_favor(): void
    {
        $datos = [
            'ingresos_brutos_cop' => 80000000,
            'retenciones_fuente_cop' => 10000000, // Retención alta
        ];

        $resultado = $this->service->calcularLiquidacion($datos, 2025);

        $this->assertEquals('SALDO_A_FAVOR', $resultado['tipo_resultado']);
        $this->assertStringContainsString('a favor', strtolower($resultado['saldo_label']));
        $this->assertGreaterThan(0, $resultado['saldo_valor']);
    }

    public function test_calcular_completo_integra_obligacion_y_liquidacion(): void
    {
        $datos = [
            'patrimonio_bruto_cop' => 300000000, // Supera 4500 UVT
            'ingresos_brutos_cop' => 90000000,   // Supera 1400 UVT
            'salud_pension_cop' => 7200000,
            'retenciones_fuente_cop' => 1500000,
        ];

        $simulacion = $this->service->calcularCompleto($datos, 2025);

        $this->assertArrayHasKey('obligacion', $simulacion);
        $this->assertArrayHasKey('liquidacion', $simulacion);
        $this->assertTrue($simulacion['obligacion']['esta_obligado']);
        $this->assertCount(2, $simulacion['obligacion']['conceptos_que_obligan']);
        $this->assertGreaterThan(0, $simulacion['liquidacion']['impuesto_cop']);
    }

    public function test_evaluacion_en_anio_2026_usa_uvt_2026(): void
    {
        // 2026 UVT = 52.374
        // 1400 UVT = 73.323.600
        $datos = [
            'ingresos_brutos_cop' => 70000000, // En 2025 superaría (69.718.600), pero en 2026 NO supera (73.323.600)
        ];

        $res2025 = $this->service->evaluarObligacion($datos, 2025);
        $res2026 = $this->service->evaluarObligacion($datos, 2026);

        $this->assertTrue($res2025['esta_obligado']);
        $this->assertFalse($res2026['esta_obligado']);
        $this->assertEquals(52374.0, $res2026['uvt']);
    }
}
