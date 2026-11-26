<?php

namespace Tests\Feature;

use App\Models\CuentaBancaria;
use App\Models\EntidadFinanciera;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Usuario;
use App\Services\SaldoCuentaService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** Cuentas de débito/ahorro: el saldo disponible se deriva de ingresos y gastos, nunca se guarda. */
class CuentaBancariaSaldoTest extends TestCase
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

    private function cuenta(string $saldoInicial = '1000000', bool $exenta = false): CuentaBancaria
    {
        $banco = EntidadFinanciera::firstOrCreate(['nombre' => 'Banco QA', 'tipo' => 'BANCO']);

        return CuentaBancaria::create([
            'usuario_id' => $this->usuario->id, 'entidad_financiera_id' => $banco->id,
            'saldo_inicial' => $saldoInicial, 'es_exenta_gmf' => $exenta,
        ]);
    }

    private function ingreso(CuentaBancaria $cuenta, string $bruto, string $estado = 'CONFIRMADO'): Ingreso
    {
        return Ingreso::create([
            'usuario_id' => $this->usuario->id, 'fuente_id' => $this->crearFuente($this->usuario), 'cuenta_id' => $cuenta->id,
            'fecha_recepcion' => '2026-11-10', 'clasificacion_tributaria' => 'RENTAS_TRABAJO', 'moneda' => 'COP',
            'monto_original' => $bruto, 'tasa_cambio_usada' => '1.0000', 'monto_bruto_cop' => $bruto,
            'retencion_fuente_cop' => '0', 'aportes_descontados_cop' => '0', 'origen' => 'MANUAL', 'estado' => $estado,
        ]);
    }

    private function gasto(CuentaBancaria $cuenta, string $monto): Gasto
    {
        $sub = DB::table('categorias')->where('tipo', 'GASTO')->where('activa', true)->whereNotNull('categoria_padre_id')->first();

        return Gasto::create([
            'usuario_id' => $this->usuario->id, 'categoria_id' => $sub->categoria_padre_id, 'subcategoria_id' => $sub->id,
            'cuenta_id' => $cuenta->id, 'fecha' => '2026-11-12', 'moneda' => 'COP', 'monto_original' => $monto,
            'tasa_cambio_usada' => '1.0000', 'monto_cop' => $monto, 'naturaleza' => 'VARIABLE', 'necesidad' => 'ESENCIAL',
            'medio_pago' => 'TARJETA_DEBITO', 'relevancia_tributaria' => 'NO_DEDUCIBLE', 'origen' => 'MANUAL', 'estado' => 'CONFIRMADO',
        ]);
    }

    public function test_sin_movimientos_el_saldo_es_el_inicial(): void
    {
        $cuenta = $this->cuenta('1500000.50');
        $this->assertSame('1500000.50', SaldoCuentaService::saldoDisponible($cuenta));
    }

    public function test_el_saldo_suma_ingresos_confirmados_y_resta_gastos(): void
    {
        $cuenta = $this->cuenta('1000000');
        $this->ingreso($cuenta, '500000');
        $this->ingreso($cuenta, '300000', 'PENDIENTE');
        $this->gasto($cuenta, '120000.25');

        $this->assertSame('1379999.75', SaldoCuentaService::saldoDisponible($cuenta));
    }

    public function test_borrar_un_movimiento_revierte_el_saldo_y_las_cuentas_no_se_mezclan(): void
    {
        $a = $this->cuenta('100');
        $b = $this->cuenta('200');
        $gasto = $this->gasto($a, '40');
        $this->ingreso($b, '10');

        $this->assertSame([$a->id => '60.00', $b->id => '210.00'], SaldoCuentaService::saldos([$a->id, $b->id]));

        $gasto->delete();
        $this->assertSame('100.00', SaldoCuentaService::saldoDisponible($a));
    }

    public function test_las_cuentas_de_otro_usuario_no_son_visibles(): void
    {
        $ajena = $this->cuenta();
        $otro = $this->crearUsuario();
        $this->entrarComo($otro);

        $this->assertNull(CuentaBancaria::find($ajena->id));
    }
}
