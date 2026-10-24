<?php

namespace Database\Seeders\Test;

use App\Models\AceptacionPolitica;
use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * MOD-04 — Datos de demostración para ejercitar y verificar los RF-025 a RF-029.
 *
 * No se registra en DatabaseSeeder a propósito: crea una cuenta con contraseña
 * conocida y solo debe ejecutarse de forma explícita en entornos de desarrollo.
 *
 *   php artisan db:seed --class="Database\Seeders\Test\Mod04DemoSeeder"
 *
 * Es idempotente: se puede volver a ejecutar sin duplicar registros, y repone
 * la subcategoría «Café» si una corrida de Postman la fusionó (CA-027.1).
 */
class Mod04DemoSeeder extends Seeder
{
    public const CORREO = 'mod04@scfp.test';
    public const PASSWORD = 'Scfp#Demo2026';

    /**
     * RNG-01: Identificadores fijos del segundo usuario y de su gasto, para que
     * las pruebas de aislamiento puedan apuntar a un recurso ajeno conocido.
     */
    public const AJENO_CORREO = 'mod04.otro@scfp.test';
    public const AJENO_USUARIO_ID = '90000000-0000-0000-0004-000000000001';
    public const AJENO_GASTO_ID = '90000000-0000-0000-0004-000000000002';

    /**
     * RF-027: Categorías propias del usuario de demostración.
     *
     * «Café» existe para poder fusionarla en «Restaurantes y domicilios»
     * (CA-027.1); «Hobbies» es una categoría de primer nivel propia con su
     * propia subcategoría.
     */
    public const SUBCATEGORIA_CAFE_ID = '90000000-0000-0000-0004-000000000010';
    public const CATEGORIA_HOBBIES_ID = '90000000-0000-0000-0004-000000000011';
    public const SUBCATEGORIA_FOTOGRAFIA_ID = '90000000-0000-0000-0004-000000000012';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Mod04DemoSeeder no se ejecuta en producción.');

            return;
        }

        $usuario = $this->crearUsuario();
        $this->crearCategoriasPropias($usuario);
        $this->crearGastos($usuario);
        $this->crearUsuarioAjeno();

        $this->command->info('MOD-04: datos de demostración listos.');
        $this->command->line('  Correo:     '.self::CORREO);
        $this->command->line('  Contraseña: '.self::PASSWORD);
        $this->command->line('  Subcategoría «Café» (CA-027.1): '.self::SUBCATEGORIA_CAFE_ID);
        $this->command->line('  Gasto ajeno (RNG-01):           '.self::AJENO_GASTO_ID);
    }

    /**
     * RN-003.1: Solo inician sesión las cuentas ACTIVA.
     * RN-009.2: La cuenta debe tener aceptada la versión vigente de la política,
     * o el middleware politica.check la desvía antes de llegar a MOD-04.
     */
    private function crearUsuario(): Usuario
    {
        $usuario = Usuario::firstOrNew(['correo' => self::CORREO]);

        $usuario->fill([
            'nombres' => 'Demo',
            'apellidos' => 'Gastos',
            'tipo_documento' => 'CC',
            'numero_documento' => '1000400401',
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
     * RF-027 RN-027.1: Categorías y subcategorías propias, además de las
     * predeterminadas del catálogo global (RF-015).
     *
     * RN-026.2: toda subcategoría lleva sus valores sugeridos de naturaleza y
     * necesidad, que son los que precarga el formulario de gasto (CA-026.1).
     */
    private function crearCategoriasPropias(Usuario $usuario): void
    {
        $alimentacion = $this->predeterminada('Alimentación');

        $this->categoriaFija(self::SUBCATEGORIA_CAFE_ID, [
            'usuario_id' => $usuario->id,
            'categoria_padre_id' => $alimentacion->id,
            'tipo' => 'GASTO',
            'nombre' => 'Café',
            'naturaleza_sugerida' => 'VARIABLE',
            'necesidad_sugerida' => 'DISCRECIONAL',
            'es_predeterminada' => false,
            'activa' => true,
        ]);

        $this->categoriaFija(self::CATEGORIA_HOBBIES_ID, [
            'usuario_id' => $usuario->id,
            'categoria_padre_id' => null,
            'tipo' => 'GASTO',
            'nombre' => 'Hobbies',
            'icono' => 'film',
            'color_hex' => '#805AD5',
            'es_predeterminada' => false,
            'activa' => true,
        ]);

        $this->categoriaFija(self::SUBCATEGORIA_FOTOGRAFIA_ID, [
            'usuario_id' => $usuario->id,
            'categoria_padre_id' => self::CATEGORIA_HOBBIES_ID,
            'tipo' => 'GASTO',
            'nombre' => 'Fotografía',
            'naturaleza_sugerida' => 'VARIABLE',
            'necesidad_sugerida' => 'DISCRECIONAL',
            'es_predeterminada' => false,
            'activa' => true,
        ]);
    }

    /**
     * RF-025 / RF-026: Gastos de ejemplo repartidos en varios meses, medios de
     * pago y dimensiones, para poder ejercitar los filtros cruzados de CA-026.2,
     * los totales de CA-029.1 y los acumulados de RN-025.2.
     */
    private function crearGastos(Usuario $usuario): void
    {
        $definiciones = [
            // [categoría, subcategoría, fecha, monto, medio de pago, relevancia, subtipo, etiquetas, comercio, descripción]
            ['Vivienda', 'Arriendo', '2026-01-05', '1800000.00', 'TRANSFERENCIA', 'NO_DEDUCIBLE', null, [], 'Inmobiliaria Centro', 'Arriendo enero'],
            ['Vivienda', 'Arriendo', '2026-02-05', '1800000.00', 'TRANSFERENCIA', 'NO_DEDUCIBLE', null, [], 'Inmobiliaria Centro', 'Arriendo febrero'],
            ['Alimentación', 'Mercado y supermercado', '2026-01-12', '450000.00', 'TARJETA_DEBITO', 'NO_DEDUCIBLE', null, ['mercado-mensual'], 'Supermercado Éxito', 'Mercado de enero'],
            // RN-025.2: consumo con tarjeta de crédito; alimenta el indicador anual.
            ['Alimentación', 'Restaurantes y domicilios', '2026-01-20', '120000.00', 'TARJETA_CREDITO', 'NO_DEDUCIBLE', null, ['salida-amigos'], 'Restaurante La Plaza', 'Cena de cumpleaños'],
            // RN-026.3: gasto potencialmente deducible con su subtipo.
            ['Salud', 'Medicina prepagada', '2026-01-15', '320000.00', 'TRANSFERENCIA', 'POTENCIALMENTE_DEDUCIBLE', 'MEDICINA_PREPAGADA', [], 'Colsanitas', 'Plan de medicina prepagada'],
            ['Entretenimiento', 'Suscripciones streaming', '2026-02-05', '39900.00', 'TARJETA_CREDITO', 'NO_DEDUCIBLE', null, ['suscripcion'], 'Netflix', 'Suscripción mensual'],
            // CA-027.1: gastos en «Café», la subcategoría propia que se fusiona.
            ['Alimentación', 'Café', '2026-02-10', '18000.00', 'EFECTIVO', 'NO_DEDUCIBLE', null, ['viaje-cartagena'], 'Juan Valdez', 'Café de la mañana'],
            ['Alimentación', 'Café', '2026-03-03', '22000.00', 'EFECTIVO', 'NO_DEDUCIBLE', null, ['viaje-cartagena'], 'Tostao', 'Café con cliente'],
            ['Transporte', 'Combustible y parqueaderos', '2026-03-08', '200000.00', 'TARJETA_CREDITO', 'NO_DEDUCIBLE', null, [], 'Terpel', 'Tanqueo de marzo'],
            ['Impuestos', 'GMF (4x1000) e impuesto de renta', '2026-03-31', '15000.00', 'OTRO', 'POTENCIALMENTE_DEDUCIBLE', 'GMF', [], null, 'GMF del trimestre'],
            ['Hobbies', 'Fotografía', '2026-03-15', '85000.00', 'BILLETERA_DIGITAL', 'NO_DEDUCIBLE', null, ['hobby'], 'Fotorevelado', 'Revelado de rollo'],
        ];

        foreach ($definiciones as [$cat, $sub, $fecha, $monto, $medio, $relevancia, $subtipo, $etiquetas, $comercio, $descripcion]) {
            $subcategoria = $this->subcategoriaDe($usuario, $cat, $sub);

            $yaExiste = Gasto::withoutGlobalScopes()->withTrashed()
                ->where('usuario_id', $usuario->id)
                ->where('subcategoria_id', $subcategoria->id)
                ->where('fecha', $fecha)
                ->exists();

            if ($yaExiste) {
                continue;
            }

            Gasto::create([
                'usuario_id' => $usuario->id,
                'categoria_id' => $subcategoria->categoria_padre_id,
                'subcategoria_id' => $subcategoria->id,
                'fecha' => $fecha,
                'moneda' => 'COP',
                'monto_original' => $monto,
                'tasa_cambio_usada' => '1.0000',
                'monto_cop' => $monto,
                // RN-026.2: la clasificación sale de la sugerencia de la subcategoría.
                'naturaleza' => $subcategoria->naturaleza_sugerida,
                'necesidad' => $subcategoria->necesidad_sugerida,
                'medio_pago' => $medio,
                'relevancia_tributaria' => $relevancia,
                'subtipo_deduccion' => $subtipo,
                'etiquetas' => $etiquetas,
                'comercio_beneficiario' => $comercio,
                'origen' => 'MANUAL',
                'estado' => 'CONFIRMADO',
                'descripcion' => $descripcion,
            ]);
        }
    }

    /**
     * RNG-01: Segundo usuario con un gasto propio. Ninguna ruta de MOD-04 debe
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
            'apellidos' => 'Usuario Gastos',
            'tipo_documento' => 'CC',
            'numero_documento' => '1000400402',
            'password_hash' => Hash::make(self::PASSWORD),
            'rol' => 'USUARIO',
            'estado' => 'ACTIVA',
            'mfa_activo' => false,
        ])->save();

        if (Gasto::withoutGlobalScopes()->withTrashed()->whereKey(self::AJENO_GASTO_ID)->exists()) {
            return;
        }

        $arriendo = $this->predeterminadaHija('Vivienda', 'Arriendo');

        $gasto = new Gasto;
        $gasto->id = self::AJENO_GASTO_ID;
        $gasto->fill([
            'usuario_id' => $usuario->id,
            'categoria_id' => $arriendo->categoria_padre_id,
            'subcategoria_id' => $arriendo->id,
            'fecha' => '2026-02-15',
            'moneda' => 'COP',
            'monto_original' => '9999999.00',
            'tasa_cambio_usada' => '1.0000',
            'monto_cop' => '9999999.00',
            'naturaleza' => 'FIJO',
            'necesidad' => 'ESENCIAL',
            'medio_pago' => 'TRANSFERENCIA',
            'relevancia_tributaria' => 'NO_DEDUCIBLE',
            'etiquetas' => [],
            'origen' => 'MANUAL',
            'estado' => 'CONFIRMADO',
            'descripcion' => 'RNG-01: este registro no debe ser visible para otro usuario.',
        ])->save();
    }

    /**
     * Crea o repone una categoría con un UUID fijo, para que las colecciones de
     * Postman puedan referenciarla sin resolverla antes.
     */
    private function categoriaFija(string $id, array $atributos): Categoria
    {
        $categoria = Categoria::find($id);

        if ($categoria === null) {
            $categoria = new Categoria;
            $categoria->id = $id;
        }

        $categoria->fill(array_merge($atributos, ['creado_en' => now()]))->save();

        return $categoria;
    }

    /** RF-015 RN-015.1: Una categoría de primer nivel del catálogo global. */
    private function predeterminada(string $nombre): Categoria
    {
        return Categoria::where('tipo', 'GASTO')
            ->where('es_predeterminada', true)
            ->whereNull('categoria_padre_id')
            ->where('nombre', $nombre)
            ->firstOrFail();
    }

    /** RF-015 RN-015.1: Una subcategoría del catálogo global. */
    private function predeterminadaHija(string $categoria, string $subcategoria): Categoria
    {
        return Categoria::where('tipo', 'GASTO')
            ->where('es_predeterminada', true)
            ->where('categoria_padre_id', $this->predeterminada($categoria)->id)
            ->where('nombre', $subcategoria)
            ->firstOrFail();
    }

    /**
     * RN-026.1 (1): La subcategoría del gasto, sea propia o predeterminada.
     */
    private function subcategoriaDe(Usuario $usuario, string $categoria, string $subcategoria): Categoria
    {
        $padre = Categoria::where('tipo', 'GASTO')
            ->whereNull('categoria_padre_id')
            ->where('nombre', $categoria)
            ->where(fn ($q) => $q->where('usuario_id', $usuario->id)->orWhere('es_predeterminada', true))
            ->firstOrFail();

        return Categoria::where('tipo', 'GASTO')
            ->where('categoria_padre_id', $padre->id)
            ->where('nombre', $subcategoria)
            ->where(fn ($q) => $q->where('usuario_id', $usuario->id)->orWhere('es_predeterminada', true))
            ->firstOrFail();
    }
}
