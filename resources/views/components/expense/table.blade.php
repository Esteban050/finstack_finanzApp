@props(['records', 'totals', 'pending'])
@php($labels = require resource_path('views/components/expense/labels.php'))
<x-ui.card class="income-table expense-table">
@if($records->isEmpty())<x-ui.empty-state illustration="empty_gastos" title="No hay gastos para los filtros seleccionados" />
@else
<table class="ui-table"><caption>{{ $records->total() }} registros · Del más reciente al más antiguo</caption><thead><tr><th scope="col">Seleccionar</th><th scope="col">Fecha</th><th scope="col">Categoría y subcategoría</th><th scope="col">Clasificación</th><th scope="col">Medio de pago</th><th scope="col" data-align="right">Monto</th><th scope="col">Acciones</th></tr></thead><tbody>
@foreach($records as $expense)<tr>
    <td><label class="expense-row-selector"><input type="checkbox" data-expense-select value="{{ $expense->id }}" aria-label="Seleccionar gasto del {{ $expense->fecha->format('d/m/Y') }} en {{ $expense->categoria?->nombre }}"></label></td>
    <td>{{ $expense->fecha->format('d/m/Y') }}<div><x-ui.badge :tone="$expense->estado === 'PENDIENTE' ? 'warning' : 'neutral'">{{ $expense->estado === 'PENDIENTE' ? 'Pendiente' : 'Confirmado' }}</x-ui.badge></div></td>
    <td><div class="ui-inline"><span data-source-visual data-nombre="{{ $expense->categoria?->nombre }}" data-icono="{{ $expense->categoria?->icono }}" data-color_hex="{{ $expense->categoria?->color_hex }}" aria-hidden="true"></span><strong>{{ $expense->categoria?->nombre ?? '—' }}</strong></div><span>{{ $expense->subcategoria?->nombre ?? '—' }}</span>@if($expense->comercio_beneficiario)<p>{{ $expense->comercio_beneficiario }}</p>@endif @if($expense->descripcion)<p class="ui-help">{{ $expense->descripcion }}</p>@endif</td>
    <td>{{ $labels[$expense->naturaleza] ?? '—' }} · {{ $labels[$expense->necesidad] ?? '—' }}<p class="ui-help">{{ $labels[$expense->relevancia_tributaria] ?? '—' }}</p>@foreach(($expense->etiquetas ?? []) as $tag)<x-ui.badge>{{ $tag }}</x-ui.badge>@endforeach</td>
    <td>{{ $labels[$expense->medio_pago] ?? '—' }}</td><td class="ui-number"><span data-money="{{ $expense->monto_cop }}">{{ $expense->monto_cop }}</span></td>
    <td><div class="income-row-actions"><x-ui.button :href="route('gastos.edit', $expense->id)" variant="ghost">Editar</x-ui.button>
    <form method="POST" action="{{ route('gastos.destroy', $expense->id) }}" data-expense-action data-confirm-delete data-confirm-date="{{ $expense->fecha->format('Y-m-d') }}" data-confirm-source="{{ $expense->categoria?->nombre }}" data-confirm-amount="{{ $expense->monto_cop }}">@csrf @method('DELETE')<x-ui.button type="submit" variant="ghost">Eliminar</x-ui.button><x-expense.status /></form></div>
    @if($expense->estado === 'PENDIENTE')<details><summary>Confirmar monto real</summary><form method="POST" action="{{ route('gastos.confirmar', $expense->id) }}" data-expense-confirm novalidate>@csrf @method('PATCH')<input type="hidden" name="moneda" value="COP"><x-ui.money-input name="monto_original" :id="'confirm-'.$expense->id" label="Monto real" :value="(string) $expense->monto_cop" /><x-expense.status /><x-ui.button type="submit">Confirmar</x-ui.button></form></details>@endif</td>
</tr>@endforeach
</tbody></table>
@endif
<dl class="income-totals"><div><dt>Total del conjunto filtrado</dt><dd data-money="{{ $totals->monto }}">{{ $totals->monto }}</dd></div><div><dt>Consumos con tarjeta de crédito</dt><dd data-money="{{ $totals->tarjeta_credito }}">{{ $totals->tarjeta_credito }}</dd></div><div><dt>Potencialmente deducible</dt><dd data-money="{{ $totals->potencialmente_deducible }}">{{ $totals->potencialmente_deducible }}</dd></div></dl>
<p class="ui-help">Totales de todos los gastos confirmados que cumplen los filtros; los pendientes se muestran por separado.</p>
@if($pending->cantidad)<p>{{ $pending->cantidad }} pendientes · <span data-money="{{ $pending->monto }}">{{ $pending->monto }}</span></p>@endif
<x-ui.pagination :paginator="$records" />
</x-ui.card>
