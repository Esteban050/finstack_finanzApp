<?php

namespace Database\Seeders\Test;

use App\Models\AceptacionPolitica;
use App\Models\Ingreso;
use App\Models\Inversion;
use App\Models\MovimientoInversion;
use App\Models\SimulacionInversion;
use App\Models\Usuario;
use App\Models\ValoracionInversion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * MOD-06 — Cuentas y datos de demostración para ejercitar las inversiones
 * desde Postman (SCFP-MOD-06).
 *
 * No se registra en DatabaseSeeder a propósito: crea cuentas con contraseña
 * conocida y solo debe ejecutarse de forma explícita en entornos de desarrollo.
 *
 *   php artisan db:seed --class="Database\Seeders\Test\Mod06DemoSeeder"
 *
 * Crea dos cuentas ACTIVA, sin MFA y con la política vigente aceptada:
 * - mod06@scfp.test, sin inversiones: la colección registra las suyas;
 * - mod06.otro@scfp.test, con una inversión de UUID fijo para RNG-01.
 *
 * Es idempotente: cada ejecución deja las dos cuentas sin inversiones (salvo
 * la de RNG-01), movimientos, valoraciones, simulaciones ni los ingresos de sus
 * rendimientos. La fuente "Rendimientos de inversiones" se conserva: se
 * reutiliza en la corrida siguiente (RN-020.3).
 */
class Mod06DemoSeeder extends Seeder
{
    public const CORREO = 'mod06@scfp.test';
    public const CORREO_OTRO = 'mod06.otro@scfp.test';
    public const PASSWORD = 'Scfp#Demo2026';

    /** UUID fijo, para que la colección lo conozca de antemano. */
    public const INVERSION_AJENA_ID = '90000000-0000-0000-0006-000000000001';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Mod06DemoSeeder no se ejecuta en producción.');

            return;
        }

        DB::transaction(function () {
            $usuario = $this->crearCuenta(self::CORREO, 'Demo', 'Inversionista', '1000060061');
            $otro = $this->crearCuenta(self::CORREO_OTRO, 'Demo', 'Otro inversionista', '1000060062');

            $this->limpiar($usuario);
            $this->limpiar($otro);

            // RNG-01: inversión de otra cuenta, para comprobar el 404.
            $this->inversionAjena($otro);
        });

        $this->command->info('MOD-06: cuentas de demostración listas.');
        $this->command->line('  Usuario:     '.self::CORREO);
        $this->command->line('  Otra cuenta: '.self::CORREO_OTRO.' (inversión '.self::INVERSION_AJENA_ID.')');
        $this->command->line('  Contraseña:  '.self::PASSWORD);
    }

    /**
     * RN-003.1 / RN-009.2: cuenta ACTIVA con la política vigente aceptada.
     */
    private function crearCuenta(string $correo, string $nombres, string $apellidos, string $documento): Usuario
    {
        $usuario = Usuario::firstOrNew(['correo' => $correo]);

        $usuario->fill([
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'tipo_documento' => 'CC',
            'numero_documento' => $documento,
            'password_hash' => Hash::make(self::PASSWORD),
            'rol' => 'USUARIO',
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

        return $usuario;
    }

    /**
     * Deja la cuenta demo sin datos de MOD-06. Los ingresos de los
     * rendimientos (RN-038.4) se borran con ellos: son datos de prueba de esta
     * cuenta. Las inversiones arrastran sus movimientos y valoraciones (FK en
     * cascada).
     */
    private function limpiar(Usuario $usuario): void
    {
        $ingresos = MovimientoInversion::withoutGlobalScopes()
            ->where('usuario_id', $usuario->id)
            ->whereNotNull('ingreso_id')
            ->pluck('ingreso_id')
            ->unique();

        Inversion::withoutGlobalScopes()->where('usuario_id', $usuario->id)->forceDelete();
        Ingreso::withoutGlobalScopes()->whereIn('id', $ingresos)->forceDelete();
        SimulacionInversion::withoutGlobalScopes()->where('usuario_id', $usuario->id)->delete();
    }

    /** RF-037: fondo de $ 1.000.000 con su aporte y su valoración inicial (RN-037.4). */
    private function inversionAjena(Usuario $otro): void
    {
        $inversion = new Inversion;
        $inversion->forceFill([
            'id' => self::INVERSION_AJENA_ID,
            'usuario_id' => $otro->id,
            'nombre' => 'Fondo de otra cuenta',
            'tipo' => 'FONDO_INVERSION_COLECTIVA',
            'moneda' => 'COP',
            'fecha_apertura' => '2025-01-01',
            'monto_inicial' => '1000000.00',
            'nivel_riesgo' => 'MEDIO',
            'estado' => 'ACTIVA',
        ])->save();

        MovimientoInversion::withoutGlobalScopes()->create([
            'usuario_id' => $otro->id,
            'inversion_id' => $inversion->id,
            'tipo' => 'APORTE',
            'fecha' => '2025-01-01',
            'monto' => '1000000.00',
            'tasa_cambio_usada' => '1.0000',
            'monto_cop' => '1000000.00',
            'nota' => 'Monto inicial (RN-037.4)',
            'es_inicial' => true,
        ]);

        ValoracionInversion::withoutGlobalScopes()->create([
            'usuario_id' => $otro->id,
            'inversion_id' => $inversion->id,
            'fecha' => '2025-01-01',
            'valor' => '1000000.00',
            'origen' => 'INICIAL',
        ]);
    }
}
