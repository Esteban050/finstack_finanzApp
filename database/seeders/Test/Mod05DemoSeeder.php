<?php

namespace Database\Seeders\Test;

use App\Models\AceptacionPolitica;
use App\Models\CalendarioTributario;
use App\Models\Categoria;
use App\Models\FuenteIngreso;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\LiquidacionSeguridadSocial;
use App\Models\PagoSeguridadSocial;
use App\Models\PerfilTributario;
use App\Models\Usuario;
use App\Services\AlertaSeguridadSocialService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * MOD-05 — Cuentas y datos de demostración para ejercitar la seguridad social
 * desde Postman (SCFP-MOD-05).
 *
 * No se registra en DatabaseSeeder a propósito: crea cuentas con contraseña
 * conocida y solo debe ejecutarse de forma explícita en entornos de desarrollo.
 *
 *   php artisan db:seed --class="Database\Seeders\Test\Mod05DemoSeeder"
 *
 * Crea tres cuentas ACTIVA, sin MFA y con la política vigente aceptada:
 * - un Independiente (dígitos 37, ARL clase I) con un ingreso de honorarios de
 *   $ 8.000.000 en 2025-03, que da la liquidación de 928.800;
 * - un Asalariado con un salario de $ 6.000.000 en 2025-06 (CA-034.1);
 * - una cuenta sin perfil tributario (CA-008.1).
 *
 * Es idempotente: cada ejecución deja las cuentas demo sin pagos ni
 * liquidaciones, para que la colección se pueda volver a correr.
 *
 * RF-036: si la base no tiene calendario de seguridad social para el año en
 * curso, crea uno de ejemplo (día 10, todos los dígitos). Nunca modifica un
 * calendario ya cargado por el Administrador.
 */
class Mod05DemoSeeder extends Seeder
{
    public const CORREO_INDEPENDIENTE = 'mod05@scfp.test';
    public const CORREO_ASALARIADO = 'mod05.asalariado@scfp.test';
    public const CORREO_SIN_PERFIL = 'mod05.sinperfil@scfp.test';
    public const PASSWORD = 'Scfp#Demo2026';

    /** UUID fijos, para que la colección los conozca de antemano. */
    public const FUENTE_HONORARIOS_ID = '90000000-0000-0000-0005-000000000001';
    public const INGRESO_HONORARIOS_ID = '90000000-0000-0000-0005-000000000002';
    public const FUENTE_SALARIO_ID = '90000000-0000-0000-0005-000000000003';
    public const INGRESO_SALARIO_ID = '90000000-0000-0000-0005-000000000004';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Mod05DemoSeeder no se ejecuta en producción.');

            return;
        }

        DB::transaction(function () {
            $independiente = $this->crearCuenta(self::CORREO_INDEPENDIENTE, 'Demo', 'Independiente', '1000050051');
            $asalariado = $this->crearCuenta(self::CORREO_ASALARIADO, 'Demo', 'Asalariado', '1000050052');
            $sinPerfil = $this->crearCuenta(self::CORREO_SIN_PERFIL, 'Demo', 'Sin perfil', '1000050053');

            $this->perfil($independiente, ['Independiente por cuenta propia'], digitos: '37', claseRiesgoArl: 1);
            $this->perfil($asalariado, ['Asalariado']);
            PerfilTributario::where('usuario_id', $sinPerfil->id)->delete();

            $this->limpiarSeguridadSocial($independiente);
            $this->limpiarSeguridadSocial($asalariado);

            // RN-033.1: rentas de trabajo de una fuente que no es "Salario".
            $this->fuente(self::FUENTE_HONORARIOS_ID, $independiente, 'Honorarios demo MOD-05', 'Honorarios/Servicios');
            $this->ingreso(self::INGRESO_HONORARIOS_ID, $independiente, self::FUENTE_HONORARIOS_ID, '2025-03-10', '8000000.00');

            // RN-034.5: salario al que se le aplican los aportes del asalariado.
            $this->fuente(self::FUENTE_SALARIO_ID, $asalariado, 'Salario demo MOD-05', 'Salario');
            $this->ingreso(self::INGRESO_SALARIO_ID, $asalariado, self::FUENTE_SALARIO_ID, '2025-06-30', '6000000.00');
        });

        $calendario = $this->calendarioDeEjemplo((int) now()->year);

        $this->command->info('MOD-05: cuentas de demostración listas.');
        $this->command->line('  Independiente: '.self::CORREO_INDEPENDIENTE);
        $this->command->line('  Asalariado:    '.self::CORREO_ASALARIADO);
        $this->command->line('  Sin perfil:    '.self::CORREO_SIN_PERFIL);
        $this->command->line('  Contraseña:    '.self::PASSWORD);
        $this->command->line($calendario
            ? '  Calendario:    se creó uno de ejemplo para '.now()->year.' (día 10).'
            : '  Calendario:    ya existía para '.now()->year.'; no se modificó.');
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

    /** RF-008: un único perfil, vigente desde 2025 (cubre los periodos de la colección). */
    private function perfil(Usuario $usuario, array $ocupaciones, ?string $digitos = null, ?int $claseRiesgoArl = null): void
    {
        PerfilTributario::where('usuario_id', $usuario->id)->delete();

        PerfilTributario::create([
            'usuario_id' => $usuario->id,
            'fecha_nacimiento' => '1990-05-10',
            'ciudad_residencia' => 'Bogotá',
            'ultimos_digitos_nit' => $digitos,
            'personas_a_cargo' => 0,
            'ocupaciones' => $ocupaciones,
            'es_salario_integral' => false,
            'clase_riesgo_arl' => $claseRiesgoArl,
            'arl_obligatoria' => $claseRiesgoArl !== null,
            'responsable_iva' => false,
            'vigente_desde' => '2025-01-01',
            'vigente_hasta' => null,
        ]);
    }

    /**
     * Deja la cuenta demo sin pagos ni liquidaciones. Los gastos de los pagos
     * se borran con ellos: son datos de prueba de esta cuenta.
     */
    private function limpiarSeguridadSocial(Usuario $usuario): void
    {
        $gastos = PagoSeguridadSocial::withoutGlobalScopes()
            ->where('usuario_id', $usuario->id)
            ->whereNotNull('gasto_id')
            ->pluck('gasto_id');

        PagoSeguridadSocial::withoutGlobalScopes()->where('usuario_id', $usuario->id)->forceDelete();
        Gasto::withoutGlobalScopes()->whereIn('id', $gastos)->forceDelete();
        LiquidacionSeguridadSocial::withoutGlobalScopes()->where('usuario_id', $usuario->id)->delete();
    }

    private function fuente(string $id, Usuario $usuario, string $nombre, string $tipo): void
    {
        $tipoFuente = Categoria::where('tipo', 'INGRESO')
            ->where('es_predeterminada', true)
            ->whereNull('categoria_padre_id')
            ->where('nombre', $tipo)
            ->firstOrFail();

        $fuente = FuenteIngreso::withoutGlobalScopes()->find($id) ?? new FuenteIngreso;
        $fuente->forceFill([
            'id' => $id,
            'usuario_id' => $usuario->id,
            'tipo_fuente_id' => $tipoFuente->id,
            'nombre' => $nombre,
            'clasificacion_tributaria' => 'RENTAS_TRABAJO',
            'activa' => true,
        ])->save();
    }

    /** RF-021: ingreso confirmado, sin retención ni aportes. */
    private function ingreso(string $id, Usuario $usuario, string $fuenteId, string $fecha, string $brutoCop): void
    {
        $ingreso = Ingreso::withoutGlobalScopes()->withTrashed()->find($id) ?? new Ingreso;
        $ingreso->forceFill([
            'id' => $id,
            'usuario_id' => $usuario->id,
            'fuente_id' => $fuenteId,
            'fecha_recepcion' => $fecha,
            'clasificacion_tributaria' => 'RENTAS_TRABAJO',
            'moneda' => 'COP',
            'monto_original' => $brutoCop,
            'tasa_cambio_usada' => '1.0000',
            'monto_bruto_cop' => $brutoCop,
            'retencion_fuente_cop' => '0.00',
            'aportes_descontados_cop' => '0.00',
            'origen' => 'MANUAL',
            'estado' => 'CONFIRMADO',
            'descripcion' => 'Dato de demostración MOD-05',
            'eliminado_en' => null,
        ])->save();
    }

    /**
     * RF-036: calendario de ejemplo solo si el año no tiene ninguno. Devuelve
     * si lo creó.
     */
    private function calendarioDeEjemplo(int $anio): bool
    {
        if (AlertaSeguridadSocialService::hayCalendario($anio)) {
            return false;
        }

        $administrador = Usuario::where('rol', 'ADMIN')->where('estado', 'ACTIVA')->first();

        if ($administrador === null) {
            $this->command->warn('  No hay un Administrador activo para registrar el calendario de ejemplo.');

            return false;
        }

        CalendarioTributario::create([
            'anio_vigencia' => $anio,
            'tipo_obligacion' => AlertaSeguridadSocialService::TIPO_OBLIGACION,
            'digito_desde' => '00',
            'digito_hasta' => '99',
            'fecha_vencimiento' => "{$anio}-01-10",
            'creado_por' => $administrador->id,
        ]);

        return true;
    }
}
