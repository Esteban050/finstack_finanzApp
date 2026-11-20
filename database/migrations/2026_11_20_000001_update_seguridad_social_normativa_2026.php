<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Columnas patronales y parafiscales en parametros_normativos
        Schema::table('parametros_normativos', function (Blueprint $table) {
            if (! Schema::hasColumn('parametros_normativos', 'tarifa_salud_patronal')) {
                $table->decimal('tarifa_salud_patronal', 5, 2)->default(8.50)->after('tarifa_salud_emp');
            }
            if (! Schema::hasColumn('parametros_normativos', 'tarifa_pension_patronal')) {
                $table->decimal('tarifa_pension_patronal', 5, 2)->default(12.00)->after('tarifa_pension_emp');
            }
            if (! Schema::hasColumn('parametros_normativos', 'tarifa_parafiscales_ccf')) {
                $table->decimal('tarifa_parafiscales_ccf', 5, 2)->default(4.00)->after('tarifa_pension_patronal');
            }
            if (! Schema::hasColumn('parametros_normativos', 'tarifa_parafiscales_sena')) {
                $table->decimal('tarifa_parafiscales_sena', 5, 2)->default(2.00)->after('tarifa_parafiscales_ccf');
            }
            if (! Schema::hasColumn('parametros_normativos', 'tarifa_parafiscales_icbf')) {
                $table->decimal('tarifa_parafiscales_icbf', 5, 2)->default(3.00)->after('tarifa_parafiscales_sena');
            }
        });

        // 2. Actualizar o asegurar vigencia 2026 con SMMLV $1.750.905 (RF-01)
        $normativo2026 = DB::table('parametros_normativos')->where('anio_vigencia', 2026)->first();

        if ($normativo2026) {
            DB::table('parametros_normativos')
                ->where('id', $normativo2026->id)
                ->update([
                    'valor_smmlv' => 1750905.00,
                    'ibc_min_smmlv' => 1.00,
                    'ibc_max_smmlv' => 25.00,
                    'tarifa_salud_patronal' => 8.50,
                    'tarifa_pension_patronal' => 12.00,
                    'tarifa_parafiscales_ccf' => 4.00,
                    'tarifa_parafiscales_sena' => 2.00,
                    'tarifa_parafiscales_icbf' => 3.00,
                    'acto_administrativo' => 'Decreto SMMLV 2026 ($1.750.905) / Aplazamiento Reforma Pensional a abril 2027',
                    'justificacion_cambio' => 'Actualización normativa vigencia 2026 (SMMLV $1.750.905, tope 25 SMMLV $43.772.625)',
                ]);
        } else {
            $adminId = DB::table('usuarios')->where('rol', 'ADMIN')->value('id')
                ?? DB::table('usuarios')->value('id');

            DB::table('parametros_normativos')->insert([
                'id' => DB::raw('gen_random_uuid()'),
                'anio_vigencia' => 2026,
                'version' => 1,
                'es_activa' => true,
                'acto_administrativo' => 'Decreto SMMLV 2026 ($1.750.905) / Aplazamiento Reforma Pensional a abril 2027',
                'fecha_acto' => '2025-12-30',
                'valor_uvt' => 52374.00,
                'valor_smmlv' => 1750905.00,
                'tarifa_salud_indep' => 12.50,
                'tarifa_pension_indep' => 16.00,
                'tarifa_salud_emp' => 4.00,
                'tarifa_pension_emp' => 4.00,
                'tarifa_salud_patronal' => 8.50,
                'tarifa_pension_patronal' => 12.00,
                'tarifa_parafiscales_ccf' => 4.00,
                'tarifa_parafiscales_sena' => 2.00,
                'tarifa_parafiscales_icbf' => 3.00,
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
                'justificacion_cambio' => 'Carga oficial vigencia 2026 (SMMLV $1.750.905, tope 25 SMMLV $43.772.625)',
                'creado_por' => $adminId,
                'creado_en' => now(),
            ]);
        }

        // 3. Tabla esquemas_costos_presuntos (UGPP RF-02)
        if (! Schema::hasTable('esquemas_costos_presuntos')) {
            Schema::create('esquemas_costos_presuntos', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $table->string('codigo_actividad', 50)->unique();
                $table->string('nombre_actividad', 150);
                $table->decimal('porcentaje_costo_presunto', 5, 2);
                $table->string('normativa_referencia', 150)->nullable();
                $table->boolean('admite_costos_reales')->default(true);
                $table->boolean('activo')->default(true);
                $table->timestampTz('creado_en')->useCurrent();
                $table->timestampTz('actualizado_en')->useCurrent();
            });

            // Sembrar esquemas oficiales mínimos (RF-02)
            DB::table('esquemas_costos_presuntos')->insert([
                [
                    'id' => DB::raw('gen_random_uuid()'),
                    'codigo_actividad' => 'PRESTACION_SERVICIOS',
                    'nombre_actividad' => 'Prestación de servicios',
                    'porcentaje_costo_presunto' => 0.00,
                    'normativa_referencia' => 'Resolución 532 de 2024 / Decreto 0379 de 2026',
                    'admite_costos_reales' => true,
                    'activo' => true,
                    'creado_en' => now(),
                    'actualizado_en' => now(),
                ],
                [
                    'id' => DB::raw('gen_random_uuid()'),
                    'codigo_actividad' => 'RENTAS_CAPITAL',
                    'nombre_actividad' => 'Rentas de capital (intereses, rendimientos financieros, dividendos)',
                    'porcentaje_costo_presunto' => 28.08,
                    'normativa_referencia' => 'Resolución 532 de 2024 / Decreto 0379 de 2026',
                    'admite_costos_reales' => true,
                    'activo' => true,
                    'creado_en' => now(),
                    'actualizado_en' => now(),
                ],
                [
                    'id' => DB::raw('gen_random_uuid()'),
                    'codigo_actividad' => 'ACTIVIDADES_INMOBILIARIAS',
                    'nombre_actividad' => 'Actividades inmobiliarias (arrendamiento de bienes inmuebles)',
                    'porcentaje_costo_presunto' => 65.70,
                    'normativa_referencia' => 'Resolución 532 de 2024 / Decreto 0379 de 2026',
                    'admite_costos_reales' => true,
                    'activo' => true,
                    'creado_en' => now(),
                    'actualizado_en' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('esquemas_costos_presuntos');

        Schema::table('parametros_normativos', function (Blueprint $table) {
            $columnas = [
                'tarifa_salud_patronal',
                'tarifa_pension_patronal',
                'tarifa_parafiscales_ccf',
                'tarifa_parafiscales_sena',
                'tarifa_parafiscales_icbf',
            ];
            foreach ($columnas as $col) {
                if (Schema::hasColumn('parametros_normativos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
