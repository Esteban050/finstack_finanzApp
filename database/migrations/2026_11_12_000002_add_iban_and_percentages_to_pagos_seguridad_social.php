<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_seguridad_social', function (Blueprint $table) {
            $table->decimal('ibc', 18, 2)->nullable()->after('es_correccion');
            $table->decimal('porcentaje_salud', 6, 2)->nullable()->after('ibc');
            $table->decimal('porcentaje_pension', 6, 2)->nullable()->after('porcentaje_salud');
            $table->decimal('porcentaje_fsp', 6, 2)->nullable()->after('porcentaje_pension');
            $table->decimal('porcentaje_arl', 6, 2)->nullable()->after('porcentaje_fsp');
        });
    }

    public function down(): void
    {
        Schema::table('pagos_seguridad_social', function (Blueprint $table) {
            $table->dropColumn(['ibc', 'porcentaje_salud', 'porcentaje_pension', 'porcentaje_fsp', 'porcentaje_arl']);
        });
    }
};
