<?php

namespace Tests\Feature;

use App\Models\Credito;
use App\Models\EntidadFinanciera;
use App\Models\Gasto;
use App\Models\Usuario;
use App\Services\Reportes\DashboardResumenService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/** Una tarjeta registrada desde Mi perfil se ofrece en el gasto con tarjeta y su cupo disponible baja con el consumo. */
class TarjetaDePerfilEnGastoTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;
    private Credito $tarjeta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal('2026-11-26 10:00:00');
        $this->usuario = $this->crearUsuario();
        $this->entrarComo($this->usuario);
        $banco = EntidadFinanciera::firstOrCreate(['nombre' => 'Banco QA', 'tipo' => 'BANCO']);
        $this->post(route('perfil.tarjetas.store'), ['entidad_financiera_id' => $banco->id, 'cupo' => '2000000', 'dia_corte' => '15', 'dia_pago' => '5'])->assertSessionHasNoErrors();
        $this->tarjeta = Credito::where('tipo_credito', 'TARJETA_CREDITO')->firstOrFail();
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function gasto(string $monto): array
    {
        $sub = DB::table('categorias')->where('tipo', 'GASTO')->where('activa', true)->whereNotNull('categoria_padre_id')->first();

        return [
            'fecha' => '2026-11-20', 'monto_original' => $monto, 'moneda' => 'COP', 'categoria_id' => $sub->categoria_padre_id,
            'subcategoria_id' => $sub->id, 'medio_pago' => 'TARJETA_CREDITO', 'credito_id' => $this->tarjeta->id,
            'naturaleza' => 'VARIABLE', 'necesidad' => 'DISCRECIONAL', 'relevancia_tributaria' => 'NO_DEDUCIBLE',
        ];
    }

    public function test_el_formulario_de_gasto_lista_la_tarjeta_registrada_en_el_perfil(): void
    {
        $this->get(route('gastos.create'))->assertOk()
            ->assertSee('value="'.$this->tarjeta->id.'"', false)->assertSee('Banco QA · Tarjeta Banco QA');
    }

    public function test_un_consumo_con_la_tarjeta_del_perfil_baja_el_cupo_disponible(): void
    {
        $id = $this->postJson(route('gastos.store'), $this->gasto('500000'))->assertCreated()->json('data.id');

        $this->assertSame($this->tarjeta->id, Gasto::find($id)->credito_id);
        $this->assertSame('500000.00', (string) $this->tarjeta->fresh()->monto);

        $tarjetas = app(DashboardResumenService::class)->obtenerCuentas($this->usuario, \Carbon\CarbonImmutable::parse('2026-11-26'))['tarjetas'];
        $this->assertSame('2000000.00', $tarjetas[0]['cupo']);
        $this->assertSame('500000.00', $tarjetas[0]['usado']);
        $this->assertSame('1500000.00', $tarjetas[0]['disponible']);
    }

    public function test_un_consumo_que_supera_el_cupo_se_rechaza(): void
    {
        $this->postJson(route('gastos.store'), $this->gasto('2500000'))->assertStatus(422)->assertJsonValidationErrors('monto_original');
    }
}
