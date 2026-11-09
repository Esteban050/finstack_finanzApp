<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_renta_variable', function (Blueprint $table) {
            $table->string('moneda_dividendo', 3)->default('COP')->after('valor_dividendo_por_unidad');
            $table->decimal('trm_dividendo', 18, 4)->nullable()->after('moneda_dividendo');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_renta_variable', function (Blueprint $table) {
            $table->dropColumn(['moneda_dividendo', 'trm_dividendo']);
        });
    }
};

