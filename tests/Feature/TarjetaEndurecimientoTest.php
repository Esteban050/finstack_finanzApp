<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Credito;
use App\Models\Gasto;
use App\Models\Usuario;
use App\Services\CreditoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** Endurecimiento de H07/H11/H17: casos límite del interés por compra, del cupo y del pago de tarjeta. */
class TarjetaEndurecimientoTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-24 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function categoriaPropia(?Categoria $padre = null): Categoria
    {
        $categoria = new Categoria;
        $categoria->forceFill([
            'usuario_id' => $this->usuario->id,
            'categoria_padre_id' => $padre?->id,
            'tipo' => 'GASTO',
            'nombre' => 'Prueba '.Str::random(8),
            'naturaleza_sugerida' => $padre === null ? null : 'VARIABLE',
            'necesidad_sugerida' => $padre === null ? null : 'DISCRECIONAL',
            'es_predeterminada' => false,
            'activa' => true,
            'creado_en' => now(),
        ])->save();

        return $categoria;
    }

    private function cuerpoGasto(string $fecha, string $monto, array $extra = []): array
    {
        $sub = $this->categoriaPropia($this->categoriaPropia());

        return array_merge([
            'categoria_id' => $sub->categoria_padre_id,
            'subcategoria_id' => $sub->id,
            'fecha' => $fecha,
            'moneda' => 'COP',
            'monto_original' => $monto,
            'medio_pago' => 'TARJETA_CREDITO',
        ], $extra);
    }

    private function tarjetaNueva(array $extra = []): array
    {
        return array_merge([
            'credito_id' => 'NUEVA', 'banco_tarjeta' => 'Bancolombia', 'numero_cuotas' => 6,
            'fecha_corte' => '2026-11-15', 'fecha_pago' => '2026-11-30',
            'tasa_interes_anual' => '28', 'cupo_tarjeta' => '5000000',
        ], $extra);
    }

    private function cuota1(string $tarjetaId)
    {
        return Credito::findOrFail($tarjetaId)->cuotasVigentes()->orderBy('numero')->first();
    }

    // (a) interesComprasPorDias

    public function test_compra_posterior_a_la_fecha_de_pago_no_genera_interes_negativo(): void
    {
        $inicio = CarbonImmutable::parse('2026-11-10');
        $hasta = CarbonImmutable::parse('2026-11-30');

        $solo = CreditoService::interesComprasPorDias('1000000', [['monto' => '1000000', 'fecha' => '2026-11-10']], '0.28', $inicio, $hasta);
        $con = CreditoService::interesComprasPorDias('1500000', [
            ['monto' => '1000000', 'fecha' => '2026-11-10'],
            ['monto' => '500000', 'fecha' => '2026-12-05'],
        ], '0.28', $inicio, $hasta);

        $this->assertSame($solo, $con);
    }

    public function test_el_capital_fechado_no_supera_el_saldo(): void
    {
        $inicio = CarbonImmutable::parse('2026-11-01');
        $hasta = CarbonImmutable::parse('2026-11-30');

        $capado = CreditoService::interesComprasPorDias('1000000', [
            ['monto' => '800000', 'fecha' => '2026-11-10'],
            ['monto' => '700000', 'fecha' => '2026-11-20'],
        ], '0.28', $inicio, $hasta);

        $esperado = bcadd(
            CreditoService::interesPorDias('800000', '0.28', 20),
            CreditoService::interesPorDias('200000', '0.28', 10),
            0
        );
        $this->assertLessThanOrEqual(1, abs((int) bcsub($capado, $esperado, 0)));
    }

    // (b) cupo_tarjeta ausente o no numérico simple

    public function test_cupo_de_tarjeta_nueva_ausente_o_no_simple_es_error_de_validacion(): void
    {
        foreach ([null, 'abc', '1e6', ['1'], '-5'] as $cupo) {
            $extra = $this->tarjetaNueva();
            if ($cupo === null) {
                unset($extra['cupo_tarjeta']);
            } else {
                $extra['cupo_tarjeta'] = $cupo;
            }

            $this->postJson('/gastos', $this->cuerpoGasto('2026-11-10', '1200000.00', $extra))
                ->assertStatus(422)->assertJsonValidationErrors('cupo_tarjeta');
        }
        $this->assertSame(0, Gasto::where('usuario_id', $this->usuario->id)->count());
    }

    // (d) pago de tarjeta

    private function tarjetaConPlan(string $tasa = '2'): array
    {
        return $this->postJson('/creditos', [
            'tipo_credito' => 'TARJETA_CREDITO', 'banco' => 'Banco QA', 'proposito' => 'Tarjeta',
            'monto' => '1000000', 'tasa' => $tasa, 'tipo_tasa' => 'MENSUAL', 'numero_cuotas' => 12,
            'periodicidad' => 'MENSUAL', 'fecha_inicio' => '2026-10-23',
        ])->assertCreated()->json('data');
    }

    public function test_pago_de_tarjeta_sin_intereses_no_crea_gasto(): void
    {
        $credito = $this->tarjetaConPlan('0');

        $respuesta = $this->postJson("/creditos/{$credito['id']}/pagos", [
            'fecha' => '2026-11-23', 'valor' => $credito['calculo']['cuota'], 'medio' => 'TRANSFERENCIA',
        ])->assertCreated()->json('data');

        $conceptos = array_column($respuesta['pago']['imputaciones'], 'valor', 'concepto');
        $this->assertSame('0', $conceptos['INTERES_CORRIENTE'] ?? '0');
        $this->assertNull($respuesta['pago']['gasto_id']);
        $this->assertSame(0, Gasto::where('usuario_id', $this->usuario->id)->count());
    }

    public function test_pago_de_tarjeta_con_abono_extraordinario_excluye_el_capital_del_gasto(): void
    {
        $credito = $this->tarjetaConPlan();
        $valor = bcadd((string) $credito['calculo']['cuota'], '100000', 0);

        $respuesta = $this->postJson("/creditos/{$credito['id']}/pagos", [
            'fecha' => '2026-11-23', 'valor' => $valor, 'medio' => 'TRANSFERENCIA', 'aplicacion_excedente' => 'ABONO_CAPITAL', 'reducir' => 'PLAZO',
        ])->assertCreated()->json('data');

        $imputado = array_column($respuesta['pago']['imputaciones'], 'valor', 'concepto');
        $this->assertArrayHasKey('ABONO_EXTRAORDINARIO', $imputado);
        $gasto = Gasto::findOrFail($respuesta['pago']['gasto_id']);
        $this->assertSame('20000.00', (string) $gasto->monto_cop);
    }

    // (e) editar y borrar recalculan el interés de la primera cuota

    public function test_editar_y_borrar_un_gasto_de_tarjeta_recalculan_el_interes_de_la_primera_cuota(): void
    {
        $primera = $this->postJson('/gastos', $this->cuerpoGasto('2026-11-10', '1200000.00', $this->tarjetaNueva()))->assertCreated();
        $tarjetaId = $primera->json('data.credito_id');
        $segundo = $this->postJson('/gastos', $this->cuerpoGasto('2026-11-24', '1500000.00', ['credito_id' => $tarjetaId]))
            ->assertCreated()->json('data.id');

        $this->assertSame('22442.00', (string) $this->cuota1($tarjetaId)->interes);

        $cuerpo = $this->cuerpoGasto('2026-11-23', '1000000.00', ['credito_id' => $tarjetaId]);
        $this->putJson("/gastos/{$segundo}", $cuerpo)->assertOk();
        $esperado = bcadd('16342', CreditoService::interesPorDias('1000000', '0.28', 7), 0);
        $this->assertLessThanOrEqual(1, abs((int) bcsub((string) $this->cuota1($tarjetaId)->interes, $esperado, 0)));

        $this->deleteJson("/gastos/{$segundo}")->assertSuccessful();
        $this->assertSame('16342.00', (string) $this->cuota1($tarjetaId)->interes);
    }
}
