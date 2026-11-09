<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Art. 1.2.4.2.1 Decreto 1625 de 2016 (compilatorio Decreto 2418 de 2013 / Decreto 3715 de 1986):
        // Base mínima diaria de no sujeción a retención sobre rendimientos en cuentas de ahorro: 0.055 UVT diarias.
        DB::table('parametro_tributario')->updateOrInsert(
            ['clave' => 'RETEFUENTE_CUENTA_AHORRO_BASE_DIARIA_UVT'],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'valor_numerico' => 0.0550,
                'unidad' => 'UVT',
                'vigente_desde' => '2013-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Art. 1.2.4.2.1 Decreto 1625 de 2016 (Base mínima diaria 0,055 UVT)',
                'estado_verificacion' => 'VERIFICADO',
            ]
        );
    }

    public function down(): void
    {
        DB::table('parametro_tributario')->where('clave', 'RETEFUENTE_CUENTA_AHORRO_BASE_DIARIA_UVT')->delete();
    }
};

