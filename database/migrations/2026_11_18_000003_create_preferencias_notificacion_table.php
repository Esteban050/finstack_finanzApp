<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-09 — RN-059.3: preferencias del resumen por correo (frecuencia y
 * bloques). Sin fila, el Usuario no recibe resumen.
 *
 * Tabla nueva del esquema de dominio. No altera ninguna tabla existente.
 * Aplicar solo esta:
 *
 *   php artisan migrate --path=database/migrations/2026_11_18_000003_create_preferencias_notificacion_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preferencias_notificacion', function (Blueprint $table) {
            $table->uuid('usuario_id')->primary();
            // NINGUNA, SEMANAL (lunes, semana anterior) o MENSUAL (día 1, mes anterior).
            $table->string('frecuencia_resumen', 10)->default('NINGUNA');
            // Último periodo enviado ("SEMANAL:2026-11-09"): evita enviar dos veces el mismo.
            $table->string('ultimo_resumen', 30)->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });

        // Bloques del resumen, como arreglo de Postgres (igual que gastos.etiquetas).
        DB::statement("ALTER TABLE preferencias_notificacion ADD COLUMN bloques_resumen character varying(20)[] DEFAULT ARRAY['INGRESOS_GASTOS', 'PRESUPUESTO', 'ALERTAS', 'VENCIMIENTOS']::character varying[] NOT NULL");
        DB::statement("ALTER TABLE preferencias_notificacion ADD CONSTRAINT chk_preferencia_frecuencia CHECK (frecuencia_resumen IN ('NINGUNA', 'SEMANAL', 'MENSUAL'))");
        DB::statement("ALTER TABLE preferencias_notificacion ADD CONSTRAINT chk_preferencia_bloques CHECK (bloques_resumen <@ ARRAY['INGRESOS_GASTOS', 'PRESUPUESTO', 'ALERTAS', 'VENCIMIENTOS']::character varying[])");
    }

    public function down(): void
    {
        Schema::dropIfExists('preferencias_notificacion');
    }
};
