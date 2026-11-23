<?php

namespace Tests\Feature;

use App\Models\AceptacionPolitica;
use App\Models\PerfilTributario;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CP-SS-065 · RN-035.1: en el pago PILA son obligatorios periodo, fecha de pago,
 * salud y pensión; cada uno con su error. Pensión admite $ 0 (pensionado).
 */
class PagoSeguridadSocialObligatoriosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => 'bd_finanzApp']);
        DB::purge('pgsql');
        DB::connection('pgsql')->beginTransaction();
        Carbon::setTestNow(Carbon::parse('2026-11-22 09:00:00', 'America/Bogota'));

        $u = Usuario::create([
            'nombres' => 'Prueba', 'apellidos' => 'Pila', 'tipo_documento' => 'CC',
            'numero_documento' => '10203040' . random_int(10, 99),
            'correo' => 'pila.' . Str::lower(Str::random(8)) . '@scfp.test',
            'password_hash' => bcrypt('ClaveSegura#2026'), 'rol' => 'USUARIO', 'estado' => 'ACTIVA', 'mfa_activo' => false,
        ]);
        AceptacionPolitica::create(['usuario_id' => $u->id, 'version_politica' => config('app.version_politica_datos', 'v1.0'),
            'fecha_aceptacion' => now(), 'ip_origen' => '127.0.0.1', 'medio' => 'WEB_REGISTRO']);
        PerfilTributario::create(['usuario_id' => $u->id, 'fecha_nacimiento' => '1990-01-01', 'ciudad_residencia' => 'Medellín',
            'ultimos_digitos_nit' => '07', 'vigente_desde' => '2026-01-01', 'ocupaciones' => ['Contratista por prestación de servicios']]);
        $this->post(route('login'), ['correo' => $u->correo, 'password' => 'ClaveSegura#2026']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::connection('pgsql')->rollBack();
        parent::tearDown();
    }

    private function pagar(array $cambios)
    {
        return $this->postJson(route('seguridad-social.pagos.store'), array_merge([
            'periodo' => '2026-10', 'fecha_pago' => '2026-11-03', 'salud' => '218900', 'pension' => '280200',
            'fsp' => '0', 'arl' => '0', 'intereses_mora' => '0',
        ], $cambios));
    }

    public function test_sin_salud_ni_pension_hay_un_error_en_cada_campo(): void
    {
        $this->pagar(['salud' => '', 'pension' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'salud' => 'Indique el valor pagado de salud.',
                'pension' => 'Indique el valor pagado de pensión.',
            ]);
    }

    public function test_pension_es_obligatoria(): void
    {
        $this->pagar(['pension' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pension' => 'Indique el valor pagado de pensión.']);
    }

    public function test_sin_fecha_de_pago_se_rechaza(): void
    {
        $this->pagar(['fecha_pago' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_pago' => 'Indique la fecha de pago.']);
    }

    public function test_pension_en_cero_se_acepta_para_el_pensionado_y_el_pago_completo_tambien(): void
    {
        $this->pagar(['pension' => '0'])->assertCreated();
        $this->pagar(['periodo' => '2026-09'])->assertCreated();
    }

    public function test_con_ibc_los_aportes_se_calculan_desde_los_porcentajes(): void
    {
        $this->pagar(['salud' => '', 'pension' => '', 'ibc' => '1750905', 'porcentaje_salud' => '12.5', 'porcentaje_pension' => '16'])
            ->assertCreated();
    }
}
