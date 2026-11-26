<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tarjetas de crédito: día de corte (extracto) y día de pago del ciclo de facturación.
 * Nulos en las tarjetas anteriores a este cambio, que conservan su comportamiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->smallInteger('dia_corte')->nullable();
            $table->smallInteger('dia_pago')->nullable();
        });
        DB::statement('ALTER TABLE creditos ADD CONSTRAINT creditos_dia_corte_rango CHECK (dia_corte IS NULL OR dia_corte BETWEEN 1 AND 31)');
        DB::statement('ALTER TABLE creditos ADD CONSTRAINT creditos_dia_pago_rango CHECK (dia_pago IS NULL OR dia_pago BETWEEN 1 AND 31)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE creditos DROP CONSTRAINT IF EXISTS creditos_dia_corte_rango');
        DB::statement('ALTER TABLE creditos DROP CONSTRAINT IF EXISTS creditos_dia_pago_rango');
        Schema::table('creditos', function (Blueprint $table) {
            $table->dropColumn(['dia_corte', 'dia_pago']);
        });
    }
};
