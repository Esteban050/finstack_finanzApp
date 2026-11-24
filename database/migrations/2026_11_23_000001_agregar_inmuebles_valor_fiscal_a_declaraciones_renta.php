<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-044 / Art. 277 ET: los inmuebles no están en los registros de la app, así
 * que el usuario declara su valor fiscal (el mayor entre el costo fiscal y el
 * avalúo o autoavalúo catastral) y se suma al patrimonio bruto del criterio 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declaraciones_renta', function (Blueprint $table) {
            $table->decimal('c1_inmuebles_valor_fiscal_cop', 18, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('declaraciones_renta', function (Blueprint $table) {
            $table->dropColumn('c1_inmuebles_valor_fiscal_cop');
        });
    }
};
