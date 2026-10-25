<?php
/** Prueba de presentación: modelos sin guardar y renderizado Blade real, solo CLI. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['request']->setLaravelSession($app['session']->driver());
$app['view']->share('errors', new Illuminate\Support\ViewErrorBag());
$category = new App\Models\Categoria(['nombre' => 'Categoría de prueba', 'tipo' => 'GASTO', 'activa' => true]);
$category->id = '00000000-0000-4000-8000-000000000001';
$child = new App\Models\Categoria(['nombre' => 'Subcategoría de prueba', 'tipo' => 'GASTO', 'categoria_padre_id' => $category->id, 'activa' => true, 'naturaleza_sugerida' => 'FIJO', 'necesidad_sugerida' => 'ESENCIAL']);
$child->id = '00000000-0000-4000-8000-000000000002';
$category->setRelation('subcategorias', collect([$child]));
$expense = new App\Models\Gasto(['fecha' => '2026-01-15', 'moneda' => 'COP', 'monto_original' => '125000.00', 'monto_cop' => '125000.00', 'categoria_id' => $category->id, 'subcategoria_id' => $child->id, 'naturaleza' => 'FIJO', 'necesidad' => 'ESENCIAL', 'medio_pago' => 'EFECTIVO', 'relevancia_tributaria' => 'NO_DEDUCIBLE', 'estado' => 'PENDIENTE', 'etiquetas' => ['prueba']]);
$expense->id = '00000000-0000-4000-8000-000000000003';
$expense->setRelation('categoria', $category)->setRelation('subcategoria', $child);
$template = new App\Models\PlantillaRecurrencia(['fecha_inicio' => '2026-01-05', 'periodicidad' => 'MENSUAL', 'modo_confirmacion' => 'REQUIERE_CONFIRMACION', 'activa' => true, 'ocurrencias_generadas' => 1, 'es_monto_estimado' => true, 'datos_plantilla' => ['categoria_id' => $category->id, 'subcategoria_id' => $child->id, 'monto_cop' => '39900.00', 'moneda' => 'COP', 'medio_pago' => 'EFECTIVO', 'naturaleza' => 'FIJO', 'necesidad' => 'ESENCIAL', 'relevancia_tributaria' => 'NO_DEDUCIBLE', 'etiquetas' => []]]);
$template->id = '00000000-0000-4000-8000-000000000004';
$table = view('components.expense.table', [
    'records' => new Illuminate\Pagination\LengthAwarePaginator(collect([$expense]), 1, 20),
    // Totales deliberadamente distintos de la fila para detectar sumas locales.
    'totals' => (object) ['monto' => '999999.99', 'tarjeta_credito' => '0', 'potencialmente_deducible' => '100.01'],
    'pending' => (object) ['cantidad' => 1, 'monto' => '125000.00'],
])->render();
$recurrences = view('gastos.recurrentes.index', [
    'categorias' => collect([$category]), 'plantillas' => collect([$template]), 'pendientes' => collect([$expense]),
    'periodicidades' => App\Models\PlantillaRecurrencia::PERIODICIDADES,
    'modosConfirmacion' => App\Models\PlantillaRecurrencia::MODOS_CONFIRMACION,
])->render();
echo json_encode(['table' => $table, 'recurrences' => $recurrences], JSON_THROW_ON_ERROR);
