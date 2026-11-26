<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Otro banco": una cuenta puede registrar el nombre de un banco que no está en el
 * catálogo global, sin insertarlo allí. Exactamente uno de entidad_financiera_id o
 * banco_otro identifica el banco de la cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE cuentas_bancarias ALTER COLUMN entidad_financiera_id DROP NOT NULL');
        DB::statement('ALTER TABLE cuentas_bancarias ADD COLUMN banco_otro VARCHAR(100) NULL');
    }

    public function down(): void
    {
        DB::statement('DELETE FROM cuentas_bancarias WHERE entidad_financiera_id IS NULL');
        DB::statement('ALTER TABLE cuentas_bancarias DROP COLUMN banco_otro');
        DB::statement('ALTER TABLE cuentas_bancarias ALTER COLUMN entidad_financiera_id SET NOT NULL');
    }
};
