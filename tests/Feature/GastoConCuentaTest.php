<?php

namespace Tests\Feature;

use App\Models\CuentaBancaria;
use App\Models\EntidadFinanciera;
use App\Models\Gasto;
use App\Models\Usuario;
use App\Services\SaldoCuentaService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** Gastos ordinarios: cuenta de débito opcional de la que sale el dinero, con GMF sincronizado. */
class GastoConCuentaTest extends TestCase
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

    private function cuenta(string $saldo = '10000000', bool $exenta = false, ?Usuario $dueno = null): CuentaBancaria
    {
        $banco = EntidadFinanciera::firstOrCreate(['nombre' => 'Banco QA', 'tipo' => 'BANCO']);

        return CuentaBancaria::create(['usuario_id' => ($dueno ?? $this->usuario)->id, 'entidad_financiera_id' => $banco->id,
            'saldo_inicial' => $saldo, 'es_exenta_gmf' => $exenta]);
    }

    private function datos(array $extra = []): array
    {
        $sub = DB::table('categorias')->where('tipo', 'GASTO')->where('activa', true)->whereNotNull('categoria_padre_id')->first();

        return array_merge([
            'fecha' => '2026-11-20', 'monto_original' => '1000000', 'moneda' => 'COP', 'categoria_id' => $sub->categoria_padre_id,
            'subcategoria_id' => $sub->id, 'medio_pago' => 'TRANSFERENCIA', 'naturaleza' => 'VARIABLE', 'necesidad' => 'DISCRECIONAL',
            'relevancia_tributaria' => 'NO_DEDUCIBLE',
        ], $extra);
    }

    private function gmfDe(string $gastoId): ?Gasto
    {
        return Gasto::where('gasto_origen_gmf_id', $gastoId)->first();
    }

    public function test_el_formulario_ofrece_las_cuentas_del_usuario(): void
    {
        $this->cuenta();
        $this->get(route('gastos.create'))->assertOk()->assertSee('Cuenta de la que sale el dinero')->assertSee('Banco QA');
    }

    public function test_crear_con_cuenta_baja_el_saldo_y_genera_el_gmf(): void
    {
        $cuenta = $this->cuenta();
        $id = $this->postJson(route('gastos.store'), $this->datos(['cuenta_id' => $cuenta->id]))->assertCreated()->json('data.id');

        $this->assertSame($cuenta->id, Gasto::find($id)->cuenta_id);
        $this->assertSame('4000.00', (string) $this->gmfDe($id)->monto_cop);
        $this->assertSame('8996000.00', SaldoCuentaService::saldoDisponible($cuenta));
    }

    public function test_la_cuenta_es_opcional_y_se_ignora_con_otros_medios(): void
    {
        $cuenta = $this->cuenta();
        $sin = $this->postJson(route('gastos.store'), $this->datos())->assertCreated()->json('data.id');
        $efectivo = $this->postJson(route('gastos.store'), $this->datos(['medio_pago' => 'EFECTIVO', 'cuenta_id' => $cuenta->id]))->assertCreated()->json('data.id');

        $this->assertNull(Gasto::find($sin)->cuenta_id);
        $this->assertNull(Gasto::find($efectivo)->cuenta_id);
        $this->assertSame('10000000.00', SaldoCuentaService::saldoDisponible($cuenta));
    }

    public function test_rechaza_una_cuenta_ajena_o_eliminada(): void
    {
        $ajena = $this->cuenta(dueno: $this->crearUsuario());
        $this->postJson(route('gastos.store'), $this->datos(['cuenta_id' => $ajena->id]))->assertUnprocessable()->assertJsonValidationErrors('cuenta_id');

        $propia = $this->cuenta();
        $propia->delete();
        $this->postJson(route('gastos.store'), $this->datos(['cuenta_id' => $propia->id]))->assertUnprocessable()->assertJsonValidationErrors('cuenta_id');
        $this->assertSame(0, Gasto::count());
    }

    public function test_editar_actualiza_el_gmf_y_cambiar_de_medio_desvincula_la_cuenta(): void
    {
        $cuenta = $this->cuenta();
        $id = $this->postJson(route('gastos.store'), $this->datos(['cuenta_id' => $cuenta->id]))->json('data.id');

        $this->putJson(route('gastos.update', $id), $this->datos(['cuenta_id' => $cuenta->id, 'monto_original' => '2000000']))->assertOk();
        $this->assertSame('8000.00', (string) $this->gmfDe($id)->monto_cop);
        $this->assertSame('7992000.00', SaldoCuentaService::saldoDisponible($cuenta));

        // Sin `cuenta_id` en la petición con un medio de débito se conserva la cuenta.
        $this->putJson(route('gastos.update', $id), $this->datos(['monto_original' => '1000000']))->assertOk();
        $this->assertSame($cuenta->id, Gasto::find($id)->cuenta_id);
        $this->assertSame('4000.00', (string) $this->gmfDe($id)->monto_cop);

        $this->putJson(route('gastos.update', $id), $this->datos(['medio_pago' => 'EFECTIVO', 'cuenta_id' => $cuenta->id]))->assertOk();
        $this->assertNull(Gasto::find($id)->cuenta_id);
        $this->assertNull($this->gmfDe($id));
        $this->assertSame('10000000.00', SaldoCuentaService::saldoDisponible($cuenta));
    }

    public function test_eliminar_el_gasto_elimina_su_gmf_y_el_gmf_no_es_editable(): void
    {
        $cuenta = $this->cuenta();
        $id = $this->postJson(route('gastos.store'), $this->datos(['cuenta_id' => $cuenta->id]))->json('data.id');
        $gmf = $this->gmfDe($id);

        $this->putJson(route('gastos.update', $gmf->id), $this->datos())->assertUnprocessable();
        $this->deleteJson(route('gastos.destroy', $gmf->id))->assertUnprocessable();

        $this->deleteJson(route('gastos.destroy', $id))->assertOk();
        $this->assertNull($this->gmfDe($id));
        $this->assertSame('10000000.00', SaldoCuentaService::saldoDisponible($cuenta));
    }

    public function test_cuenta_exenta_no_genera_gmf_dentro_del_tope(): void
    {
        $cuenta = $this->cuenta(exenta: true);
        $id = $this->postJson(route('gastos.store'), $this->datos(['cuenta_id' => $cuenta->id]))->json('data.id');

        $this->assertNull($this->gmfDe($id));
        $this->assertSame('9000000.00', SaldoCuentaService::saldoDisponible($cuenta));
    }
}
