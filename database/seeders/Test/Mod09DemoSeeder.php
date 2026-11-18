<?php

namespace Database\Seeders\Test;

use App\Models\AceptacionPolitica;
use App\Models\Categoria;
use App\Models\FuenteIngreso;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\LineaPresupuesto;
use App\Models\Notificacion;
use App\Models\Presupuesto;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * MOD-09 — Cuentas y datos de demostración para ejercitar los presupuestos
 * desde Postman (SCFP-MOD-09).
 *
 * No se registra en DatabaseSeeder a propósito: crea cuentas con contraseña
 * conocida y solo debe ejecutarse de forma explícita en entornos de desarrollo.
 *
 *   php artisan db:seed --class="Database\Seeders\Test\Mod09DemoSeeder"
 *
 * Las fechas son relativas al día en que se ejecuta (meses M-3, M-2 y M-1,
 * y el mes actual M), para que la colección sirva cualquier día:
 *
 * - mod09@scfp.test, sin presupuestos. Categorías propias "Alimentación demo"
 *   (Mercado, Restaurantes), "Vivienda demo" (Arriendo) y "Transporte demo"
 *   (Taxi). Ingresos de $ 6.000.000 el día 10 de M-3 a M-1. En cada uno de
 *   esos meses: Mercado $ 300.000 el día 1 y Restaurantes $ 640.000 el día 28.
 *   En M-1 además Taxi $ 200.000 el día 5. En M: Mercado $ 1.000.000 el día 1.
 * - mod09.otro@scfp.test, con un presupuesto del mes actual, una línea y una
 *   notificación de UUID fijo, para RNG-01.
 *
 * Es idempotente: cada ejecución borra y recrea los datos de las dos cuentas.
 */
class Mod09DemoSeeder extends Seeder
{
    public const CORREO = 'mod09@scfp.test';
    public const CORREO_OTRO = 'mod09.otro@scfp.test';
    public const PASSWORD = 'Scfp#Demo2026';

    /** UUID fijos, para que la colección los conozca de antemano. */
    public const ALIMENTACION_ID = '90000000-0000-0000-0009-000000000101';
    public const MERCADO_ID = '90000000-0000-0000-0009-000000000102';
    public const RESTAURANTES_ID = '90000000-0000-0000-0009-000000000103';
    public const VIVIENDA_ID = '90000000-0000-0000-0009-000000000104';
    public const ARRIENDO_ID = '90000000-0000-0000-0009-000000000105';
    public const TRANSPORTE_ID = '90000000-0000-0000-0009-000000000106';
    public const TAXI_ID = '90000000-0000-0000-0009-000000000107';
    public const LINEA_AJENA_ID = '90000000-0000-0000-0009-000000000001';
    public const NOTIFICACION_AJENA_ID = '90000000-0000-0000-0009-000000000002';

    private const CATEGORIA_AJENA_ID = '90000000-0000-0000-0009-000000000201';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Mod09DemoSeeder no se ejecuta en producción.');

            return;
        }

        $mes = CarbonImmutable::today()->startOfMonth();

        DB::transaction(function () use ($mes) {
            $usuario = $this->crearCuenta(self::CORREO, 'Demo', 'Presupuesto', '1000090091');
            $otro = $this->crearCuenta(self::CORREO_OTRO, 'Demo', 'Otro presupuesto', '1000090092');

            $this->limpiar($usuario);
            $this->limpiar($otro);

            $this->datosDelUsuario($usuario, $mes);
            $this->presupuestoAjeno($otro, $mes);
        });

        $this->command->info('MOD-09: cuentas de demostración listas.');
        $this->command->line('  Usuario:     '.self::CORREO);
        $this->command->line('  Otra cuenta: '.self::CORREO_OTRO.' (línea '.self::LINEA_AJENA_ID.', notificación '.self::NOTIFICACION_AJENA_ID.')');
        $this->command->line('  Contraseña:  '.self::PASSWORD);
    }

    /** RN-003.1 / RN-009.2: cuenta ACTIVA con la política vigente aceptada. */
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
     * Deja la cuenta sin presupuestos, gastos, ingresos, fuentes ni categorías
     * propias (borrado físico: son datos de prueba de esta cuenta).
     */
    private function limpiar(Usuario $usuario): void
    {
        DB::table('notificaciones')->where('usuario_id', $usuario->id)->delete();
        DB::table('reglas_alerta')->where('usuario_id', $usuario->id)->delete();
        DB::table('preferencias_notificacion')->where('usuario_id', $usuario->id)->delete();
        DB::table('presupuestos')->where('usuario_id', $usuario->id)->delete();
        DB::table('gastos')->where('usuario_id', $usuario->id)->delete();
        DB::table('ingresos')->where('usuario_id', $usuario->id)->delete();
        DB::table('plantillas_recurrencia')->where('usuario_id', $usuario->id)->delete();
        DB::table('fuentes_ingreso')->where('usuario_id', $usuario->id)->delete();
        DB::table('categorias')->where('usuario_id', $usuario->id)->whereNotNull('categoria_padre_id')->delete();
        DB::table('categorias')->where('usuario_id', $usuario->id)->delete();
    }

    private function datosDelUsuario(Usuario $usuario, CarbonImmutable $mes): void
    {
        $alimentacion = $this->categoria(self::ALIMENTACION_ID, $usuario, 'Alimentación demo');
        $mercado = $this->categoria(self::MERCADO_ID, $usuario, 'Mercado', $alimentacion, 'ESENCIAL');
        $restaurantes = $this->categoria(self::RESTAURANTES_ID, $usuario, 'Restaurantes', $alimentacion);
        $vivienda = $this->categoria(self::VIVIENDA_ID, $usuario, 'Vivienda demo');
        $this->categoria(self::ARRIENDO_ID, $usuario, 'Arriendo', $vivienda, 'ESENCIAL', 'FIJO');
        $transporte = $this->categoria(self::TRANSPORTE_ID, $usuario, 'Transporte demo');
        $taxi = $this->categoria(self::TAXI_ID, $usuario, 'Taxi', $transporte);

        $fuente = $this->fuente($usuario);

        for ($k = 3; $k >= 1; $k--) {
            $m = $mes->subMonthsNoOverflow($k);
            $this->ingreso($usuario, $fuente, $m->day(10), '6000000');
            $this->gasto($usuario, $mercado, $m->day(1), '300000', 'Supermercado Ejemplo');
            $this->gasto($usuario, $restaurantes, $m->day(28), '640000', 'Restaurante Demo');
        }

        $this->gasto($usuario, $taxi, $mes->subMonthNoOverflow()->day(5), '200000');
        $this->gasto($usuario, $mercado, $mes, '1000000', 'Supermercado Ejemplo');
    }

    /** RNG-01: presupuesto del mes actual de otra cuenta, con una línea de UUID fijo. */
    private function presupuestoAjeno(Usuario $otro, CarbonImmutable $mes): void
    {
        $categoria = $this->categoria(self::CATEGORIA_AJENA_ID, $otro, 'Categoría ajena demo');

        $presupuesto = Presupuesto::withoutGlobalScopes()->create([
            'usuario_id' => $otro->id,
            'mes' => $mes->toDateString(),
            'repetir_cada_mes' => false,
            'trasladar_saldo' => false,
            'ingreso_base' => '0',
            'base_fijada' => true,
        ]);

        $linea = new LineaPresupuesto;
        $linea->forceFill([
            'id' => self::LINEA_AJENA_ID,
            'presupuesto_id' => $presupuesto->id,
            'categoria_id' => $categoria->id,
            'modo_asignacion' => LineaPresupuesto::PESOS,
            'monto_asignado' => '500000',
        ])->save();

        // RNG-01 (RF-058): notificación de la otra cuenta, de UUID fijo.
        $notificacion = new Notificacion;
        $notificacion->forceFill([
            'id' => self::NOTIFICACION_AJENA_ID,
            'usuario_id' => $otro->id,
            'tipo' => 'UMBRAL_LINEA',
            'prioridad' => Notificacion::ADVERTENCIA,
            'estado' => Notificacion::NO_LEIDA,
            'clave' => 'DEMO_AJENA',
            'datos' => ['mes' => $mes->format('Y-m')],
        ])->save();
    }

    private function categoria(
        string $id,
        Usuario $usuario,
        string $nombre,
        ?Categoria $padre = null,
        string $necesidad = 'DISCRECIONAL',
        string $naturaleza = 'VARIABLE'
    ): Categoria {
        $categoria = new Categoria;
        $categoria->forceFill([
            'id' => $id,
            'usuario_id' => $usuario->id,
            'categoria_padre_id' => $padre?->id,
            'tipo' => 'GASTO',
            'nombre' => $nombre,
            'naturaleza_sugerida' => $padre === null ? null : $naturaleza,
            'necesidad_sugerida' => $padre === null ? null : $necesidad,
            'es_predeterminada' => false,
            'activa' => true,
            'creado_en' => now(),
        ])->save();

        return $categoria;
    }

    private function fuente(Usuario $usuario): FuenteIngreso
    {
        $tipoFuente = Categoria::where('tipo', 'INGRESO')
            ->where('es_predeterminada', true)
            ->whereNull('categoria_padre_id')
            ->where('nombre', 'Salario')
            ->firstOrFail();

        $fuente = new FuenteIngreso;
        $fuente->forceFill([
            'usuario_id' => $usuario->id,
            'tipo_fuente_id' => $tipoFuente->id,
            'nombre' => 'Salario demo MOD-09',
            'clasificacion_tributaria' => 'RENTAS_TRABAJO',
            'activa' => true,
        ])->save();

        return $fuente;
    }

    /** RF-021: ingreso confirmado, sin retención ni aportes. */
    private function ingreso(Usuario $usuario, FuenteIngreso $fuente, CarbonImmutable $fecha, string $bruto): void
    {
        $ingreso = new Ingreso;
        $ingreso->forceFill([
            'usuario_id' => $usuario->id,
            'fuente_id' => $fuente->id,
            'fecha_recepcion' => $fecha->toDateString(),
            'clasificacion_tributaria' => 'RENTAS_TRABAJO',
            'moneda' => 'COP',
            'monto_original' => $bruto,
            'tasa_cambio_usada' => '1.0000',
            'monto_bruto_cop' => $bruto,
            'retencion_fuente_cop' => '0.00',
            'aportes_descontados_cop' => '0.00',
            'origen' => 'MANUAL',
            'estado' => 'CONFIRMADO',
            'descripcion' => 'Dato de demostración MOD-09',
        ])->save();
    }

    /** RF-025: gasto confirmado con la clasificación sugerida por su subcategoría. */
    private function gasto(Usuario $usuario, Categoria $subcategoria, CarbonImmutable $fecha, string $monto, ?string $comercio = null): void
    {
        $gasto = new Gasto;
        $gasto->forceFill([
            'usuario_id' => $usuario->id,
            'categoria_id' => $subcategoria->categoria_padre_id,
            'subcategoria_id' => $subcategoria->id,
            'fecha' => $fecha->toDateString(),
            'moneda' => 'COP',
            'monto_original' => $monto,
            'tasa_cambio_usada' => '1.0000',
            'monto_cop' => $monto,
            'naturaleza' => $subcategoria->naturaleza_sugerida,
            'necesidad' => $subcategoria->necesidad_sugerida,
            'medio_pago' => 'TARJETA_DEBITO',
            'relevancia_tributaria' => 'NO_DEDUCIBLE',
            'etiquetas' => [],
            'comercio_beneficiario' => $comercio,
            'origen' => 'MANUAL',
            'estado' => 'CONFIRMADO',
            'descripcion' => 'Dato de demostración MOD-09',
        ])->save();
    }
}
