<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // El artículo 1 de la Resolución DIAN 000238 de 2025 fija la UVT de 2026.
        DB::table('parametros_normativos')
            ->where('anio_vigencia', 2026)
            ->where('valor_uvt', '52289.00')
            ->update(['valor_uvt' => '52374.00']);
    }

    public function down(): void
    {
        // Mantener el valor oficial, que también puede venir de la carga inicial.
    }
};
