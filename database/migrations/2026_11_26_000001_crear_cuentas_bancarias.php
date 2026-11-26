<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuentas bancarias de débito/ahorro del usuario.
 *
 * - cuentas_bancarias: banco, saldo inicial y si la cuenta es exenta de GMF (4x1000).
 *   El saldo disponible NO se guarda: se deriva (SaldoCuentaService).
 * - ingresos.cuenta_id / gastos.cuenta_id: cuenta que recibe o de la que sale el dinero.
 * - gastos.gasto_origen_gmf_id: el gasto de GMF generado por la salida de una cuenta (1 a 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignUuid('entidad_financiera_id')->constrained('entidad_financiera');
            $table->string('alias', 80)->nullable();
            $table->decimal('saldo_inicial', 16, 2)->default(0);
            $table->boolean('es_exenta_gmf')->default(false);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();
            $table->timestampTz('eliminado_en')->nullable();
            $table->index(['usuario_id', 'eliminado_en']);
        });

        Schema::table('ingresos', function (Blueprint $table) {
            $table->foreignUuid('cuenta_id')->nullable()->constrained('cuentas_bancarias')->nullOnDelete();
            $table->index('cuenta_id');
        });

        Schema::table('gastos', function (Blueprint $table) {
            $table->foreignUuid('cuenta_id')->nullable()->constrained('cuentas_bancarias')->nullOnDelete();
            $table->foreignUuid('gasto_origen_gmf_id')->nullable()->unique()
                ->constrained('gastos')->nullOnDelete();
            $table->index('cuenta_id');
        });
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gasto_origen_gmf_id');
            $table->dropConstrainedForeignId('cuenta_id');
        });
        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_id');
        });
        Schema::dropIfExists('cuentas_bancarias');
    }
};
