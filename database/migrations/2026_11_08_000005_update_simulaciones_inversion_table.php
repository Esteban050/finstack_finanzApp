<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulaciones_inversion', function (Blueprint $table) {
            $table->uuid('inversion_id')->nullable()->after('usuario_id');
            $table->date('fecha_simulacion')->nullable()->after('inversion_id');
            $table->jsonb('alternativas')->nullable()->change();
            $table->jsonb('parametros_entrada')->nullable()->after('alternativas');
            $table->string('version_parametros', 50)->nullable()->after('parametros_entrada');
            $table->decimal('bruto', 18, 2)->nullable()->after('version_parametros');
            $table->decimal('retencion_total', 18, 2)->nullable()->after('bruto');
            $table->decimal('gmf_total', 18, 2)->nullable()->after('retencion_total');
            $table->decimal('comisiones', 18, 2)->nullable()->after('gmf_total');
            $table->decimal('neto', 18, 2)->nullable()->after('comisiones');
            $table->decimal('rentabilidad_neta_ea', 7, 4)->nullable()->after('neto');
            $table->jsonb('detalle_lineas')->nullable()->after('rentabilidad_neta_ea');
            $table->decimal('impuesto_renta_estimado', 18, 2)->nullable()->after('detalle_lineas');

            $table->foreign('inversion_id')->references('id')->on('inversiones')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('simulaciones_inversion', function (Blueprint $table) {
            $table->dropForeign(['inversion_id']);
            $table->dropColumn([
                'inversion_id',
                'fecha_simulacion',
                'parametros_entrada',
                'version_parametros',
                'bruto',
                'retencion_total',
                'gmf_total',
                'comisiones',
                'neto',
                'rentabilidad_neta_ea',
                'detalle_lineas',
                'impuesto_renta_estimado',
            ]);
        });
    }
};
