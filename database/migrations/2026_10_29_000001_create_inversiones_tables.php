<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-06 — Inversiones y rentabilidad: inversiones (RF-037), sus movimientos
 * (RF-038), sus valoraciones de mercado (RF-039) y las simulaciones guardadas
 * (RF-042).
 *
 * Tablas nuevas del esquema de dominio. No altera ninguna tabla existente.
 *
 * El esquema de dominio no tiene migraciones, así que un `php artisan
 * migrate` a secas intentaría correr también las migraciones base de Laravel.
 * Aplicar solo esta:
 *
 *   php artisan migrate --path=database/migrations/2026_10_29_000001_create_inversiones_tables.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inversiones', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            // RN-037.1
            $table->string('nombre', 60);
            $table->string('tipo', 30);
            $table->string('entidad', 80)->nullable();
            $table->char('moneda', 3);
            $table->date('fecha_apertura');
            $table->decimal('monto_inicial', 18, 2);
            $table->string('nivel_riesgo', 5)->nullable();
            // RN-037.2: CDT y Bonos/TES.
            $table->decimal('tasa_ea', 7, 4)->nullable();
            $table->integer('plazo_dias')->nullable();
            $table->string('periodicidad_intereses', 15)->nullable();
            $table->date('fecha_vencimiento')->nullable();
            // RN-037.3: Acciones, ETF y Criptoactivos, con unidades de 8 decimales.
            $table->string('simbolo', 15)->nullable();
            $table->decimal('unidades_iniciales', 24, 8)->nullable();
            $table->decimal('precio_unitario_compra', 24, 8)->nullable();
            // RN-037.5
            $table->string('estado', 10)->default('ACTIVA');
            $table->date('liquidada_en')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();
            // RNG-07: la eliminación de registros financieros es lógica.
            $table->timestampTz('eliminado_en')->nullable();

            $table->index(['usuario_id', 'estado'], 'ix_inversiones_usuario_estado');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });

        Schema::create('movimientos_inversion', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            $table->uuid('inversion_id');
            // RN-038.1
            $table->string('tipo', 20);
            $table->date('fecha');
            // En la moneda de la inversión, con la tasa usada y el monto en COP
            // (RN-021.3), para convertir el portafolio con cada flujo (RN-041.4).
            $table->decimal('monto', 18, 2);
            $table->decimal('tasa_cambio_usada', 18, 4)->default(1);
            $table->decimal('monto_cop', 18, 2);
            $table->decimal('unidades', 24, 8)->nullable();
            $table->string('nota', 255)->nullable();
            // RN-037.4: el aporte que crea el registro de la inversión; no se elimina.
            $table->boolean('es_inicial')->default(false);
            // RN-038.4: ingreso creado por un rendimiento (y por su retención).
            $table->uuid('ingreso_id')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();
            $table->timestampTz('eliminado_en')->nullable();

            $table->index(['inversion_id', 'fecha'], 'ix_movimientos_inversion_fecha');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('inversion_id')->references('id')->on('inversiones')->cascadeOnDelete();
            $table->foreign('ingreso_id')->references('id')->on('ingresos')->nullOnDelete();
        });

        Schema::create('valoraciones_inversion', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            $table->uuid('inversion_id');
            // RN-039.1
            $table->date('fecha');
            $table->decimal('valor', 18, 2);
            $table->decimal('precio_unitario', 24, 8)->nullable();
            $table->decimal('unidades', 24, 8)->nullable();
            // INICIAL (decisión 7, SC-001), MANUAL, CAUSADO (RN-039.2), LIQUIDACION (RN-038.3).
            $table->string('origen', 12);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            // Una por fecha: registrar otra el mismo día la reemplaza.
            $table->unique(['inversion_id', 'fecha'], 'uq_valoracion_inversion_fecha');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('inversion_id')->references('id')->on('inversiones')->cascadeOnDelete();
        });

        Schema::create('simulaciones_inversion', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            $table->string('nombre', 60);
            // RN-042.1: de 1 a 4 alternativas con sus datos.
            $table->jsonb('alternativas');
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->unique(['usuario_id', 'nombre'], 'uq_simulacion_usuario_nombre');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_nombre CHECK (char_length(TRIM(BOTH FROM nombre)) BETWEEN 3 AND 60)");
        DB::statement("ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_tipo CHECK (tipo IN ('CDT', 'CUENTA_AHORRO_ALTO_RENDIMIENTO', 'FONDO_INVERSION_COLECTIVA', 'ACCIONES', 'ETF', 'BONOS_TES', 'FONDO_VOLUNTARIO_PENSION', 'CRIPTOACTIVO', 'INMUEBLE_RENTA', 'NEGOCIO_PARTICIPACION', 'OTRO'))");
        DB::statement("ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_moneda CHECK (moneda IN ('COP', 'USD'))");
        DB::statement('ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_monto_inicial CHECK (monto_inicial > 0 AND monto_inicial <= 999999999999.99)');
        DB::statement("ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_riesgo CHECK (nivel_riesgo IS NULL OR nivel_riesgo IN ('BAJO', 'MEDIO', 'ALTO'))");
        DB::statement('ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_tasa CHECK (tasa_ea IS NULL OR tasa_ea BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_plazo CHECK (plazo_dias IS NULL OR plazo_dias BETWEEN 1 AND 3650)');
        DB::statement("ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_periodicidad CHECK (periodicidad_intereses IS NULL OR periodicidad_intereses IN ('AL_VENCIMIENTO', 'MENSUAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL'))");
        DB::statement('ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_vencimiento CHECK (fecha_vencimiento IS NULL OR fecha_vencimiento > fecha_apertura)');
        DB::statement('ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_unidades CHECK ((unidades_iniciales IS NULL OR unidades_iniciales > 0) AND (precio_unitario_compra IS NULL OR precio_unitario_compra > 0))');
        DB::statement("ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_estado CHECK (estado IN ('ACTIVA', 'VENCIDA', 'LIQUIDADA'))");

        DB::statement("ALTER TABLE movimientos_inversion ADD CONSTRAINT chk_movimiento_tipo CHECK (tipo IN ('APORTE', 'RETIRO_PARCIAL', 'RENDIMIENTO', 'COMISION', 'RETENCION', 'LIQUIDACION_TOTAL'))");
        // RNG-09; la liquidación total admite 0 si la inversión se perdió.
        DB::statement("ALTER TABLE movimientos_inversion ADD CONSTRAINT chk_movimiento_monto CHECK (monto <= 999999999999.99 AND monto_cop >= 0 AND (monto > 0 OR (tipo = 'LIQUIDACION_TOTAL' AND monto = 0)))");
        DB::statement('ALTER TABLE movimientos_inversion ADD CONSTRAINT chk_movimiento_tasa CHECK (tasa_cambio_usada > 0)');
        DB::statement('ALTER TABLE movimientos_inversion ADD CONSTRAINT chk_movimiento_unidades CHECK (unidades IS NULL OR unidades > 0)');

        DB::statement('ALTER TABLE valoraciones_inversion ADD CONSTRAINT chk_valoracion_valor CHECK (valor >= 0 AND valor <= 999999999999.99)');
        DB::statement("ALTER TABLE valoraciones_inversion ADD CONSTRAINT chk_valoracion_origen CHECK (origen IN ('INICIAL', 'MANUAL', 'CAUSADO', 'LIQUIDACION'))");

        DB::statement("ALTER TABLE simulaciones_inversion ADD CONSTRAINT chk_simulacion_nombre CHECK (char_length(TRIM(BOTH FROM nombre)) BETWEEN 3 AND 60)");
    }

    public function down(): void
    {
        Schema::dropIfExists('simulaciones_inversion');
        Schema::dropIfExists('valoraciones_inversion');
        Schema::dropIfExists('movimientos_inversion');
        Schema::dropIfExists('inversiones');
    }
};
