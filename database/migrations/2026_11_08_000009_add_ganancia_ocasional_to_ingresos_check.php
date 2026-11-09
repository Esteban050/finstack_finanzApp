<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Art. 300 ET: Ganancias ocasionales provenientes de la venta de activos fijos poseídos por 2 o más años.
     */
    public function up(): void
    {
        // 1. Ampliar check en fuentes_ingreso
        DB::statement('ALTER TABLE fuentes_ingreso DROP CONSTRAINT IF EXISTS fuentes_ingreso_clasificacion_tributaria_check');
        DB::statement("ALTER TABLE fuentes_ingreso ADD CONSTRAINT fuentes_ingreso_clasificacion_tributaria_check CHECK (clasificacion_tributaria IN ('RENTAS_TRABAJO', 'RENTAS_CAPITAL', 'RENTAS_NO_LABORALES', 'PENSIONES', 'DIVIDENDOS', 'GANANCIA_OCASIONAL'))");

        // 2. Ampliar check en ingresos
        DB::statement('ALTER TABLE ingresos DROP CONSTRAINT IF EXISTS ingresos_clasificacion_tributaria_check');
        DB::statement("ALTER TABLE ingresos ADD CONSTRAINT ingresos_clasificacion_tributaria_check CHECK (clasificacion_tributaria IN ('RENTAS_TRABAJO', 'RENTAS_CAPITAL', 'RENTAS_NO_LABORALES', 'PENSIONES', 'DIVIDENDOS', 'GANANCIA_OCASIONAL'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE ingresos DROP CONSTRAINT IF EXISTS ingresos_clasificacion_tributaria_check');
        DB::statement("ALTER TABLE ingresos ADD CONSTRAINT ingresos_clasificacion_tributaria_check CHECK (clasificacion_tributaria IN ('RENTAS_TRABAJO', 'RENTAS_CAPITAL', 'RENTAS_NO_LABORALES', 'PENSIONES', 'DIVIDENDOS'))");

        DB::statement('ALTER TABLE fuentes_ingreso DROP CONSTRAINT IF EXISTS fuentes_ingreso_clasificacion_tributaria_check');
        DB::statement("ALTER TABLE fuentes_ingreso ADD CONSTRAINT fuentes_ingreso_clasificacion_tributaria_check CHECK (clasificacion_tributaria IN ('RENTAS_TRABAJO', 'RENTAS_CAPITAL', 'RENTAS_NO_LABORALES', 'PENSIONES', 'DIVIDENDOS'))");
    }
};

