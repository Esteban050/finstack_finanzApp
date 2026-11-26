<?php

namespace Tests\Feature;

use App\Models\CuentaBancaria;
use App\Models\EntidadFinanciera;
use App\Models\Gasto;
use App\Models\Usuario;
use App\Services\GmfCuentaService;
use App\Services\SaldoCuentaService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** Pago PILA desde una cuenta de débito: baja el saldo y genera/sincroniza el GMF (4x1000) si no es exenta. */
class PagoPilaCuentaGmfTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal();
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function cuenta(bool $exenta = false, ?Usuario $dueno = null): CuentaBancaria
    {
        $banco = EntidadFinanciera::firstOrCreate(['nombre' => 'Banco QA', 'tipo' => 'BANCO']);

        return CuentaBancaria::create(['usuario_id' => ($dueno ?? $this->usuario)->id, 'entidad_financiera_id' => $banco->id,
            'saldo_inicial' => '10000000', 'es_exenta_gmf' => $exenta]);
    }

    private function pagar(array $extra = [])
    {
        return $this->postJson(route('seguridad-social.pagos.store'), array_merge([
            'periodo' => '2026-10', 'fecha_pago' => '2026-11-03', 'salud' => '218900', 'pension' => '280200',
            'fsp' => '0', 'arl' => '0', 'intereses_mora' => '0',
        ], $extra));
    }

    private function gmfDe(string $gastoId): ?Gasto
    {
        return Gasto::where('gasto_origen_gmf_id', $gastoId)->first();
    }

    public function test_el_pago_desde_una_cuenta_gravada_baja_el_saldo_y_genera_el_gmf(): void
    {
        $cuenta = $this->cuenta();
        $pagoId = $this->pagar(['cuenta_id' => $cuenta->id])->assertCreated()->json('data.id');

        $gasto = Gasto::where('cuenta_id', $cuenta->id)->whereNull('gasto_origen_gmf_id')->firstOrFail();
        $this->assertSame('499100.00', (string) $gasto->monto_cop);
        $gmf = $this->gmfDe($gasto->id);
        $this->assertSame('1996.40', (string) $gmf->monto_cop);
        $this->assertSame($cuenta->id, $gmf->cuenta_id);
        $this->assertSame('GMF', $gmf->subtipo_deduccion);
        // 10.000.000 − 499.100 − 1.996,40
        $this->assertSame('9498903.60', SaldoCuentaService::saldoDisponible($cuenta));
        $this->assertNotNull($pagoId);
    }

    public function test_la_cuenta_exenta_no_paga_gmf_dentro_del_tope(): void
    {
        $cuenta = $this->cuenta(exenta: true);
        $this->pagar(['cuenta_id' => $cuenta->id])->assertCreated();

        $gasto = Gasto::where('cuenta_id', $cuenta->id)->firstOrFail();
        $this->assertNull($this->gmfDe($gasto->id));
        $this->assertSame('9500900.00', SaldoCuentaService::saldoDisponible($cuenta));
    }

    public function test_la_cuenta_exenta_paga_gmf_solo_sobre_lo_que_supera_el_tope_mensual(): void
    {
        $cuenta = $this->cuenta(exenta: true);
        $sub = DB::table('categorias')->where('tipo', 'GASTO')->where('activa', true)->whereNotNull('categoria_padre_id')->first();
        $crear = fn (string $monto) => Gasto::create([
            'usuario_id' => $this->usuario->id, 'categoria_id' => $sub->categoria_padre_id, 'subcategoria_id' => $sub->id,
            'cuenta_id' => $cuenta->id, 'fecha' => '2026-11-10', 'moneda' => 'COP', 'monto_original' => $monto,
            'tasa_cambio_usada' => '1.0000', 'monto_cop' => $monto, 'naturaleza' => 'VARIABLE', 'necesidad' => 'ESENCIAL',
            'medio_pago' => 'TRANSFERENCIA', 'relevancia_tributaria' => 'NO_DEDUCIBLE', 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ]);

        $primero = $crear('15000000');
        GmfCuentaService::sincronizar($primero);
        $this->assertNull($this->gmfDe($primero->id));

        $segundo = $crear('9000000');
        GmfCuentaService::sincronizar($segundo);
        $gmf = $this->gmfDe($segundo->id);
        $this->assertNotNull($gmf);
        $this->assertTrue(bccomp((string) $gmf->monto_cop, '0', 2) === 1);
        $this->assertTrue(bccomp((string) $gmf->monto_cop, bcmul('9000000', '0.004', 2), 2) === -1);
    }

    public function test_editar_y_eliminar_el_pago_sincroniza_el_gmf(): void
    {
        $cuenta = $this->cuenta();
        $pagoId = $this->pagar(['cuenta_id' => $cuenta->id])->assertCreated()->json('data.id');
        $gasto = Gasto::where('cuenta_id', $cuenta->id)->whereNull('gasto_origen_gmf_id')->firstOrFail();

        $this->putJson(route('seguridad-social.pagos.update', $pagoId), [
            'periodo' => '2026-10', 'fecha_pago' => '2026-11-03', 'salud' => '1000000', 'pension' => '0',
        ])->assertOk();
        $this->assertSame('4000.00', (string) $this->gmfDe($gasto->id)->monto_cop);
        $this->assertSame('8996000.00', SaldoCuentaService::saldoDisponible($cuenta));

        $this->deleteJson(route('seguridad-social.pagos.destroy', $pagoId))->assertOk();
        $this->assertNull($this->gmfDe($gasto->id));
        $this->assertSame('10000000.00', SaldoCuentaService::saldoDisponible($cuenta));
    }

    public function test_el_gmf_de_cuenta_no_se_edita_a_mano_y_la_cuenta_ajena_se_rechaza(): void
    {
        $cuenta = $this->cuenta();
        $this->pagar(['cuenta_id' => $cuenta->id])->assertCreated();
        $gmf = Gasto::whereNotNull('gasto_origen_gmf_id')->firstOrFail();
        $this->putJson(route('gastos.update', $gmf->id), ['fecha' => '2026-11-03', 'moneda' => 'COP', 'monto_original' => '1', 'categoria_id' => $gmf->categoria_id, 'subcategoria_id' => $gmf->subcategoria_id, 'medio_pago' => 'OTRO', 'naturaleza' => 'VARIABLE', 'necesidad' => 'ESENCIAL', 'relevancia_tributaria' => 'NO_DEDUCIBLE'])->assertStatus(422)->assertJsonValidationErrors('gasto');

        $ajena = $this->cuenta(false, $this->crearUsuario());
        $this->entrarComo($this->usuario);
        $this->pagar(['cuenta_id' => $ajena->id, 'periodo' => '2026-09'])->assertStatus(422)->assertJsonValidationErrors('cuenta_id');
    }

    public function test_sin_cuenta_el_pago_no_genera_gmf(): void
    {
        $this->pagar()->assertCreated();
        $this->assertSame(0, Gasto::whereNotNull('gasto_origen_gmf_id')->count());
    }
}
