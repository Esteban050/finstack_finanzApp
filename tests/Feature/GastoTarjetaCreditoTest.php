<?php

namespace Tests\Feature;

use App\Models\Credito;
use App\Models\Gasto;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Gasto con tarjeta de crédito: se vincula a un crédito (MOD-08) con su plan de
 * cuotas, y los detalles de ingresos y gastos en USD muestran la TRM usada.
 *
 * Igual que las demás pruebas de dominio: base local de PostgreSQL dentro de
 * una transacción que se revierte al terminar cada prueba.
 */
class GastoTarjetaCreditoTest extends TestCase
{
    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => 'bd_finanzApp']);
        DB::purge('pgsql');
        DB::connection('pgsql')->beginTransaction();

        $this->usuario = Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => 'Tarjeta',
            'correo' => 'tarjeta.' . Str::lower(Str::random(8)) . '@scfp.test',
            'password_hash' => bcrypt('Secret123!'),
            'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(1000000000, 1999999999),
            'rol' => 'USUARIO',
            'estado' => 'ACTIVA',
        ]);

        $this->actingAs($this->usuario);
        $this->withoutMiddleware(\App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class);
    }

    protected function tearDown(): void
    {
        DB::connection('pgsql')->rollBack();
        parent::tearDown();
    }

    /** @return array{0: string, 1: string} categoría y subcategoría de gasto */
    private function categoria(): array
    {
        $sub = DB::table('categorias')
            ->where('tipo', 'GASTO')->where('activa', true)->whereNotNull('categoria_padre_id')
            ->first();

        return [$sub->categoria_padre_id, $sub->id];
    }

    private function payload(array $extra = []): array
    {
        [$categoria, $sub] = $this->categoria();

        return array_merge([
            'fecha' => CarbonImmutable::today()->subDays(5)->toDateString(),
            'monto_original' => '1200000.00',
            'moneda' => 'COP',
            'categoria_id' => $categoria,
            'subcategoria_id' => $sub,
            'medio_pago' => 'EFECTIVO',
            'naturaleza' => 'VARIABLE',
            'necesidad' => 'DISCRECIONAL',
            'relevancia_tributaria' => 'NO_DEDUCIBLE',
        ], $extra);
    }

    private function camposTarjeta(array $extra = []): array
    {
        return array_merge([
            'medio_pago' => 'TARJETA_CREDITO',
            'credito_id' => 'NUEVA',
            'banco_tarjeta' => 'Bancolombia',
            'numero_cuotas' => 12,
            'fecha_corte' => CarbonImmutable::today()->addDays(3)->toDateString(),
            'fecha_pago' => CarbonImmutable::today()->addDays(12)->toDateString(),
            'tasa_interes_anual' => '24.5',
            'cupo_tarjeta' => '5000000',
        ], $extra);
    }

    public function test_gasto_con_tarjeta_credito_crea_un_credito_vinculado_con_su_plan(): void
    {
        $respuesta = $this->postJson(route('gastos.store'), $this->payload($this->camposTarjeta()));

        $respuesta->assertCreated();
        $gasto = Gasto::findOrFail($respuesta->json('data.id'));

        $this->assertNotNull($gasto->credito_id);
        $this->assertSame(1, Credito::where('usuario_id', $this->usuario->id)->count());

        $credito = Credito::findOrFail($gasto->credito_id);
        $this->assertSame('TARJETA_CREDITO', $credito->tipo_credito);
        $this->assertSame('Bancolombia', $credito->banco);
        $this->assertSame('1200000.00', (string) $credito->monto);
        $this->assertSame(12, $credito->numero_cuotas);
        $this->assertSame('MENSUAL', $credito->periodicidad);
        $this->assertSame('FRANCES', $credito->sistema_amortizacion);
        $this->assertSame('EA', $credito->tipo_tasa);
        $this->assertSame('24.500000', (string) $credito->tasa_ingresada);
        $this->assertSame($gasto->fecha->toDateString(), $credito->fecha_inicio->toDateString());
        $this->assertSame(CarbonImmutable::today()->addDays(12)->toDateString(), $credito->fecha_primer_pago->toDateString());
        $this->assertSame(CarbonImmutable::today()->addDays(3)->toDateString(), $credito->fecha_corte->toDateString());
        $this->assertSame(12, $credito->cuotasVigentes()->count());
        // El formulario de la tarjeta registra también su cupo.
        $this->assertSame('5000000.00', (string) $credito->cupo);
    }

    public function test_cupo_de_la_tarjeta_invalido_se_rechaza(): void
    {
        foreach (['0', '-100', '1500000.50', 'abc'] as $cupo) {
            $this->postJson(route('gastos.store'), $this->payload($this->camposTarjeta(['cupo_tarjeta' => $cupo])))
                ->assertStatus(422)->assertJsonValidationErrors(['cupo_tarjeta']);
        }
        $this->assertSame(0, Credito::where('usuario_id', $this->usuario->id)->count());
    }

    public function test_gasto_con_tarjeta_sin_tarjetas_registradas_exige_registrarla(): void
    {
        $this->postJson(route('gastos.store'), $this->payload(['medio_pago' => 'TARJETA_CREDITO']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['credito_id' => 'Registra la tarjeta: elige "Registrar tarjeta nueva" e indica el banco, el interés anual y el cupo.']);
        $this->assertSame(0, Gasto::where('usuario_id', $this->usuario->id)->count());
    }

    public function test_tarjeta_registrada_con_un_gasto_recibe_los_siguientes_gastos(): void
    {
        $tarjetaId = $this->postJson(route('gastos.store'), $this->payload($this->camposTarjeta()))->assertCreated()->json('data.credito_id');

        // Con una sola tarjeta, el formulario la deja elegida y el gasto se suma a ella.
        $this->get(route('gastos.create'))->assertOk()->assertSee('<option value="'.$tarjetaId.'" selected', false);
        $id = $this->postJson(route('gastos.store'), $this->payload(['medio_pago' => 'TARJETA_CREDITO', 'monto_original' => '300000.00']))
            ->assertCreated()->json('data.id');

        $this->assertSame($tarjetaId, Gasto::findOrFail($id)->credito_id);
        $this->assertSame('1500000.00', (string) Credito::findOrFail($tarjetaId)->monto);
        $this->assertSame(1, Credito::where('usuario_id', $this->usuario->id)->count());
    }

    public function test_faltan_campos_de_tarjeta_devuelve_errores_y_no_crea_nada(): void
    {
        $respuesta = $this->postJson(route('gastos.store'), $this->payload(['medio_pago' => 'TARJETA_CREDITO', 'credito_id' => 'NUEVA']));

        $respuesta->assertStatus(422)->assertJsonValidationErrors([
            'banco_tarjeta', 'numero_cuotas', 'fecha_corte', 'fecha_pago', 'tasa_interes_anual', 'cupo_tarjeta',
        ]);
        $this->assertSame(0, Credito::where('usuario_id', $this->usuario->id)->count());
        $this->assertSame(0, Gasto::where('usuario_id', $this->usuario->id)->count());
    }

    public function test_cuotas_e_interes_fuera_de_rango_se_rechazan(): void
    {
        $this->postJson(route('gastos.store'), $this->payload($this->camposTarjeta(['numero_cuotas' => 100, 'tasa_interes_anual' => '-1'])))
            ->assertStatus(422)->assertJsonValidationErrors(['numero_cuotas', 'tasa_interes_anual']);
    }

    public function test_gasto_en_efectivo_no_crea_credito_ni_exige_campos(): void
    {
        $respuesta = $this->postJson(route('gastos.store'), $this->payload());

        $respuesta->assertCreated();
        $this->assertNull(Gasto::findOrFail($respuesta->json('data.id'))->credito_id);
        $this->assertSame(0, Credito::where('usuario_id', $this->usuario->id)->count());
    }

    public function test_la_compra_nueva_marca_el_gasto_y_no_suma_el_consumo_dos_veces(): void
    {
        $id = $this->postJson(route('gastos.store'), $this->payload($this->camposTarjeta()))->assertCreated()->json('data.id');
        $gasto = Gasto::findOrFail($id);

        $this->assertTrue($gasto->genera_credito);
        $this->assertSame('1200000.00', (string) Credito::findOrFail($gasto->credito_id)->monto);
    }

    public function test_fecha_de_pago_no_posterior_a_la_del_gasto_se_rechaza(): void
    {
        $this->postJson(route('gastos.store'), $this->payload($this->camposTarjeta(['fecha_pago' => CarbonImmutable::today()->subDays(5)->toDateString()])))
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_pago']);
        $this->assertSame(0, Credito::where('usuario_id', $this->usuario->id)->count());
    }

    public function test_los_campos_de_compra_nueva_se_ignoran_con_otro_medio_de_pago(): void
    {
        $respuesta = $this->postJson(route('gastos.store'), $this->payload(['credito_id' => 'NUEVA']));

        $respuesta->assertCreated();
        $gasto = Gasto::findOrFail($respuesta->json('data.id'));
        $this->assertNull($gasto->credito_id);
        $this->assertFalse($gasto->genera_credito);
        $this->assertSame(0, Credito::where('usuario_id', $this->usuario->id)->count());
    }

    public function test_credito_id_invalido_se_rechaza(): void
    {
        $this->postJson(route('gastos.store'), $this->payload(['medio_pago' => 'TARJETA_CREDITO', 'credito_id' => 'otra']))
            ->assertStatus(422)->assertJsonValidationErrors(['credito_id']);
    }

    private function tarjetaExistente(string $monto = '1000000.00'): Credito
    {
        $controlador = app(\App\Http\Controllers\CreditoController::class);
        $datos = $controlador->validarDatos([
            'tipo_credito' => 'TARJETA_CREDITO', 'banco' => 'Davivienda', 'proposito' => 'Tarjeta personal',
            'monto' => $monto, 'tipo_tasa' => 'EA', 'tasa' => '20', 'numero_cuotas' => 12,
            'periodicidad' => 'MENSUAL', 'sistema_amortizacion' => 'FRANCES',
            'fecha_inicio' => CarbonImmutable::today()->subMonth()->toDateString(),
            'fecha_primer_pago' => CarbonImmutable::today()->addDays(10)->toDateString(),
        ]);

        return $controlador->registrar($datos, $this->usuario->id);
    }

    public function test_elegir_una_tarjeta_existente_suma_el_consumo_sin_crear_otro_credito(): void
    {
        $tarjeta = $this->tarjetaExistente();

        $respuesta = $this->postJson(route('gastos.store'), $this->payload(['medio_pago' => 'TARJETA_CREDITO', 'credito_id' => $tarjeta->id]));

        $respuesta->assertCreated();
        $gasto = Gasto::findOrFail($respuesta->json('data.id'));
        $this->assertSame($tarjeta->id, $gasto->credito_id);
        $this->assertFalse($gasto->genera_credito);
        $this->assertSame(1, Credito::where('usuario_id', $this->usuario->id)->count());
        $this->assertSame('2200000.00', (string) $tarjeta->fresh()->monto);
    }

    public function test_con_tarjeta_existente_y_nueva_opcion_se_puede_elegir_la_nueva_sin_tocar_la_existente(): void
    {
        $tarjeta = $this->tarjetaExistente();

        $respuesta = $this->postJson(route('gastos.store'), $this->payload($this->camposTarjeta()));

        $respuesta->assertCreated();
        $this->assertSame(2, Credito::where('usuario_id', $this->usuario->id)->count());
        $this->assertSame('1000000.00', (string) $tarjeta->fresh()->monto);
    }

    public function test_editar_un_gasto_con_credito_no_duplica_el_credito(): void
    {
        $id = $this->postJson(route('gastos.store'), $this->payload($this->camposTarjeta()))->json('data.id');
        $creditoId = Gasto::findOrFail($id)->credito_id;

        $this->putJson(route('gastos.update', $id), $this->payload($this->camposTarjeta(['descripcion' => 'Editado', 'monto_original' => '900000.00'])))->assertOk();

        $gasto = Gasto::findOrFail($id);
        $this->assertSame(1, Credito::where('usuario_id', $this->usuario->id)->count());
        $this->assertSame($creditoId, $gasto->credito_id);
        $this->assertTrue($gasto->genera_credito);
        $this->assertSame('1200000.00', (string) Credito::findOrFail($creditoId)->monto);
    }

    public function test_eliminar_un_gasto_que_genero_su_credito_no_resta_capital_ni_borra_el_credito(): void
    {
        $id = $this->postJson(route('gastos.store'), $this->payload($this->camposTarjeta()))->json('data.id');
        $creditoId = Gasto::findOrFail($id)->credito_id;

        $this->deleteJson(route('gastos.destroy', $id))->assertOk();

        $credito = Credito::findOrFail($creditoId);
        $this->assertSame('1200000.00', (string) $credito->monto);
        $this->assertSame(12, $credito->cuotasVigentes()->count());
    }

    public function test_cambiar_a_otro_medio_de_pago_suelta_el_credito_sin_modificarlo(): void
    {
        $id = $this->postJson(route('gastos.store'), $this->payload($this->camposTarjeta()))->json('data.id');
        $creditoId = Gasto::findOrFail($id)->credito_id;

        $this->putJson(route('gastos.update', $id), $this->payload(['medio_pago' => 'EFECTIVO']))->assertOk();

        $gasto = Gasto::findOrFail($id);
        $this->assertNull($gasto->credito_id);
        $this->assertFalse($gasto->genera_credito);
        $this->assertSame('1200000.00', (string) Credito::findOrFail($creditoId)->monto);
    }

    public function test_un_gasto_con_tarjeta_existente_puede_pasar_a_compra_nueva_y_devuelve_el_consumo(): void
    {
        $tarjeta = $this->tarjetaExistente();
        $id = $this->postJson(route('gastos.store'), $this->payload(['medio_pago' => 'TARJETA_CREDITO', 'credito_id' => $tarjeta->id]))->json('data.id');

        $this->putJson(route('gastos.update', $id), $this->payload($this->camposTarjeta()))->assertOk();

        $this->assertSame('1000000.00', (string) $tarjeta->fresh()->monto);
        $gasto = Gasto::findOrFail($id);
        $this->assertTrue($gasto->genera_credito);
        $this->assertNotSame($tarjeta->id, $gasto->credito_id);
    }

    public function test_eliminar_un_gasto_de_tarjeta_existente_resta_su_consumo(): void
    {
        $tarjeta = $this->tarjetaExistente();
        $id = $this->postJson(route('gastos.store'), $this->payload(['medio_pago' => 'TARJETA_CREDITO', 'credito_id' => $tarjeta->id]))->json('data.id');

        $this->deleteJson(route('gastos.destroy', $id))->assertOk();

        $this->assertSame('1000000.00', (string) $tarjeta->fresh()->monto);
    }

    public function test_detalle_del_gasto_incluye_el_credito_vinculado(): void
    {
        $id = $this->postJson(route('gastos.store'), $this->payload($this->camposTarjeta()))->json('data.id');
        $creditoId = Gasto::findOrFail($id)->credito_id;

        $this->getJson(route('gastos.edit', $id))->assertOk()->assertJsonPath('data.credito.id', $creditoId);
        $this->get(route('gastos.edit', $id))->assertOk()->assertSee(route('creditos.show', $creditoId), false);
    }

    public function test_formulario_de_gasto_ofrece_la_opcion_nueva_compra_incluso_sin_tarjetas(): void
    {
        // Sin tarjetas, "Registrar tarjeta nueva" queda elegida para que el formulario aparezca.
        $this->get(route('gastos.create'))->assertOk()
            ->assertSee('<option value="NUEVA" selected', false)
            ->assertSee('name="cupo_tarjeta"', false)
            ->assertDontSee('Sin tarjeta vinculada');
    }

    public function test_formulario_de_gasto_trae_los_campos_de_tarjeta_ocultos(): void
    {
        $this->get(route('gastos.create'))->assertOk()
            ->assertSee('data-credit-card-fields', false)
            ->assertSee('name="banco_tarjeta"', false)
            ->assertSee('name="fecha_corte"', false);
    }

    public function test_gasto_usd_expone_y_muestra_la_trm_usada(): void
    {
        $respuesta = $this->postJson(route('gastos.store'), $this->payload([
            'moneda' => 'USD', 'monto_original' => '100.00', 'tasa_cambio_usada' => '4123.4500',
        ]));
        $respuesta->assertCreated();
        $id = $respuesta->json('data.id');

        $this->getJson(route('gastos.edit', $id))->assertOk()->assertJsonPath('data.tasa_cambio_usada', '4123.4500');
        $this->getJson(route('gastos.index'))->assertOk()->assertJsonPath('data.gastos.0.tasa_cambio_usada', '4123.4500');
        $this->get(route('gastos.index'))->assertOk()->assertSee('TRM usada');
    }

    public function test_ingreso_usd_expone_y_muestra_la_trm_usada(): void
    {
        $tipo = DB::table('categorias')->where('tipo', 'INGRESO')->value('id');
        $fuente = (string) Str::uuid();
        DB::table('fuentes_ingreso')->insert([
            'id' => $fuente, 'usuario_id' => $this->usuario->id, 'tipo_fuente_id' => $tipo,
            'nombre' => 'Fuente USD', 'clasificacion_tributaria' => 'RENTAS_TRABAJO',
        ]);
        DB::table('ingresos')->insert([
            'id' => (string) Str::uuid(), 'usuario_id' => $this->usuario->id, 'fuente_id' => $fuente,
            'fecha_recepcion' => CarbonImmutable::today()->toDateString(), 'clasificacion_tributaria' => 'RENTAS_TRABAJO',
            'moneda' => 'USD', 'monto_original' => '100', 'tasa_cambio_usada' => '4100.5000',
            'monto_bruto_cop' => '410050', 'retencion_fuente_cop' => '0', 'aportes_descontados_cop' => '0',
        ]);

        $this->getJson(route('ingresos.index'))->assertOk()->assertJsonPath('data.ingresos.0.tasa_cambio_usada', '4100.5000');
        $this->get(route('ingresos.index'))->assertOk()->assertSee('TRM usada');
    }
}
