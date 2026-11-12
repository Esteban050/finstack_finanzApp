<?php

use Database\Seeders\DemoDeclaracionRentaSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración para poblar el escenario de prueba del Módulo de Renta (MOD-07).
 *
 * Registra para los usuarios existentes los ingresos, gastos e inversiones del año gravable 2025,
 * de modo que al consultar el módulo en 2026 se evalúen los 6 topes de la DIAN y se determine
 * la obligación formal de declarar junto con la estimación del impuesto a pagar.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $seeder = new DemoDeclaracionRentaSeeder();
        $seeder->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Limpiar registros del año gravable 2025 generados por el escenario
        DB::table('declaraciones_renta')->where('anio_gravable', 2025)->delete();
        DB::table('ingresos')->whereBetween('fecha_recepcion', ['2025-01-01', '2025-12-31'])->delete();
        DB::table('gastos')->whereBetween('fecha', ['2025-01-01', '2025-12-31'])->delete();
    }
};

