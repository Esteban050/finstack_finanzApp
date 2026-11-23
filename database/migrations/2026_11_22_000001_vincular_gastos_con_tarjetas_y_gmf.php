<?php

use App\Models\Categoria;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->foreignUuid('credito_id')->nullable()->constrained('creditos')->nullOnDelete();
            $table->foreignUuid('movimiento_gmf_id')->nullable()->unique()
                ->constrained('movimientos_inversion')->nullOnDelete();
            $table->index('credito_id');
        });

        $impuestos = Categoria::firstOrCreate(
            ['nombre' => 'Impuestos', 'tipo' => 'GASTO', 'usuario_id' => null, 'categoria_padre_id' => null],
            ['es_predeterminada' => true, 'activa' => true],
        );
        Categoria::firstOrCreate(
            ['nombre' => 'GMF', 'tipo' => 'GASTO', 'usuario_id' => null, 'categoria_padre_id' => $impuestos->id],
            ['es_predeterminada' => true, 'activa' => true, 'naturaleza_sugerida' => 'VARIABLE', 'necesidad_sugerida' => 'ESENCIAL'],
        );
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credito_id');
            $table->dropConstrainedForeignId('movimiento_gmf_id');
        });
    }
};
