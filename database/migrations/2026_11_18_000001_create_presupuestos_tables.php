<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-09 — RF-055 / RF-056: presupuesto mensual por categoría.
 *
 * Una fila por usuario y mes, con sus líneas. El mes siguiente se genera
 * copiando el anterior cuando `repetir_cada_mes` está activo (RN-055.1), con
 * el saldo no usado trasladado si `trasladar_saldo` lo pide (RN-055.5).
 *
 * Tablas nuevas del esquema de dominio. No altera ninguna tabla existente.
 * Aplicar solo esta:
 *
 *   php artisan migrate --path=database/migrations/2026_11_18_000001_create_presupuestos_tables.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            // Siempre el día 1 del mes presupuestado.
            $table->date('mes');
            // RN-055.1: al empezar el mes siguiente se copia este presupuesto.
            $table->boolean('repetir_cada_mes')->default(true);
            // RN-055.5: el saldo no usado pasa a la misma línea del mes siguiente.
            $table->boolean('trasladar_saldo')->default(false);
            // RN-055.4: ingreso neto promedio de los 3 meses completos anteriores,
            // base de las líneas en % del ingreso. Fijo durante el mes.
            $table->decimal('ingreso_base', 18, 2)->default(0);
            // false si se creó antes de empezar el mes: al empezar, la base y las
            // líneas en % se recalculan una vez con los meses ya completos.
            $table->boolean('base_fijada')->default(false);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->unique(['usuario_id', 'mes'], 'uq_presupuestos_usuario_mes');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE presupuestos ADD CONSTRAINT chk_presupuesto_mes_dia_1 CHECK (EXTRACT(DAY FROM mes) = 1)');
        DB::statement('ALTER TABLE presupuestos ADD CONSTRAINT chk_presupuesto_ingreso_base CHECK (ingreso_base >= 0)');

        Schema::create('lineas_presupuesto', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('presupuesto_id');
            // Categoría de primer nivel o subcategoría (RN-055.2).
            $table->uuid('categoria_id');
            // PESOS o PORC_INGRESO (% del ingreso base).
            $table->string('modo_asignacion', 15);
            $table->decimal('porcentaje_ingreso', 5, 2)->nullable();
            // Monto del mes. En PORC_INGRESO = porcentaje × ingreso_base, al peso.
            $table->decimal('monto_asignado', 18, 2);
            // RN-055.5: saldo no usado que llegó del mes anterior.
            $table->decimal('saldo_trasladado', 18, 2)->default(0);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            // CA-055.2: una línea por categoría y periodo.
            $table->unique(['presupuesto_id', 'categoria_id'], 'uq_lineas_presupuesto_categoria');
            $table->foreign('presupuesto_id')->references('id')->on('presupuestos')->cascadeOnDelete();
            $table->foreign('categoria_id')->references('id')->on('categorias')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE lineas_presupuesto ADD CONSTRAINT chk_linea_presupuesto_modo CHECK (modo_asignacion IN ('PESOS', 'PORC_INGRESO'))");
        DB::statement("ALTER TABLE lineas_presupuesto ADD CONSTRAINT chk_linea_presupuesto_porcentaje CHECK (
            (modo_asignacion = 'PESOS' AND porcentaje_ingreso IS NULL)
            OR (modo_asignacion = 'PORC_INGRESO' AND porcentaje_ingreso > 0 AND porcentaje_ingreso <= 100)
        )");
        DB::statement('ALTER TABLE lineas_presupuesto ADD CONSTRAINT chk_linea_presupuesto_monto CHECK (monto_asignado > 0 AND monto_asignado <= 999999999999.99)');
        DB::statement('ALTER TABLE lineas_presupuesto ADD CONSTRAINT chk_linea_presupuesto_saldo CHECK (saldo_trasladado >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('lineas_presupuesto');
        Schema::dropIfExists('presupuestos');
    }
};
