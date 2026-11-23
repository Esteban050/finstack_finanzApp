<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carga el calendario DIAN de declaración de renta de personas naturales (RENTA_PN) con vencimientos
 * en 2026 (año gravable 2025), por los dos últimos dígitos del NIT / documento (RF-016).
 *
 * Fuente: DIAN, Comunicado de prensa 090-2026 (plazos del 12 de agosto al 26 de octubre de 2026),
 * contrastado con https://www.gerencie.com/calendario-dian/renta-personas-naturales.
 * El turno "99 y 00" del 26 de octubre se guarda en dos filas (99 y 00) porque la tabla exige
 * digito_desde <= digito_hasta.
 *
 * Es idempotente y no destructiva: si ya existe calendario RENTA_PN para 2026 (p. ej. cargado por el
 * administrador desde el CSV), no lo modifica. El mismo contenido está en
 * database/data/calendario_renta_pn_2026.csv para importarlo desde Administración → Calendario tributario.
 */
return new class extends Migration
{
    private const ANIO_VIGENCIA = 2026;

    /** [digito_desde, digito_hasta, fecha_vencimiento] */
    private const RANGOS = [
        ['00', '00', '2026-10-26'],
        ['01', '02', '2026-08-12'],
        ['03', '04', '2026-08-13'],
        ['05', '06', '2026-08-14'],
        ['07', '08', '2026-08-18'],
        ['09', '10', '2026-08-19'],
        ['11', '12', '2026-08-20'],
        ['13', '14', '2026-08-21'],
        ['15', '16', '2026-08-24'],
        ['17', '18', '2026-08-25'],
        ['19', '20', '2026-08-26'],
        ['21', '22', '2026-08-27'],
        ['23', '24', '2026-08-28'],
        ['25', '26', '2026-08-31'],
        ['27', '28', '2026-09-01'],
        ['29', '30', '2026-09-02'],
        ['31', '32', '2026-09-03'],
        ['33', '34', '2026-09-04'],
        ['35', '36', '2026-09-07'],
        ['37', '38', '2026-09-08'],
        ['39', '40', '2026-09-09'],
        ['41', '42', '2026-09-10'],
        ['43', '44', '2026-09-11'],
        ['45', '46', '2026-09-14'],
        ['47', '48', '2026-09-15'],
        ['49', '50', '2026-09-16'],
        ['51', '52', '2026-09-17'],
        ['53', '54', '2026-09-18'],
        ['55', '56', '2026-09-21'],
        ['57', '58', '2026-09-22'],
        ['59', '60', '2026-09-23'],
        ['61', '62', '2026-09-24'],
        ['63', '64', '2026-09-25'],
        ['65', '66', '2026-09-28'],
        ['67', '68', '2026-10-01'],
        ['69', '70', '2026-10-02'],
        ['71', '72', '2026-10-05'],
        ['73', '74', '2026-10-06'],
        ['75', '76', '2026-10-07'],
        ['77', '78', '2026-10-08'],
        ['79', '80', '2026-10-09'],
        ['81', '82', '2026-10-13'],
        ['83', '84', '2026-10-14'],
        ['85', '86', '2026-10-15'],
        ['87', '88', '2026-10-16'],
        ['89', '90', '2026-10-19'],
        ['91', '92', '2026-10-20'],
        ['93', '94', '2026-10-21'],
        ['95', '96', '2026-10-22'],
        ['97', '98', '2026-10-23'],
        ['99', '99', '2026-10-26'],
    ];

    public function up(): void
    {
        $yaExiste = DB::table('calendario_tributario')
            ->where('anio_vigencia', self::ANIO_VIGENCIA)
            ->where('tipo_obligacion', 'RENTA_PN')
            ->exists();

        if ($yaExiste) {
            return;
        }

        $creadoPor = DB::table('usuarios')->where('rol', 'ADMIN')->value('id')
            ?? DB::table('usuarios')->value('id');

        // Sin usuarios no hay a quién atribuir la carga (creado_por es obligatorio): se puede
        // importar luego el CSV desde la pantalla de administración.
        if (! $creadoPor) {
            return;
        }

        foreach (self::RANGOS as [$desde, $hasta, $fecha]) {
            DB::table('calendario_tributario')->insert([
                'id' => (string) Str::uuid(),
                'anio_vigencia' => self::ANIO_VIGENCIA,
                'tipo_obligacion' => 'RENTA_PN',
                'digito_desde' => $desde,
                'digito_hasta' => $hasta,
                'fecha_vencimiento' => $fecha,
                'creado_por' => $creadoPor,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('calendario_tributario')
            ->where('anio_vigencia', self::ANIO_VIGENCIA)
            ->where('tipo_obligacion', 'RENTA_PN')
            ->delete();
    }
};
