<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\CuentaBancaria;
use App\Models\Gasto;
use App\Models\Scopes\DelUsuarioAutenticado;
use Carbon\CarbonImmutable;

/**
 * GMF (4x1000) de las salidas de una cuenta de débito/ahorro.
 *
 * Un gasto con `cuenta_id` genera un gasto de GMF vinculado (`gasto_origen_gmf_id`,
 * categoría Impuestos > GMF) con la misma cuenta, así que también reduce el saldo.
 * `sincronizar` se llama tras crear, editar o eliminar el gasto de origen y deja el
 * GMF coherente: lo crea, lo actualiza o lo elimina (lógicamente).
 *
 * Cuenta exenta: el GMF solo se cobra sobre lo que supera el tope mensual de 350 UVT,
 * acumulado entre las salidas (sin GMF) de las cuentas exentas del usuario en ese mes,
 * en orden de fecha y creación. Limitación: editar una salida anterior no recalcula el
 * GMF de las posteriores del mismo mes.
 */
class GmfCuentaService
{
    public static function sincronizar(Gasto $origen): void
    {
        // El propio GMF nunca genera otro GMF.
        if ($origen->gasto_origen_gmf_id !== null) {
            return;
        }

        $hijo = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->withTrashed()->where('gasto_origen_gmf_id', $origen->id)->first();

        $cuenta = $origen->cuenta_id === null || $origen->trashed() || $origen->estado !== 'CONFIRMADO'
            ? null
            : CuentaBancaria::withoutGlobalScope(DelUsuarioAutenticado::class)->find($origen->cuenta_id);

        $gmf = $cuenta === null ? '0.00' : self::calcular($origen, $cuenta);

        if (bccomp($gmf, '0', 2) <= 0) {
            if ($hijo !== null && ! $hijo->trashed()) {
                $hijo->delete();
            }

            return;
        }

        $atributos = [
            'cuenta_id' => $origen->cuenta_id,
            'fecha' => $origen->fecha->toDateString(),
            'monto_original' => $gmf,
            'monto_cop' => $gmf,
        ];

        if ($hijo === null) {
            [$categoria, $subcategoria] = self::categoriaGmf();
            Gasto::create(array_merge($atributos, [
                'usuario_id' => $origen->usuario_id,
                'categoria_id' => $categoria->id,
                'subcategoria_id' => $subcategoria->id,
                'gasto_origen_gmf_id' => $origen->id,
                'moneda' => 'COP',
                'tasa_cambio_usada' => '1.0000',
                'naturaleza' => 'VARIABLE',
                'necesidad' => 'ESENCIAL',
                'medio_pago' => 'OTRO',
                'relevancia_tributaria' => 'POTENCIALMENTE_DEDUCIBLE',
                'subtipo_deduccion' => 'GMF',
                'origen' => 'MANUAL',
                'estado' => 'CONFIRMADO',
                'descripcion' => 'GMF (4x1000) de una salida de la cuenta · 50 % potencialmente deducible',
            ]));
        } else {
            if ($hijo->trashed()) {
                $hijo->restore();
            }
            $hijo->update($atributos);
        }
    }

    /** GMF de la salida `$origen` desde `$cuenta`, con 2 decimales. */
    private static function calcular(Gasto $origen, CuentaBancaria $cuenta): string
    {
        $previos = '0.00';
        if ($cuenta->es_exenta_gmf) {
            $previos = self::salidasExentasPrevias($origen);
        }

        return CalculoImpuestoInversionService::calcularRetiroCuenta(
            (string) $origen->monto_cop,
            (bool) $cuenta->es_exenta_gmf,
            $previos,
            $origen->fecha->toDateString()
        )['gmf_aplicado'];
    }

    /** Salidas (sin GMF) de las cuentas exentas del usuario en el mes del gasto, anteriores a este. */
    private static function salidasExentasPrevias(Gasto $origen): string
    {
        $mes = CarbonImmutable::parse($origen->fecha);

        $total = Gasto::withoutGlobalScope(DelUsuarioAutenticado::class)
            ->where('usuario_id', $origen->usuario_id)
            ->whereIn('cuenta_id', CuentaBancaria::withoutGlobalScope(DelUsuarioAutenticado::class)
                ->where('usuario_id', $origen->usuario_id)->where('es_exenta_gmf', true)->select('id'))
            ->whereNull('gasto_origen_gmf_id')
            ->where('estado', 'CONFIRMADO')
            ->whereBetween('fecha', [$mes->startOfMonth()->toDateString(), $mes->endOfMonth()->toDateString()])
            ->where('id', '!=', $origen->id)
            ->where(function ($q) use ($origen) {
                $q->where('fecha', '<', $origen->fecha->toDateString())
                    ->orWhere(fn ($r) => $r->where('fecha', $origen->fecha->toDateString())
                        ->where(fn ($s) => $s->where('creado_en', '<', $origen->creado_en)
                            ->orWhere(fn ($t) => $t->where('creado_en', $origen->creado_en)->where('id', '<', $origen->id))));
            })
            ->sum('monto_cop');

        return bcadd((string) $total, '0', 2);
    }

    /** @return array{0: Categoria, 1: Categoria} Impuestos > GMF (catálogo global). */
    private static function categoriaGmf(): array
    {
        $categoria = Categoria::whereNull('usuario_id')->whereNull('categoria_padre_id')
            ->where('tipo', 'GASTO')->where('nombre', 'Impuestos')->firstOrFail();
        $subcategoria = Categoria::whereNull('usuario_id')->where('categoria_padre_id', $categoria->id)
            ->where('tipo', 'GASTO')->where('nombre', 'GMF')->firstOrFail();

        return [$categoria, $subcategoria];
    }
}
