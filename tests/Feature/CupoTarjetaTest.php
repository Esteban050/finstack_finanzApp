<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Credito;
use App\Models\Usuario;
use Illuminate\Support\Str;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * MOD-08 — CP-CRE-013: cupo de la tarjeta de crédito. Es opcional, solo para
 * TARJETA_CREDITO, y si el saldo de capital lo supera se avisa sin bloquear.
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

    /** El aviso sale en la simulación, en el gasto que supera el cupo y en el detalle. */
    public function test_cp_cre_013_avisa_cuando_el_saldo_supera_el_cupo(): void
    {
        $simulacion = collect($this->postJson('/creditos/calcular', $this->tarjeta(['monto' => '1500000', 'cupo' => '1000000']))
            ->assertOk()->json('data.alertas'))->firstWhere('codigo', 'SUPERA_CUPO');
        $this->assertSame('500000', $simulacion['exceso']);

        $id = $this->postJson('/creditos', $this->tarjeta(['cupo' => '1200000']))->assertCreated()->json('data.id');
        $this->assertSame([], $this->alertasCupo($id));

        $subcategoria = $this->categoriaPropia($this->categoriaPropia());
        $gasto = $this->postJson('/gastos', [
            'categoria_id' => $subcategoria->categoria_padre_id,
            'subcategoria_id' => $subcategoria->id,
            'fecha' => '2026-11-20',
            'moneda' => 'COP',
            'monto_original' => '300000.00',
            'medio_pago' => 'TARJETA_CREDITO',
            'credito_id' => $id,
        ])->assertCreated();

        $gasto->assertJsonPath('advertencias.0.codigo', 'SUPERA_CUPO')
            ->assertJsonPath('advertencias.0.exceso', '100000');
        $this->assertSame('Gasto registrado exitosamente. La tarjeta Banco QA supera su cupo en $ 100.000.', $gasto->json('mensaje'));

        $alertas = $this->alertasCupo($id);
        $this->assertCount(1, $alertas);
        $this->assertSame('ADVERTENCIA', $alertas[0]['nivel']);
        $this->assertSame('1300000', $alertas[0]['saldo']);
        $this->assertSame('Los consumos de la tarjeta ($ 1.300.000) superan su cupo de $ 1.200.000 en $ 100.000.', $alertas[0]['mensaje']);
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
