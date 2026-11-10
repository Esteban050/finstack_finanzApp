<?php

namespace Database\Seeders\Test;

use App\Models\AceptacionPolitica;
use App\Models\Credito;
use App\Models\Gasto;
use App\Models\PagoCredito;
use App\Models\Usuario;
use App\Services\CreditoService;
use App\Services\PagoCreditoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * MOD-08 — Cuentas y datos de demostración para ejercitar los créditos
 * desde Postman (SCFP-MOD-08).
 *
 * No se registra en DatabaseSeeder a propósito: crea cuentas con contraseña
 * conocida y solo debe ejecutarse de forma explícita en entornos de desarrollo.
 *
 *   php artisan db:seed --class="Database\Seeders\Test\Mod08DemoSeeder"
 *
 * Crea dos cuentas ACTIVA, sin MFA y con la política vigente aceptada:
 * - mod08@scfp.test, sin créditos: la colección registra los suyos;
 * - mod08.otro@scfp.test, con un crédito de UUID fijo para RNG-01.
 *
 * Es idempotente: cada ejecución deja las dos cuentas sin créditos (ni
 * siquiera los eliminados lógicamente), salvo el de RNG-01.
 */
class Mod08DemoSeeder extends Seeder
{
    public const CORREO = 'mod08@scfp.test';
    public const CORREO_OTRO = 'mod08.otro@scfp.test';
    public const PASSWORD = 'Scfp#Demo2026';

    /** UUID fijo, para que la colección lo conozca de antemano. */
    public const CREDITO_AJENO_ID = '90000000-0000-0000-0008-000000000001';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Mod08DemoSeeder no se ejecuta en producción.');

            return;
        }

        DB::transaction(function () {
            $usuario = $this->crearCuenta(self::CORREO, 'Demo', 'Deudor', '1000080081');
            $otro = $this->crearCuenta(self::CORREO_OTRO, 'Demo', 'Otro deudor', '1000080082');

            $this->limpiar($usuario);
            $this->limpiar($otro);

            // RNG-01: crédito de otra cuenta, para comprobar el 404 y la bitácora.
            $this->creditoAjeno($otro);
        });

        $this->command->info('MOD-08: cuentas de demostración listas.');
        $this->command->line('  Usuario:     '.self::CORREO);
        $this->command->line('  Otra cuenta: '.self::CORREO_OTRO.' (crédito '.self::CREDITO_AJENO_ID.')');
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
     * Deja la cuenta sin créditos. withoutGlobalScopes() quita también el
     * scope de SoftDeletes, así que se borran además los eliminados (RNG-07).
     * Primero los gastos que generaron sus pagos (son datos de prueba de esta
     * cuenta); el crédito arrastra cuotas, pagos e imputaciones (FK en cascada).
     */
    private function limpiar(Usuario $usuario): void
    {
        $gastos = PagoCredito::withoutGlobalScopes()
            ->where('usuario_id', $usuario->id)
            ->get(['gasto_id', 'gasto_intereses_id'])
            ->flatMap(fn (PagoCredito $p) => [$p->gasto_id, $p->gasto_intereses_id])
            ->filter()
            ->all();

        Gasto::withoutGlobalScopes()->whereIn('id', $gastos)->forceDelete();
        Credito::withoutGlobalScopes()->where('usuario_id', $usuario->id)->forceDelete();
    }

    /**
     * RF-048: crédito de vehículo de $ 5.000.000 al 1,2 % mensual a 12 cuotas
     * mensuales, con su EA y su tasa periódica (Créditos RF-05).
     */
    private function creditoAjeno(Usuario $otro): void
    {
        $ea = CreditoService::tasaEa('1.2', 'MENSUAL');

        $credito = new Credito;
        $credito->forceFill([
            'id' => self::CREDITO_AJENO_ID,
            'usuario_id' => $otro->id,
            'tipo_credito' => 'VEHICULO',
            'banco' => 'Banco de Bogotá',
            'proposito' => 'Crédito de otra cuenta',
            'monto' => '5000000.00',
            'tipo_tasa' => 'MENSUAL',
            'tasa_ingresada' => '1.200000',
            'capitalizacion' => null,
            'tasa_ea' => $ea,
            'tasa_periodica' => CreditoService::tasaPeriodica($ea, 'MENSUAL'),
            'plazo_meses' => 12,
            'periodicidad' => 'MENSUAL',
            'numero_cuotas' => 12,
            'fecha_inicio' => '2026-01-15',
            'version_plan_vigente' => 1,
        ])->save();

        // Créditos RF-22: el plan guardado, como al registrarlo por la API.
        PagoCreditoService::crearPlan($credito->fresh(), 1);
    }
}
