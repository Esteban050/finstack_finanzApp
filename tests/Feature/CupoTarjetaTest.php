<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Credito;
use App\Models\Gasto;
use App\Models\Usuario;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * MOD-08 — CP-CRE-013: cupo de la tarjeta de crédito. Es opcional, solo para
 * TARJETA_CREDITO, el saldo de capital que lo supera se avisa en el crédito y los gastos que lo superan se rechazan (H17).
 */
class CupoTarjetaTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-23 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    public function test_cp_cre_013_valida_el_cupo(): void
    {
        foreach (['0', '1500000.50'] as $cupo) {
            $this->postJson('/creditos', $this->tarjeta(['cupo' => $cupo]))
                ->assertStatus(422)->assertJsonValidationErrors('cupo');
        }

        $this->postJson('/creditos', $this->tarjeta(['tipo_credito' => 'LIBRE_INVERSION', 'cupo' => '2000000']))
            ->assertStatus(422)
            ->assertJsonPath('errors.cupo.0', 'El cupo solo aplica a tarjetas de crédito.');

        $id = $this->postJson('/creditos', $this->tarjeta(['cupo' => '2000000']))->assertCreated()->json('data.id');
        $this->assertSame('2000000.00', Credito::find($id)->cupo);
    }

    /** El aviso sale en la simulación y en el detalle; el gasto que supera el cupo se rechaza (H17). */
    public function test_cp_cre_013_avisa_en_la_simulacion_y_bloquea_el_gasto_que_supera_el_cupo(): void
    {
        $simulacion = collect($this->postJson('/creditos/calcular', $this->tarjeta(['monto' => '1500000', 'cupo' => '1000000']))
            ->assertOk()->json('data.alertas'))->firstWhere('codigo', 'SUPERA_CUPO');
        $this->assertSame('500000', $simulacion['exceso']);

        $id = $this->postJson('/creditos', $this->tarjeta(['cupo' => '1200000']))->assertCreated()->json('data.id');
        $this->assertSame([], $this->alertasCupo($id));

        // Disponible 200.000: 300.000 lo supera por 100.000 y no se guarda.
        $this->postJson('/gastos', $this->gastoTarjeta($id, '300000.00'))
            ->assertStatus(422)
            ->assertJsonPath('errors.monto_original.0', 'El gasto supera el cupo disponible de la tarjeta Banco QA ($ 200.000) en $ 100.000.');
        $this->assertSame('1000000.00', (string) Credito::find($id)->monto);
        $this->assertSame(0, Gasto::where('usuario_id', $this->usuario->id)->count(), 'el gasto rechazado no deja fila');

        // Exactamente el disponible sí entra.
        $this->postJson('/gastos', $this->gastoTarjeta($id, '200000.00'))->assertCreated();
        $this->assertSame('1200000.00', (string) Credito::find($id)->monto);
        $this->assertSame(1, Gasto::where('usuario_id', $this->usuario->id)->count());
    }

    /** El aviso del detalle sigue existiendo cuando el saldo supera el cupo (p. ej. al bajar el cupo). */
    public function test_el_detalle_avisa_cuando_el_saldo_supera_el_cupo(): void
    {
        $id = $this->postJson('/creditos', $this->tarjeta(['cupo' => '1500000']))->assertCreated()->json('data.id');
        $this->assertSame([], $this->alertasCupo($id));

        $this->putJson("/creditos/{$id}", $this->tarjeta(['cupo' => '900000']))->assertOk();

        $alertas = $this->alertasCupo($id);
        $this->assertCount(1, $alertas);
        $this->assertSame('ADVERTENCIA', $alertas[0]['nivel']);
        $this->assertSame('1000000', $alertas[0]['saldo']);
        $this->assertSame('900000', $alertas[0]['cupo']);
        $this->assertSame('100000', $alertas[0]['exceso']);
        $this->assertSame('Los consumos de la tarjeta ($ 1.000.000) superan su cupo de $ 900.000 en $ 100.000.', $alertas[0]['mensaje']);
    }

    public function test_h17_editar_un_gasto_de_tarjeta_por_encima_del_cupo_se_rechaza(): void
    {
        $id = $this->postJson('/creditos', $this->tarjeta(['cupo' => '1200000']))->assertCreated()->json('data.id');
        $gasto = $this->postJson('/gastos', $this->gastoTarjeta($id, '100000.00'))->assertCreated()->json('data.id');

        $this->putJson("/gastos/{$gasto}", $this->gastoTarjeta($id, '300000.00'))
            ->assertStatus(422)->assertJsonValidationErrors('monto_original');
        $this->putJson("/gastos/{$gasto}", $this->gastoTarjeta($id, '200000.00'))->assertOk();
    }

    public function test_h17_compra_nueva_a_cuotas_mayor_que_el_cupo_se_rechaza(): void
    {
        $this->postJson('/gastos', $this->gastoTarjeta('NUEVA', '1500000.00', [
            'banco_tarjeta' => 'Banco QA', 'numero_cuotas' => 6, 'fecha_corte' => '2026-11-25',
            'fecha_pago' => '2026-12-05', 'tasa_interes_anual' => '28', 'cupo_tarjeta' => '1000000',
        ]))->assertStatus(422)->assertJsonValidationErrors('monto_original');
        $this->assertSame(0, Credito::where('usuario_id', $this->usuario->id)->count());
    }

    private function gastoTarjeta(string $creditoId, string $monto, array $extra = []): array
    {
        $subcategoria = $this->categoriaPropia($this->categoriaPropia());

        return array_merge([
            'categoria_id' => $subcategoria->categoria_padre_id,
            'subcategoria_id' => $subcategoria->id,
            'fecha' => '2026-11-20',
            'moneda' => 'COP',
            'monto_original' => $monto,
            'medio_pago' => 'TARJETA_CREDITO',
            'credito_id' => $creditoId,
        ], $extra);
    }

    /** El cupo no es una condición del plan: se cambia aunque haya pagos. */
    public function test_cp_cre_013_el_cupo_se_edita_con_pagos(): void
    {
        $credito = $this->postJson('/creditos', $this->tarjeta(['fecha_inicio' => '2026-10-23']))->assertCreated()->json('data');

        $this->postJson("/creditos/{$credito['id']}/pagos", [
            'fecha' => '2026-11-23', 'valor' => $credito['calculo']['cuota'], 'medio' => 'TRANSFERENCIA',
        ])->assertCreated();

        $this->putJson("/creditos/{$credito['id']}", $this->tarjeta(['fecha_inicio' => '2026-10-23', 'cupo' => '3000000']))->assertOk();
        $this->assertSame('3000000.00', Credito::find($credito['id'])->cupo);

        $this->putJson("/creditos/{$credito['id']}", $this->tarjeta(['fecha_inicio' => '2026-10-23', 'monto' => '1100000']))
            ->assertStatus(422)->assertJsonValidationErrors('credito');
    }

    private function tarjeta(array $sobreescribir = []): array
    {
        return array_merge([
            'tipo_credito' => 'TARJETA_CREDITO',
            'banco' => 'Banco QA',
            'proposito' => 'Tarjeta QA',
            'monto' => '1000000',
            'tasa' => '2',
            'tipo_tasa' => 'MENSUAL',
            'numero_cuotas' => 12,
            'periodicidad' => 'MENSUAL',
            'fecha_inicio' => '2026-11-15',
        ], $sobreescribir);
    }

    /** Categoría de gasto propia; con padre es subcategoría (RN-026.2). */
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

    private function alertasCupo(string $id): array
    {
        return array_values(array_filter(
            $this->getJson("/creditos/{$id}")->assertOk()->json('data.alertas'),
            fn (array $alerta) => $alerta['codigo'] === 'SUPERA_CUPO'
        ));
    }
}
