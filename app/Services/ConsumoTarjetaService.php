<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Gasto;
use App\Models\PagoCredito;
use App\Models\Scopes\DelUsuarioAutenticado;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ConsumoTarjetaService
{
    /** Valor de credito_id que pide crear, en el propio gasto, el crédito de una compra a cuotas. */
    public const CREDITO_NUEVO = 'NUEVA';

    public static function esCreditoNuevo(mixed $valor): bool
    {
        return $valor === self::CREDITO_NUEVO;
    }

    public static function tarjetas(string $usuarioId): Builder
    {
        return Credito::where('usuario_id', $usuarioId)->where('tipo_credito', 'TARJETA_CREDITO');
    }

    /**
     * Ajusta el capital y su plan en la misma transacción que el gasto.
     * Con pagos vigentes, conserva sus imputaciones y recalcula las cuotas futuras.
     */
    public static function aplicar(string $usuarioId, ?Gasto $anterior, array $nuevo, ?string $fechaNueva = null): ?string
    {
        // El crédito creado por el propio gasto ya tiene la compra como capital: nunca se ajusta aquí.
        if ($anterior?->genera_credito && $anterior->credito_id !== null) {
            return ($nuevo['medio_pago'] ?? null) === 'TARJETA_CREDITO' ? $anterior->credito_id : null;
        }

        $vinculoAnterior = $anterior?->credito_id;
        $vinculo = $nuevo['credito_id'] ?? null;

        $eraConsumoSinVinculo = $anterior?->medio_pago === 'TARJETA_CREDITO' && $vinculoAnterior === null;
        if (($nuevo['medio_pago'] ?? null) === 'TARJETA_CREDITO' && $vinculo === null && ! $eraConsumoSinVinculo) {
            $disponibles = self::tarjetas($usuarioId)->orderBy('id')->limit(2)->pluck('id');
            if ($disponibles->count() > 1) {
                throw ValidationException::withMessages(['credito_id' => 'Selecciona la tarjeta usada para pagar el gasto.']);
            }
            // Todo gasto con tarjeta queda vinculado a un crédito del módulo de créditos:
            // sin tarjetas registradas hay que registrar la tarjeta con el gasto.
            if ($disponibles->isEmpty()) {
                throw ValidationException::withMessages(['credito_id' => 'Registra la tarjeta: elige "Registrar tarjeta nueva" e indica el banco, el interés anual y el cupo.']);
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
            $cuotasNuevas = max(1, (int) ($nuevo['numero_cuotas'] ?? 1));
            $sinInteresesNuevo = (bool) ($nuevo['sin_intereses'] ?? false);

            // Las cuotas y "sin intereses" son del plan por compra, que exige el ciclo (corte y pago) de la tarjeta.
            if ($tarjeta->id === $vinculo && ($cuotasNuevas > 1 || $sinInteresesNuevo) && ! CicloTarjetaService::tieneCiclo($tarjeta)) {
                throw ValidationException::withMessages([
                    'numero_cuotas' => 'Esta tarjeta no tiene día de corte ni de pago. Registra la tarjeta en Mi perfil con esos datos para comprar a cuotas o sin intereses.',
                ]);
            }

            // Mismo capital pero otras condiciones de la compra (cuotas, intereses, fecha): el plan por compra cambia.
            $condicionesCambian = $tarjeta->id === $vinculo && $tarjeta->id === $vinculoAnterior
                && CicloTarjetaService::tieneCiclo($tarjeta)
                && ((int) $anterior->numero_cuotas !== $cuotasNuevas
                    || (bool) $anterior->sin_intereses !== $sinInteresesNuevo
                    || $anterior->fecha->toDateString() !== ($fechaNueva ?? $anterior->fecha->toDateString()));
            if (bccomp($cambio, '0', 2) === 0 && ! $condicionesCambian) {
                continue;
            }

            // H17: un consumo que supera el cupo disponible se rechaza (no solo se avisa).
            if (bccomp($cambio, '0', 2) === 1 && $tarjeta->cupo !== null) {
                self::validarCupoDisponible($tarjeta, $cambio);
            }

            $capital = bcadd((string) $tarjeta->monto, $cambio, 2);
            if (bccomp($capital, '0', 2) <= 0 || bccomp($capital, '999999999999.99', 2) > 0) {
                throw ValidationException::withMessages(['monto_original' => 'El consumo deja el saldo de la tarjeta fuera del rango permitido.']);
            }

            $tarjeta->update(['monto' => $capital]);
            // El gasto aún no está guardado: las compras del plan se arman con lo que queda y lo nuevo.
            $compras = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->where('credito_id', $tarjeta->id)
                ->when($anterior !== null, fn (Builder $q) => $q->where('id', '!=', $anterior->id))
                ->get(['fecha', 'monto_cop', 'numero_cuotas', 'sin_intereses'])
                ->map(fn (Gasto $g) => [
                    'monto' => (string) $g->monto_cop,
                    'fecha' => $g->fecha->toDateString(),
                    'numero_cuotas' => (int) $g->numero_cuotas,
                    'sin_intereses' => (bool) $g->sin_intereses,
                ]);
            if (bccomp($despues, '0', 2) === 1) {
                $compras->push([
                    'monto' => $despues,
                    'fecha' => $fechaNueva ?? $anterior?->fecha?->toDateString() ?? now()->toDateString(),
                    'numero_cuotas' => $cuotasNuevas,
                    'sin_intereses' => $sinInteresesNuevo,
                ]);
            }
            if (PagoCredito::where('credito_id', $tarjeta->id)->vigentes()->exists()) {
                PagoCreditoService::ajustarPlanTarjeta($tarjeta, $condicionesCambian, $compras->all());
            } else {
                PagoCreditoService::reemplazarPlan($tarjeta, $compras->all());
            }
        }

        return $vinculo;
    }

    /**
     * H17: el consumo adicional $aumento no puede superar el cupo disponible (cupo − saldo de capital a hoy).
     *
     * @throws ValidationException
     */
    public static function validarCupoDisponible(Credito $tarjeta, string $aumento): void
    {
        $cupo = CreditoService::alPeso((string) $tarjeta->cupo);
        $saldo = PagoCreditoService::situacion($tarjeta, CarbonImmutable::today())['saldo_capital'];
        $disponible = bcsub($cupo, $saldo, 0);
        $exceso = bcsub(CreditoService::alPeso($aumento), $disponible, 0);

        if (bccomp($exceso, '0', 0) === 1) {
            $formato = fn (string $v) => number_format((float) $v, 0, ',', '.');
            $disponibleMostrado = bccomp($disponible, '0', 0) === 1 ? $disponible : '0';

            throw ValidationException::withMessages([
                'monto_original' => 'El gasto supera el cupo disponible de la tarjeta '.$tarjeta->banco.' ($ '.$formato($disponibleMostrado).') en $ '.$formato($exceso).'.',
            ]);
        }
    }
}
