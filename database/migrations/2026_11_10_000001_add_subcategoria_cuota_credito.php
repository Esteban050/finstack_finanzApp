<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MOD-08 — Créditos RF-29 / RF-015: cada pago de crédito genera su gasto en
 * MOD-04 bajo "Obligaciones financieras › Cuota de crédito / hipoteca"
 * (PagoCreditoService::CATEGORIA_GASTO / SUBCATEGORIA_GASTO).
 *
 * Esa subcategoría está en el catálogo cargado en la base, pero no en
 * CategoriaPredeterminadaSeeder (que crea "Tarjetas de Crédito" y "Créditos de
 * Consumo"). En una base armada con el seeder, todo pago de crédito respondía
 * 422. Esta migración solo inserta datos: no altera la tabla categorias.
 *
 * - Crea la categoría raíz "Obligaciones financieras" si no existe.
 * - Crea la subcategoría si no existe. Si existe inactiva, no la reactiva:
 *   activarla o no es decisión del Administrador (RF-015).
 *
 * Aplicar solo esta:
 *
 *   php artisan migrate --path=database/migrations/2026_11_10_000001_add_subcategoria_cuota_credito.php
 */
return new class extends Migration
{
    private const CATEGORIA = 'Obligaciones financieras';

    private const SUBCATEGORIA = 'Cuota de crédito / hipoteca';

    public function up(): void
    {
        DB::transaction(function () {
            $padreId = $this->idPredeterminada(self::CATEGORIA, null);

            if ($padreId === null) {
                $padreId = DB::table('categorias')->insertGetId([
                    'tipo' => 'GASTO',
                    'nombre' => self::CATEGORIA,
                    'naturaleza_sugerida' => 'FIJO',
                    'necesidad_sugerida' => 'ESENCIAL',
                    'icono' => 'credit-card',
                    'es_predeterminada' => true,
                    'activa' => true,
                ]);
            }

            if ($this->idPredeterminada(self::SUBCATEGORIA, $padreId) !== null) {
                return;
            }

            DB::table('categorias')->insert([
                'categoria_padre_id' => $padreId,
                'tipo' => 'GASTO',
                'nombre' => self::SUBCATEGORIA,
                'naturaleza_sugerida' => 'FIJO',
                'necesidad_sugerida' => 'ESENCIAL',
                'es_predeterminada' => true,
                'activa' => true,
            ]);
        });
    }

    /**
     * No borra nada: en una base donde la subcategoría ya existía, o donde ya
     * tiene gastos (RNG-07), quitarla rompería el historial.
     */
    public function down(): void
    {
        //
    }

    /**
     * Categoría global de gasto (usuario_id nulo) con ese nombre, sin importar
     * mayúsculas, como lo compara el índice único uq_categorias_nombre_nivel.
     */
    private function idPredeterminada(string $nombre, ?string $padreId): ?string
    {
        $id = DB::table('categorias')
            ->where('tipo', 'GASTO')
            ->whereNull('usuario_id')
            ->when(
                $padreId === null,
                fn ($q) => $q->whereNull('categoria_padre_id'),
                fn ($q) => $q->where('categoria_padre_id', $padreId)
            )
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->value('id');

        return $id === null ? null : (string) $id;
    }
};
