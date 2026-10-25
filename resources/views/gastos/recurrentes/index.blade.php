@extends('layouts.app')
@section('title', 'Gastos recurrentes')
@section('content')
<header class="income-page-header"><div><h1>Gastos recurrentes</h1><p>Programa gastos y confirma los montos reales de las ocurrencias pendientes.</p></div><x-ui.button href="#new-expense-recurrence">Nueva recurrencia</x-ui.button></header>
@if($pendientes->isNotEmpty())<section class="income-source-list" aria-label="Gastos pendientes"><h2>Pendientes de confirmar</h2>@foreach($pendientes as $pending)<x-expense.pending :record="$pending" />@endforeach</section>@endif
<section class="income-source-list" aria-label="Plantillas de gasto">
@forelse($plantillas as $template)
    @php($record = array_merge($template->datos_plantilla ?? [], ['monto_original' => (string) ($template->datos_plantilla['monto_cop'] ?? '')]))
    <x-ui.card>
        <div class="income-source-head"><div><h2>{{ ($record['descripcion'] ?? '') ?: 'Gasto recurrente' }}</h2><p>{{ ucfirst(strtolower($template->periodicidad)) }} · <span data-money="{{ $record['monto_original'] }}">{{ $record['monto_original'] }}</span></p><p>Inicio: {{ $template->fecha_inicio->format('d/m/Y') }} · Próxima: {{ $template->proxima_ejecucion?->format('d/m/Y') ?? '—' }}</p><p>{{ $template->modo_confirmacion === 'AUTOMATICO' ? 'Registrar automáticamente' : 'Requiere confirmación' }} · {{ $template->ocurrencias_generadas }} ocurrencias generadas</p>@if($template->es_monto_estimado)<x-ui.badge tone="warning">Monto estimado</x-ui.badge>@endif</div><x-ui.badge :tone="$template->activa ? 'success' : 'neutral'">{{ $template->activa ? 'Activa' : 'Inactiva' }}</x-ui.badge></div>
        <div class="ui-inline">
            <form method="POST" action="{{ route('gastos.recurrentes.toggle', $template->id) }}" data-expense-action>@csrf @method('PATCH')<x-ui.button type="submit" variant="secondary">{{ $template->activa ? 'Pausar' : 'Reanudar' }}</x-ui.button><x-expense.status /></form>
            <form method="POST" action="{{ route('gastos.recurrentes.destroy', $template->id) }}" data-expense-action data-confirm-delete data-confirm-title="Eliminar plantilla recurrente" data-confirm-label="Eliminar plantilla" data-confirm-description="Se eliminará la plantilla y dejarán de generarse gastos. Los gastos ya generados se conservan.">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger">Eliminar plantilla</x-ui.button><x-expense.status /></form>
        </div>
        <details><summary>Editar plantilla</summary><form method="POST" action="{{ route('gastos.recurrentes.update', $template->id) }}" data-expense-form novalidate>
            @csrf @method('PUT')
            <x-expense.form :categories="$categorias" :record="$record" :prefix="'expense-template-'.$template->id" :recurrent="true">
                <x-ui.card><x-ui.recurrence-form :periodicities="$periodicidades" :modes="$modosConfirmacion" :record="$template" :prefix="'expense-schedule-'.$template->id" />
                    <label><input type="checkbox" name="es_monto_estimado" value="1" @checked($template->es_monto_estimado)> Monto estimado</label><p class="ui-help">La ocurrencia queda Pendiente hasta que confirmes el monto real.</p>
                </x-ui.card>
            </x-expense.form>
            <x-expense.status /><div class="income-actions"><x-ui.button type="submit">Guardar cambios</x-ui.button></div>
        </form></details>
    </x-ui.card>
@empty<x-ui.empty-state illustration="empty_gastos" title="No hay gastos recurrentes configurados" />@endforelse
</section>
<details id="new-expense-recurrence" open class="income-source-list"><summary>Nueva recurrencia</summary><form method="POST" action="{{ route('gastos.recurrentes.store') }}" data-expense-form novalidate>
    @csrf
    <x-expense.form :categories="$categorias" prefix="new-expense-recurring" :recurrent="true">
        <x-ui.card><x-ui.recurrence-form :periodicities="$periodicidades" :modes="$modosConfirmacion" prefix="new-expense-schedule" />
            <label><input type="checkbox" name="es_monto_estimado" value="1"> Monto estimado</label><p class="ui-help">La ocurrencia queda Pendiente hasta que confirmes el monto real.</p>
        </x-ui.card>
    </x-expense.form>
    <x-expense.status /><div class="income-actions"><x-ui.button type="submit">Guardar recurrencia</x-ui.button></div>
</form></details>
@endsection
