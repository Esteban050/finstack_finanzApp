<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega los parámetros tributarios oficiales del Artículo 242 del Estatuto Tributario
     * (modificado por la Ley 2277 de 2022) para la retención en la fuente sobre dividendos:
     * - Umbral exento: 1.090 UVT (Tarifa 0%)
     * - Tarifa sobre el exceso: 15%
     */
    public function up(): void
    {
        DB::table('parametro_tributario')->insert([
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'DIVIDENDOS_UMBRAL_UVT_BVC',
                'valor_numerico' => 1090.0000,
                'unidad' => 'UVT',
                'vigente_desde' => '2023-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Parágrafo Art. 242 Estatuto Tributario (Ley 2277 de 2022)',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'DIVIDENDOS_TARIFA_EXCESO_BVC',
                'valor_numerico' => 15.0000,
                'unidad' => 'PORCENTAJE',
                'vigente_desde' => '2023-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Parágrafo Art. 242 Estatuto Tributario (Ley 2277 de 2022)',
                'estado_verificacion' => 'VERIFICADO',
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('parametro_tributario')
            ->whereIn('clave', ['DIVIDENDOS_UMBRAL_UVT_BVC', 'DIVIDENDOS_TARIFA_EXCESO_BVC'])
            ->delete();
    }
};

