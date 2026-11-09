<?php

namespace App\Console\Commands;

use App\Models\Ingreso;
use App\Models\MovimientoInversion;
use App\Services\IngresoService;
use App\Services\InversionService;
use Illuminate\Console\Command;

class SincronizarIngresosInversionCommand extends Command
{
    protected $signature = 'inversiones:sincronizar-ingresos';
    protected $description = 'Sincroniza dividendos y rendimientos de inversiones hacia la tabla ingresos';

    public function handle(): int
    {
        $movimientos = MovimientoInversion::with('inversion')
            ->whereIn('tipo', ['DIVIDENDO', 'ABONO_INTERES', 'RENDIMIENTO'])
            ->whereNull('ingreso_id')
            ->get();

        $count = 0;
        foreach ($movimientos as $mov) {
            $inv = $mov->inversion;
            if (! $inv) continue;

            $esDividendo = $mov->tipo === 'DIVIDENDO';
            $nombreFuente = $esDividendo ? 'Dividendos de inversiones' : InversionService::FUENTE_RENDIMIENTOS;
            $tipoFuente = $esDividendo ? 'Dividendos' : InversionService::TIPO_FUENTE_RENDIMIENTOS;
            $clasificacion = $esDividendo ? 'DIVIDENDOS' : InversionService::CLASIFICACION_RENDIMIENTOS;

            $fuente = InversionService::fuenteDeRendimientos($mov->usuario_id, $nombreFuente, $tipoFuente, $clasificacion);
            $tasa = (string) ($mov->tasa_cambio_usada ?: '1.0000');
            $neto = (string) $mov->monto;
            $netoCop = IngresoService::convertirACop($neto, $tasa);

            $ingreso = Ingreso::create([
                'usuario_id' => $mov->usuario_id,
                'fuente_id' => $fuente->id,
                'fecha_recepcion' => $mov->fecha,
                'clasificacion_tributaria' => $clasificacion,
                'moneda' => $inv->moneda,
                'monto_original' => $neto,
                'tasa_cambio_usada' => $tasa,
                'monto_bruto_cop' => $netoCop,
                'retencion_fuente_cop' => '0.00',
                'aportes_descontados_cop' => '0.00',
                'origen' => 'MANUAL',
                'estado' => 'CONFIRMADO',
                'descripcion' => ($esDividendo ? 'Dividendo neto de «' : 'Rendimiento/Interés neto de «') . $inv->nombre . '»',
            ]);

            $mov->update(['ingreso_id' => $ingreso->id]);
            $count++;
        }

        $this->info("Se sincronizaron {$count} movimientos hacia el módulo de ingresos.");
        return 0;
    }
}

