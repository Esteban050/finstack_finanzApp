<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use App\Services\SecurityTokenService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * RN-002.1 y RN-002.2: reenvío del correo de verificación cada 60 s como
 * mínimo, máximo 5 reenvíos en 24 horas, y cada reenvío invalida los
 * enlaces anteriores.
 */
class ReenvioVerificacionTest extends TestCase
{
    private Usuario $usuario;
    private Carbon $inicio;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => 'bd_finanzApp']);
        DB::purge('pgsql');
        DB::connection('pgsql')->beginTransaction();

        $this->inicio = Carbon::parse('2026-11-22 10:00:00', 'America/Bogota');
        Carbon::setTestNow($this->inicio);

        $this->usuario = Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => 'Reenvío',
            'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(10000000, 99999999),
            'correo' => 'reenvio.' . Str::lower(Str::random(8)) . '@scfp.test',
            'password_hash' => bcrypt('ClaveSegura#2026'),
            'rol' => 'USUARIO',
            'estado' => 'PENDIENTE_VERIFICACION',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::connection('pgsql')->rollBack();
        parent::tearDown();
    }

    /** El registro envía el primer correo (no cuenta como reenvío). */
    private function registrar(): string
    {
        return SecurityTokenService::generarTokenVerificacion($this->usuario)['token_plano'];
    }

    private function reenviar()
    {
        return $this->from(route('login'))->post(route('verification.resend.token'), ['correo' => $this->usuario->correo]);
    }

    public function test_antes_de_60_segundos_pide_esperar_y_a_los_60_permite(): void
    {
        $this->registrar();

        Carbon::setTestNow($this->inicio->copy()->addSeconds(59));
        $this->reenviar()->assertSessionHasErrors(['reenvio' => 'Debes esperar 1 segundos antes de solicitar otro correo.']);

        Carbon::setTestNow($this->inicio->copy()->addSeconds(60));
        $this->reenviar()->assertSessionHasNoErrors()->assertSessionHas('status', 'Correo de verificación reenviado exitosamente.');
    }

    public function test_permite_5_reenvios_y_bloquea_el_sexto_con_mensaje(): void
    {
        $this->registrar();

        for ($i = 1; $i <= 5; $i++) {
            Carbon::setTestNow($this->inicio->copy()->addMinutes($i));
            $this->reenviar()->assertSessionHasNoErrors()->assertSessionHas('status', 'Correo de verificación reenviado exitosamente.');
        }

        Carbon::setTestNow($this->inicio->copy()->addMinutes(6));
        $respuesta = $this->reenviar();
        $respuesta->assertSessionHas('reenvio_bloqueado', true);
        $this->assertStringStartsWith(
            'Ya no hay más reenvíos permitidos: alcanzaste el máximo de 5 en 24 horas. Podrás solicitar otro correo el 23/11/2026 a las 10:01.',
            session('errors')->first('reenvio')
        );
        $this->assertSame(6, DB::table('tokens_seguridad')->where('usuario_id', $this->usuario->id)->count());
    }

    public function test_pasadas_24_horas_del_primer_reenvio_vuelve_a_permitir(): void
    {
        $this->registrar();
        for ($i = 1; $i <= 5; $i++) {
            Carbon::setTestNow($this->inicio->copy()->addMinutes($i));
            $this->reenviar()->assertSessionHasNoErrors();
        }

        Carbon::setTestNow($this->inicio->copy()->addMinutes(1)->addHours(24)->subSecond());
        $this->reenviar()->assertSessionHas('reenvio_bloqueado', true);

        Carbon::setTestNow($this->inicio->copy()->addMinutes(1)->addHours(24)->addSecond());
        $this->reenviar()->assertSessionHasNoErrors();
    }

    public function test_el_enlace_anterior_se_rechaza_despues_de_un_reenvio(): void
    {
        $enlaceViejo = $this->registrar();

        Carbon::setTestNow($this->inicio->copy()->addMinutes(2));
        $enlaceNuevo = SecurityTokenService::generarTokenVerificacion($this->usuario)['token_plano'];

        $this->get(route('verification.verify.token', ['token' => $enlaceViejo]))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('verificacion_error');
        $this->assertSame('PENDIENTE_VERIFICACION', $this->usuario->fresh()->estado);

        $this->get(route('verification.verify.token', ['token' => $enlaceNuevo]))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Cuenta verificada');
        $this->assertSame('ACTIVA', $this->usuario->fresh()->estado);
    }
}
