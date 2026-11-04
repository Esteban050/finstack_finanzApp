<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-05 — Seguridad social: liquidaciones estimadas (RF-033, RN-033.7) y pagos
 * reales hechos por PILA (RF-035).
 *
 * Tablas nuevas del esquema de dominio. No altera ninguna tabla existente.
 *
 * El esquema de dominio no tiene migraciones, así que un `php artisan
 * migrate` a secas intentaría correr también las migraciones base de Laravel.
 * Aplicar solo esta:
 *
 *   php artisan migrate --path=database/migrations/2026_10_28_000001_create_seguridad_social_tables.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidaciones_seguridad_social', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            // RN-033.7: periodo de cotización AAAA-MM.
            $table->char('periodo', 7);
            // RN-033.1: suma de los ingresos registrados o valor ingresado a mano.
            $table->string('origen_ingreso', 15);
            $table->decimal('ingreso_base', 18, 2);
            $table->decimal('ibc', 18, 2);
            $table->decimal('salud', 18, 2);
            $table->decimal('pension', 18, 2);
            $table->decimal('fsp', 18, 2);
            $table->decimal('arl', 18, 2);
            $table->decimal('total', 18, 2);
            $table->uuid('parametro_normativo_id');
            // RN-033.7: "con los parámetros usados".
            $table->jsonb('parametros_usados');
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            // RN-033.7: "se puede volver a calcular": recalcular actualiza la fila.
            $table->unique(['usuario_id', 'periodo'], 'uq_liquidacion_usuario_periodo');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('parametro_normativo_id')->references('id')->on('parametros_normativos');
        });

        Schema::create('pagos_seguridad_social', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            // RN-035.1: periodo cotizado AAAA-MM.
            $table->char('periodo', 7);
            $table->date('fecha_pago');
            $table->string('numero_planilla', 20)->nullable();
            // RN-035.2: la planilla de corrección admite un segundo pago del periodo.
            $table->boolean('es_correccion')->default(false);
            $table->decimal('salud', 18, 2);
            $table->decimal('pension', 18, 2);
            $table->decimal('fsp', 18, 2)->default(0);
            $table->decimal('arl', 18, 2)->default(0);
            $table->decimal('intereses_mora', 18, 2)->default(0);
            // RN-035.3: gasto creado automáticamente con el pago.
            $table->uuid('gasto_id')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();
            // RNG-07: la eliminación de registros financieros es lógica.
            $table->timestampTz('eliminado_en')->nullable();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('gasto_id')->references('id')->on('gastos')->nullOnDelete();
        });

        DB::statement("ALTER TABLE liquidaciones_seguridad_social ADD CONSTRAINT chk_liquidacion_periodo CHECK (periodo ~ '^[0-9]{4}-(0[1-9]|1[0-2])$')");
        DB::statement("ALTER TABLE liquidaciones_seguridad_social ADD CONSTRAINT chk_liquidacion_origen CHECK (origen_ingreso IN ('REGISTRADO', 'MANUAL'))");
        DB::statement('ALTER TABLE liquidaciones_seguridad_social ADD CONSTRAINT chk_liquidacion_montos CHECK (ingreso_base >= 0 AND ibc > 0 AND salud >= 0 AND pension >= 0 AND fsp >= 0 AND arl >= 0 AND total >= 0)');

        DB::statement("ALTER TABLE pagos_seguridad_social ADD CONSTRAINT chk_pago_periodo CHECK (periodo ~ '^[0-9]{4}-(0[1-9]|1[0-2])$')");
        DB::statement('ALTER TABLE pagos_seguridad_social ADD CONSTRAINT chk_pago_montos CHECK (salud >= 0 AND pension >= 0 AND fsp >= 0 AND arl >= 0 AND intereses_mora >= 0)');
        // El total lo calcula Postgres, igual que ingresos.monto_neto_cop (RN-021.2).
        DB::statement('ALTER TABLE pagos_seguridad_social ADD COLUMN total numeric(18,2) GENERATED ALWAYS AS (salud + pension + fsp + arl + intereses_mora) STORED');
        DB::statement('ALTER TABLE pagos_seguridad_social ADD CONSTRAINT chk_pago_total CHECK (total > 0)');
        // CA-035.2: un solo pago por periodo, salvo las planillas de corrección.
        DB::statement('CREATE UNIQUE INDEX uq_pago_periodo ON pagos_seguridad_social (usuario_id, periodo) WHERE es_correccion = false AND eliminado_en IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_seguridad_social');
        Schema::dropIfExists('liquidaciones_seguridad_social');
    }
};
