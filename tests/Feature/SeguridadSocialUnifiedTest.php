<?php

namespace Tests\Feature;

use App\Models\Gasto;
use App\Models\PagoSeguridadSocial;
use App\Models\ParametroNormativo;
use App\Models\PerfilTributario;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SeguridadSocialUnifiedTest extends TestCase
{
    use DatabaseTransactions;

    protected Usuario $usuario;
    protected ParametroNormativo $parametro;
    protected PerfilTributario $perfil;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql']);
        config(['database.connections.pgsql.database' => 'bd_finanzApp']);

        $this->usuario = Usuario::where('correo', 'estebansalazar1407@metabiblioteca.com')->first()
            ?? Usuario::where('correo', 'admin@scfp.com')->first()
            ?? Usuario::first();

        if (! $this->usuario) {
            $this->usuario = Usuario::create([
                'nombres' => 'Usuario',
                'apellidos' => 'SeguridadSocial',
                'correo' => 'test.ss@finanzapp.local',
                'password_hash' => bcrypt('Secret123!'),
                'tipo_documento' => 'CC',
                'numero_documento' => '1020304050',
                'rol' => 'USUARIO',
                'estado' => 'ACTIVA',
            ]);
        }

        // Asegurar perfil con ocupación para pasar el middleware perfil.completo
        $this->perfil = PerfilTributario::where('usuario_id', $this->usuario->id)->orderByDesc('vigente_desde')->first()
            ?? PerfilTributario::create([
                'usuario_id' => $this->usuario->id,
                'vigente_desde' => '2026-01-01',
                'ocupaciones' => ['Contratista por prestación de servicios', 'Rentista de capital'],
                'clase_riesgo_arl' => 1,
            ]);

        if (empty($this->perfil->ocupaciones)) {
            $this->perfil->update(['ocupaciones' => ['Contratista por prestación de servicios', 'Rentista de capital']]);
        }

        $this->parametro = ParametroNormativo::where('anio_vigencia', 2026)->where('es_activa', true)->first()
            ?? ParametroNormativo::where('es_activa', true)->orderByDesc('anio_vigencia')->first();
    }

    public function test_vista_principal_seguridad_social_responde_correctamente(): void
    {
        $response = $this->actingAs($this->usuario)->get(route('seguridad-social.index'));

        $response->assertStatus(200);
        $response->assertViewIs('seguridad-social.index');
        $response->assertViewHasAll([
            'usuario',
            'perfil',
            'parametro',
            'periodoActual',
            'pagosRecientes',
        ]);
        $response->assertSee('Calculadora unificada de aportes');
    }

    public function test_calcular_unificado_asalariado(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('seguridad-social.calcular'), [
                'tipo_cotizante' => 'ASALARIADO',
                'salario' => '5000000',
                'periodo' => '2026-05',
                'retencion_fuente' => '100000',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'mensaje' => 'Aportes de seguridad social calculados con éxito.',
            'data' => [
                'tipo_cotizante' => 'ASALARIADO',
                'salario' => '5000000.00',
                'ibc' => '5000000.00',
                'porcentaje_salud' => '4.00',
                'salud' => '200000.00',
                'porcentaje_pension' => '4.00',
                'pension' => '200000.00',
                'total_aportes' => '400000.00',
                'retencion_fuente' => '100000.00',
                'salario_neto_estimado' => '4500000.00',
            ],
        ]);
    }

    public function test_calcular_unificado_independiente(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('seguridad-social.calcular'), [
                'tipo_cotizante' => 'INDEPENDIENTE',
                'monto_contrato' => '10000000',
                'periodo' => '2026-05',
                'clase_riesgo_arl' => 0,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'mensaje' => 'Aportes de seguridad social calculados con éxito.',
            'data' => [
                'tipo_cotizante' => 'INDEPENDIENTE',
                'monto_contrato' => '10000000.00',
                'ibc' => '4000000.00',
                'porcentaje_salud' => '12.50',
                'salud' => '500000.00',
                'porcentaje_pension' => '16.00',
                'pension' => '640000.00',
                'fsp' => '0.00',
                'total' => '1140000.00',
            ],
        ]);
    }

    public function test_calcular_unificado_rentista_capital_aplica_presuncion_28_08(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('seguridad-social.calcular'), [
                'tipo_cotizante' => 'RENTISTA_CAPITAL',
                'monto_contrato' => '10000000',
                'periodo' => '2026-05',
                'clase_riesgo_arl' => 0,
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertSame('RENTISTA_CAPITAL', $data['tipo_cotizante']);
        $this->assertSame('10000000.00', $data['monto_contrato']);
        $this->assertSame('28.08', $data['porcentaje_presuncion_costos']);
        $this->assertSame('2808000.00', $data['presuncion_costos']);
        $this->assertSame('7192000.00', $data['ingreso_neto']);
        $this->assertSame('2876800.00', $data['ibc']);
        $this->assertSame('12.50', $data['porcentaje_salud']);
        $this->assertSame('359600.00', $data['salud']);
        $this->assertSame('16.00', $data['porcentaje_pension']);
        $this->assertSame('460288.00', $data['pension']);
        $this->assertSame('0.00', $data['fsp']);
        $this->assertSame('819888.00', $data['total']);
    }

    public function test_pagar_seguridad_social_crea_pago_y_genera_gasto_en_gastos(): void
    {
        $periodo = '2025-01'; // Periodo pasado único para evitar colisiones
        PagoSeguridadSocial::where('usuario_id', $this->usuario->id)->where('periodo', $periodo)->forceDelete();

        $response = $this->actingAs($this->usuario)
            ->post(route('seguridad-social.pagos.store'), [
                'redirect_to' => 'seguridad-social.index',
                'periodo' => $periodo,
                'fecha_pago' => '2025-02-05',
                'salud' => '359600.00',
                'pension' => '460288.00',
                'fsp' => '0.00',
                'arl' => '0.00',
                'intereses_mora' => '0.00',
                'medio_pago' => 'TRANSFERENCIA',
                'numero_planilla' => '8899221100',
            ]);

        $response->assertRedirect(route('seguridad-social.index'));
        $response->assertSessionHas('status');
        $response->assertSessionHas('gasto_creado_id');

        $pago = PagoSeguridadSocial::where('usuario_id', $this->usuario->id)
            ->where('periodo', $periodo)
            ->first();

        $this->assertNotNull($pago, 'El registro PagoSeguridadSocial no fue creado.');
        $this->assertSame('819888.00', (string) $pago->total);
        $this->assertNotNull($pago->gasto_id, 'El pago no tiene asignado gasto_id.');

        $gasto = Gasto::find($pago->gasto_id);
        $this->assertNotNull($gasto, 'El gasto generado automáticamente no existe en la tabla gastos.');
        $this->assertSame('819888.00', (string) $gasto->monto_cop);
        $this->assertSame('TRANSFERENCIA', $gasto->medio_pago);
        $this->assertStringContainsString('Pago PILA del periodo '.$periodo, $gasto->descripcion);
    }
}
