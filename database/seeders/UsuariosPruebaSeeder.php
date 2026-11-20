<?php

namespace Database\Seeders;

use App\Models\AceptacionPolitica;
use App\Models\HistorialContrasena;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuarios de prueba para desarrollo local (no usar en producción).
 * Quedan activos, sin verificación en dos pasos y con la política de datos aceptada,
 * para entrar sin depender del envío de correos.
 *
 * Ejecutar: php artisan db:seed --class=UsuariosPruebaSeeder
 */
class UsuariosPruebaSeeder extends Seeder
{
    public const CONTRASENA = 'Pruebas@Scfp2026';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('UsuariosPruebaSeeder no se ejecuta en producción.');
            return;
        }

        $usuarios = [
            ['correo' => 'derly.admin@scfp.test',   'rol' => 'ADMIN',   'numero_documento' => '1000000101', 'apellidos' => 'Quejada Admin'],
            ['correo' => 'derly.usuario@scfp.test', 'rol' => 'USUARIO', 'numero_documento' => '1000000102', 'apellidos' => 'Quejada'],
        ];

        foreach ($usuarios as $datos) {
            $usuario = Usuario::updateOrCreate(
                ['correo' => $datos['correo']],
                [
                    'nombres' => 'Derly Elena',
                    'apellidos' => $datos['apellidos'],
                    'tipo_documento' => 'CC',
                    'numero_documento' => $datos['numero_documento'],
                    'password_hash' => Hash::make(self::CONTRASENA),
                    'rol' => $datos['rol'],
                    'estado' => 'ACTIVA',
                    'intentos_fallidos' => 0,
                    'bloqueado_hasta' => null,
                    'mfa_activo' => false,
                ]
            );

            AceptacionPolitica::firstOrCreate(
                ['usuario_id' => $usuario->id, 'version_politica' => config('app.version_politica_datos', 'v1.0')],
                ['fecha_aceptacion' => now(), 'ip_origen' => '127.0.0.1', 'medio' => 'WEB_REGISTRO']
            );

            HistorialContrasena::create([
                'usuario_id' => $usuario->id,
                'password_hash' => $usuario->password_hash,
                'creado_en' => now(),
            ]);

            $this->command?->info("{$datos['rol']}: {$datos['correo']}");
        }
    }
}
