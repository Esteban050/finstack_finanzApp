<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait PreparaVigenciasCalculadora
{
    protected function prepararVigenciasCalculadora(): void
    {
        config([
            'database.default' => 'calculadora_testing',
            'database.connections.calculadora_testing' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ]);

        $topes = [
            'tope_patrimonio_uvt' => 4500,
            'tope_ingresos_uvt' => 1400,
            'tope_tc_uvt' => 1400,
            'tope_compras_uvt' => 1400,
            'tope_consignaciones_uvt' => 1400,
            'limite_global_exentas_uvt' => 1340,
            'limite_renta_exenta_25_uvt' => 790,
        ];
        Schema::create('parametros_normativos', function (Blueprint $table) use ($topes) {
            $table->integer('anio_vigencia')->primary();
            $table->boolean('es_activa');
            $table->decimal('valor_uvt', 8, 2);
            $table->decimal('porc_limite_global_exentas', 5, 2);
            foreach (array_keys($topes) as $campo) {
                $table->integer($campo);
            }
        });
        Schema::create('parametro_tributario', function (Blueprint $table) {
            $table->string('clave')->primary();
            $table->decimal('valor_numerico', 8, 4);
        });
        foreach ([2025 => 49799, 2026 => 52374] as $anio => $uvt) {
            DB::table('parametros_normativos')->insert($topes + [
                'anio_vigencia' => $anio,
                'es_activa' => true,
                'valor_uvt' => $uvt,
                'porc_limite_global_exentas' => 40,
            ]);
        }
    }
}
