<?php

namespace Tests\Feature;

use App\Models\Credito;
use App\Models\CuentaBancaria;
use App\Models\EntidadFinanciera;
use App\Models\Usuario;
use App\Services\Reportes\DashboardResumenService;
use Carbon\CarbonImmutable;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** "Otro banco": cuentas y tarjetas cuyo banco no está en el catálogo global. */
class CuentaOtroBancoTest extends TestCase
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

    public function test_cuenta_con_otro_banco_guarda_el_nombre_sin_tocar_el_catalogo(): void
    {
        $antes = EntidadFinanciera::count();

        $this->post(route('perfil.cuentas.store'), [
            'entidad_financiera_id' => 'OTRO', 'banco_otro' => '  Banco Pichincha  ', 'saldo_inicial' => '1000',
        ])->assertSessionHasNoErrors();

        $cuenta = CuentaBancaria::firstOrFail();
        $this->assertNull($cuenta->entidad_financiera_id);
        $this->assertSame('Banco Pichincha', $cuenta->banco_otro);
        $this->assertSame('Banco Pichincha', $cuenta->nombre_banco);
        $this->assertSame($antes, EntidadFinanciera::count());
        $this->get(route('profile.show'))->assertOk()->assertSee('Banco Pichincha')->assertSee('Otro banco');
    }

    public function test_exige_exactamente_uno_de_los_dos(): void
    {
        $this->post(route('perfil.cuentas.store'), ['entidad_financiera_id' => 'OTRO', 'saldo_inicial' => '1'])
            ->assertSessionHasErrorsIn('cuenta', ['banco_otro']);
        $this->post(route('perfil.cuentas.store'), ['saldo_inicial' => '1'])
            ->assertSessionHasErrorsIn('cuenta', ['entidad_financiera_id']);
        $this->post(route('perfil.cuentas.store'), ['entidad_financiera_id' => 'OTRO', 'banco_otro' => str_repeat('a', 101), 'saldo_inicial' => '1'])
            ->assertSessionHasErrorsIn('cuenta', ['banco_otro']);
        $this->assertSame(0, CuentaBancaria::count());

        // Con un banco de la lista se descarta el texto libre.
        $this->post(route('perfil.cuentas.store'), ['entidad_financiera_id' => $this->banco->id, 'banco_otro' => 'Sobrante', 'saldo_inicial' => '1'])
            ->assertSessionHasNoErrors();
        $cuenta = CuentaBancaria::firstOrFail();
        $this->assertSame($this->banco->id, $cuenta->entidad_financiera_id);
        $this->assertNull($cuenta->banco_otro);
        $this->assertSame('Banco QA', $cuenta->nombre_banco);
    }

    public function test_tarjeta_con_otro_banco_usa_el_nombre_escrito(): void
    {
        $this->post(route('perfil.tarjetas.store'), ['entidad_financiera_id' => 'OTRO', 'banco_otro' => 'Neobanco X', 'cupo' => '2000000', 'dia_corte' => '15', 'dia_pago' => '5'])
            ->assertSessionHasNoErrors();

        $tarjeta = Credito::where('tipo_credito', 'TARJETA_CREDITO')->firstOrFail();
        $this->assertSame('Neobanco X', $tarjeta->banco);
        $this->get(route('profile.show'))->assertSee('Neobanco X');

        $this->post(route('perfil.tarjetas.store'), ['entidad_financiera_id' => 'OTRO', 'cupo' => '1000', 'dia_corte' => '15', 'dia_pago' => '5'])
            ->assertSessionHasErrorsIn('tarjeta', ['banco_otro']);
    }

    public function test_el_nombre_se_muestra_en_dashboard_ingreso_y_pila(): void
    {
        $this->post(route('perfil.cuentas.store'), ['entidad_financiera_id' => 'OTRO', 'banco_otro' => 'Banco Raro', 'saldo_inicial' => '50000'])
            ->assertSessionHasNoErrors();

        $debito = app(DashboardResumenService::class)->obtenerDashboard($this->usuario, 'mes-actual', null, null, CarbonImmutable::parse('2026-11-22'))['cuentas']['debito'];
        $this->assertSame('Banco Raro', $debito[0]['nombre']);
        $this->get(route('ingresos.create'))->assertOk()->assertSee('Banco Raro');
        $this->get(route('seguridad-social.index'))->assertOk()->assertSee('Banco Raro');
    }
}
