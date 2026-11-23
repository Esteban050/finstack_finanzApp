<?php

use App\Models\ParametroNormativo;
use App\Models\TarifaEscalonada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Asegura las tarifas escalonadas de ARL (Clases I a V, Decreto 1072 de 2015)
     * para todas las vigencias normativas registradas (incluyendo 2026).
     */
    public function up(): void
    {
        $tramosArl = [
            ['desde' => 1, 'hasta' => 1, 'tarifa' => 0.522],
            ['desde' => 2, 'hasta' => 2, 'tarifa' => 1.044],
            ['desde' => 3, 'hasta' => 3, 'tarifa' => 2.436],
            ['desde' => 4, 'hasta' => 4, 'tarifa' => 4.350],
            ['desde' => 5, 'hasta' => 5, 'tarifa' => 6.960],
        ];

        $parametros = ParametroNormativo::all();

        foreach ($parametros as $parametro) {
            foreach ($tramosArl as $a) {
                TarifaEscalonada::firstOrCreate(
                    [
                        'parametro_normativo_id' => $parametro->id,
                        'tipo_tabla' => 'ARL',
                        'rango_desde' => $a['desde'],
                    ],
                    [
                        'rango_hasta' => $a['hasta'],
                        'tarifa_porc' => $a['tarifa'],
                        'impuesto_base_uvt' => 0,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        $parametro2026 = ParametroNormativo::where('anio_vigencia', 2026)->first();
        if ($parametro2026) {
            TarifaEscalonada::where('parametro_normativo_id', $parametro2026->id)
                ->where('tipo_tabla', 'ARL')
                ->delete();
        }
    }
};
