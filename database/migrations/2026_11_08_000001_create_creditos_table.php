<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-08 — Créditos: créditos del Usuario (RF-048) con la tasa periódica
 * guardada (Créditos 7.2). La tabla de amortización (RF-049) se guarda en
 * cuotas_credito, con sus versiones (2026_11_09_000001, Créditos RF-22 / RF-26).
 *
 * Tabla nueva del esquema de dominio. No altera ninguna tabla existente.
 *
 * El esquema de dominio no tiene migraciones, así que un `php artisan
 * migrate` a secas intentaría correr también las migraciones base de Laravel.
 * Aplicar solo esta:
 *
 *   php artisan migrate --path=database/migrations/2026_11_08_000001_create_creditos_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creditos', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('usuario_id');
            // Créditos RN-02: VIVIENDA exige un plazo de 60 a 360 meses.
            $table->string('tipo_credito', 20);
            $table->string('banco', 80);
            // Para identificar el crédito: "Compra de moto".
            $table->string('proposito', 120);
            $table->decimal('monto', 18, 2);
            // Créditos RF-05: la tasa como la escribió el usuario, en %
            // (EA, NOMINAL o MENSUAL = periódica mes vencido).
            $table->string('tipo_tasa', 8);
            $table->decimal('tasa_ingresada', 12, 6);
            // Solo con tipo_tasa NOMINAL: cada cuánto capitaliza (NMV = MENSUAL).
            $table->string('capitalizacion', 12)->nullable();
            // Créditos RF-05 / 7.2: EA y tasa periódica como fracción (0.22 = 22 %),
            // con 18 decimales.
            $table->decimal('tasa_ea', 30, 18);
            $table->decimal('tasa_periodica', 30, 18);
            // Nulo si el plazo llegó directamente en número de cuotas (Créditos RF-11).
            $table->integer('plazo_meses')->nullable();
            $table->string('periodicidad', 12);
            // Créditos RF-11: plazo × periodos por año / 12, o el número recibido.
            $table->integer('numero_cuotas');
            // Créditos 2.2 / 2.4: FRANCES (cuota fija) o ALEMAN (abono constante a capital).
            $table->string('sistema_amortizacion', 8)->default('FRANCES');
            // Desembolso: la cuota 1 vence un periodo después, salvo que se
            // indique la fecha del primer pago.
            $table->date('fecha_inicio');
            // Créditos RF-12 / RF-14: vencimiento de la cuota 1; si no coincide con
            // el regular, esa cuota cobra el interés por días.
            $table->date('fecha_primer_pago')->nullable();
            // Créditos RF-02 / RF-16: seguro de vida deudor en % mensual del saldo
            // y cargo fijo por cuota (cuota de manejo).
            $table->decimal('seguro_porc_mensual', 9, 6)->nullable();
            $table->decimal('cargo_fijo', 18, 2)->nullable();
            // Créditos RN-03: solo vivienda, para la alerta de financiación 70 % / 80 % VIS.
            $table->decimal('valor_inmueble', 18, 2)->nullable();
            $table->boolean('es_vis')->nullable();
            // Créditos RF-39: tasa de mora en EA (fracción), la del extracto. Sin
            // ella no se calcula interés de mora.
            $table->decimal('tasa_mora_ea', 30, 18)->nullable();
            // Créditos RF-26: versión del plan (cuotas_credito) que está vigente.
            $table->smallInteger('version_plan_vigente')->default(1);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();
            // RNG-07: la eliminación de registros financieros es lógica.
            $table->timestampTz('eliminado_en')->nullable();

            $table->index('usuario_id', 'ix_creditos_usuario');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creditos');
    }
};
