<?php

namespace Database\Seeders\Test;

use App\Models\AceptacionPolitica;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * RF-017 — Cuentas de demostración para ejercitar la TRM desde Postman.
 *
 * No se registra en DatabaseSeeder a propósito: crea cuentas con contraseña
 * conocida y solo debe ejecutarse de forma explícita en entornos de desarrollo.
 *
 *   php artisan db:seed --class="Database\Seeders\Test\TrmDemoSeeder"
 *
 * Crea un Usuario (consulta y conversión) y un Administrador (RN-017.3), los
 * dos ACTIVA, sin MFA y con la política vigente aceptada. No siembra ninguna
 * TRM: la colección la obtiene de la fuente oficial o la registra a mano.
 * Es idempotente.
 */
class TrmDemoSeeder extends Seeder
{
    public const CORREO = 'trm@scfp.test';
    public const CORREO_ADMIN = 'trm.admin@scfp.test';
    public const PASSWORD = 'Scfp#Demo2026';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('TrmDemoSeeder no se ejecuta en producción.');

            return;
        }

        $this->crearCuenta(self::CORREO, 'USUARIO', 'Demo', 'TRM', '1000170171');
        $this->crearCuenta(self::CORREO_ADMIN, 'ADMIN', 'Admin', 'TRM', '1000170172');

        $this->command->info('RF-017: cuentas de demostración listas.');
        $this->command->line('  Usuario:       '.self::CORREO);
        $this->command->line('  Administrador: '.self::CORREO_ADMIN);
        $this->command->line('  Contraseña:    '.self::PASSWORD);
    }

    /**
     * RN-003.1: Solo inician sesión las cuentas ACTIVA.
     * RN-009.2: La cuenta debe tener aceptada la versión vigente de la política,
     * o el middleware politica.check la desvía antes de llegar a la consulta.
     */
    private function crearCuenta(string $correo, string $rol, string $nombres, string $apellidos, string $documento): void
    {
        $usuario = Usuario::firstOrNew(['correo' => $correo]);

        $usuario->fill([
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'tipo_documento' => 'CC',
            'numero_documento' => $documento,
            'password_hash' => Hash::make(self::PASSWORD),
            'rol' => $rol,
            'estado' => 'ACTIVA',
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            // RF-012 pediría un código por correo en cada inicio de sesión.
            'mfa_activo' => false,
        ])->save();

        $version = config('app.version_politica_datos', 'v1.0');

        $yaAcepto = AceptacionPolitica::where('usuario_id', $usuario->id)
            ->where('version_politica', $version)
            ->whereNull('revocada_en')
            ->exists();

        if (! $yaAcepto) {
            AceptacionPolitica::create([
                'usuario_id' => $usuario->id,
                'version_politica' => $version,
                'fecha_aceptacion' => now(),
                'ip_origen' => '127.0.0.1',
                'medio' => 'SEEDER',
            ]);
        }
    }
}
