<?php

namespace Tests\Feature;

use App\Models\CuentaBancaria;
use App\Models\Credito;
use App\Models\EntidadFinanciera;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** Mi perfil: registro de cuentas de débito/ahorro y tarjetas de crédito. */
class PerfilCuentasTarjetasTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;
    private EntidadFinanciera $banco;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-26 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
        $this->banco = EntidadFinanciera::firstOrCreate(['nombre' => 'Banco QA', 'tipo' => 'BANCO']);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    public function test_el_perfil_muestra_la_seccion_y_el_boton(): void
    {
        $this->get(route('profile.show'))->assertOk()
            ->assertSee('Cuentas y tarjetas')->assertSee('Agregar cuenta o tarjeta')->assertSee('Banco QA');
    }

    public function test_registra_una_cuenta_de_debito_y_la_lista(): void
    {
        $this->post(route('perfil.cuentas.store'), [
            'entidad_financiera_id' => $this->banco->id, 'saldo_inicial' => '2500000.50', 'es_exenta_gmf' => '1', 'alias' => 'Nómina',
        ])->assertRedirect(route('profile.show'));

        $cuenta = CuentaBancaria::where('usuario_id', $this->usuario->id)->firstOrFail();
        $this->assertSame('2500000.50', $cuenta->saldo_inicial);
        $this->assertTrue($cuenta->es_exenta_gmf);
        $this->get(route('profile.show'))->assertSee('Nómina')->assertSee('exenta GMF')->assertSee('$ 2.500.001');
    }

    public function test_valida_banco_y_saldo(): void
    {
        $this->post(route('perfil.cuentas.store'), ['saldo_inicial' => '-5'])
            ->assertSessionHasErrorsIn('cuenta', ['entidad_financiera_id', 'saldo_inicial']);
        $this->post(route('perfil.cuentas.store'), ['entidad_financiera_id' => $this->banco->id, 'saldo_inicial' => '10.123'])
            ->assertSessionHasErrorsIn('cuenta', ['saldo_inicial']);
        $this->assertSame(0, CuentaBancaria::count());
    }

    public function test_elimina_la_cuenta_con_borrado_logico_y_solo_las_propias(): void
    {
        $cuenta = CuentaBancaria::create(['usuario_id' => $this->usuario->id, 'entidad_financiera_id' => $this->banco->id, 'saldo_inicial' => '10']);
        $this->delete(route('perfil.cuentas.destroy', $cuenta->id))->assertRedirect(route('profile.show'));

        $this->assertNull(CuentaBancaria::find($cuenta->id));
        $this->assertNotNull(CuentaBancaria::withTrashed()->find($cuenta->id)->eliminado_en);

        $ajena = CuentaBancaria::create(['usuario_id' => $this->usuario->id, 'entidad_financiera_id' => $this->banco->id, 'saldo_inicial' => '10']);
        $otro = $this->crearUsuario();
        $this->entrarComo($otro);
        $this->delete(route('perfil.cuentas.destroy', $ajena->id))->assertNotFound();
    }

    public function test_registra_una_tarjeta_como_credito_con_su_cupo(): void
    {
        $this->post(route('perfil.tarjetas.store'), ['entidad_financiera_id' => $this->banco->id, 'cupo' => '5000000'])
            ->assertRedirect(route('profile.show'))->assertSessionHasNoErrors();

        $tarjeta = Credito::where('usuario_id', $this->usuario->id)->where('tipo_credito', 'TARJETA_CREDITO')->firstOrFail();
        $this->assertSame('Banco QA', $tarjeta->banco);
        $this->assertSame('5000000.00', (string) $tarjeta->cupo);
        $this->assertSame('0.00', (string) $tarjeta->monto);
        $this->get(route('profile.show'))->assertSee('cupo $ 5.000.000');
    }

    public function test_valida_el_cupo_de_la_tarjeta(): void
    {
        foreach (['0', '1500.5', ''] as $cupo) {
            $this->post(route('perfil.tarjetas.store'), ['entidad_financiera_id' => $this->banco->id, 'cupo' => $cupo])
                ->assertSessionHasErrorsIn('tarjeta', ['cupo']);
        }
        $this->assertSame(0, Credito::where('tipo_credito', 'TARJETA_CREDITO')->count());
    }
}
