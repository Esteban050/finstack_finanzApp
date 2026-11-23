<?php

use App\Models\ParametroNormativo;
use App\Models\TarifaEscalonada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Asegura las tablas de tarifas escalonadas de FSP (Ley 797 de 2003, Art. 8 / RN-014.2)
     * y RENTA_ART241 para todas las vigencias normativas (incluyendo 2026).
     */
    public function up(): void
    {
        $tramosFsp = [
            ['desde' => 4, 'hasta' => 16, 'tarifa' => 1.0],
            ['desde' => 16, 'hasta' => 17, 'tarifa' => 1.2],
            ['desde' => 17, 'hasta' => 18, 'tarifa' => 1.4],
            ['desde' => 18, 'hasta' => 19, 'tarifa' => 1.6],
            ['desde' => 19, 'hasta' => 20, 'tarifa' => 1.8],
            ['desde' => 20, 'hasta' => null, 'tarifa' => 2.0],
        ];

        $tramosRenta = [
            ['desde' => 0, 'hasta' => 1090, 'tarifa' => 0, 'impuesto_base' => 0],
            ['desde' => 1090, 'hasta' => 1700, 'tarifa' => 19, 'impuesto_base' => 0],
            ['desde' => 1700, 'hasta' => 4100, 'tarifa' => 28, 'impuesto_base' => 116],
            ['desde' => 4100, 'hasta' => 8670, 'tarifa' => 33, 'impuesto_base' => 788],
            ['desde' => 8670, 'hasta' => 18970, 'tarifa' => 35, 'impuesto_base' => 2296],
            ['desde' => 18970, 'hasta' => 31000, 'tarifa' => 37, 'impuesto_base' => 5901],
            ['desde' => 31000, 'hasta' => null, 'tarifa' => 39, 'impuesto_base' => 10352],
        ];

        $parametros = ParametroNormativo::all();

        foreach ($parametros as $parametro) {
            foreach ($tramosFsp as $f) {
                TarifaEscalonada::firstOrCreate(
                    [
                        'parametro_normativo_id' => $parametro->id,
                        'tipo_tabla' => 'FSP',
                        'rango_desde' => $f['desde'],
                    ],
                    [
                        'rango_hasta' => $f['hasta'],
                        'tarifa_porc' => $f['tarifa'],
                        'impuesto_base_uvt' => 0,
                    ]
                );
            }

            foreach ($tramosRenta as $t) {
                TarifaEscalonada::firstOrCreate(
                    [
                        'parametro_normativo_id' => $parametro->id,
                        'tipo_tabla' => 'RENTA_ART241',
                        'rango_desde' => $t['desde'],
                    ],
                    [
                        'rango_hasta' => $t['hasta'],
                        'tarifa_porc' => $t['tarifa'],
                        'impuesto_base_uvt' => $t['impuesto_base'],
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
                ->whereIn('tipo_tabla', ['FSP', 'RENTA_ART241'])
                ->delete();
        }
    }
};
