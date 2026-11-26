<?php

namespace Tests\Feature;

use App\Models\CuentaBancaria;
use App\Models\EntidadFinanciera;
use App\Models\Ingreso;
use App\Models\Usuario;
use App\Services\SaldoCuentaService;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** Ingresos: cuenta de débito opcional que recibe el dinero (suma al saldo disponible). */
class IngresoConCuentaTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;
    private string $fuenteId;
    private CuentaBancaria $cuenta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-26 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
        $this->fuenteId = $this->crearFuente($this->usuario);
        $this->cuenta = $this->nuevaCuenta($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function nuevaCuenta(Usuario $u): CuentaBancaria
    {
        $banco = EntidadFinanciera::firstOrCreate(['nombre' => 'Banco QA', 'tipo' => 'BANCO']);

        return CuentaBancaria::create(['usuario_id' => $u->id, 'entidad_financiera_id' => $banco->id, 'saldo_inicial' => '100000']);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'fuente_id' => $this->fuenteId, 'fecha_recepcion' => '2026-11-20', 'monto_original' => '500000', 'moneda' => 'COP',
        ], $extra);
    }

    public function test_el_ingreso_con_cuenta_aumenta_el_saldo(): void
    {
        $id = $this->postJson(route('ingresos.store'), $this->datos(['cuenta_id' => $this->cuenta->id]))->assertCreated()->json('data.id');

        $this->assertSame($this->cuenta->id, Ingreso::find($id)->cuenta_id);
        $this->assertSame('600000.00', SaldoCuentaService::saldoDisponible($this->cuenta));
    }

    public function test_la_cuenta_es_opcional(): void
    {
        $id = $this->postJson(route('ingresos.store'), $this->datos())->assertCreated()->json('data.id');

        $this->assertNull(Ingreso::find($id)->cuenta_id);
        $this->assertSame('100000.00', SaldoCuentaService::saldoDisponible($this->cuenta));
    }

    public function test_rechaza_una_cuenta_ajena_o_eliminada(): void
    {
        $ajena = $this->nuevaCuenta($this->crearUsuario());
        $this->postJson(route('ingresos.store'), $this->datos(['cuenta_id' => $ajena->id]))
            ->assertStatus(422)->assertJsonValidationErrors('cuenta_id');

        $this->cuenta->delete();
        $this->postJson(route('ingresos.store'), $this->datos(['cuenta_id' => $this->cuenta->id]))
            ->assertStatus(422)->assertJsonValidationErrors('cuenta_id');
    }

    public function test_editar_cambia_quita_o_conserva_la_cuenta(): void
    {
        $otra = $this->nuevaCuenta($this->usuario);
        $id = $this->postJson(route('ingresos.store'), $this->datos(['cuenta_id' => $this->cuenta->id]))->json('data.id');

        $this->putJson(route('ingresos.update', $id), $this->datos(['cuenta_id' => $otra->id]))->assertOk();
        $this->assertSame($otra->id, Ingreso::find($id)->cuenta_id);
        $this->assertSame('100000.00', SaldoCuentaService::saldoDisponible($this->cuenta));

        $this->putJson(route('ingresos.update', $id), $this->datos(['monto_original' => '700000']))->assertOk();
        $this->assertSame($otra->id, Ingreso::find($id)->cuenta_id);

        $this->putJson(route('ingresos.update', $id), $this->datos(['cuenta_id' => '']))->assertOk();
        $this->assertNull(Ingreso::find($id)->cuenta_id);
    }

    public function test_los_formularios_ofrecen_las_cuentas(): void
    {
        $this->get(route('ingresos.create'))->assertOk()->assertSee('Cuenta que recibe el dinero')->assertSee('Banco QA');
    }
}
