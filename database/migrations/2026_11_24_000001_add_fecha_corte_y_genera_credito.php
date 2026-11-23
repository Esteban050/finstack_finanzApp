<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-04 / MOD-08 — Compra a cuotas con tarjeta de crédito.
 *
 * - creditos.fecha_corte: día de corte de la tarjeta (informativo).
 * - gastos.genera_credito: el crédito vinculado (gastos.credito_id) lo creó el
 *   propio gasto, así que su monto ya es la compra y no se suma de nuevo al
 *   capital (ConsumoTarjetaService). Un gasto vinculado a una tarjeta existente
 *   queda en false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->date('fecha_corte')->nullable();
        });

        Schema::table('gastos', function (Blueprint $table) {
            $table->boolean('genera_credito')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropColumn('genera_credito');
        });

        Schema::table('creditos', function (Blueprint $table) {
            $table->dropColumn('fecha_corte');
        });
    }
};
