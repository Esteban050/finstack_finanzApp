<?php

namespace Tests\Unit;

use App\Models\Gasto;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsumosConTarjetaTest extends TestCase
{
    public function test_el_indicador_excluye_los_gastos_generados_por_pagos_de_credito(): void
    {
        config([
            'database.default' => 'consumos_testing',
            'database.connections.consumos_testing' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ]);

        Schema::create('gastos', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('medio_pago');
            $table->decimal('monto_cop');
            $table->timestamp('eliminado_en')->nullable();
        });
        Schema::create('pagos_credito', function (Blueprint $table) {
            $table->string('gasto_id')->nullable();
            $table->string('gasto_intereses_id')->nullable();
        });

        DB::table('gastos')->insert([
            ['id' => 'compra', 'medio_pago' => 'TARJETA_CREDITO', 'monto_cop' => '300000'],
            ['id' => 'cuota', 'medio_pago' => 'TARJETA_CREDITO', 'monto_cop' => '100000'],
            ['id' => 'intereses', 'medio_pago' => 'TARJETA_CREDITO', 'monto_cop' => '20000'],
            ['id' => 'efectivo', 'medio_pago' => 'EFECTIVO', 'monto_cop' => '15000'],
        ]);
        DB::table('pagos_credito')->insert(['gasto_id' => 'cuota', 'gasto_intereses_id' => 'intereses']);

        $this->assertSame(['compra'], Gasto::consumosConTarjeta()->pluck('id')->all());
    }
}
