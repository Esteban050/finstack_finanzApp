<?php

namespace Tests\Feature;

use App\Models\Credito;
use App\Models\CuentaBancaria;
use App\Models\EntidadFinanciera;
use App\Models\Gasto;
use App\Services\Reportes\DashboardResumenService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** Resumen: el bloque `cuentas` (débito con saldo disponible, tarjetas con cupo) va en el payload del Dashboard. */
class ResumenCuentasTest extends TestCase
{
    use UsaBaseLocal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal();
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    public function test_el_resumen_trae_cuentas_de_debito_y_tarjetas_con_sus_disponibles(): void
    {
        $u = $this->crearUsuario();
        $this->entrarComo($u);
        $banco = EntidadFinanciera::firstOrCreate(['nombre' => 'Banco QA', 'tipo' => 'BANCO']);
        $cuenta = CuentaBancaria::create(['usuario_id' => $u->id, 'entidad_financiera_id' => $banco->id, 'saldo_inicial' => '1000000', 'es_exenta_gmf' => true]);
        $sub = DB::table('categorias')->where('tipo', 'GASTO')->where('activa', true)->whereNotNull('categoria_padre_id')->first();
        Gasto::create([
            'usuario_id' => $u->id, 'categoria_id' => $sub->categoria_padre_id, 'subcategoria_id' => $sub->id, 'cuenta_id' => $cuenta->id,
            'fecha' => '2026-11-20', 'moneda' => 'COP', 'monto_original' => '250000', 'tasa_cambio_usada' => '1.0000', 'monto_cop' => '250000',
            'naturaleza' => 'VARIABLE', 'necesidad' => 'ESENCIAL', 'medio_pago' => 'TARJETA_DEBITO', 'relevancia_tributaria' => 'NO_DEDUCIBLE',
            'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ]);
        $this->post(route('perfil.tarjetas.store'), ['entidad_financiera_id' => $banco->id, 'cupo' => '3000000'])->assertSessionHasNoErrors();
        $this->assertSame(1, Credito::where('tipo_credito', 'TARJETA_CREDITO')->count());

        $cuentas = app(DashboardResumenService::class)->obtenerDashboard($u, 'mes-actual', null, null, CarbonImmutable::parse('2026-11-22'))['cuentas'];

        $this->assertCount(1, $cuentas['debito']);
        $this->assertSame('750000.00', $cuentas['debito'][0]['saldo_disponible']);
        $this->assertTrue($cuentas['debito'][0]['exenta_gmf']);
        $this->assertCount(1, $cuentas['tarjetas']);
        $this->assertSame('DEBITO', $cuentas['debito'][0]['tipo']);
        $this->assertSame('CREDITO', $cuentas['tarjetas'][0]['tipo']);
        $this->assertSame('3000000.00', $cuentas['tarjetas'][0]['cupo']);
        $this->assertSame('0.00', $cuentas['tarjetas'][0]['usado']);
        $this->assertSame('3000000.00', $cuentas['tarjetas'][0]['disponible']);
    }

    public function test_sin_cuentas_el_bloque_viene_vacio(): void
    {
        $u = $this->crearUsuario();
        $this->actingAs($u);

        $cuentas = app(DashboardResumenService::class)->obtenerDashboard($u)['cuentas'];

        $this->assertSame(['debito' => [], 'tarjetas' => []], $cuentas);
    }
}
