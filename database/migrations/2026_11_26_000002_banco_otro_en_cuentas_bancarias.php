<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Otro banco": una cuenta puede registrar el nombre de un banco que no está en el
 * catálogo global, sin insertarlo allí. Exactamente uno de entidad_financiera_id o
 * banco_otro identifica el banco de la cuenta, y un CHECK lo exige en la base
 * (la aplicación normaliza con CuentaBancariaController::normalizarBanco).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE cuentas_bancarias ALTER COLUMN entidad_financiera_id DROP NOT NULL');
        DB::statement('ALTER TABLE cuentas_bancarias ADD COLUMN banco_otro VARCHAR(100) NULL');
        DB::statement('ALTER TABLE cuentas_bancarias ADD CONSTRAINT cuentas_bancarias_banco_xor CHECK ((entidad_financiera_id IS NULL) <> (banco_otro IS NULL))');
    }

    /**
     * Revertir no borra datos: si hay cuentas con "Otro banco" (sin entidad del catálogo) el
     * rollback se detiene y hay que reasignarlas o eliminarlas a mano antes de reintentar.
     */
    public function down(): void
    {
        if (DB::table('cuentas_bancarias')->whereNull('entidad_financiera_id')->exists()) {
            throw new RuntimeException(
                'No se puede revertir: hay cuentas con "Otro banco" (entidad_financiera_id nulo). '
                .'Asígneles una entidad del catálogo o elimínelas manualmente y vuelva a intentarlo.'
            );
        }

        DB::statement('ALTER TABLE cuentas_bancarias DROP CONSTRAINT IF EXISTS cuentas_bancarias_banco_xor');
        DB::statement('ALTER TABLE cuentas_bancarias DROP COLUMN banco_otro');
        DB::statement('ALTER TABLE cuentas_bancarias ALTER COLUMN entidad_financiera_id SET NOT NULL');
    }
};
