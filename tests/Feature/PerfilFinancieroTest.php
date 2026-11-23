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
 * Correcciones del plan de pruebas del perfil financiero (CP-PER-001 a 013).
 */
class PerfilFinancieroTest extends TestCase
{
    private Usuario $usuario;
    private string $clave = 'ClaveSegura#2026';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => 'bd_finanzApp']);
        DB::purge('pgsql');
        DB::connection('pgsql')->beginTransaction();

        Carbon::setTestNow(Carbon::parse('2026-11-22 09:00:00', 'America/Bogota'));

        $this->usuario = Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => 'Perfil',
            'tipo_documento' => 'CC',
            'numero_documento' => '10203040' . random_int(10, 99),
            'correo' => 'perfil.' . Str::lower(Str::random(8)) . '@scfp.test',
            'password_hash' => bcrypt($this->clave),
            'rol' => 'USUARIO',
            'estado' => 'ACTIVA',
            'mfa_activo' => false,
        ]);
        AceptacionPolitica::create([
            'usuario_id' => $this->usuario->id,
            'version_politica' => config('app.version_politica_datos', 'v1.0'),
            'fecha_aceptacion' => now(),
            'ip_origen' => '127.0.0.1',
            'medio' => 'WEB_REGISTRO',
        ]);
        PerfilTributario::create([
            'usuario_id' => $this->usuario->id,
            'fecha_nacimiento' => '1990-05-17',
            'ciudad_residencia' => 'Medellín',
            'ultimos_digitos_nit' => '45',
            'vigente_desde' => '2026-01-01',
            'ocupaciones' => ['Asalariado'],
            'es_salario_integral' => true,
        ]);

        $this->post(route('login'), ['correo' => $this->usuario->correo, 'password' => $this->clave]);
        $this->assertAuthenticated();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::connection('pgsql')->rollBack();
        parent::tearDown();
    }

    private function datosPerfil(array $cambios = []): array
    {
        return array_merge([
            'nombres' => 'Prueba',
            'apellidos' => 'Perfil',
            'fecha_nacimiento' => '1990-05-17',
            'ciudad_residencia' => 'Medellín',
            'telefono' => '3001234567',
            'ultimos_digitos_nit' => '45',
            'personas_a_cargo' => 0,
            'ocupaciones' => ['Asalariado'],
            'es_salario_integral' => 1,
            'responsable_iva' => 0,
        ], $cambios);
    }

    /** CP-PER-005: un salario integral menor a 13 SMMLV se rechaza. */
    public function test_salario_integral_menor_a_13_smmlv_se_rechaza(): void
    {
        $this->postJson(route('seguridad-social.calcular'), [
            'tipo_cotizante' => 'ASALARIADO',
            'periodo' => '2026-11',
            'salario' => '5000000',
        ])->assertStatus(422)->assertJsonValidationErrors(['salario']);

        $this->postJson(route('seguridad-social.calcular'), [
            'tipo_cotizante' => 'ASALARIADO',
            'periodo' => '2026-11',
            'salario' => '22761765',
        ])->assertOk();
    }

    /** Sin salario integral, un salario bajo se calcula normalmente. */
    public function test_salario_ordinario_bajo_se_calcula(): void
    {
        $this->postJson(route('seguridad-social.calcular'), [
            'tipo_cotizante' => 'ASALARIADO',
            'periodo' => '2026-11',
            'salario' => '5000000',
            'es_salario_integral' => 0,
        ])->assertOk();
    }

    /** CP-PER-001: la fecha de nacimiento guardada se precarga en el campo de fecha. */
    public function test_el_perfil_precarga_fecha_de_nacimiento_y_nit(): void
    {
        $this->get(route('profile.show'))
            ->assertOk()
            ->assertSee('name="fecha_nacimiento"', false)
            ->assertSee('value="1990-05-17"', false)
            ->assertSee('id="ultimos_digitos_nit"', false)
            ->assertSee('name="vigente_desde"', false);
    }

    /** CP-PER-006 / 009: mensajes que nombran la regla. */
    public function test_mensajes_de_validacion_nombran_la_regla(): void
    {
        $this->from(route('profile.show'))
            ->patch(route('profile.update'), $this->datosPerfil(['telefono' => '300123456', 'clase_riesgo_arl' => 6]))
            ->assertSessionHasErrors([
                'telefono' => 'El teléfono debe tener exactamente 10 dígitos.',
                'clase_riesgo_arl' => 'La clase de riesgo ARL debe estar entre I y V.',
            ]);
    }

    /** CP-PER-007: el usuario puede indicar los dígitos de su NIT. */
    public function test_guarda_los_digitos_del_nit_indicados(): void
    {
        $this->patch(route('profile.update'), $this->datosPerfil(['ultimos_digitos_nit' => '07']))
            ->assertSessionHas('status', 'Perfil actualizado');
        $this->assertSame('07', $this->usuario->perfilTributarioActivo()->first()->ultimos_digitos_nit);
    }

    /** CP-PER-008 / CA-008.2: vigencia indicada, ni futura ni anterior a la versión vigente. */
    public function test_vigencia_indicada_crea_version_y_valida_limites(): void
    {
        $this->from(route('profile.show'))
            ->patch(route('profile.update'), $this->datosPerfil(['vigente_desde' => '2026-11-23']))
            ->assertSessionHasErrors(['vigente_desde' => 'La fecha de vigencia no puede ser futura.']);

        $this->from(route('profile.show'))
            ->patch(route('profile.update'), $this->datosPerfil(['vigente_desde' => '2025-12-31']))
            ->assertSessionHasErrors('vigente_desde');

        $this->patch(route('profile.update'), $this->datosPerfil([
            'ocupaciones' => ['Asalariado', 'Pensionado'],
            'vigente_desde' => '2026-10-15',
        ]))->assertSessionHas('status', 'Perfil actualizado');

        $versiones = PerfilTributario::where('usuario_id', $this->usuario->id)->orderBy('vigente_desde')->get();
        $this->assertCount(2, $versiones);
        $this->assertSame('2026-10-14', $versiones[0]->vigente_hasta->toDateString());
        $this->assertSame('2026-10-15', $versiones[1]->vigente_desde->toDateString());
    }

    /** CP-PER-011: el error de ELIMINAR aparece junto al campo. */
    public function test_error_de_eliminar_junto_al_campo(): void
    {
        $html = $this->from(route('profile.show'))->followingRedirects()->post(route('profile.delete.account'), [
            'password' => $this->clave,
            'confirmacion_palabra' => 'eliminar',
        ])->assertOk()->getContent();

        // Aparece en el resumen de arriba y también debajo del campo (después del input).
        $campo = strpos($html, 'id="confirmacion_palabra"');
        $this->assertNotFalse($campo);
        $this->assertNotFalse(strpos($html, 'Debes escribir exactamente la palabra ELIMINAR para confirmar.', $campo));
        $this->assertSame('ACTIVA', $this->usuario->fresh()->estado);
    }

    /** CP-PER-012 / RN-010.3: la bitácora de la supresión no guarda el id del titular. */
    public function test_supresion_definitiva_no_deja_el_id_del_titular(): void
    {
        $this->usuario->update(['estado' => 'PENDIENTE_SUPRESION', 'fecha_solicitud_supresion' => now()->subDays(16)]);
        $id = $this->usuario->id;

        $this->artisan('scfp:purgar-supresiones')->assertSuccessful();

        $this->assertNull(Usuario::find($id));
        $registro = DB::table('bitacora_auditoria')->where('accion', 'SUPRESION_DEFINITIVA_CUENTA')->orderByDesc('fecha_evento')->first();
        $this->assertNotNull($registro);
        $this->assertNull($registro->usuario_id);
        $this->assertStringNotContainsString($id, (string) $registro->justificacion);
    }
}
