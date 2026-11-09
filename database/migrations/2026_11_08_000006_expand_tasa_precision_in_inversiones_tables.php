<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplía la precisión decimal de tasas de interés de decimal(7,4) a decimal(12,8)
     * para permitir que cálculos financieros y simulaciones bancarias (ej. CDT 11.9000277%)
     * cuadren con exactitud de centavos frente a calculadoras bancarias oficiales.
     */
    public function up(): void
    {
        // 1. inversiones.tasa_ea
        DB::statement('ALTER TABLE inversiones ALTER COLUMN tasa_ea TYPE NUMERIC(12, 8)');

        // 2. detalle_cdt.tasa_valor
        DB::statement('ALTER TABLE detalle_cdt ALTER COLUMN tasa_valor TYPE NUMERIC(12, 8)');

        // 3. detalle_cuenta.tasa_ea
        DB::statement('ALTER TABLE detalle_cuenta ALTER COLUMN tasa_ea TYPE NUMERIC(12, 8)');

        // 4. detalle_cesantias.tasa_interes_esperada
        DB::statement('ALTER TABLE detalle_cesantias ALTER COLUMN tasa_interes_esperada TYPE NUMERIC(12, 8)');

        // 5. simulaciones_inversion.rentabilidad_neta_ea
        DB::statement('ALTER TABLE simulaciones_inversion ALTER COLUMN rentabilidad_neta_ea TYPE NUMERIC(12, 8)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE simulaciones_inversion ALTER COLUMN rentabilidad_neta_ea TYPE NUMERIC(7, 4)');
        DB::statement('ALTER TABLE detalle_cesantias ALTER COLUMN tasa_interes_esperada TYPE NUMERIC(7, 4)');
        DB::statement('ALTER TABLE detalle_cuenta ALTER COLUMN tasa_ea TYPE NUMERIC(7, 4)');
        DB::statement('ALTER TABLE detalle_cdt ALTER COLUMN tasa_valor TYPE NUMERIC(7, 4)');
        DB::statement('ALTER TABLE inversiones ALTER COLUMN tasa_ea TYPE NUMERIC(7, 4)');
    }
};

