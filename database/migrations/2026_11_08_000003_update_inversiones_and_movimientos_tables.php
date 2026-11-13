<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ampliar columnas en inversiones
        Schema::table('inversiones', function (Blueprint $table) {
            $table->uuid('entidad_id')->nullable()->after('entidad');
            $table->foreign('entidad_id')->references('id')->on('entidad_financiera')->nullOnDelete();
        });

        // Vincular entidades existentes por nombre
        $entidades = DB::table('entidad_financiera')->get()->keyBy('nombre');
        $inversionesExistentes = DB::table('inversiones')->get();
        foreach ($inversionesExistentes as $inv) {
            if (!empty($inv->entidad) && isset($entidades[$inv->entidad])) {
                DB::table('inversiones')->where('id', $inv->id)->update([
                    'entidad_id' => $entidades[$inv->entidad]->id,
                ]);
            }
        }

        // Modificar CHECK constraints en inversiones para aceptar los 6 tipos estrictos y el estado CERRADA
        DB::statement('ALTER TABLE inversiones DROP CONSTRAINT IF EXISTS chk_inversion_tipo');
        DB::statement('ALTER TABLE inversiones DROP CONSTRAINT IF EXISTS chk_inversion_estado');

        // Mapear los tipos de los registros existentes
        DB::table('inversiones')->where('tipo', 'CUENTA_AHORRO_ALTO_RENDIMIENTO')->update(['tipo' => 'CUENTA_ALTO_RENDIMIENTO']);
        DB::table('inversiones')->where('tipo', 'ACCIONES')->update(['tipo' => 'ACCION_ETF']);
        DB::table('inversiones')->where('tipo', 'ETF')->update(['tipo' => 'ACCION_ETF']);
        DB::table('inversiones')->where('tipo', 'FONDO_INVERSION_COLECTIVA')->update(['tipo' => 'FIC']);
        DB::table('inversiones')->where('tipo', 'FONDO_VOLUNTARIO_PENSION')->update(['tipo' => 'FPV']);
        DB::table('inversiones')->where('tipo', 'OTRO')->update(['tipo' => 'FIC']);

        DB::statement("ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_tipo CHECK (tipo IN ('CDT', 'ACCION_ETF', 'FIC', 'CUENTA_ALTO_RENDIMIENTO', 'FPV', 'CESANTIAS'))");
        DB::statement("ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_estado CHECK (estado IN ('ACTIVA', 'CERRADA', 'VENCIDA', 'LIQUIDADA'))");

        // Crear los detalles 1:1 para los 3 registros existentes si existen
        $cdt = DB::table('inversiones')->where('nombre', 'CDT')->where('tipo', 'CDT')->first();
        if ($cdt && !DB::table('detalle_cdt')->where('inversion_id', $cdt->id)->exists()) {
            DB::table('detalle_cdt')->insert([
                'id' => DB::raw('gen_random_uuid()'),
                'inversion_id' => $cdt->id,
                'capital' => $cdt->monto_inicial,
                'tasa_valor' => $cdt->tasa_ea ?? 13.0000,
                'tipo_tasa' => 'EA',
                'periodicidad_nominal' => null,
                'plazo_dias' => $cdt->plazo_dias ?? 178,
                'fecha_vencimiento' => $cdt->fecha_vencimiento ?? '2027-05-02',
                'periodicidad_pago' => 'VENCIMIENTO',
                'es_cdat' => false,
                'base_dias' => 365,
            ]);
        }

        $cuenta = DB::table('inversiones')->where('nombre', 'Fondo de emergencia')->where('tipo', 'CUENTA_ALTO_RENDIMIENTO')->first();
        if ($cuenta && !DB::table('detalle_cuenta')->where('inversion_id', $cuenta->id)->exists()) {
            DB::table('detalle_cuenta')->insert([
                'id' => DB::raw('gen_random_uuid()'),
                'inversion_id' => $cuenta->id,
                'saldo_inicial' => $cuenta->monto_inicial,
                'tasa_ea' => 12.0000,
                'es_cuenta_gmf_exenta' => true,
                'base_dias' => 365,
                'periodicidad_abono' => 'MENSUAL',
            ]);
        }

        $accion = DB::table('inversiones')->where('nombre', 'Acciones Bancolombia')->where('tipo', 'ACCION_ETF')->first();
        if ($accion && !DB::table('detalle_renta_variable')->where('inversion_id', $accion->id)->exists()) {
            DB::table('detalle_renta_variable')->insert([
                'id' => DB::raw('gen_random_uuid()'),
                'inversion_id' => $accion->id,
                'simbolo' => 'PFBCOLOM',
                'nombre_instrumento' => 'Bancolombia Pref.',
                'tipo' => 'ACCION',
                'mercado' => 'BVC',
                'esta_inscrito_bvc' => true,
                'porcentaje_circulacion_vendido_anio' => 0.00,
                'unidades_total' => 4.00000000,
                'costo_promedio_unitario' => 550000.00000000,
                'fecha_primera_compra' => '2026-11-01',
                'tipo_reparto' => 'DISTRIBUTIVO',
                'valor_actual_unidad' => 600000.00000000,
                'valor_dividendo_por_unidad' => 0.0000,
                'trm_compra' => 1.0000,
            ]);
        }

        // 2. Modificaciones a movimientos_inversion
        Schema::table('movimientos_inversion', function (Blueprint $table) {
            $table->decimal('monto_bruto', 18, 2)->nullable()->after('monto_cop');
            $table->decimal('valor_unidad', 24, 8)->nullable()->after('unidades');
            $table->decimal('trm', 18, 4)->default(1)->after('tasa_cambio_usada');
            $table->decimal('comision', 18, 2)->default(0)->after('monto_bruto');
            $table->decimal('retencion_aplicada', 18, 2)->default(0)->after('comision');
            $table->decimal('gmf_aplicado', 18, 2)->default(0)->after('retencion_aplicada');
            $table->decimal('impuesto_origen', 18, 2)->default(0)->after('gmf_aplicado');
        });

        // Actualizar datos históricos en movimientos_inversion
        DB::statement('UPDATE movimientos_inversion SET monto_bruto = monto, trm = tasa_cambio_usada WHERE monto_bruto IS NULL');

        // Actualizar CHECK constraint en movimientos_inversion
        DB::statement('ALTER TABLE movimientos_inversion DROP CONSTRAINT IF EXISTS chk_movimiento_tipo');
        DB::statement("ALTER TABLE movimientos_inversion ADD CONSTRAINT chk_movimiento_tipo CHECK (tipo IN ('COMPRA', 'VENTA', 'APORTE', 'RETIRO', 'ABONO_INTERES', 'DIVIDENDO', 'COMISION', 'REDENCION', 'RETIRO_PARCIAL', 'RENDIMIENTO', 'RETENCION', 'LIQUIDACION_TOTAL'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE movimientos_inversion DROP CONSTRAINT IF EXISTS chk_movimiento_tipo');
        DB::statement("ALTER TABLE movimientos_inversion ADD CONSTRAINT chk_movimiento_tipo CHECK (tipo IN ('APORTE', 'RETIRO_PARCIAL', 'RENDIMIENTO', 'COMISION', 'RETENCION', 'LIQUIDACION_TOTAL'))");

        Schema::table('movimientos_inversion', function (Blueprint $table) {
            $table->dropColumn(['monto_bruto', 'valor_unidad', 'trm', 'comision', 'retencion_aplicada', 'gmf_aplicado', 'impuesto_origen']);
        });

        DB::statement('ALTER TABLE inversiones DROP CONSTRAINT IF EXISTS chk_inversion_tipo');
        DB::statement("ALTER TABLE inversiones ADD CONSTRAINT chk_inversion_tipo CHECK (tipo IN ('CDT', 'CUENTA_AHORRO_ALTO_RENDIMIENTO', 'FONDO_INVERSION_COLECTIVA', 'ACCIONES', 'ETF', 'BONOS_TES', 'FONDO_VOLUNTARIO_PENSION', 'CRIPTOACTIVO', 'INMUEBLE_RENTA', 'NEGOCIO_PARTICIPACION', 'OTRO'))");

        Schema::table('inversiones', function (Blueprint $table) {
            $table->dropForeign(['entidad_id']);
            $table->dropColumn('entidad_id');
        });
    }
};
