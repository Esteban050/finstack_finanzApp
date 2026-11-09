<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 9.3 / 9.5.6: Tabla puente valor_deducible_registro hacia el módulo de Ingresos
        Schema::create('valor_deducible_registro', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            $table->uuid('inversion_id');
            $table->uuid('transaccion_id')->nullable();
            $table->integer('anio');
            $table->string('concepto', 40); // APORTE_FPV, CESANTIAS_EXENTA, UTILIDAD_NO_GRAVADA_BVC, RETENCION_DIVIDENDOS, AJUSTE_FPV_RETIRO
            $table->decimal('valor', 18, 2);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->index(['usuario_id', 'anio'], 'ix_deducible_usuario_anio');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('inversion_id')->references('id')->on('inversiones')->cascadeOnDelete();
            $table->foreign('transaccion_id')->references('id')->on('movimientos_inversion')->nullOnDelete();
        });

        // 9.3 / 9.5.7: Tabla puente ingreso_inversion hacia el módulo de Ingresos
        Schema::create('ingreso_inversion', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            $table->uuid('inversion_id');
            $table->uuid('transaccion_id')->nullable();
            $table->integer('anio');
            $table->string('tipo', 30); // RENDIMIENTO, UTILIDAD_VENTA, DIVIDENDO
            $table->decimal('valor_bruto', 18, 2);
            $table->decimal('retencion', 18, 2)->default(0);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->index(['usuario_id', 'anio'], 'ix_ingreso_inv_usuario_anio');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('inversion_id')->references('id')->on('inversiones')->cascadeOnDelete();
            $table->foreign('transaccion_id')->references('id')->on('movimientos_inversion')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingreso_inversion');
        Schema::dropIfExists('valor_deducible_registro');
    }
};
