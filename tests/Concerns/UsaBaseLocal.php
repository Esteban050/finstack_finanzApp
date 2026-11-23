<?php

namespace Tests\Concerns;

use App\Models\AceptacionPolitica;
use App\Models\PerfilTributario;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pruebas contra la base local (pgsql bd_finanzApp) dentro de una transacción que
 * se deshace al terminar, con la fecha del sistema fijada y usuarios de prueba.
 */
trait UsaBaseLocal
{
    protected string $claveDePrueba = 'ClaveSegura#2026';

    protected function iniciarBaseLocal(string $hoy = '2026-11-22 09:00:00'): void
    {
        config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => 'bd_finanzApp']);
        DB::purge('pgsql');
        DB::connection('pgsql')->beginTransaction();
        Carbon::setTestNow(Carbon::parse($hoy, 'America/Bogota'));
    }

    protected function cerrarBaseLocal(): void
    {
        Carbon::setTestNow();
        DB::connection('pgsql')->rollBack();
    }

    /** Usuario activo con la política aceptada. $perfil = ocupaciones del perfil tributario, o null sin perfil. */
    protected function crearUsuario(?array $perfil = ['Contratista por prestación de servicios'], array $datos = []): Usuario
    {
        $u = Usuario::create(array_merge([
            'nombres' => 'Prueba', 'apellidos' => 'Automatica', 'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(10000000, 99999999),
            'correo' => 'auto.' . Str::lower(Str::random(10)) . '@scfp.test',
            'password_hash' => bcrypt($this->claveDePrueba), 'rol' => 'USUARIO', 'estado' => 'ACTIVA', 'mfa_activo' => false,
        ], $datos));
        AceptacionPolitica::create(['usuario_id' => $u->id, 'version_politica' => config('app.version_politica_datos', 'v1.0'),
            'fecha_aceptacion' => now(), 'ip_origen' => '127.0.0.1', 'medio' => 'WEB_REGISTRO']);
        if ($perfil !== null) {
            PerfilTributario::create(['usuario_id' => $u->id, 'fecha_nacimiento' => '1990-01-01', 'ciudad_residencia' => 'Medellín',
                'ultimos_digitos_nit' => '07', 'vigente_desde' => '2026-01-01', 'ocupaciones' => $perfil]);
        }

        return $u;
    }

    protected function entrarComo(Usuario $u): void
    {
        $this->app['auth']->forgetGuards();
        $this->post(route('logout'));
        $this->app['auth']->forgetGuards();
        $this->post(route('login'), ['correo' => $u->correo, 'password' => $this->claveDePrueba]);
        $this->assertAuthenticatedAs($u);
    }

    /** Fuente de ingreso propia (honorarios, rentas de trabajo). */
    protected function crearFuente(Usuario $u, bool $activa = true): string
    {
        $id = (string) Str::uuid();
        DB::table('fuentes_ingreso')->insert(['id' => $id, 'usuario_id' => $u->id, 'nombre' => 'Honorarios ' . Str::random(4), 'activa' => $activa,
            'tipo_fuente_id' => DB::table('categorias')->where('tipo', 'INGRESO')->where('nombre', 'ilike', 'honorarios%')->value('id'),
            'clasificacion_tributaria' => 'RENTAS_TRABAJO']);

        return $id;
    }
}
