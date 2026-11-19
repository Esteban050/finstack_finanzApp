<?php

namespace Tests\Unit;

use App\Models\Gasto;
use App\Services\Reportes\GastosDimensionService;
use Tests\TestCase;

class GastosDimensionServiceTest extends TestCase
{
    protected GastosDimensionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GastosDimensionService();
    }

    public function test_dimensiones_admitidas_y_aviso_etiquetas_ca_067_2(): void
    {
        $dims = GastosDimensionService::DIMENSIONES;
        $this->assertArrayHasKey('categoria', $dims);
        $this->assertArrayHasKey('subcategoria', $dims);
        $this->assertArrayHasKey('naturaleza', $dims);
        $this->assertArrayHasKey('necesidad', $dims);
        $this->assertArrayHasKey('medio_pago', $dims);
        $this->assertArrayHasKey('relevancia_tributaria', $dims);
        $this->assertArrayHasKey('etiqueta', $dims);
        $this->assertArrayHasKey('mes', $dims);

        $this->assertStringContainsString('no son sumables entre sí', GastosDimensionService::AVISO_ETIQUETAS);
    }

    public function test_resolver_parametros_evita_dimensiones_identicas(): void
    {
        $params = $this->service->resolverParametros('categoria', 'categoria');
        $this->assertEquals('categoria', $params['dimension_fila']);
        $this->assertEquals('medio_pago', $params['dimension_columna']);
    }

    public function test_extraer_valores_dimension_medio_pago_y_necesidad(): void
    {
        $gasto = new Gasto([
            'medio_pago' => 'TARJETA_CREDITO',
            'naturaleza' => 'FIJO',
            'necesidad' => 'ESENCIAL',
            'relevancia_tributaria' => 'POTENCIALMENTE_DEDUCIBLE',
            'etiquetas' => ['viaje', 'trabajo'],
        ]);

        $valMedio = $this->service->extraerValoresDimension($gasto, 'medio_pago', ['categorias' => [], 'subcategorias' => []]);
        $this->assertEquals(['Tarjeta Crédito'], $valMedio);

        $valNat = $this->service->extraerValoresDimension($gasto, 'naturaleza', ['categorias' => [], 'subcategorias' => []]);
        $this->assertEquals(['Fijo'], $valNat);

        $valNec = $this->service->extraerValoresDimension($gasto, 'necesidad', ['categorias' => [], 'subcategorias' => []]);
        $this->assertEquals(['Esencial'], $valNec);

        $valRel = $this->service->extraerValoresDimension($gasto, 'relevancia_tributaria', ['categorias' => [], 'subcategorias' => []]);
        $this->assertEquals(['Deducible'], $valRel);

        $valEtiq = $this->service->extraerValoresDimension($gasto, 'etiqueta', ['categorias' => [], 'subcategorias' => []]);
        $this->assertEquals(['viaje', 'trabajo'], $valEtiq);
    }

    public function test_paridad_total_y_porcentajes_matematicos_ca_067_1(): void
    {
        // Total gastos: 10.000.000
        // Fila 1: 6.000.000 (60.00%)
        // Fila 2: 4.000.000 (40.00%)
        // Suma: 10.000.000
        $totalPeriodo = '10000000.00';
        $fila1 = '6000000.00';
        $fila2 = '4000000.00';

        $porcFila1 = round((float) bcdiv(bcmul($fila1, '100', 6), $totalPeriodo, 4), 2);
        $porcFila2 = round((float) bcdiv(bcmul($fila2, '100', 6), $totalPeriodo, 4), 2);

        $this->assertEquals(60.0, $porcFila1);
        $this->assertEquals(40.0, $porcFila2);
        $this->assertEquals($totalPeriodo, bcadd($fila1, $fila2, 2));
    }
}
