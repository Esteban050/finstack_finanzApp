<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Asegurar parámetros normativos requeridos para 2026 en parametros_normativos
        $adminId = DB::table('usuarios')->where('rol', 'ADMIN')->value('id')
            ?? DB::table('usuarios')->value('id');

        $existeNormativo2026 = DB::table('parametros_normativos')
            ->where('anio_vigencia', 2026)
            ->exists();

        if (! $existeNormativo2026) {
            DB::table('parametros_normativos')->insert([
                'id' => DB::raw('gen_random_uuid()'),
                'anio_vigencia' => 2026,
                'version' => 1,
                'es_activa' => true,
                'acto_administrativo' => 'Res. DIAN 000238 de 2025',
                'fecha_acto' => '2025-12-15',
                'valor_uvt' => 52374.00,
                'valor_smmlv' => 1550000.00,
                'tarifa_salud_indep' => 12.50,
                'tarifa_pension_indep' => 16.00,
                'tarifa_salud_emp' => 4.00,
                'tarifa_pension_emp' => 4.00,
                'porc_ibc_indep' => 40.00,
                'porc_ibc_integral' => 70.00,
                'ibc_min_smmlv' => 1.00,
                'ibc_max_smmlv' => 25.00,
                'tope_patrimonio_uvt' => 4500,
                'tope_ingresos_uvt' => 1400,
                'tope_tc_uvt' => 1400,
                'tope_compras_uvt' => 1400,
                'tope_consignaciones_uvt' => 1400,
                'limite_renta_exenta_25_uvt' => 790,
                'porc_limite_global_exentas' => 40.00,
                'limite_global_exentas_uvt' => 1340,
                'tarifa_gmf' => 0.40,
                'exencion_gmf_uvt' => 350,
                'ipc_anual' => 5.20,
                'justificacion_cambio' => 'Parámetros proyectados y UVT oficial 2026',
                'creado_por' => $adminId,
                'creado_en' => now(),
            ]);
        }

        // Componente inflacionario 2025: 65.40% según ERS MOD-07
        DB::table('parametro_tributario')
            ->where('clave', 'COMPONENTE_INFLACIONARIO_2025')
            ->update([
                'valor_numerico' => 65.4000,
                'norma_referencia' => 'Decreto Reglamentario de Rendimientos Financieros 2025',
                'estado_verificacion' => 'VERIFICADO',
            ]);

        // 2. Tabla principal: declaraciones_renta (MOD-07)
        Schema::create('declaraciones_renta', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            $table->integer('anio_gravable');
            
            // Estados y veredictos
            $table->string('estado_evaluacion', 30)->default('INCOMPLETO'); // OBLIGADO, NO_OBLIGADO, INCOMPLETO
            $table->boolean('es_preliminar')->default(false); // true si anio en curso consultado antes de 31/12
            $table->string('estado_declaracion', 30)->default('PENDIENTE'); // PENDIENTE, PRESENTADA
            $table->date('fecha_presentacion')->nullable();
            $table->string('numero_radicado_dian', 100)->nullable();

            // C1: Patrimonio bruto al 31/12 (Tope 4.500 UVT)
            $table->decimal('c1_patrimonio_bruto_cop', 18, 2)->default(0);
            $table->decimal('c1_ajuste_manual_cop', 18, 2)->default(0);
            $table->decimal('c1_valor_final_cop', 18, 2)->default(0);

            // C2: Ingresos brutos del año (Tope 1.400 UVT con operador >=)
            $table->decimal('c2_ingresos_brutos_cop', 18, 2)->default(0);
            $table->decimal('c2_ajuste_manual_cop', 18, 2)->default(0);
            $table->decimal('c2_valor_final_cop', 18, 2)->default(0);

            // C3: Consumos con tarjeta de crédito (Tope 1.400 UVT con operador >)
            $table->decimal('c3_consumos_tarjeta_cop', 18, 2)->default(0);

            // C4: Compras y consumos totales (Tope 1.400 UVT con operador >)
            $table->decimal('c4_compras_totales_cop', 18, 2)->default(0);

            // C5: Consignaciones, depósitos o inversiones (Tope 1.400 UVT con operador >)
            $table->decimal('c5_consignaciones_declaradas_cop', 18, 2)->nullable();
            $table->decimal('c5_piso_inversiones_cop', 18, 2)->default(0);
            $table->boolean('c5_confirmado')->default(false);

            // C6: Responsable de IVA
            $table->boolean('c6_responsable_iva')->default(false);

            // Caches json de datos consolidados y estimación 8 pasos
            $table->jsonb('datos_consolidado_cache')->nullable();
            $table->jsonb('datos_estimacion_cache')->nullable();

            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->unique(['usuario_id', 'anio_gravable'], 'uq_declaracion_usuario_anio');
        });

        // 3. Tabla de auditoría: declaracion_renta_ajustes (RF-044 / RN-044.3)
        Schema::create('declaracion_renta_ajustes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('declaracion_renta_id');
            $table->string('concepto', 30); // PATRIMONIO_BRUTO, INGRESOS_BRUTOS
            $table->decimal('valor_anterior_cop', 18, 2);
            $table->decimal('valor_nuevo_cop', 18, 2);
            $table->decimal('monto_ajuste_cop', 18, 2);
            $table->text('justificacion'); // Entre 10 y 500 caracteres
            $table->uuid('usuario_id');
            $table->timestampTz('creado_en')->useCurrent();

            $table->foreign('declaracion_renta_id')->references('id')->on('declaraciones_renta')->cascadeOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });

        // 4. Tabla de recordatorios: declaracion_renta_recordatorios (RF-047 / RN-047.1)
        Schema::create('declaracion_renta_recordatorios', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('declaracion_renta_id');
            $table->integer('dias_antes'); // 30, 15, 5, 1
            $table->date('fecha_programada');
            $table->string('estado', 20)->default('PROGRAMADO'); // PROGRAMADO, ENVIADO, CANCELADO
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('declaracion_renta_id')->references('id')->on('declaraciones_renta')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declaracion_renta_recordatorios');
        Schema::dropIfExists('declaracion_renta_ajustes');
        Schema::dropIfExists('declaraciones_renta');
    }
};
