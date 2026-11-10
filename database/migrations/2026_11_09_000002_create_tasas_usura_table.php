<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-08 — Créditos RN-01 / RF-07: tasa de usura por modalidad y vigencia,
 * certificada por la Superintendencia Financiera. La carga el Administrador;
 * no se siembran valores.
 *
 * Tabla nueva del esquema de dominio. Aplicar solo esta:
 *
 *   php artisan migrate --path=database/migrations/2026_11_09_000002_create_tasas_usura_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasas_usura', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            // CONSUMO_ORDINARIO, MICROCREDITO o CONSUMO_BAJO_MONTO.
            $table->string('modalidad', 30);
            // % EA certificado: 25.5200 = 25,52 % EA.
            $table->decimal('tasa_ea', 7, 4);
            $table->date('vigente_desde');
            $table->date('vigente_hasta');
            // Resolución de la Superintendencia Financiera que la certifica.
            $table->string('acto_administrativo', 120);
            $table->uuid('registrado_por')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->unique(['modalidad', 'vigente_desde'], 'uq_tasas_usura_modalidad_desde');
            $table->foreign('registrado_por')->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasas_usura');
    }
};
