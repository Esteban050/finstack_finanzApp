<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Credito;
use App\Models\Usuario;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * H07: el interés de la primera cuota de una tarjeta se causa por compra,
 * desde la fecha de cada compra y con días reales (28 % EA, base 365).
 */
class TarjetaInteresPrimeraCuotaPorCompraTest extends TestCase
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

    private function gastoTarjeta(string $fecha, string $monto, array $extra = [])
    {
        $sub = $this->categoriaPropia($this->categoriaPropia());

        return $this->postJson('/gastos', array_merge([
            'categoria_id' => $sub->categoria_padre_id,
            'subcategoria_id' => $sub->id,
            'fecha' => $fecha,
            'moneda' => 'COP',
            'monto_original' => $monto,
            'medio_pago' => 'TARJETA_CREDITO',
        ], $extra))->assertCreated();
    }

    public function test_interes_de_la_primera_cuota_se_calcula_por_compra_con_dias_reales(): void
    {
        $primera = $this->gastoTarjeta('2026-11-10', '1200000.00', [
            'credito_id' => 'NUEVA', 'banco_tarjeta' => 'Bancolombia', 'numero_cuotas' => 6,
            'fecha_corte' => '2026-11-15', 'fecha_pago' => '2026-11-30',
            'tasa_interes_anual' => '28', 'cupo_tarjeta' => '5000000',
        ]);
        $tarjetaId = $primera->json('data.credito_id');

        // Platzi 1.200.000 x 20 dias = 16.342,17 (compra unica: ya era correcto).
        $this->assertSame('16342.00', (string) Credito::findOrFail($tarjetaId)->cuotasVigentes()->orderBy('numero')->first()->interes);

        $this->gastoTarjeta('2026-11-24', '1500000.00', ['credito_id' => $tarjetaId]);
        $this->gastoTarjeta('2026-11-24', '400000.00', ['credito_id' => $tarjetaId]);

        $cuota1 = Credito::findOrFail($tarjetaId)->cuotasVigentes()->orderBy('numero')->first();

        // 16.342,17 + 6.099,33 + 1.626,49 = 24.067,99 -> al peso 24.068 (antes: 42.217).
        $this->assertSame('24068.00', (string) $cuota1->interes);
    }

    /** N01: "para cancelar a la fecha" causa por compra y días reales; las compras de hoy aún no causan. */
    public function test_para_cancelar_a_la_fecha_causa_interes_por_compra_y_dias_reales(): void
    {
        $primera = $this->gastoTarjeta('2026-11-10', '1200000.00', [
            'credito_id' => 'NUEVA', 'banco_tarjeta' => 'Bancolombia', 'numero_cuotas' => 6,
            'fecha_corte' => '2026-11-15', 'fecha_pago' => '2026-11-30',
            'tasa_interes_anual' => '28', 'cupo_tarjeta' => '3000000',
        ]);
        $tarjetaId = $primera->json('data.credito_id');

        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-25 10:00:00', 'America/Bogota'));
        $this->entrarComo($this->usuario);
        $this->gastoTarjeta('2026-11-25', '1500000.00', ['credito_id' => $tarjetaId]);
        $this->gastoTarjeta('2026-11-25', '300000.00', ['credito_id' => $tarjetaId]);

        $cuenta = $this->getJson("/creditos/{$tarjetaId}")->assertOk()->json('data.estado_cuenta');
        $this->assertSame('3012236', (string) $cuenta['saldo_para_cancelar']);

        $pago = $this->postJson("/creditos/{$tarjetaId}/pagos", [
            'fecha' => '2026-11-25', 'valor' => '497082', 'medio' => 'TRANSFERENCIA', 'aplicacion_excedente' => 'ANTICIPO',
        ])->assertCreated()->json('data');
        $conceptos = array_column($pago['pago']['imputaciones'], 'valor', 'concepto');
        $this->assertSame('12236', (string) $conceptos['INTERES_CORRIENTE']);
        $this->assertSame('2515154', (string) $this->getJson("/creditos/{$tarjetaId}")->json('data.estado_cuenta.saldo_capital'));
    }
}
