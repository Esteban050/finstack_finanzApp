<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametro_tributario', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('clave', 60)->unique();
            $table->decimal('valor_numerico', 18, 4);
            $table->string('unidad', 20); // PORCENTAJE, UVT, COP, ANIOS, DIAS
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->string('norma_referencia', 200)->nullable();
            $table->string('estado_verificacion', 20)->default('VERIFICADO'); // VERIFICADO, VALIDAR
            $table->uuid('creado_por')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('creado_por')->references('id')->on('usuarios')->nullOnDelete();
        });

        Schema::create('tabla_exencion_cesantias', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->decimal('uvt_desde', 10, 2);
            $table->decimal('uvt_hasta', 10, 2)->nullable();
            $table->decimal('porcentaje_exento', 5, 2);
            $table->date('vigente_desde');
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();
        });

        Schema::create('entidad_financiera', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('nombre', 100);
            $table->string('tipo', 30); // BANCO, FIDUCIARIA, COMISIONISTA, AFP, FONDO_CESANTIAS, OTRA
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();
        });

        Schema::create('fic_catalogo', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('nombre', 100);
            $table->string('perfil', 50)->default('CONSERVADOR'); // CONSERVADOR, MODERADO, AGRESIVO
            $table->boolean('activo')->default(true);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();
        });

        Schema::create('fic_catalogo_composicion', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('fic_catalogo_id');
            $table->string('concepto', 30); // RENTA_FIJA, REND_FINANCIEROS, RENTA_VARIABLE, OTRO
            $table->decimal('porcentaje', 5, 2);
            $table->decimal('tarifa_retencion', 5, 2);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('fic_catalogo_id')->references('id')->on('fic_catalogo')->cascadeOnDelete();
        });

        // Insertar Parámetros Tributarios Oficiales 2026
        DB::table('parametro_tributario')->insert([
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'UVT',
                'valor_numerico' => 52374.0000,
                'unidad' => 'COP',
                'vigente_desde' => '2026-01-01',
                'vigente_hasta' => '2026-12-31',
                'norma_referencia' => 'Resolución DIAN 000238 de 2025',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'RETEFUENTE_RENTA_FIJA',
                'valor_numerico' => 4.0000,
                'unidad' => 'PORCENTAJE',
                'vigente_desde' => '2025-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Decreto 572 de 2025 / Art. 1.2.4.2.83 Dec. 1625/2016',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'RETEFUENTE_REND_FINANCIEROS',
                'valor_numerico' => 7.0000,
                'unidad' => 'PORCENTAJE',
                'vigente_desde' => '2020-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Art. 395 ET',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'GMF_TARIFA',
                'valor_numerico' => 0.4000,
                'unidad' => 'PORCENTAJE',
                'vigente_desde' => '2000-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Art. 871 ET (4x1000)',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'GMF_TOPE_EXENTO_UVT',
                'valor_numerico' => 350.0000,
                'unidad' => 'UVT',
                'vigente_desde' => '2020-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Art. 879 num. 1 ET',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'GO_TARIFA',
                'valor_numerico' => 15.0000,
                'unidad' => 'PORCENTAJE',
                'vigente_desde' => '2023-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Art. 314 ET',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'GO_ANIOS_TENENCIA',
                'valor_numerico' => 2.0000,
                'unidad' => 'ANIOS',
                'vigente_desde' => '2000-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Art. 300 ET',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'ART_36_1_TOPE_PORC_CIRCULACION',
                'valor_numerico' => 3.0000,
                'unidad' => 'PORCENTAJE',
                'vigente_desde' => '2000-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Art. 36-1 ET',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'FPV_TOPE_PORC_INGRESO',
                'valor_numerico' => 30.0000,
                'unidad' => 'PORCENTAJE',
                'vigente_desde' => '2013-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Art. 126-1 ET',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'FPV_TOPE_UVT',
                'valor_numerico' => 3800.0000,
                'unidad' => 'UVT',
                'vigente_desde' => '2013-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Art. 126-1 ET',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'FPV_PERMANENCIA_ANIOS',
                'valor_numerico' => 10.0000,
                'unidad' => 'ANIOS',
                'vigente_desde' => '2013-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Art. 126-1 ET',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'FPV_RETENCION_RETIRO_ANTICIPADO',
                'valor_numerico' => 7.0000,
                'unidad' => 'PORCENTAJE',
                'vigente_desde' => '2017-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Art. 1.2.4.1.33 Dec. 1625/2016',
                'estado_verificacion' => 'VALIDAR',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'CESANTIAS_UMBRAL_UVT',
                'valor_numerico' => 350.0000,
                'unidad' => 'UVT',
                'vigente_desde' => '2017-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Art. 206 num. 4 ET',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'DIVIDENDOS_RETENCION_INDIVIDUAL',
                'valor_numerico' => 30.0000,
                'unidad' => 'PORCENTAJE',
                'vigente_desde' => '2023-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Art. 242 ET / Sección 9.8.1',
                'estado_verificacion' => 'VALIDAR',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'DIVIDENDOS_RETENCION_ACUMULATIVO',
                'valor_numerico' => 15.0000,
                'unidad' => 'PORCENTAJE',
                'vigente_desde' => '2023-01-01',
                'vigente_hasta' => null,
                'norma_referencia' => 'Sección 9.8.1',
                'estado_verificacion' => 'VALIDAR',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'COMPONENTE_INFLACIONARIO_2025',
                'valor_numerico' => 50.8800,
                'unidad' => 'PORCENTAJE',
                'vigente_desde' => '2025-01-01',
                'vigente_hasta' => '2025-12-31',
                'norma_referencia' => 'Decreto 771 de 2025',
                'estado_verificacion' => 'VERIFICADO',
            ],
            [
                'id' => DB::raw('gen_random_uuid()'),
                'clave' => 'COMPONENTE_INFLACIONARIO_2026',
                'valor_numerico' => 45.0000,
                'unidad' => 'PORCENTAJE',
                'vigente_desde' => '2026-01-01',
                'vigente_hasta' => '2026-12-31',
                'norma_referencia' => 'Decreto 898 de 2026 (Estimado)',
                'estado_verificacion' => 'VALIDAR',
            ],
        ]);

        // Insertar Tabla de Exención de Cesantías (Art. 206 Num. 4 ET)
        DB::table('tabla_exencion_cesantias')->insert([
            ['id' => DB::raw('gen_random_uuid()'), 'uvt_desde' => 0.00, 'uvt_hasta' => 350.00, 'porcentaje_exento' => 100.00, 'vigente_desde' => '2017-01-01'],
            ['id' => DB::raw('gen_random_uuid()'), 'uvt_desde' => 350.01, 'uvt_hasta' => 410.00, 'porcentaje_exento' => 90.00, 'vigente_desde' => '2017-01-01'],
            ['id' => DB::raw('gen_random_uuid()'), 'uvt_desde' => 410.01, 'uvt_hasta' => 470.00, 'porcentaje_exento' => 80.00, 'vigente_desde' => '2017-01-01'],
            ['id' => DB::raw('gen_random_uuid()'), 'uvt_desde' => 470.01, 'uvt_hasta' => 530.00, 'porcentaje_exento' => 60.00, 'vigente_desde' => '2017-01-01'],
            ['id' => DB::raw('gen_random_uuid()'), 'uvt_desde' => 530.01, 'uvt_hasta' => 590.00, 'porcentaje_exento' => 40.00, 'vigente_desde' => '2017-01-01'],
            ['id' => DB::raw('gen_random_uuid()'), 'uvt_desde' => 590.01, 'uvt_hasta' => 650.00, 'porcentaje_exento' => 20.00, 'vigente_desde' => '2017-01-01'],
            ['id' => DB::raw('gen_random_uuid()'), 'uvt_desde' => 650.01, 'uvt_hasta' => null, 'porcentaje_exento' => 0.00, 'vigente_desde' => '2017-01-01'],
        ]);

        // Insertar Entidades Financieras Iniciales
        DB::table('entidad_financiera')->insert([
            ['id' => DB::raw('gen_random_uuid()'), 'nombre' => 'Bancolombia', 'tipo' => 'BANCO'],
            ['id' => DB::raw('gen_random_uuid()'), 'nombre' => 'Banco de Bogotá', 'tipo' => 'BANCO'],
            ['id' => DB::raw('gen_random_uuid()'), 'nombre' => 'Davivienda', 'tipo' => 'BANCO'],
            ['id' => DB::raw('gen_random_uuid()'), 'nombre' => 'PIBANK', 'tipo' => 'BANCO'],
            ['id' => DB::raw('gen_random_uuid()'), 'nombre' => 'Nu', 'tipo' => 'BANCO'],
            ['id' => DB::raw('gen_random_uuid()'), 'nombre' => 'Skandia', 'tipo' => 'AFP'],
            ['id' => DB::raw('gen_random_uuid()'), 'nombre' => 'Protección', 'tipo' => 'AFP'],
            ['id' => DB::raw('gen_random_uuid()'), 'nombre' => 'Porvenir', 'tipo' => 'AFP'],
            ['id' => DB::raw('gen_random_uuid()'), 'nombre' => 'Tyba / Credicorp Capital', 'tipo' => 'FIDUCIARIA'],
            ['id' => DB::raw('gen_random_uuid()'), 'nombre' => 'Trii', 'tipo' => 'COMISIONISTA'],
        ]);

        // Insertar Catálogo FIC según Sección 9.4
        $ficLiquidezId = DB::table('fic_catalogo')->insertGetId([
            'nombre' => 'FIC Liquidez',
            'perfil' => 'CONSERVADOR',
            'activo' => true,
        ]);
        DB::table('fic_catalogo_composicion')->insert([
            ['id' => DB::raw('gen_random_uuid()'), 'fic_catalogo_id' => $ficLiquidezId, 'concepto' => 'RENTA_FIJA', 'porcentaje' => 30.00, 'tarifa_retencion' => 4.00],
            ['id' => DB::raw('gen_random_uuid()'), 'fic_catalogo_id' => $ficLiquidezId, 'concepto' => 'REND_FINANCIEROS', 'porcentaje' => 70.00, 'tarifa_retencion' => 7.00],
            ['id' => DB::raw('gen_random_uuid()'), 'fic_catalogo_id' => $ficLiquidezId, 'concepto' => 'RENTA_VARIABLE', 'porcentaje' => 0.00, 'tarifa_retencion' => 0.00],
        ]);

        $ficRfcId = DB::table('fic_catalogo')->insertGetId([
            'nombre' => 'FIC Renta Fija Corto Plazo',
            'perfil' => 'CONSERVADOR',
            'activo' => true,
        ]);
        DB::table('fic_catalogo_composicion')->insert([
            ['id' => DB::raw('gen_random_uuid()'), 'fic_catalogo_id' => $ficRfcId, 'concepto' => 'RENTA_FIJA', 'porcentaje' => 80.00, 'tarifa_retencion' => 4.00],
            ['id' => DB::raw('gen_random_uuid()'), 'fic_catalogo_id' => $ficRfcId, 'concepto' => 'REND_FINANCIEROS', 'porcentaje' => 20.00, 'tarifa_retencion' => 7.00],
        ]);

        $ficRflId = DB::table('fic_catalogo')->insertGetId([
            'nombre' => 'FIC Renta Fija Largo Plazo',
            'perfil' => 'MODERADO',
            'activo' => true,
        ]);
        DB::table('fic_catalogo_composicion')->insert([
            ['id' => DB::raw('gen_random_uuid()'), 'fic_catalogo_id' => $ficRflId, 'concepto' => 'RENTA_FIJA', 'porcentaje' => 100.00, 'tarifa_retencion' => 4.00],
        ]);

        $ficBalId = DB::table('fic_catalogo')->insertGetId([
            'nombre' => 'FIC Balanceado',
            'perfil' => 'MODERADO',
            'activo' => true,
        ]);
        DB::table('fic_catalogo_composicion')->insert([
            ['id' => DB::raw('gen_random_uuid()'), 'fic_catalogo_id' => $ficBalId, 'concepto' => 'RENTA_FIJA', 'porcentaje' => 50.00, 'tarifa_retencion' => 4.00],
            ['id' => DB::raw('gen_random_uuid()'), 'fic_catalogo_id' => $ficBalId, 'concepto' => 'REND_FINANCIEROS', 'porcentaje' => 20.00, 'tarifa_retencion' => 7.00],
            ['id' => DB::raw('gen_random_uuid()'), 'fic_catalogo_id' => $ficBalId, 'concepto' => 'RENTA_VARIABLE', 'porcentaje' => 30.00, 'tarifa_retencion' => 0.00],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fic_catalogo_composicion');
        Schema::dropIfExists('fic_catalogo');
        Schema::dropIfExists('entidad_financiera');
        Schema::dropIfExists('tabla_exencion_cesantias');
        Schema::dropIfExists('parametro_tributario');
    }
};
