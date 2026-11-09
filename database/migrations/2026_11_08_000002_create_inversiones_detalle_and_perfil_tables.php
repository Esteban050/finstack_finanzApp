<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RF-PER-01 / RF-PER-03: Perfil tributario por año gravable
        Schema::create('usuario_perfil_tributario', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            $table->integer('anio_gravable');
            $table->boolean('es_declarante')->default(false);
            $table->boolean('es_residente')->default(true);
            $table->boolean('obligado_contabilidad')->default(false);
            $table->decimal('ingreso_anual_estimado', 18, 2)->default(0);
            $table->decimal('ingreso_laboral_promedio_6m', 18, 2)->default(0);
            $table->decimal('tarifa_marginal_estimada', 5, 2)->default(0);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->unique(['usuario_id', 'anio_gravable'], 'uq_perfil_tributario_usuario_anio');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });

        // 1:1 CDT / CDAT
        Schema::create('detalle_cdt', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('inversion_id')->unique();
            $table->decimal('capital', 18, 2);
            $table->decimal('tasa_valor', 7, 4);
            $table->string('tipo_tasa', 10)->default('EA'); // EA, NOMINAL
            $table->string('periodicidad_nominal', 30)->nullable();
            $table->integer('plazo_dias');
            $table->date('fecha_vencimiento');
            $table->string('periodicidad_pago', 20)->default('VENCIMIENTO'); // VENCIMIENTO, MENSUAL, TRIMESTRAL, SEMESTRAL, ANUAL
            $table->boolean('es_cdat')->default(false);
            $table->integer('base_dias')->default(365); // 365, 360
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('inversion_id')->references('id')->on('inversiones')->cascadeOnDelete();
        });

        // 1:1 CUENTA_ALTO_RENDIMIENTO
        Schema::create('detalle_cuenta', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('inversion_id')->unique();
            $table->decimal('saldo_inicial', 18, 2);
            $table->decimal('tasa_ea', 7, 4);
            $table->boolean('es_cuenta_gmf_exenta')->default(false);
            $table->integer('base_dias')->default(365);
            $table->string('periodicidad_abono', 15)->default('MENSUAL'); // MENSUAL, DIARIA
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('inversion_id')->references('id')->on('inversiones')->cascadeOnDelete();
        });

        // 1:1 ACCION_ETF
        Schema::create('detalle_renta_variable', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('inversion_id')->unique();
            $table->string('simbolo', 15);
            $table->string('nombre_instrumento', 100)->nullable();
            $table->string('tipo', 10)->default('ACCION'); // ACCION, ETF
            $table->string('mercado', 15)->default('BVC'); // BVC, EXTERIOR
            $table->boolean('esta_inscrito_bvc')->default(false);
            $table->decimal('porcentaje_circulacion_vendido_anio', 5, 2)->nullable()->default(0);
            $table->decimal('unidades_total', 24, 8);
            $table->decimal('costo_promedio_unitario', 24, 8);
            $table->date('fecha_primera_compra');
            $table->string('tipo_reparto', 20)->default('NO_APLICA'); // DISTRIBUTIVO, ACUMULATIVO, NO_APLICA
            $table->decimal('valor_actual_unidad', 24, 8)->nullable();
            $table->decimal('valor_dividendo_por_unidad', 18, 4)->nullable()->default(0);
            $table->decimal('trm_compra', 18, 4)->default(1);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('inversion_id')->references('id')->on('inversiones')->cascadeOnDelete();
        });

        // 1:1 FIC
        Schema::create('detalle_fic', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('inversion_id')->unique();
            $table->uuid('fic_catalogo_id');
            $table->decimal('unidades', 24, 8);
            $table->decimal('valor_unidad_compra', 24, 8);
            $table->decimal('valor_unidad_actual', 24, 8);
            $table->integer('pacto_permanencia_dias')->nullable();
            $table->decimal('comision_administracion_ea', 5, 2)->nullable()->default(0);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('inversion_id')->references('id')->on('inversiones')->cascadeOnDelete();
            $table->foreign('fic_catalogo_id')->references('id')->on('fic_catalogo')->cascadeOnDelete();
        });

        // 1:1 FPV
        Schema::create('detalle_fpv', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('inversion_id')->unique();
            $table->string('administradora', 80);
            $table->string('tipo_vinculacion', 20)->default('DIRECTO'); // DIRECTO, VIA_EMPLEADOR
            $table->decimal('saldo_actual', 18, 2)->default(0);
            $table->decimal('unidades', 24, 8)->nullable();
            $table->decimal('valor_unidad', 24, 8)->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('inversion_id')->references('id')->on('inversiones')->cascadeOnDelete();
        });

        // 1:1 CESANTIAS
        Schema::create('detalle_cesantias', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('inversion_id')->unique();
            $table->string('fondo', 80);
            $table->string('regimen', 20)->default('ANUALIZADO'); // ANUALIZADO, TRADICIONAL
            $table->decimal('saldo', 18, 2);
            $table->decimal('tasa_interes_esperada', 7, 4)->default(0);
            $table->integer('plazo_dias')->default(365);
            $table->decimal('salario_promedio_6m_override', 18, 2)->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('inversion_id')->references('id')->on('inversiones')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_cesantias');
        Schema::dropIfExists('detalle_fpv');
        Schema::dropIfExists('detalle_fic');
        Schema::dropIfExists('detalle_renta_variable');
        Schema::dropIfExists('detalle_cuenta');
        Schema::dropIfExists('detalle_cdt');
        Schema::dropIfExists('usuario_perfil_tributario');
    }
};
