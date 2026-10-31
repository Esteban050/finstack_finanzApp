<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inversiones', function (Blueprint $table) {
            $table->decimal('rentabilidad_esperada_porc', 7, 4)->nullable();
            $table->integer('plazo_meses_esperado')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inversiones', function (Blueprint $table) {
            $table->dropColumn(['rentabilidad_esperada_porc', 'plazo_meses_esperado']);
        });
    }
};

