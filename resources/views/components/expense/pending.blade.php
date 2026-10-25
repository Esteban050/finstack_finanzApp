@props(['record'])
<x-ui.card>
    <div class="income-source-head"><div><h3>{{ $record->categoria?->nombre ?? '—' }} · {{ $record->subcategoria?->nombre ?? '—' }}</h3><p>{{ $record->fecha->format('d/m/Y') }} · <span data-money="{{ $record->monto_cop }}">{{ $record->monto_cop }}</span> · Monto pendiente de confirmar</p></div><x-ui.badge tone="warning">Pendiente</x-ui.badge></div>
    <form method="POST" action="{{ route('gastos.confirmar', $record->id) }}" data-expense-confirm novalidate>
        @csrf @method('PATCH')<input type="hidden" name="moneda" value="COP">
        <x-ui.money-input name="monto_original" :id="'real-'.$record->id" label="Monto real" :value="(string) $record->monto_cop" />
        <x-expense.status /><x-ui.button type="submit">Confirmar monto real</x-ui.button>
    </form>
    <form method="POST" action="{{ route('gastos.destroy', $record->id) }}" data-expense-action data-confirm-delete data-confirm-title="Descartar gasto pendiente" data-confirm-label="Descartar" data-confirm-date="{{ $record->fecha->format('Y-m-d') }}" data-confirm-source="{{ $record->categoria?->nombre }}" data-confirm-amount="{{ $record->monto_cop }}">
        @csrf @method('DELETE')<x-expense.status /><x-ui.button type="submit" variant="secondary">Descartar</x-ui.button>
    </form>
</x-ui.card>
