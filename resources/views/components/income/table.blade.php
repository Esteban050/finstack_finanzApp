@props(['incomes', 'totals', 'paginator'])
<section class="income-source-list income-table" aria-label="Movimientos de ingresos">
<x-ui.card>
@if(count($incomes) === 0)
    <x-ui.empty-state illustration="empty_ingresos" title="No hay ingresos para los filtros seleccionados" />
@else
<table class="ui-table"><caption>{{ $paginator->total() }} {{ $paginator->total() === 1 ? 'registro' : 'registros' }} · ordenados del más reciente al más antiguo</caption>
<thead><tr><th scope="col">Fecha</th><th scope="col">Fuente de ingreso</th><th scope="col">Clasificación tributaria</th><th scope="col" data-align="right">Monto bruto</th><th scope="col" data-align="right">Retención</th><th scope="col" data-align="right">Aportes</th><th scope="col" data-align="right">Neto</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
<tbody>
@foreach($incomes as $income)
@php($date = substr((string) $income->fecha_recepcion, 0, 10))
<tr>
<td data-label="Fecha"><time data-date="{{ $date }}">{{ implode('/', array_reverse(explode('-', $date))) }}</time></td>
<td data-label="Fuente de ingreso">{{ $income->fuente?->nombre ?? '—' }}</td>
<td data-label="Clasificación tributaria"><x-income.classification-label :code="$income->clasificacion_tributaria" /></td>
<td data-label="Monto bruto"><span data-money="{{ $income->monto_bruto_cop }}">{{ $income->monto_bruto_cop }}</span></td>
<td data-label="Retención"><span data-money="{{ $income->retencion_fuente_cop }}">{{ $income->retencion_fuente_cop }}</span></td>
<td data-label="Aportes"><span data-money="{{ $income->aportes_descontados_cop }}">{{ $income->aportes_descontados_cop }}</span></td>
<td data-label="Neto"><strong data-money="{{ $income->monto_neto_cop }}">{{ $income->monto_neto_cop }}</strong></td>
<td data-label="Estado"><x-ui.badge :tone="$income->estado === 'PENDIENTE' ? 'warning' : 'success'">{{ $income->estado === 'PENDIENTE' ? 'Pendiente' : 'Confirmado' }}</x-ui.badge></td>
<td data-label="Acciones"><div class="income-row-actions">
@if($income->estado === 'PENDIENTE')<form method="POST" action="{{ route('ingresos.confirmar', $income->id) }}" data-income-action>@csrf @method('PATCH')<x-ui.button type="submit" variant="secondary">Confirmar</x-ui.button><x-income.form-status /></form>@endif
<x-ui.button :href="route('ingresos.edit', $income->id)" variant="ghost">Editar</x-ui.button>
<form method="POST" action="{{ route('ingresos.destroy', $income->id) }}" data-income-action data-confirm-delete data-confirm-date="{{ $date }}" data-confirm-source="{{ $income->fuente?->nombre }}" data-confirm-amount="{{ $income->monto_bruto_cop }}">@csrf @method('DELETE')<x-ui.button type="submit" variant="ghost">Eliminar</x-ui.button><x-income.form-status /></form>
</div></td>
</tr>
@endforeach
</tbody>
</table>
@endif
<dl class="income-totals" aria-label="Totales del conjunto filtrado completo">
<div><dt>Monto bruto total</dt><dd data-money="{{ $totals->bruto }}">{{ $totals->bruto }}</dd></div>
<div><dt>Retención total</dt><dd data-money="{{ $totals->retencion }}">{{ $totals->retencion }}</dd></div>
<div><dt>Monto neto total</dt><dd data-money="{{ $totals->neto }}">{{ $totals->neto }}</dd></div>
</dl>
<p class="ui-help">Los totales corresponden al conjunto filtrado completo y excluyen ingresos pendientes.</p>
<x-ui.pagination :paginator="$paginator" />
</x-ui.card>
</section>
