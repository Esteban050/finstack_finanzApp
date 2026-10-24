<?php

namespace Database\Seeders\Test;

use App\Models\AceptacionPolitica;
use App\Models\Categoria;
use App\Models\FuenteIngreso;
use App\Models\Ingreso;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * MOD-03 — Datos de demostración para ejercitar y verificar los RF-020 a RF-024.
 *
 * No se registra en DatabaseSeeder a propósito: crea una cuenta con contraseña
 * conocida y solo debe ejecutarse de forma explícita en entornos de desarrollo.
 *
 *   php artisan db:seed --class=Mod03DemoSeeder
 *
 * Es idempotente: se puede volver a ejecutar sin duplicar registros.
 */
class Mod03DemoSeeder extends Seeder
{
    public const CORREO = 'mod03@scfp.test';
    public const PASSWORD = 'Scfp#Demo2026';

    /**
     * RNG-01: Identificadores fijos del segundo usuario y de su ingreso, para que
     * las pruebas de aislamiento puedan apuntar a un recurso ajeno conocido.
     */
    public const AJENO_CORREO = 'mod03.otro@scfp.test';
    public const AJENO_USUARIO_ID = '90000000-0000-0000-0003-000000000001';
    public const AJENO_FUENTE_ID = '90000000-0000-0000-0003-000000000002';
    public const AJENO_INGRESO_ID = '90000000-0000-0000-0003-000000000003';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Mod03DemoSeeder no se ejecuta en producción.');

            return;
        }

        $usuario = $this->crearUsuario();
        $fuentes = $this->crearFuentes($usuario);
        $this->crearIngresos($usuario, $fuentes);
        $this->crearUsuarioAjeno();

        $this->command->info('MOD-03: datos de demostración listos.');
        $this->command->line('  Correo:     '.self::CORREO);
        $this->command->line('  Contraseña: '.self::PASSWORD);
        $this->command->line('  Ingreso ajeno (RNG-01): '.self::AJENO_INGRESO_ID);
    }

    /**
     * RNG-01: Segundo usuario con un ingreso propio. Ninguna ruta de MOD-03 debe
     * devolverlo al usuario de demostración: la respuesta esperada es 404 y el
     * intento queda registrado en la bitácora (RF-019).
     */
    private function crearUsuarioAjeno(): void
    {
        $usuario = Usuario::firstOrNew(['correo' => self::AJENO_CORREO]);

        if (! $usuario->exists) {
            $usuario->id = self::AJENO_USUARIO_ID;
        }

        $usuario->fill([
            'nombres' => 'Otro',
            'apellidos' => 'Usuario',
            'tipo_documento' => 'CC',
            'numero_documento' => '1000400400',
            'password_hash' => Hash::make(self::PASSWORD),
            'rol' => 'USUARIO',
            'estado' => 'ACTIVA',
            'mfa_activo' => false,
        ])->save();

        $tipoFuente = Categoria::where('tipo', 'INGRESO')
            ->where('es_predeterminada', true)
            ->whereNull('categoria_padre_id')
            ->where('nombre', 'Salario')
            ->firstOrFail();

        if (! FuenteIngreso::withoutGlobalScopes()->whereKey(self::AJENO_FUENTE_ID)->exists()) {
            $fuente = new FuenteIngreso;
            $fuente->id = self::AJENO_FUENTE_ID;
            $fuente->fill([
                'usuario_id' => $usuario->id,
                'tipo_fuente_id' => $tipoFuente->id,
                'nombre' => 'Fuente de otro usuario',
                'clasificacion_tributaria' => 'RENTAS_TRABAJO',
                'activa' => true,
            ])->save();
        }

        if (! Ingreso::withoutGlobalScopes()->withTrashed()->whereKey(self::AJENO_INGRESO_ID)->exists()) {
            $ingreso = new Ingreso;
            $ingreso->id = self::AJENO_INGRESO_ID;
            $ingreso->fill([
                'usuario_id' => $usuario->id,
                'fuente_id' => self::AJENO_FUENTE_ID,
                'fecha_recepcion' => '2026-02-15',
                'clasificacion_tributaria' => 'RENTAS_TRABAJO',
                'moneda' => 'COP',
                'monto_original' => '9999999.00',
                'tasa_cambio_usada' => '1.0000',
                'monto_bruto_cop' => '9999999.00',
                'retencion_fuente_cop' => '0.00',
                'aportes_descontados_cop' => '0.00',
                'origen' => 'MANUAL',
                'estado' => 'CONFIRMADO',
                'descripcion' => 'RNG-01: este registro no debe ser visible para otro usuario.',
            ])->save();
        }
    }

    /**
     * RN-003.1: Solo inician sesión las cuentas ACTIVA.
     * RN-009.2: La cuenta debe tener aceptada la versión vigente de la política,
     * o el middleware politica.check la desvía antes de llegar a MOD-03.
     */
    private function crearUsuario(): Usuario
    {
        $usuario = Usuario::firstOrNew(['correo' => self::CORREO]);

        $usuario->fill([
            'nombres' => 'Demo',
            'apellidos' => 'Ingresos',
            'tipo_documento' => 'CC',
            'numero_documento' => '1000300300',
            'password_hash' => Hash::make(self::PASSWORD),
            'rol' => 'USUARIO',
            'estado' => 'ACTIVA',
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            // RF-012 es de prioridad baja y pediría un código por correo en cada
            // inicio de sesión: la cuenta de demostración lo deja desactivado.
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
     * RF-020 RN-020.1: Fuentes de ingreso con su cédula tributaria (art. 330 ET).
     * Los tipos provienen del catálogo global (RN-015.2).
     *
     * @return array<string, FuenteIngreso>
     */
    private function crearFuentes(Usuario $usuario): array
    {
        $definiciones = [
            'salario' => ['Empresa XYZ – salario', 'Salario', 'RENTAS_TRABAJO', 'Empresa XYZ S.A.S.'],
            'arriendo' => ['Apartamento Calle 10 – arriendo', 'Arrendamientos', 'RENTAS_CAPITAL', null],
            'honorarios' => ['Consultoría independiente', 'Honorarios/Servicios', 'RENTAS_TRABAJO', null],
            // RN-020.3: una fuente inactiva no aparece al registrar ingresos nuevos.
            'inactiva' => ['CDT Banco ABC (cerrado)', 'Rendimientos financieros', 'RENTAS_CAPITAL', 'Banco ABC'],
        ];

        $fuentes = [];

        foreach ($definiciones as $clave => [$nombre, $tipo, $clasificacion, $pagador]) {
            $tipoFuente = Categoria::where('tipo', 'INGRESO')
                ->where('es_predeterminada', true)
                ->whereNull('categoria_padre_id')
                ->where('nombre', $tipo)
                ->firstOrFail();

            $fuente = FuenteIngreso::firstOrNew([
                'usuario_id' => $usuario->id,
                'nombre' => $nombre,
            ]);

            $fuente->fill([
                'tipo_fuente_id' => $tipoFuente->id,
                'clasificacion_tributaria' => $clasificacion,
                'pagador_entidad' => $pagador,
                'activa' => $clave !== 'inactiva',
            ])->save();

            $fuentes[$clave] = $fuente;
        }

        return $fuentes;
    }

    /**
     * RF-021: Ingresos de ejemplo repartidos en varios meses y monedas, para
     * poder ejercitar los filtros y los totales de RF-024.
     *
     * @param  array<string, FuenteIngreso>  $fuentes
     */
    private function crearIngresos(Usuario $usuario, array $fuentes): void
    {
        $definiciones = [
            // CA-021.1: bruto 5.000.000 − retención 150.000 − aportes 400.000 = neto 4.450.000
            ['salario', '2026-01-30', 'COP', '5000000.00', '1.0000', '150000.00', '400000.00', 'Salario enero'],
            ['salario', '2026-02-27', 'COP', '5000000.00', '1.0000', '150000.00', '400000.00', 'Salario febrero'],
            ['salario', '2026-03-31', 'COP', '5000000.00', '1.0000', '150000.00', '400000.00', 'Salario marzo'],
            ['arriendo', '2026-01-05', 'COP', '1800000.00', '1.0000', '0.00', '0.00', 'Arriendo enero'],
            ['arriendo', '2026-02-05', 'COP', '1800000.00', '1.0000', '0.00', '0.00', 'Arriendo febrero'],
            // RN-021.3: ingreso en divisa; el monto en COP es el que alimenta los cálculos.
            ['honorarios', '2026-03-15', 'USD', '1200.00', '3950.5000', '0.00', '0.00', 'Consultoría en USD'],
        ];

        foreach ($definiciones as [$claveFuente, $fecha, $moneda, $montoOriginal, $tasa, $retencion, $aportes, $descripcion]) {
            $fuente = $fuentes[$claveFuente];

            $yaExiste = Ingreso::withTrashed()
                ->where('usuario_id', $usuario->id)
                ->where('fuente_id', $fuente->id)
                ->where('fecha_recepcion', $fecha)
                ->exists();

            if ($yaExiste) {
                continue;
            }

            // monto_neto_cop no se envía: Postgres lo calcula (RN-021.2).
            Ingreso::create([
                'usuario_id' => $usuario->id,
                'fuente_id' => $fuente->id,
                'fecha_recepcion' => $fecha,
                'clasificacion_tributaria' => $fuente->clasificacion_tributaria,
                'moneda' => $moneda,
                'monto_original' => $montoOriginal,
                'tasa_cambio_usada' => $tasa,
                'monto_bruto_cop' => bcadd(bcmul($montoOriginal, $tasa, 6), '0.005', 2),
                'retencion_fuente_cop' => $retencion,
                'aportes_descontados_cop' => $aportes,
                'origen' => 'MANUAL',
                'estado' => 'CONFIRMADO',
                'descripcion' => $descripcion,
            ]);
        }
    }
}
