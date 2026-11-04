<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Rangos oficiales de pago de seguridad social para independientes
     * según el Decreto 1990 de 2016 (Artículo 3.2.2.1 del Decreto 780 de 2016).
     *
     * La fecha_vencimiento almacena el día hábil en su campo día (2 al 16).
     */
    private const RANGOS_DECRETO_1990 = [
        ['desde' => '00', 'hasta' => '07', 'dia_habil' => 2],
        ['desde' => '08', 'hasta' => '14', 'dia_habil' => 3],
        ['desde' => '15', 'hasta' => '21', 'dia_habil' => 4],
        ['desde' => '22', 'hasta' => '28', 'dia_habil' => 5],
        ['desde' => '29', 'hasta' => '35', 'dia_habil' => 6],
        ['desde' => '36', 'hasta' => '42', 'dia_habil' => 7],
        ['desde' => '43', 'hasta' => '49', 'dia_habil' => 8],
        ['desde' => '50', 'hasta' => '56', 'dia_habil' => 9],
        ['desde' => '57', 'hasta' => '63', 'dia_habil' => 10],
        ['desde' => '64', 'hasta' => '69', 'dia_habil' => 11],
        ['desde' => '70', 'hasta' => '75', 'dia_habil' => 12],
        ['desde' => '76', 'hasta' => '81', 'dia_habil' => 13],
        ['desde' => '82', 'hasta' => '87', 'dia_habil' => 14],
        ['desde' => '88', 'hasta' => '93', 'dia_habil' => 15],
        ['desde' => '94', 'hasta' => '99', 'dia_habil' => 16],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $adminId = DB::table('usuarios')->where('rol', 'ADMIN')->value('id')
            ?? DB::table('usuarios')->value('id');

        // Vigencias a configurar (2025 y 2026)
        $vigencias = [2025, 2026, 2027];

        foreach ($vigencias as $anio) {
            // Eliminar registros previos de prueba de seguridad social independientes
            DB::table('calendario_tributario')
                ->where('anio_vigencia', $anio)
                ->where('tipo_obligacion', 'SEGURIDAD_SOCIAL_INDEP')
                ->delete();

            // Insertar los 15 rangos normativos oficiales
            foreach (self::RANGOS_DECRETO_1990 as $rango) {
                DB::table('calendario_tributario')->insert([
                    'id' => (string) Str::uuid(),
                    'anio_vigencia' => $anio,
                    'tipo_obligacion' => 'SEGURIDAD_SOCIAL_INDEP',
                    'digito_desde' => $rango['desde'],
                    'digito_hasta' => $rango['hasta'],
                    'fecha_vencimiento' => sprintf('%04d-01-%02d', $anio, $rango['dia_habil']),
                    'creado_por' => $adminId,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('calendario_tributario')
            ->whereIn('anio_vigencia', [2025, 2026, 2027])
            ->where('tipo_obligacion', 'SEGURIDAD_SOCIAL_INDEP')
            ->delete();
    }
};

