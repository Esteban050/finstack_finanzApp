<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * N02 — Corrección única: el abono de intereses de un CDT se sumaba a su valoración y además
 * contaba como rendimiento, duplicando la ganancia. Resta a cada valoración MANUAL de un CDT
 * activo el neto de los abonos hechos hasta su fecha. Las filas que quedarían negativas se omiten.
 * Corre una sola vez (la tabla migrations la protege); no es reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cdts = DB::table('inversiones')->where('tipo', 'CDT')->whereNull('liquidada_en')->pluck('id');

        foreach ($cdts as $id) {
            $abonos = DB::table('movimientos_inversion')
                ->where('inversion_id', $id)->where('tipo', 'ABONO_INTERES')
                ->get(['fecha', 'monto']);
            if ($abonos->isEmpty()) {
                continue;
            }

            $valoraciones = DB::table('valoraciones_inversion')->where('inversion_id', $id)->where('origen', 'MANUAL')->get(['id', 'fecha', 'valor']);
            foreach ($valoraciones as $v) {
                $suma = (string) $abonos->filter(fn ($a) => (string) $a->fecha <= (string) $v->fecha)->sum('monto');
                if (bccomp($suma, '0', 2) <= 0) {
                    continue;
                }
                $nuevo = bcsub((string) $v->valor, $suma, 2);
                if (bccomp($nuevo, '0', 2) < 0) {
                    continue;
                }
                DB::table('valoraciones_inversion')->where('id', $v->id)->update(['valor' => $nuevo]);
            }
        }
    }

    public function down(): void
    {
        // Corrección de datos: no se revierte.
    }
};
