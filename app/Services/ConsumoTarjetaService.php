<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Gasto;
use App\Models\PagoCredito;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ConsumoTarjetaService
{
    public static function tarjetas(string $usuarioId): Builder
    {
        return Credito::where('usuario_id', $usuarioId)->where('tipo_credito', 'TARJETA_CREDITO');
    }

    /**
     * Ajusta el capital y su plan en la misma transacción que el gasto.
     * Con pagos vigentes, conserva sus imputaciones y recalcula las cuotas futuras.
     */
    public static function aplicar(string $usuarioId, ?Gasto $anterior, array $nuevo): ?string
    {
        $vinculoAnterior = $anterior?->credito_id;
        $vinculo = $nuevo['credito_id'] ?? null;

        $eraConsumoSinVinculo = $anterior?->medio_pago === 'TARJETA_CREDITO' && $vinculoAnterior === null;
        if (($nuevo['medio_pago'] ?? null) === 'TARJETA_CREDITO' && $vinculo === null && ! $eraConsumoSinVinculo) {
            $disponibles = self::tarjetas($usuarioId)->orderBy('id')->limit(2)->pluck('id');
            if ($disponibles->count() > 1) {
                throw ValidationException::withMessages(['credito_id' => 'Selecciona la tarjeta usada para pagar el gasto.']);
            }
            $vinculo = $disponibles->first();
        }

        $ids = array_values(array_unique(array_filter([$vinculoAnterior, $vinculo])));
        if ($ids === []) {
            return null;
        }

        $tarjetas = self::tarjetas($usuarioId)->whereIn('id', $ids)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        if ($vinculo !== null && ! $tarjetas->has($vinculo)) {
            throw ValidationException::withMessages(['credito_id' => 'Elige una de tus tarjetas de crédito registradas.']);
        }

        foreach ($tarjetas as $tarjeta) {
            $antes = $tarjeta->id === $vinculoAnterior ? (string) $anterior->monto_cop : '0';
            $despues = $tarjeta->id === $vinculo ? (string) $nuevo['monto_cop'] : '0';
            $cambio = bcsub($despues, $antes, 2);
            if (bccomp($cambio, '0', 2) === 0) {
                continue;
            }

            $capital = bcadd((string) $tarjeta->monto, $cambio, 2);
            if (bccomp($capital, '0', 2) <= 0 || bccomp($capital, '999999999999.99', 2) > 0) {
                throw ValidationException::withMessages(['monto_original' => 'El consumo deja el saldo de la tarjeta fuera del rango permitido.']);
            }

            $tarjeta->update(['monto' => $capital]);
            if (PagoCredito::where('credito_id', $tarjeta->id)->vigentes()->exists()) {
                PagoCreditoService::ajustarPlanTarjeta($tarjeta);
            } else {
                PagoCreditoService::reemplazarPlan($tarjeta);
            }
        }

        return $vinculo;
    }
}
