<?php

namespace Tests\Feature;

use App\Models\CuentaBancaria;
use App\Models\EntidadFinanciera;
use App\Models\Gasto;
use App\Models\Usuario;
use App\Services\SaldoCuentaService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** Seguimientos de la segunda revisión: orden de bloqueos, CHECK del banco, cuenta eliminada y alcance del GMF. */
class SeguimientosSegundaRevisionCuentasTest extends TestCase
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

    private function cuenta(bool $exenta = false, string $saldo = '10000000'): CuentaBancaria
    {
        $banco = EntidadFinanciera::firstOrCreate(['nombre' => 'Banco QA', 'tipo' => 'BANCO']);

        return CuentaBancaria::create(['usuario_id' => $this->usuario->id, 'entidad_financiera_id' => $banco->id,
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

    public function test_bloquear_varias_toma_los_bloqueos_en_orden_deterministico_sin_duplicados_ni_nulos(): void
    {
        $a = $this->cuenta();
        $b = $this->cuenta();
        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            if (str_contains($q->sql, 'for update')) {
                $consultas[] = $q;
            }
        });

        SaldoCuentaService::bloquearVarias([$b->id, null, $a->id, $b->id]);

        $this->assertCount(1, $consultas);
        $this->assertStringContainsString('order by "id"', $consultas[0]->sql);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $consultas[0]->bindings);
    }

    public function test_bloquear_varias_sin_ids_no_consulta(): void
    {
        $consultas = 0;
        DB::listen(function () use (&$consultas) {
            $consultas++;
        });
        SaldoCuentaService::bloquearVarias([null, null]);
        $this->assertSame(0, $consultas);
    }

    public function test_la_base_exige_exactamente_uno_de_entidad_o_banco_otro(): void
    {
        $banco = EntidadFinanciera::firstOrCreate(['nombre' => 'Banco QA', 'tipo' => 'BANCO']);
        $base = ['usuario_id' => $this->usuario->id, 'saldo_inicial' => 0, 'es_exenta_gmf' => false];

        foreach ([
            ['entidad_financiera_id' => null, 'banco_otro' => null],
            ['entidad_financiera_id' => $banco->id, 'banco_otro' => 'Otro'],
        ] as $caso) {
            try {
                DB::transaction(fn () => DB::table('cuentas_bancarias')->insert(
                    array_merge($base, $caso, ['id' => (string) Str::uuid()])
                ));
                $this->fail('La base aceptó una cuenta con banco inválido.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('cuentas_bancarias_banco_xor', $e->getMessage());
            }
        }
    }

    public function test_editar_sin_cuenta_en_la_peticion_desvincula_una_cuenta_eliminada(): void
    {
        $cuenta = $this->cuenta();
        $id = $this->postJson(route('gastos.store'), $this->datos(['cuenta_id' => $cuenta->id]))->assertCreated()->json('data.id');
        $this->assertSame(1, Gasto::where('gasto_origen_gmf_id', $id)->count());
        $cuenta->delete();

        $this->putJson(route('gastos.update', $id), $this->datos(['monto_original' => '2000000']))->assertOk();

        $this->assertNull(Gasto::find($id)->cuenta_id);
        $this->assertSame(0, Gasto::where('gasto_origen_gmf_id', $id)->count());
    }

    public function test_editar_pago_pila_sin_cuenta_en_la_peticion_desvincula_una_cuenta_eliminada(): void
    {
        $cuenta = $this->cuenta();
        $periodo = ['periodo' => '2026-10', 'salud' => '218900', 'pension' => '280200', 'fsp' => '0', 'arl' => '0', 'intereses_mora' => '0'];
        $pagoId = $this->postJson(route('seguridad-social.pagos.store'), $periodo + ['fecha_pago' => '2026-11-03', 'cuenta_id' => $cuenta->id])
            ->assertCreated()->json('data.id');
        $gastoId = DB::table('pagos_seguridad_social')->where('id', $pagoId)->value('gasto_id');
        $this->assertSame($cuenta->id, Gasto::find($gastoId)->cuenta_id);
        $cuenta->delete();

        $this->putJson(route('seguridad-social.pagos.update', $pagoId), $periodo + ['fecha_pago' => '2026-11-04'])->assertOk();

        $this->assertNull(Gasto::find($gastoId)->cuenta_id);
        $this->assertSame(0, Gasto::where('gasto_origen_gmf_id', $gastoId)->count());
    }

    public function test_un_gasto_sin_cuenta_no_toca_el_gmf_de_las_cuentas_exentas(): void
    {
        $cuenta = $this->cuenta(exenta: true, saldo: '999000000');
        // Sobre el tope mensual de 350 UVT para que exista GMF que vigilar.
        $id = $this->postJson(route('gastos.store'), $this->datos(['cuenta_id' => $cuenta->id, 'monto_original' => '90000000']))
            ->assertCreated()->json('data.id');
        $gmf = Gasto::where('gasto_origen_gmf_id', $id)->firstOrFail();
        DB::table('gastos')->where('id', $gmf->id)->update(['monto_cop' => '1.00', 'monto_original' => '1.00']);

        $efectivo = $this->postJson(route('gastos.store'), $this->datos(['medio_pago' => 'EFECTIVO']))->assertCreated()->json('data.id');
        $this->putJson(route('gastos.update', $efectivo), $this->datos(['medio_pago' => 'EFECTIVO', 'monto_original' => '5000']))->assertOk();

        $this->assertSame('1.00', (string) $gmf->fresh()->monto_cop);

        // Mover la salida exenta sí recalcula (comportamiento de T7).
        $this->putJson(route('gastos.update', $id), $this->datos(['cuenta_id' => $cuenta->id, 'monto_original' => '90000000', 'fecha' => '2026-11-21']))->assertOk();
        $this->assertNotSame('1.00', (string) $gmf->fresh()->monto_cop);
    }
}
