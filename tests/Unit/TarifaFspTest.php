<?php

namespace Tests\Unit;

use App\Models\ParametroNormativo;
use App\Services\SeguridadSocialService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TarifaFspTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'fsp_testing',
            'database.connections.fsp_testing' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ]);

        Schema::create('tarifas_escalonadas', function (Blueprint $table) {
            $table->uuid('parametro_normativo_id');
            $table->string('tipo_tabla');
            $table->decimal('rango_desde');
            $table->decimal('rango_hasta')->nullable();
            $table->decimal('tarifa_porc', 5, 3);
        });
    }

    public function test_la_tarifa_del_dos_por_ciento_empieza_por_encima_de_veinte_smmlv(): void
    {
        $parametro = new ParametroNormativo();
        $parametro->id = '903f0955-67f0-4fbc-9a30-c3c123f7465d';
        foreach ([[4, 16, '1.000'], [16, 17, '1.200'], [17, 18, '1.400'], [19, 20, '1.800']] as [$desde, $hasta, $tarifa]) {
            DB::table('tarifas_escalonadas')->insert([
                'parametro_normativo_id' => $parametro->id,
                'tipo_tabla' => 'FSP',
                'rango_desde' => $desde,
                'rango_hasta' => $hasta,
                'tarifa_porc' => $tarifa,
            ]);
        }

        $smmlv = '1750905.00';
        $this->assertSame('1.000', SeguridadSocialService::tarifaFsp('7003620.00', $smmlv, $parametro));
        $this->assertSame('1.200', SeguridadSocialService::tarifaFsp('28014480.00', $smmlv, $parametro));
        $this->assertSame('1.200', SeguridadSocialService::tarifaFsp('29765385.00', $smmlv, $parametro));
        $this->assertSame('1.400', SeguridadSocialService::tarifaFsp('29765385.01', $smmlv, $parametro));
        $this->assertSame('1.800', SeguridadSocialService::tarifaFsp('35018099.99', $smmlv, $parametro));
        $this->assertSame('1.800', SeguridadSocialService::tarifaFsp('35018100.00', $smmlv, $parametro));
        $this->assertSame('2.00', SeguridadSocialService::tarifaFsp('35018100.01', $smmlv, $parametro));
    }

    public function test_una_vigencia_sin_tramo_requerido_informa_el_error_de_configuracion(): void
    {
        $parametro = new ParametroNormativo(['anio_vigencia' => 2026]);
        $parametro->id = '903f0955-67f0-4fbc-9a30-c3c123f7465d';

        $this->assertSame('0', SeguridadSocialService::tarifaFsp('7003619.99', '1750905.00', $parametro));
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Falta configurar el tramo del Fondo de Solidaridad Pensional para la vigencia 2026.');
        SeguridadSocialService::tarifaFsp('35018099.99', '1750905.00', $parametro);
    }
}
