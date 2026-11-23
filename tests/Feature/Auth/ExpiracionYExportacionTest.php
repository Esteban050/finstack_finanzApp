<?php

namespace Tests\Feature\Auth;

use App\Models\AceptacionPolitica;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * RN-006.1: la sesión expira a los 15 minutos de inactividad o a las 8 horas.
 * RN-011.2: máximo una exportación de datos cada 24 horas.
 */
class ExpiracionYExportacionTest extends TestCase
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
            'apellidos' => 'Sesion',
            'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(10000000, 99999999),
            'correo' => 'sesion.' . Str::lower(Str::random(8)) . '@scfp.test',
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

    private function iniciarSesion(): void
    {
        $this->post(route('login'), ['correo' => $this->usuario->correo, 'password' => $this->clave])
            ->assertRedirect();
        $this->assertAuthenticated();
    }

    private function enMinuto(float $minutos): void
    {
        Carbon::setTestNow($this->inicio->copy()->addSeconds((int) round($minutos * 60)));
    }

    public function test_sigue_activa_a_los_14_59_y_expira_a_los_15_minutos_sin_actividad(): void
    {
        $this->iniciarSesion();

        $this->enMinuto(14 + 59 / 60);
        $this->get(route('profile.show'))->assertOk();

        // Contando desde la última actividad (14:59), 15 minutos después expira.
        $this->enMinuto(14 + 59 / 60 + 15);
        $this->get(route('profile.show'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['correo' => 'Tu sesión expiró por inactividad.']);
        $this->assertGuest();
    }

    public function test_expira_a_las_8_horas_aunque_haya_actividad(): void
    {
        $this->iniciarSesion();

        // Actividad cada 10 minutos hasta 7 h 59 min.
        for ($m = 10; $m < 8 * 60; $m += 10) {
            $this->enMinuto($m);
            $this->get(route('profile.show'))->assertOk();
        }
        $this->enMinuto(8 * 60 - 1);
        $this->get(route('profile.show'))->assertOk();

        $this->enMinuto(8 * 60);
        $this->get(route('profile.show'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['correo' => 'Tu sesión ha expirado por límite de tiempo máximo (8 horas).']);
    }

    public function test_cookie_de_sesion_httponly_strict_y_secure_segun_configuracion(): void
    {
        foreach ([false, true] as $secure) {
            config(['session.secure' => $secure]);
            $this->app['auth']->forgetGuards();
            $respuesta = $this->post(route('login'), ['correo' => $this->usuario->correo, 'password' => $this->clave]);

            $cookie = collect($respuesta->headers->getCookies())->first(fn ($c) => $c->getName() === 'scfp_session_token');
            $this->assertNotNull($cookie, 'Debe crearse la cookie scfp_session_token');
            $this->assertTrue($cookie->isHttpOnly());
            $this->assertSame('strict', strtolower((string) $cookie->getSameSite()));
            $this->assertSame($secure, $cookie->isSecure());

            $this->post(route('logout'));
        }
    }

    public function test_exportacion_bloqueada_antes_de_24_horas_y_permitida_despues(): void
    {
        $this->iniciarSesion();
        $this->usuario->update(['ultima_exportacion_en' => $this->inicio->copy()->subHours(24)->addSecond()]);
        $this->app['auth']->forgetGuards(); // que la siguiente petición lea el usuario actualizado

        $this->from(route('profile.show'))->get(route('profile.export'))
            ->assertRedirect(route('profile.show'))
            ->assertSessionHasErrors(['exportacion_error' => 'Solo se permite una exportación cada 24 horas. Podrás solicitarla nuevamente el 22/11/2026 a las 08:00.']);

        $this->usuario->update(['ultima_exportacion_en' => $this->inicio->copy()->subHours(24)]);
        $this->app['auth']->forgetGuards();
        $respuesta = $this->from(route('profile.show'))->get(route('profile.export'));
        $respuesta->assertSessionHasNoErrors();
        $this->assertNotSame(route('profile.show'), $respuesta->headers->get('Location'));
    }
}
