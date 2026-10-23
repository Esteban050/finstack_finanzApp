<?php

namespace Database\Seeders;

use App\Models\ParametroNormativo;
use App\Models\TarifaEscalonada;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ParametroNormativoSeeder extends Seeder
{
    /**
     * RF-013 & RF-014: Semilla de parámetros normativos oficiales y tablas escalonadas 2025
     */
    public function run(): void
    {
        // Asegurar que existe al menos un Administrador como autor
        $admin = Usuario::firstOrCreate(
            ['correo' => 'admin@scfp.com'],
            [
                'nombres' => 'Administrador',
                'apellidos' => 'Principal',
                'tipo_documento' => 'CC',
                'numero_documento' => '1000000000',
                'password_hash' => Hash::make('Admin12345!'),
                'rol' => 'ADMIN',
                'estado' => 'ACTIVA',
                'creado_en' => now(),
            ]
        );

        // Crear Parámetros Normativos 2025 (RN-013.1)
        $parametro = ParametroNormativo::firstOrCreate(
            ['anio_vigencia' => 2025, 'version' => 1],
            [
                'es_activa' => true,
                'acto_administrativo' => 'Res. DIAN 000193 de 2024 y Decreto SMMLV 2025',
                'fecha_acto' => '2024-12-28',
                'valor_uvt' => 49799.00,
                'valor_smmlv' => 1423500.00,
                'tarifa_salud_indep' => 12.50,
                'tarifa_pension_indep' => 16.00,
                'tarifa_salud_emp' => 4.00,
                'tarifa_pension_emp' => 4.00,
                'porc_ibc_indep' => 40.00,
                'porc_ibc_integral' => 70.00,
                'ibc_min_smmlv' => 1.00,
                'ibc_max_smmlv' => 25.00,
                'tope_patrimonio_uvt' => 4500,
                'tope_ingresos_uvt' => 1400,
                'tope_tc_uvt' => 1400,
                'tope_compras_uvt' => 1400,
                'tope_consignaciones_uvt' => 1400,
                'limite_renta_exenta_25_uvt' => 790,
                'porc_limite_global_exentas' => 40.00,
                'limite_global_exentas_uvt' => 1340,
                'tarifa_gmf' => 0.4000,
                'exencion_gmf_uvt' => 350,
                'ipc_anual' => 5.20,
                'justificacion_cambio' => 'Carga inicial oficial según resoluciones DIAN y decretos nacionales.',
                'creado_por' => $admin->id,
                'creado_en' => now(),
            ]
        );

        // Tabla Art. 241 ET (RN-014.1)
        $tramosRenta = [
            ['desde' => 0, 'hasta' => 1090, 'tarifa' => 0, 'impuesto_base' => 0],
            ['desde' => 1090, 'hasta' => 1700, 'tarifa' => 19, 'impuesto_base' => 0],
            ['desde' => 1700, 'hasta' => 4100, 'tarifa' => 28, 'impuesto_base' => 116],
            ['desde' => 4100, 'hasta' => 8670, 'tarifa' => 33, 'impuesto_base' => 788],
            ['desde' => 8670, 'hasta' => 18970, 'tarifa' => 35, 'impuesto_base' => 2296],
            ['desde' => 18970, 'hasta' => 31000, 'tarifa' => 37, 'impuesto_base' => 5901],
            ['desde' => 31000, 'hasta' => null, 'tarifa' => 39, 'impuesto_base' => 10352],
        ];

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

        // Tabla FSP (RN-014.2)
        $tramosFsp = [
            ['desde' => 4, 'hasta' => 16, 'tarifa' => 1.0],
            ['desde' => 16, 'hasta' => 17, 'tarifa' => 1.2],
            ['desde' => 17, 'hasta' => 18, 'tarifa' => 1.4],
            ['desde' => 18, 'hasta' => 19, 'tarifa' => 1.6],
            ['desde' => 19, 'hasta' => 20, 'tarifa' => 1.8],
            ['desde' => 20, 'hasta' => null, 'tarifa' => 2.0],
        ];

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

        // Tabla ARL (RN-014.3)
        $tramosArl = [
            ['desde' => 1, 'hasta' => 1, 'tarifa' => 0.522],
            ['desde' => 2, 'hasta' => 2, 'tarifa' => 1.044],
            ['desde' => 3, 'hasta' => 3, 'tarifa' => 2.436],
            ['desde' => 4, 'hasta' => 4, 'tarifa' => 4.350],
            ['desde' => 5, 'hasta' => 5, 'tarifa' => 6.960],
        ];

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
