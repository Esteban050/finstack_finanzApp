@extends('layouts.app')
@section('title', 'Ingresos recurrentes')
@section('content')
<header class="income-page-header"><div><h1>Ingresos recurrentes</h1><p>Editar la plantilla solo cambia las ocurrencias futuras</p></div><x-ui.button href="#new-recurrence">Nueva recurrencia</x-ui.button></header>
@include('ingresos.partials.authoritative-errors')
@if($pendientes->isNotEmpty())
<section class="income-source-list" aria-label="Ocurrencias pendientes">
    <h2>Pendientes de confirmar</h2>
    @foreach($pendientes as $pending)<x-ui.card>
        <div class="income-source-head"><div><h3>{{ $pending->fuente?->nombre ?? '—' }}</h3><p><time data-date="{{ \Carbon\Carbon::parse($pending->fecha_recepcion)->toDateString() }}">{{ \Carbon\Carbon::parse($pending->fecha_recepcion)->format('d/m/Y') }}</time> · <span data-money="{{ $pending->monto_bruto_cop }}">{{ $pending->monto_bruto_cop }}</span></p></div><x-ui.badge tone="warning">Pendiente</x-ui.badge></div>
        <div class="ui-inline"><form method="POST" action="{{ route('ingresos.confirmar', $pending->id) }}" data-income-action>@csrf @method('PATCH')<x-ui.button type="submit">Confirmar</x-ui.button><x-income.form-status /></form>
        <form method="POST" action="{{ route('ingresos.destroy', $pending->id) }}" data-income-action data-confirm-delete data-confirm-date="{{ \Carbon\Carbon::parse($pending->fecha_recepcion)->toDateString() }}" data-confirm-source="{{ $pending->fuente?->nombre }}" data-confirm-amount="{{ $pending->monto_bruto_cop }}" data-confirm-label="Descartar" data-confirm-title="Descartar ingreso pendiente">@csrf @method('DELETE')<x-ui.button type="submit" variant="secondary">Descartar</x-ui.button><x-income.form-status /></form></div>
    </x-ui.card>@endforeach
</section>
@endif
<section class="income-source-list" aria-label="Plantillas de recurrencia">
@foreach($plantillas as $template)
    @php
        $data = $template->datos_plantilla;
        $record = ['fuente_id' => $data['fuente_id'], 'monto_original' => (string) $data['monto_bruto_cop'], 'retencion_fuente' => (string) $data['retencion_fuente_cop'], 'aportes_descontados' => (string) $data['aportes_descontados_cop'], 'clasificacion_tributaria' => $data['clasificacion_tributaria'], 'descripcion' => $data['descripcion'] ?? ''];
    @endphp
    <x-ui.card>
        <div class="income-source-head"><div><h2>{{ ($data['descripcion'] ?? '') ?: 'Ingreso recurrente' }}</h2><p>{{ ucfirst(strtolower($template->periodicidad)) }} · <span data-money="{{ $data['monto_bruto_cop'] ?? $data['monto_original'] }}">{{ $data['monto_bruto_cop'] ?? $data['monto_original'] }}</span></p><p>Inicio: {{ $template->fecha_inicio->format('d/m/Y') }} · Próxima: {{ $template->proxima_ejecucion?->format('d/m/Y') ?? '—' }}</p><p>{{ $template->modo_confirmacion === 'AUTOMATICO' ? 'Registrar automáticamente' : 'Requiere confirmación' }} · {{ $template->ocurrencias_generadas }} ocurrencias generadas</p></div><x-ui.badge :tone="$template->activa ? 'success' : 'neutral'">{{ $template->activa ? 'Activa' : 'Inactiva' }}</x-ui.badge></div>
        <div class="ui-inline"><form method="POST" action="{{ route('ingresos.recurrentes.toggle', $template->id) }}" data-income-action>@csrf @method('PATCH')<x-ui.button type="submit" variant="secondary">{{ $template->activa ? 'Pausar' : 'Reanudar' }}</x-ui.button><x-income.form-status /></form>
        <form method="POST" action="{{ route('ingresos.recurrentes.destroy', $template->id) }}" data-income-action data-confirm-delete data-confirm-title="Eliminar plantilla recurrente" data-confirm-label="Eliminar plantilla" data-confirm-description="Se eliminará la plantilla y dejarán de generarse ingresos. Las ocurrencias ya generadas se conservan.">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger">Eliminar plantilla</x-ui.button><x-income.form-status /></form></div>
        <details><summary>Editar plantilla</summary><form method="POST" action="{{ route('ingresos.recurrentes.update', $template->id) }}" data-income-form novalidate>
            @csrf @method('PUT')
            <x-income.form :sources="$fuentes" :classifications="$clasificaciones" :record="$record" :prefix="'template-'.$template->id" :recurrent="true">
                <x-ui.card><x-ui.recurrence-form :periodicities="$periodicidades" :modes="$modosConfirmacion" :record="$template" :prefix="'schedule-'.$template->id" /></x-ui.card>
            </x-income.form>
            <x-income.form-status /><div class="income-actions"><x-ui.button type="submit">Guardar cambios</x-ui.button></div>
        </form></details>
    </x-ui.card>
@endforeach
@if($plantillas->isEmpty())<x-ui.empty-state illustration="empty_ingresos" title="No hay ingresos recurrentes configurados" />@endif
</section>
<details id="new-recurrence" class="income-source-list" open><summary>Nueva recurrencia</summary><form method="POST" action="{{ route('ingresos.recurrentes.store') }}" data-income-form novalidate>
    @csrf
    <x-income.form :sources="$fuentes" :classifications="$clasificaciones" prefix="new-recurring" :recurrent="true">
        <x-ui.card><x-ui.recurrence-form :periodicities="$periodicidades" :modes="$modosConfirmacion" /></x-ui.card>
    </x-income.form>
    <x-income.form-status /><div class="income-actions"><x-ui.button type="submit">Guardar recurrencia</x-ui.button></div>
</form></details>
@endsection

