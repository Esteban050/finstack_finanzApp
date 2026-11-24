<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-08 / CP-CRE-013 — Cupo de la tarjeta de crédito.
 *
 * - creditos.cupo: cupo total de una tarjeta (solo TARJETA_CREDITO), opcional.
 *   Si el saldo de capital lo supera se avisa; no bloquea el consumo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->decimal('cupo', 14, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->dropColumn('cupo');
        });
    }
};
