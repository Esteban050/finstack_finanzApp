<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RF-017 — Tasa Representativa del Mercado (TRM): pesos colombianos por 1 USD.
 *
 * Tabla nueva del esquema de dominio. No altera ninguna tabla existente.
 *
 * ⚠️ El esquema de dominio no tiene migraciones y la base no tiene tabla
 * `migrations`, así que un `php artisan migrate` a secas intentaría correr
 * también las migraciones base de Laravel. Aplicar solo esta:
 *
 *   php artisan migrate --path=database/migrations/2026_10_27_000001_create_tasas_cambio_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasas_cambio', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->char('moneda', 3)->default('USD');
            // Misma escala que `tasa_cambio_usada` de ingresos y gastos (RNG-02).
            $table->decimal('valor', 18, 4);
            // RN-017.2: la fuente oficial publica rangos que cubren fines de
            // semana y festivos, así que cada fila es un intervalo de vigencia.
            $table->date('vigencia_desde');
            $table->date('vigencia_hasta');
            $table->string('fuente', 20);
            // RN-017.3: la corrección manual del Administrador lleva justificación.
            $table->text('justificacion')->nullable();
            $table->uuid('registrado_por')->nullable();
            // Última actualización: cuándo se consultó la fuente o se corrigió.
            $table->timestampTz('consultado_en');
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->unique(['moneda', 'vigencia_desde'], 'uq_tasas_cambio_moneda_desde');
            $table->foreign('registrado_por')->references('id')->on('usuarios')->nullOnDelete();
        });

        DB::statement("ALTER TABLE tasas_cambio ADD CONSTRAINT tasas_cambio_moneda_check CHECK (moneda ~ '^[A-Z]{3}$' AND moneda <> 'COP')");
        DB::statement('ALTER TABLE tasas_cambio ADD CONSTRAINT tasas_cambio_valor_check CHECK (valor > 0)');
        DB::statement('ALTER TABLE tasas_cambio ADD CONSTRAINT chk_tasas_cambio_vigencia CHECK (vigencia_hasta >= vigencia_desde)');
        DB::statement("ALTER TABLE tasas_cambio ADD CONSTRAINT tasas_cambio_fuente_check CHECK (fuente IN ('DATOS_ABIERTOS', 'MANUAL'))");
        DB::statement("ALTER TABLE tasas_cambio ADD CONSTRAINT chk_tasas_cambio_justificacion CHECK (fuente <> 'MANUAL' OR char_length(TRIM(BOTH FROM justificacion)) >= 20)");
    }

    public function down(): void
    {
        Schema::dropIfExists('tasas_cambio');
    }
};
