<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-08 — Créditos, fase 2: plan de pagos guardado (Créditos RF-22, RF-26),
 * pagos (RF-29) y su detalle de imputación (RF-31).
 *
 * Tablas nuevas del esquema de dominio. No altera ninguna tabla existente:
 * el enlace con el gasto que genera cada pago vive en pagos_credito.
 *
 * Aplicar solo esta (después de 2026_11_08_000001):
 *
 *   php artisan migrate --path=database/migrations/2026_11_09_000001_create_pagos_creditos_tables.php
 */
return new class extends Migration
{
    public function up(): void
    {
        // Créditos RF-22 / RF-26: cuotas programadas con interés y capital
        // separados. El plan vigente son las filas sin reemplazada_en_version;
        // las versiones anteriores se conservan.
        Schema::create('cuotas_credito', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('credito_id');
            $table->uuid('usuario_id');
            $table->smallInteger('version_plan');
            $table->integer('numero');
            $table->date('fecha_vencimiento');
            $table->decimal('saldo_inicial', 18, 2);
            $table->decimal('cuota', 18, 2);
            $table->decimal('interes', 18, 2);
            $table->decimal('capital', 18, 2);
            $table->decimal('saldo_final', 18, 2);
            // Créditos RF-21: seguros y cargos van aparte de la cuota (2.5).
            $table->decimal('seguro', 18, 2)->default(0);
            $table->decimal('cargos', 18, 2)->default(0);
            $table->smallInteger('reemplazada_en_version')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->unique(['credito_id', 'version_plan', 'numero'], 'uq_cuotas_credito_version_numero');
            $table->index(['credito_id', 'reemplazada_en_version'], 'ix_cuotas_credito_vigentes');
            $table->foreign('credito_id')->references('id')->on('creditos')->cascadeOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });

        // Créditos RF-29 / RF-32 / RF-33: pago real. Un reverso no lo borra: lo
        // marca con fecha y motivo.
        Schema::create('pagos_credito', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('credito_id');
            $table->uuid('usuario_id');
            // Orden de registro dentro del crédito (1, 2, 3…), reversados
            // incluidos: define cuál es "el último pago" (RF-33).
            $table->integer('numero');
            $table->date('fecha');
            $table->decimal('valor', 18, 2);
            // Misma lista que gastos.medio_pago: el pago genera su gasto.
            $table->string('medio', 25);
            $table->string('referencia', 60)->nullable();
            // ANTICIPO o ABONO_CAPITAL; nulo si no hubo excedente.
            $table->string('aplicacion_excedente', 14)->nullable();
            // PLAZO o CUOTA, solo con ABONO_CAPITAL.
            $table->string('reducir', 6)->nullable();
            // Versión del plan que generó el abono; el reverso la deshace.
            $table->smallInteger('version_plan_generada')->nullable();
            // Gasto del pago y, en vivienda, el de los intereses (deducibles).
            $table->uuid('gasto_id')->nullable();
            $table->uuid('gasto_intereses_id')->nullable();
            $table->timestampTz('reversado_en')->nullable();
            $table->string('motivo_reverso', 200)->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->unique(['credito_id', 'numero'], 'uq_pagos_credito_numero');
            $table->foreign('credito_id')->references('id')->on('creditos')->cascadeOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('gasto_id')->references('id')->on('gastos')->nullOnDelete();
            $table->foreign('gasto_intereses_id')->references('id')->on('gastos')->nullOnDelete();
        });

        // Créditos RF-31 / 7.1: qué parte de cada pago fue a cada concepto y
        // cuota. No se edita; se corrige con un reverso.
        Schema::create('imputaciones_pago_credito', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('pago_id');
            // Nulo en el abono extraordinario: va al saldo, no a una cuota.
            $table->uuid('cuota_id')->nullable();
            // CAPITAL, INTERES_CORRIENTE, INTERES_MORA, SEGURO, CARGO o ABONO_EXTRAORDINARIO.
            $table->string('concepto', 24);
            $table->decimal('valor', 18, 2);
            $table->timestampTz('creado_en')->useCurrent();

            $table->index('pago_id', 'ix_imputaciones_pago');
            $table->index('cuota_id', 'ix_imputaciones_cuota');
            $table->foreign('pago_id')->references('id')->on('pagos_credito')->cascadeOnDelete();
            // Diferida hasta el final de la transacción: al borrar un crédito, la
            // cascada elimina sus cuotas antes que las imputaciones de sus pagos, y
            // la verificación inmediata fallaba aunque al final no quede nada huérfano.
            $table->foreign('cuota_id')->references('id')->on('cuotas_credito')->deferrable()->initiallyImmediate(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imputaciones_pago_credito');
        Schema::dropIfExists('pagos_credito');
        Schema::dropIfExists('cuotas_credito');
    }
};
