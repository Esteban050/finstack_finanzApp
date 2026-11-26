<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compra con tarjeta de crédito: número de cuotas (1 por defecto) y si es sin intereses.
 * Cada compra se amortiza por su cuenta en el plan de la tarjeta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->smallInteger('numero_cuotas')->default(1);
            $table->boolean('sin_intereses')->default(false);
        });
        DB::statement('ALTER TABLE gastos ADD CONSTRAINT gastos_numero_cuotas_minimo CHECK (numero_cuotas >= 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE gastos DROP CONSTRAINT IF EXISTS gastos_numero_cuotas_minimo');
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropColumn(['numero_cuotas', 'sin_intereses']);
        });
    }
};
