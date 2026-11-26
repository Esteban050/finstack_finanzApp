<?php

namespace Tests\Feature;

use App\Models\CuentaBancaria;
use App\Models\EntidadFinanciera;
use App\Models\Gasto;
use App\Models\PagoSeguridadSocial;
use App\Models\Usuario;
use App\Services\SaldoCuentaService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** Una salida (gasto o pago PILA) no puede superar lo disponible en la cuenta: monto + GMF. */
class SalidaSaldoInsuficienteTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-26 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function cuenta(string $saldo, bool $exenta = false): CuentaBancaria
    {
        $banco = EntidadFinanciera::firstOrCreate(['nombre' => 'Banco QA', 'tipo' => 'BANCO']);

        return CuentaBancaria::create(['usuario_id' => $this->usuario->id, 'entidad_financiera_id' => $banco->id,
            'saldo_inicial' => $saldo, 'es_exenta_gmf' => $exenta]);
    }

    private function datos(CuentaBancaria $cuenta, string $monto = '1000000', array $extra = []): array
    {
        $sub = DB::table('categorias')->where('tipo', 'GASTO')->where('activa', true)->whereNotNull('categoria_padre_id')->first();

        return array_merge([
            'fecha' => '2026-11-20', 'monto_original' => $monto, 'moneda' => 'COP', 'categoria_id' => $sub->categoria_padre_id,
            'subcategoria_id' => $sub->id, 'medio_pago' => 'TRANSFERENCIA', 'cuenta_id' => $cuenta->id, 'naturaleza' => 'VARIABLE',
            'necesidad' => 'DISCRECIONAL', 'relevancia_tributaria' => 'NO_DEDUCIBLE',
        ], $extra);
    }

    public function test_cuenta_gravada_el_monto_mas_el_gmf_justo_pasa_y_un_centavo_mas_falla(): void
    {
        // 1.000.000 + GMF 4.000 = 1.004.000
        $cuenta = $this->cuenta('1004000.00');
        $this->postJson(route('gastos.store'), $this->datos($cuenta))->assertCreated();
        $this->assertSame('0.00', SaldoCuentaService::saldoDisponible($cuenta));

        $corta = $this->cuenta('1003999.99');
        $this->postJson(route('gastos.store'), $this->datos($corta))->assertUnprocessable()
            ->assertJsonValidationErrors('cuenta_id')
            ->assertJsonPath('errors.cuenta_id.0', 'Saldo insuficiente en la cuenta. Disponible: $ 1.003.999,99');
        $this->assertSame('1003999.99', SaldoCuentaService::saldoDisponible($corta));
        $this->assertSame(1, Gasto::where('cuenta_id', '!=', null)->whereNull('gasto_origen_gmf_id')->count());
    }

    public function test_el_gmf_cuenta_como_parte_de_lo_requerido(): void
    {
        // El monto solo cabe (1.000.000) pero con su GMF (4.000) no.
        $cuenta = $this->cuenta('1000000');
        $this->postJson(route('gastos.store'), $this->datos($cuenta))->assertUnprocessable()
            ->assertJsonPath('errors.cuenta_id.0', 'Saldo insuficiente en la cuenta. Disponible: $ 1.000.000');
        $this->assertSame(0, Gasto::count());
    }

    public function test_cuenta_exenta_no_suma_gmf_y_el_limite_es_exacto(): void
    {
        $cuenta = $this->cuenta('1000000.00', exenta: true);
        $this->postJson(route('gastos.store'), $this->datos($cuenta))->assertCreated();
        $this->assertSame('0.00', SaldoCuentaService::saldoDisponible($cuenta));

        $corta = $this->cuenta('999999.99', exenta: true);
        $this->postJson(route('gastos.store'), $this->datos($corta))->assertUnprocessable()->assertJsonValidationErrors('cuenta_id');
    }

    public function test_editar_no_se_bloquea_a_si_mismo_pero_si_aumentar_mas_alla_del_saldo(): void
    {
        $cuenta = $this->cuenta('1004000.00');
        $id = $this->postJson(route('gastos.store'), $this->datos($cuenta))->assertCreated()->json('data.id');

        // Saldo 0: reenviar el mismo monto (cambiando solo la descripción) debe pasar.
        $this->putJson(route('gastos.update', $id), $this->datos($cuenta, '1000000', ['descripcion' => 'Arriendo']))->assertOk();
        // Bajar el monto también.
        $this->putJson(route('gastos.update', $id), $this->datos($cuenta, '500000'))->assertOk();
        $this->assertSame('502000.00', SaldoCuentaService::saldoDisponible($cuenta));
        // Subir hasta el límite exacto pasa (1.000.000 + 4.000 = 1.004.000)...
        $this->putJson(route('gastos.update', $id), $this->datos($cuenta, '1000000'))->assertOk();
        // ...y un peso más no.
        $this->putJson(route('gastos.update', $id), $this->datos($cuenta, '1000001'))->assertUnprocessable()
            ->assertJsonPath('errors.cuenta_id.0', 'Saldo insuficiente en la cuenta. Disponible: $ 1.004.000');
        $this->assertSame('0.00', SaldoCuentaService::saldoDisponible($cuenta));
        $this->assertSame('1000000.00', (string) Gasto::find($id)->monto_cop);
    }

    public function test_un_gasto_pendiente_no_se_valida_ni_se_cuenta_pero_si_al_confirmarse(): void
    {
        $cuenta = $this->cuenta('500000');
        $sub = DB::table('categorias')->where('tipo', 'GASTO')->where('activa', true)->whereNotNull('categoria_padre_id')->first();
        $pendiente = Gasto::create([
            'usuario_id' => $this->usuario->id, 'categoria_id' => $sub->categoria_padre_id, 'subcategoria_id' => $sub->id,
            'cuenta_id' => $cuenta->id, 'fecha' => '2026-11-20', 'moneda' => 'COP', 'monto_original' => '900000',
            'tasa_cambio_usada' => '1.0000', 'monto_cop' => '900000', 'naturaleza' => 'VARIABLE', 'necesidad' => 'ESENCIAL',
            'medio_pago' => 'TRANSFERENCIA', 'relevancia_tributaria' => 'NO_DEDUCIBLE', 'origen' => 'MANUAL', 'estado' => 'PENDIENTE',
        ]);

        // No reduce el saldo mientras esté pendiente.
        $this->assertSame('500000.00', SaldoCuentaService::saldoDisponible($cuenta));
        // Al confirmarlo por un monto que no cabe, se rechaza y sigue pendiente.
        $this->patchJson(route('gastos.confirmar', $pendiente->id))->assertUnprocessable()->assertJsonValidationErrors('cuenta_id');
        $this->assertSame('PENDIENTE', $pendiente->fresh()->estado);
        // Con un monto real que cabe (300.000 + GMF 1.200), se confirma y genera su GMF.
        $this->patchJson(route('gastos.confirmar', $pendiente->id), ['monto_original' => '300000'])->assertOk();
        $this->assertSame('198800.00', SaldoCuentaService::saldoDisponible($cuenta));
    }

    private function pagar(?CuentaBancaria $cuenta, array $extra = [])
    {
        return $this->postJson(route('seguridad-social.pagos.store'), array_merge([
            'periodo' => '2026-10', 'fecha_pago' => '2026-11-03', 'salud' => '218900', 'pension' => '280200',
            'fsp' => '0', 'arl' => '0', 'intereses_mora' => '0', 'cuenta_id' => $cuenta?->id,
        ], $extra));
    }

    public function test_el_pago_pila_se_rechaza_si_el_monto_mas_gmf_supera_el_saldo(): void
    {
        // Total 499.100 + GMF 1.996,40 = 501.096,40
        $corta = $this->cuenta('501096.39');
        $this->pagar($corta)->assertUnprocessable()
            ->assertJsonPath('errors.cuenta_id.0', 'Saldo insuficiente en la cuenta. Disponible: $ 501.096,39');
        $this->assertSame(0, PagoSeguridadSocial::count());
        $this->assertSame(0, Gasto::count());

        $justa = $this->cuenta('501096.40');
        $this->pagar($justa)->assertCreated();
        $this->assertSame('0.00', SaldoCuentaService::saldoDisponible($justa));
    }

    public function test_editar_el_pago_pila_no_se_bloquea_a_si_mismo(): void
    {
        $cuenta = $this->cuenta('501096.40');
        $id = $this->pagar($cuenta)->assertCreated()->json('data.id');

        $this->putJson(route('seguridad-social.pagos.update', $id), [
            'periodo' => '2026-10', 'fecha_pago' => '2026-11-04', 'salud' => '218900', 'pension' => '280200',
            'fsp' => '0', 'arl' => '0', 'intereses_mora' => '0', 'numero_planilla' => '123',
        ])->assertOk();

        $this->putJson(route('seguridad-social.pagos.update', $id), [
            'periodo' => '2026-10', 'fecha_pago' => '2026-11-04', 'salud' => '300000', 'pension' => '280200',
            'fsp' => '0', 'arl' => '0', 'intereses_mora' => '0',
        ])->assertUnprocessable()->assertJsonValidationErrors('cuenta_id');
        $this->assertSame('0.00', SaldoCuentaService::saldoDisponible($cuenta));
    }

    public function test_el_formulario_html_del_pago_muestra_el_rechazo(): void
    {
        $corta = $this->cuenta('1000');
        $respuesta = $this->followingRedirects()->from(route('seguridad-social.index'))->post(route('seguridad-social.pagos.store'), [
            'redirect_to' => 'seguridad-social.index', 'periodo' => '2026-10', 'fecha_pago' => '2026-11-03', 'salud' => '218900',
            'pension' => '280200', 'fsp' => '0', 'arl' => '0', 'intereses_mora' => '0', 'cuenta_id' => $corta->id,
        ]);

        $respuesta->assertSee('No se registró el pago')->assertSee('Saldo insuficiente en la cuenta. Disponible: $ 1.000');
    }
}
