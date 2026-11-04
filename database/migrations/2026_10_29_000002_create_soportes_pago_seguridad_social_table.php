<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-05 — RN-035.1: soporte del pago PILA, con las reglas de RF-030.
 *
 * Tabla nueva del esquema de dominio. No altera pagos_seguridad_social.
 *
 * El esquema de dominio no tiene migraciones, así que un `php artisan
 * migrate` a secas intentaría correr también las migraciones base de Laravel.
 * Aplicar solo esta:
 *
 *   php artisan migrate --path=database/migrations/2026_10_29_000002_create_soportes_pago_seguridad_social_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soportes_pago_seguridad_social', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            $table->uuid('pago_id');
            // RN-030.1 / RN-030.2: nombre original, tipo validado por contenido y tamaño.
            $table->string('nombre_original', 255);
            $table->string('tipo_mime', 20);
            $table->integer('tamano_bytes');
            // RN-030.3: archivo cifrado en el disco privado.
            $table->string('ruta', 255);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            // RN-035.1: un soporte por pago.
            $table->unique('pago_id', 'uq_soporte_pago');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('pago_id')->references('id')->on('pagos_seguridad_social')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE soportes_pago_seguridad_social ADD CONSTRAINT chk_soporte_tipo CHECK (tipo_mime IN ('application/pdf', 'image/jpeg', 'image/png'))");
        DB::statement('ALTER TABLE soportes_pago_seguridad_social ADD CONSTRAINT chk_soporte_tamano CHECK (tamano_bytes > 0 AND tamano_bytes <= 5242880)');
    }

    public function down(): void
    {
        Schema::dropIfExists('soportes_pago_seguridad_social');
    }
};
