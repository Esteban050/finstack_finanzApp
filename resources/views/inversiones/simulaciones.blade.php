@extends('layouts.app')
@section('title', 'Simulaciones de inversión')
@section('content')
<div class="finance-page">
<header class="income-page-header">
    <div>
        <h1>Simulador de Inversiones</h1>
        <p>Compara hasta cuatro alternativas sin afectar tus inversiones ni movimientos reales.</p>
    </div>
    <div class="ui-inline" style="gap: 0.5rem; flex-wrap: wrap;">
        <x-ui.button :href="route('inversiones.index')" variant="secondary">Ver inversiones</x-ui.button>
        <x-ui.button :href="route('inversiones.portafolio')" variant="secondary">Portafolio</x-ui.button>
    </div>
</header>
<x-ui.card title="Configurar alternativas"><form method="POST" action="{{ route('inversiones.simulaciones.calcular') }}" data-finance-form="simulation" data-finance-compare>@csrf
<div data-alternatives>@foreach(old('alternativas', [[]]) as $index=>$alternative)<x-ui.investment-alternative :index="$index" :alternative="$alternative" />@endforeach</div>
<template data-alternative-template><x-ui.investment-alternative :index="0" /></template>
<p data-alternative-count class="ui-help" aria-live="polite">1 de 4 alternativas</p><div class="ui-inline"><x-ui.button type="button" variant="secondary" data-add-alternative>Agregar alternativa</x-ui.button><x-ui.button type="submit">Comparar alternativas</x-ui.button></div><x-ui.module-status /></form></x-ui.card>
<section data-comparison hidden tabindex="-1" aria-labelledby="comparison-title"><h2 id="comparison-title">Resultados de la comparación</h2><p data-comparison-notices class="ui-help" role="status"></p><div data-comparison-cards class="finance-grid"></div><x-ui.card title="Guardar esta simulación"><form method="POST" action="{{ route('inversiones.simulaciones.store') }}" data-finance-form="save-simulation" data-comparison-save>@csrf<x-ui.field name="nombre" label="Nombre de la simulación" :required="true" help="Entre 3 y 60 caracteres. Puedes guardar hasta 20 simulaciones."><input id="nombre" name="nombre" minlength="3" maxlength="60" required></x-ui.field><x-ui.module-status /><x-ui.button type="submit">Guardar simulación</x-ui.button></form></x-ui.card></section>
<x-ui.card title="Simulaciones guardadas"><p class="ui-help">Máximo {{ $maximo }} por usuario.</p>@if($simulaciones->isEmpty())<x-ui.empty-state title="No tienes simulaciones guardadas" />@else
<table class="ui-table"><caption>Comparaciones guardadas, de la más reciente a la más antigua</caption><thead><tr><th>Nombre</th><th>Alternativas</th><th>Fecha</th><th>Acciones</th></tr></thead><tbody>@foreach($simulaciones as $sim)<tr><td>{{ $sim->nombre }}</td><td>{{ count($sim->alternativas) }}</td><td>{{ $sim->creado_en->timezone('America/Bogota')->format('d/m/Y') }}</td><td><div class="income-row-actions"><form method="POST" action="{{ route('inversiones.simulaciones.calcular') }}" data-finance-form="simulation" data-finance-compare>@csrf @foreach($sim->alternativas as $index=>$alternative)@foreach($alternative as $key=>$value)<input type="hidden" name="alternativas[{{ $index }}][{{ $key }}]" value="{{ $value }}">@endforeach @endforeach<x-ui.module-status /><x-ui.button type="submit" variant="secondary">Comparar de nuevo</x-ui.button></form><form method="POST" action="{{ route('inversiones.simulaciones.destroy',$sim->id) }}" data-finance-form="action" data-confirm-description="Se eliminará la simulación guardada; no afecta tus inversiones.">@csrf @method('DELETE')<x-ui.module-status /><x-ui.button type="submit" variant="ghost">Eliminar</x-ui.button></form></div></td></tr>@endforeach</tbody></table>@endif<x-ui.pagination :paginator="$simulaciones" /></x-ui.card>
<x-ui.calculation-notice />
</div>
@endsection
