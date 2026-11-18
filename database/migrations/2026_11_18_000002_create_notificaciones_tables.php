<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-09 — RF-057 / RF-058 / RF-059: reglas de alerta por usuario y centro
 * de notificaciones.
 *
 * Tablas nuevas del esquema de dominio. No altera ninguna tabla existente.
 * Aplicar solo esta:
 *
 *   php artisan migrate --path=database/migrations/2026_11_18_000002_create_notificaciones_tables.php
 */
return new class extends Migration
{
    public function up(): void
    {
        // RF-057 / RF-059: qué vigilar, con qué límite y si también va por
        // correo. Sin fila se usan los valores por defecto de config/scfp.php.
        Schema::create('reglas_alerta', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            $table->string('tipo', 40);
            $table->boolean('activa')->default(true);
            // RN-059.1: dentro de la aplicación siempre se avisa; el correo es opcional.
            $table->boolean('enviar_correo')->default(false);
            // {"umbrales": [80, 100]} o {"umbral": 110}, según el tipo.
            $table->jsonb('parametros')->default('{}');
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->unique(['usuario_id', 'tipo'], 'uq_reglas_alerta_usuario_tipo');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });

        // RF-058: notificaciones del Usuario. Sin textos: el frontend los arma
        // con el tipo y las cifras de `datos` (RN-057.5).
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            $table->string('tipo', 40);
            // RN-058.3: INFORMATIVA, ADVERTENCIA o CRITICA.
            $table->string('prioridad', 15);
            // RN-058.3: NO_LEIDA, LEIDA o ARCHIVADA.
            $table->string('estado', 15)->default('NO_LEIDA');
            // RN-057.2: identifica el evento (tipo, línea, mes, umbral) para no repetirlo.
            $table->string('clave', 200);
            $table->jsonb('datos')->default('{}');
            $table->string('referencia_tipo', 40)->nullable();
            $table->uuid('referencia_id')->nullable();
            $table->timestampTz('leida_en')->nullable();
            $table->timestampTz('archivada_en')->nullable();
            $table->timestampTz('correo_enviado_en')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->unique(['usuario_id', 'clave'], 'uq_notificaciones_usuario_clave');
            $table->index(['usuario_id', 'estado', 'creado_en'], 'ix_notificaciones_bandeja');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE notificaciones ADD CONSTRAINT chk_notificacion_prioridad CHECK (prioridad IN ('INFORMATIVA', 'ADVERTENCIA', 'CRITICA'))");
        DB::statement("ALTER TABLE notificaciones ADD CONSTRAINT chk_notificacion_estado CHECK (estado IN ('NO_LEIDA', 'LEIDA', 'ARCHIVADA'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
        Schema::dropIfExists('reglas_alerta');
    }
};
