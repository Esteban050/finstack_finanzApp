<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Ley 797 de 2003, artículo 8. Los porcentajes incluyen el aporte base.
    private const TRAMOS = [
        ['2293bd31-a927-41a8-b700-27ba2c92a900', 0, 4, '0.000'],
        ['2293bd31-a927-41a8-b700-27ba2c92a901', 4, 16, '1.000'],
        ['2293bd31-a927-41a8-b700-27ba2c92a902', 16, 17, '1.200'],
        ['2293bd31-a927-41a8-b700-27ba2c92a903', 17, 18, '1.400'],
        ['2293bd31-a927-41a8-b700-27ba2c92a904', 18, 19, '1.600'],
        ['2293bd31-a927-41a8-b700-27ba2c92a905', 19, 20, '1.800'],
        ['2293bd31-a927-41a8-b700-27ba2c92a906', 20, null, '2.000'],
    ];

    public function up(): void
    {
        DB::select('SELECT pg_advisory_xact_lock(1301, ?)', [2026]);
        $vigencia = DB::table('parametros_normativos')->where('anio_vigencia', 2026)
            ->where('es_activa', true)->value('id');

        if ($vigencia === null || DB::table('tarifas_escalonadas')
            ->where('parametro_normativo_id', $vigencia)->where('tipo_tabla', 'FSP')->exists()) {
            return;
        }

        $filas = [];
        foreach (self::TRAMOS as [$id, $desde, $hasta, $tarifa]) {
            $filas[] = [
                'id' => $id,
                'parametro_normativo_id' => $vigencia,
                'tipo_tabla' => 'FSP',
                'rango_desde' => $desde,
                'rango_hasta' => $hasta,
                'tarifa_porc' => $tarifa,
                'impuesto_base_uvt' => 0,
            ];
        }
        DB::table('tarifas_escalonadas')->insert($filas);
    }

    public function down(): void
    {
        DB::table('tarifas_escalonadas')->whereIn('id', array_column(self::TRAMOS, 0))->delete();
    }
};
