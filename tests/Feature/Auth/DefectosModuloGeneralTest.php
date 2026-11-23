<?php

namespace Tests\Feature\Auth;

use App\Models\AceptacionPolitica;
use App\Models\PerfilTributario;
use App\Models\SesionUsuario;
use App\Models\TokenSeguridad;
use App\Models\Usuario;
use App\Services\SecurityTokenService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Correcciones de los casos CP-GEN-036, 038, 045, 047 y 048 del plan de pruebas.
 */
class DefectosModuloGeneralTest extends TestCase
{
    private Usuario $usuario;
    private Carbon $inicio;
    private string $clave = 'ClaveSegura#2026';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => 'bd_finanzApp']);
        DB::purge('pgsql');
        DB::connection('pgsql')->beginTransaction();

        $this->inicio = Carbon::parse('2026-11-22 08:00:00', 'America/Bogota');
        Carbon::setTestNow($this->inicio);

        $this->usuario = Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => 'Defectos',
            'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(10000000, 99999999),
            'correo' => 'defectos.' . Str::lower(Str::random(8)) . '@scfp.test',
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::connection('pgsql')->rollBack();
        parent::tearDown();
    }

    private function fallarLogin(): void
    {
        $this->post(route('login'), ['correo' => $this->usuario->correo, 'password' => 'Incorrecta#2026']);
    }

    private function iniciarSesion(): void
    {
        $this->post(route('login'), ['correo' => $this->usuario->correo, 'password' => $this->clave])->assertRedirect();
        $this->assertAuthenticated();
    }

    /** CP-GEN-036: 5 fallos repartidos en 16 minutos no bloquean la cuenta. */
    public function test_fallos_fuera_de_la_ventana_de_15_minutos_no_bloquean(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->fallarLogin();
        }
        $this->assertSame(4, $this->usuario->fresh()->intentos_fallidos);

        Carbon::setTestNow($this->inicio->copy()->addMinutes(16));
        $this->fallarLogin();

        $usuario = $this->usuario->fresh();
        $this->assertSame('ACTIVA', $usuario->estado);
        $this->assertSame(1, $usuario->intentos_fallidos);
    }

    /** RN-004.1: 5 fallos dentro de 15 minutos sí bloquean. */
    public function test_cinco_fallos_dentro_de_15_minutos_bloquean(): void
    {
        for ($i = 0; $i < 4; $i++) {
            Carbon::setTestNow($this->inicio->copy()->addMinutes($i * 3));
            $this->fallarLogin();
        }
        Carbon::setTestNow($this->inicio->copy()->addMinutes(14)->addSeconds(59));
        $this->fallarLogin();

        $this->assertSame('BLOQUEADA', $this->usuario->fresh()->estado);
    }

    /** CP-GEN-038: una cuenta bloqueada recibe el enlace y al restablecer queda desbloqueada. */
    public function test_cuenta_bloqueada_puede_restablecer_la_contrasena_y_se_desbloquea(): void
    {
        $this->usuario->update(['estado' => 'BLOQUEADA', 'intentos_fallidos' => 5, 'bloqueado_hasta' => now()->addMinutes(15)]);

        $this->post(route('password.email'), ['correo' => $this->usuario->correo])
            ->assertSessionHas('status', 'Si el correo está registrado, recibirás un enlace');
        $this->assertTrue(TokenSeguridad::where('usuario_id', $this->usuario->id)->where('tipo', 'RECUPERACION_PASS')->exists());

        $token = SecurityTokenService::generarTokenRecuperacion($this->usuario->fresh());
        $nueva = 'OtraClave#2027Segura';
        $this->post(route('password.update.custom'), [
            'token' => $token,
            'password' => $nueva,
            'password_confirmation' => $nueva,
        ])->assertSessionHasNoErrors();

        $usuario = $this->usuario->fresh();
        $this->assertSame('ACTIVA', $usuario->estado);
        $this->assertSame(0, $usuario->intentos_fallidos);
        $this->assertNull($usuario->bloqueado_hasta);
    }

    /** CP-GEN-045: cambiar la contraseña anula los dispositivos recordados. */
    public function test_cambio_de_contrasena_anula_dispositivos_recordados(): void
    {
        $this->iniciarSesion();
        SesionUsuario::where('usuario_id', $this->usuario->id)->update(['recordar_dispositivo_until' => now()->addDays(30)]);

        $nueva = 'OtraClave#2027Segura';
        $this->put(route('password.custom.change'), [
            'current_password' => $this->clave,
            'password' => $nueva,
            'password_confirmation' => $nueva,
        ])->assertSessionHas('status', 'Contraseña actualizada');

        $this->assertFalse(
            SesionUsuario::where('usuario_id', $this->usuario->id)->whereNotNull('recordar_dispositivo_until')->exists()
        );
    }

    /** CP-GEN-047: revocar la autorización marca la aceptación y pide la supresión. */
    public function test_revocar_autorizacion_solicita_supresion(): void
    {
        $this->iniciarSesion();

        $this->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Revocar la autorización de tratamiento de datos')
            ->assertSee('revocarla implica solicitar la supresión de tu cuenta');

        $this->post(route('profile.delete.account'), [
            'revocar_autorizacion' => '1',
            'password' => $this->clave,
            'confirmacion_palabra' => 'ELIMINAR',
        ])->assertRedirect(route('login'));

        $this->assertSame('PENDIENTE_SUPRESION', $this->usuario->fresh()->estado);
        $this->assertFalse(AceptacionPolitica::where('usuario_id', $this->usuario->id)->whereNull('revocada_en')->exists());
        $this->assertDatabaseHas('bitacora_auditoria', ['usuario_id' => $this->usuario->id, 'accion' => 'REVOCACION_AUTORIZACION_DATOS']);
    }

    /** CP-GEN-048: sin perfil de ocupación, renta envía a completar el perfil. */
    public function test_renta_sin_perfil_envia_a_completar_el_perfil(): void
    {
        $this->iniciarSesion();

        $this->get(route('renta.index'))
            ->assertRedirect(route('profile.show'))
            ->assertSessionHasErrors(['perfil' => 'Completa tu perfil financiero para usar este módulo']);

        PerfilTributario::create([
            'usuario_id' => $this->usuario->id,
            'fecha_nacimiento' => '1990-01-01',
            'ciudad_residencia' => 'Medellín',
            'vigente_desde' => '2026-01-01',
            'ocupaciones' => ['Contratista por prestación de servicios'],
        ]);
        $this->app['auth']->forgetGuards();

        $respuesta = $this->get(route('renta.index'));
        $this->assertNotSame(route('profile.show'), $respuesta->headers->get('Location'));
    }
}
