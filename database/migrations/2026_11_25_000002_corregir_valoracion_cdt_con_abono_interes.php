<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * N02 — Corrección única: el abono de intereses de un CDT se sumaba a su valoración y además
 * contaba como rendimiento, duplicando la ganancia.
 *
 * Todas las valoraciones del sistema tienen origen MANUAL, así que no se distinguen por origen. Se
 * corrigen solo las que el registro del abono pudo inflar, por su fecha y sus marcas de tiempo:
 *  - la valoración de la misma fecha del abono, salvo que el usuario la haya vuelto a editar después
 *    (actualizado_en posterior en más de 60 s al registro del abono);
 *  - las valoraciones de fecha posterior que ya existían al registrar el abono (creado_en <= creado_en
 *    del abono), que la propagación sumó. Las digitadas después del abono no se tocan.
 * A cada una se le resta el neto del abono (bcadd, sin flotantes). Se omiten los CDT liquidados y las
 * filas que quedarían negativas. Corre una sola vez (la tabla migrations la protege); no es reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cdts = DB::table('inversiones')->where('tipo', 'CDT')->whereNull('liquidada_en')->pluck('id');

        foreach ($cdts as $id) {
            $abonos = DB::table('movimientos_inversion')
                ->where('inversion_id', $id)->where('tipo', 'ABONO_INTERES')->whereNull('eliminado_en')
                ->orderBy('creado_en')
                ->get(['fecha', 'monto', 'creado_en']);

            foreach ($abonos as $abono) {
                $registro = CarbonImmutable::parse($abono->creado_en);
                $valoraciones = DB::table('valoraciones_inversion')
                    ->where('inversion_id', $id)->where('origen', 'MANUAL')->where('fecha', '>=', $abono->fecha)
                    ->get(['id', 'fecha', 'valor', 'creado_en', 'actualizado_en']);

                foreach ($valoraciones as $v) {
                    $inflada = (string) $v->fecha === (string) $abono->fecha
                        ? CarbonImmutable::parse($v->actualizado_en)->lte($registro->addSeconds(60))
                        : CarbonImmutable::parse($v->creado_en)->lte($registro);
                    if (! $inflada) {
                        continue;
                    }

                    $nuevo = bcsub((string) $v->valor, (string) $abono->monto, 2);
                    if (bccomp($nuevo, '0', 2) < 0) {
                        continue;
                    }
                    DB::table('valoraciones_inversion')->where('id', $v->id)->update(['valor' => $nuevo]);
                }
            }
        }
    }

    public function down(): void
    {
        // Corrección de datos: no se revierte.
    }
};
