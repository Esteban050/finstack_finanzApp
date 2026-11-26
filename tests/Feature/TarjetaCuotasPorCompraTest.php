<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Credito;
use App\Models\EntidadFinanciera;
use App\Models\Gasto;
use App\Models\Usuario;
use App\Services\CreditoService;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * Plan de la tarjeta compra por compra: cada compra se reparte en sus cuotas, entra en el extracto
 * según el día de corte y vence el día de pago siguiente; sin intereses no causa ninguno.
 * Hoy fijo: 2026-11-26; corte día 15, pago día 5.
 */
class TarjetaCuotasPorCompraTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;
    private Categoria $padre;
    private Categoria $sub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-26 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
        $this->padre = $this->categoria();
        $this->sub = $this->categoria($this->padre);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    public function test_compra_a_tres_cuotas_sin_intereses_reparte_el_capital_por_mes_desde_el_pago_del_corte(): void
    {
        $tarjeta = $this->tarjeta();
        $this->comprar($tarjeta, '300000', ['fecha' => '2026-11-10', 'numero_cuotas' => 3, 'sin_intereses' => true]);

        $this->assertSame([
            ['2026-12-05', '100000', '0'], ['2027-01-05', '100000', '0'], ['2027-02-05', '100000', '0'],
        ], $this->plan($tarjeta));
    }

    public function test_la_compra_posterior_al_corte_cae_en_el_extracto_siguiente(): void
    {
        $tarjeta = $this->tarjeta();
        $this->comprar($tarjeta, '300000', ['fecha' => '2026-11-20', 'numero_cuotas' => 3, 'sin_intereses' => true]);

        $this->assertSame('2027-01-05', $this->plan($tarjeta)[0][0]);
    }

    public function test_cuotas_vacias_es_una_cuota_con_interes_por_dias_a_la_tasa_de_la_tarjeta(): void
    {
        $tarjeta = $this->tarjeta();
        $id = $this->comprar($tarjeta, '100000', ['fecha' => '2026-11-10', 'numero_cuotas' => '']);

        $this->assertSame(1, Gasto::find($id)->numero_cuotas);
        $esperado = CreditoService::interesPorDias('100000', (string) Credito::find($tarjeta)->tasa_ea, 25);
        $this->assertSame([['2026-12-05', '100000', $esperado]], $this->plan($tarjeta));
        $this->assertSame(1, bccomp($esperado, '0', 0));
    }

    public function test_cada_compra_se_amortiza_por_su_cuenta_y_las_cuotas_de_un_mes_se_suman(): void
    {
        $tarjeta = $this->tarjeta();
        $this->comprar($tarjeta, '300000', ['fecha' => '2026-11-10', 'numero_cuotas' => 3, 'sin_intereses' => true]);
        $this->comprar($tarjeta, '100000', ['fecha' => '2026-11-12', 'sin_intereses' => true]);

        $this->assertSame([
            ['2026-12-05', '200000', '0'], ['2027-01-05', '100000', '0'], ['2027-02-05', '100000', '0'],
        ], $this->plan($tarjeta));

        // El detalle muestra cuotas y condición de cada compra.
        $compras = $this->getJson("/creditos/{$tarjeta}")->assertOk()->json('data.compras');
        $this->assertEqualsCanonicalizing([[3, true], [1, true]], array_map(fn ($c) => [$c['numero_cuotas'], $c['sin_intereses']], $compras));
    }

    public function test_con_intereses_las_cuotas_siguientes_causan_sobre_el_saldo_de_la_compra(): void
    {
        $tarjeta = $this->tarjeta();
        $this->comprar($tarjeta, '300000', ['fecha' => '2026-11-10', 'numero_cuotas' => 3]);

        $filas = $this->plan($tarjeta);
        $i = (string) Credito::find($tarjeta)->tasa_periodica;
        $this->assertSame(CreditoService::alPeso(bcmul('200000', $i, 30)), $filas[1][2]);
        $this->assertSame(CreditoService::alPeso(bcmul('100000', $i, 30)), $filas[2][2]);
        $this->assertSame('100000', $filas[1][1]);
    }

    public function test_editar_las_cuotas_o_borrar_la_compra_regenera_el_plan(): void
    {
        $tarjeta = $this->tarjeta();
        $id = $this->comprar($tarjeta, '300000', ['fecha' => '2026-11-10', 'sin_intereses' => true]);
        $this->assertCount(1, $this->plan($tarjeta));

        $this->putJson("/gastos/{$id}", $this->payload($tarjeta, '300000', ['fecha' => '2026-11-10', 'numero_cuotas' => 3, 'sin_intereses' => true]))->assertOk();
        $this->assertCount(3, $this->plan($tarjeta));

        $this->putJson("/gastos/{$id}", $this->payload($tarjeta, '300000', ['fecha' => '2026-11-10', 'numero_cuotas' => 3, 'sin_intereses' => false]))->assertOk();
        $this->assertGreaterThan(0, (int) $this->plan($tarjeta)[0][2]);

        $otra = $this->comprar($tarjeta, '100000', ['fecha' => '2026-11-12', 'sin_intereses' => true]);
        $this->assertCount(3, $this->plan($tarjeta));
        $this->deleteJson("/gastos/{$otra}")->assertOk();
        $this->assertSame('300000.00', (string) Credito::find($tarjeta)->monto);
    }

    public function test_una_tarjeta_sin_corte_ni_pago_no_admite_cuotas_ni_sin_intereses(): void
    {
        $legada = $this->postJson('/creditos', [
            'tipo_credito' => 'TARJETA_CREDITO', 'banco' => 'Banco QA', 'proposito' => 'Tarjeta vieja', 'monto' => '1000000', 'tasa' => '2',
            'tipo_tasa' => 'MENSUAL', 'numero_cuotas' => 12, 'periodicidad' => 'MENSUAL', 'fecha_inicio' => '2026-11-01', 'cupo' => '5000000',
        ])->assertCreated()->json('data.id');

        $this->postJson('/gastos', $this->payload($legada, '100000', ['numero_cuotas' => 3]))
            ->assertStatus(422)->assertJsonValidationErrors('numero_cuotas');
        $this->postJson('/gastos', $this->payload($legada, '100000', ['sin_intereses' => true]))
            ->assertStatus(422)->assertJsonValidationErrors('numero_cuotas');
        // Sin cuotas ni «sin intereses» sigue funcionando como antes.
        $this->postJson('/gastos', $this->payload($legada, '100000'))->assertCreated();
    }

    public function test_valida_el_rango_de_cuotas_de_la_compra(): void
    {
        $tarjeta = $this->tarjeta();
        foreach ([0, 73, 'abc'] as $cuotas) {
            $this->postJson('/gastos', $this->payload($tarjeta, '100000', ['numero_cuotas' => $cuotas]))
                ->assertStatus(422)->assertJsonValidationErrors('numero_cuotas');
        }
    }

    public function test_una_compra_nueva_con_pagos_vigentes_conserva_el_capital_del_plan(): void
    {
        $tarjeta = $this->tarjeta();
        $this->comprar($tarjeta, '300000', ['fecha' => '2026-10-10', 'numero_cuotas' => 3, 'sin_intereses' => true]);
        // Plan: 2026-11-05 (ya exigible), 2026-12-05 y 2027-01-05.
        $this->postJson("/creditos/{$tarjeta}/pagos", ['fecha' => '2026-11-26', 'valor' => '100000', 'medio' => 'TRANSFERENCIA'])->assertCreated();

        $this->comprar($tarjeta, '60000', ['fecha' => '2026-11-26', 'numero_cuotas' => 2, 'sin_intereses' => true]);

        $this->assertSame('360000.00', (string) Credito::find($tarjeta)->monto);
        $this->assertSame('260000', (string) $this->getJson("/creditos/{$tarjeta}")->assertOk()->json('data.estado_cuenta.saldo_capital'));
    }

    /** @return list<array{string, string, string}> [fecha, capital, interés] de las cuotas vigentes */
    private function plan(string $tarjeta): array
    {
        return array_map(
            fn ($f) => [$f['fecha'], (string) $f['abono_capital'], (string) $f['interes']],
            $this->getJson("/creditos/{$tarjeta}")->assertOk()->json('data.calculo.tabla')
        );
    }

    private function tarjeta(): string
    {
        $banco = EntidadFinanciera::query()->firstOrFail();
        $this->post(route('perfil.tarjetas.store'), [
            'entidad_financiera_id' => $banco->id, 'cupo' => '5000000', 'tasa_interes_anual' => '24', 'dia_corte' => '15', 'dia_pago' => '5',
        ])->assertSessionHasNoErrors();

        return Credito::where('usuario_id', $this->usuario->id)->where('tipo_credito', 'TARJETA_CREDITO')->firstOrFail()->id;
    }

    private function comprar(string $tarjeta, string $monto, array $extra = []): string
    {
        return $this->postJson('/gastos', $this->payload($tarjeta, $monto, $extra))->assertCreated()->json('data.id');
    }

    private function payload(string $tarjeta, string $monto, array $extra = []): array
    {
        return array_merge([
            'categoria_id' => $this->padre->id, 'subcategoria_id' => $this->sub->id, 'fecha' => '2026-11-10', 'moneda' => 'COP',
            'monto_original' => $monto, 'medio_pago' => 'TARJETA_CREDITO', 'credito_id' => $tarjeta,
        ], $extra);
    }

    private function categoria(?Categoria $padre = null): Categoria
    {
        $c = new Categoria;
        $c->forceFill([
            'usuario_id' => $this->usuario->id, 'categoria_padre_id' => $padre?->id, 'tipo' => 'GASTO',
            'nombre' => 'Prueba '.Str::random(8), 'naturaleza_sugerida' => $padre === null ? null : 'VARIABLE',
            'necesidad_sugerida' => $padre === null ? null : 'DISCRECIONAL', 'es_predeterminada' => false, 'activa' => true, 'creado_en' => now(),
        ])->save();

        return $c;
    }
}
