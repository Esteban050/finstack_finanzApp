<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * CP-REN-011 / Art. 277 ET: el valor fiscal de los inmuebles (el mayor entre
 * costo fiscal y avalúo catastral) se registra en información complementaria
 * y se suma al patrimonio bruto del criterio 1, sin exigir nota de ajuste.
 */
class RentaInmueblesValorFiscalTest extends TestCase
{
    use UsaBaseLocal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal();
        Http::fake(['*' => Http::response([], 200)]);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    public function test_inmueble_a_valor_fiscal_suma_al_patrimonio(): void
    {
        $usuario = $this->crearUsuario();
        $this->entrarComo($usuario);
        $declaracion = fn () => DB::table('declaraciones_renta')->where('usuario_id', $usuario->id)->where('anio_gravable', 2025)->first();

        $this->post(route('renta.complementaria.guardar', ['anio' => 2025]), ['c1_inmuebles_valor_fiscal_cop' => '320000000.50', 'accion' => 'guardar'])
            ->assertSessionHasNoErrors();
        $d = $declaracion();
        $this->assertSame('320000000.50', (string) $d->c1_inmuebles_valor_fiscal_cop);
        $this->assertEqualsWithDelta((float) $d->c1_patrimonio_bruto_cop + 320000000.50, (float) $d->c1_valor_final_cop, 0.001);
        $this->assertSame(0, DB::table('declaracion_renta_ajustes')->where('declaracion_renta_id', $d->id)->count());

        $this->get(route('renta.complementaria', ['anio' => 2025]))->assertOk()->assertSee('Valor fiscal de tus inmuebles')->assertSee('320000000.50');
        $this->get(route('renta.consolidado', ['anio' => 2025]))->assertOk()->assertSee('Inmuebles declarados en información complementaria');

        // Un ajuste manual se suma después de los inmuebles y no puede dejar el total negativo.
        $this->post(route('renta.complementaria.guardar', ['anio' => 2025]), ['c1_ajuste_manual_cop' => '-320000001', 'justificacion_ajuste' => 'Prueba de no negatividad'])
            ->assertSessionHasErrors('c1_ajuste_manual_cop');
        $this->post(route('renta.complementaria.guardar', ['anio' => 2025]), ['c1_inmuebles_valor_fiscal_cop' => '-5'])
            ->assertSessionHasErrors(['c1_inmuebles_valor_fiscal_cop' => 'El valor fiscal de los inmuebles no puede ser negativo.']);
        $this->assertSame('320000000.50', (string) $declaracion()->c1_inmuebles_valor_fiscal_cop);
    }
}
