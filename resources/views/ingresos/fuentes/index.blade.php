@extends('layouts.app')
@section('title', 'Fuentes de ingreso')
@section('content')
<header class="income-page-header"><div><h1>Fuentes de ingreso</h1><p>Administra el origen y la clasificación de tus ingresos.</p></div><a href="#new-source" class="ui-button ui-button--primary">Nueva fuente</a></header>
@php($sourceCatalog = $fuentes->map(fn($source) => ['id' => $source->id, 'nombre' => $source->nombre])->values())
<div data-source-catalog="{{ json_encode($sourceCatalog) }}"></div>
@include('ingresos.partials.authoritative-errors')
<x-ui.card title="Nueva fuente de ingreso" id="new-source">
    <form method="POST" action="{{ route('ingresos.fuentes.store') }}" data-source-form data-return-url="{{ route('ingresos.fuentes.index') }}" novalidate>
        @csrf
        <x-income.source-form :types="$tiposFuente" :classifications="$clasificaciones" prefix="new-source" />
        <p class="ui-help">Estado inicial: Activa</p>
        <x-income.form-status /><div class="income-actions"><x-ui.button type="submit">Guardar fuente</x-ui.button></div>
    </form>
</x-ui.card>
<section class="income-source-list" aria-label="Fuentes registradas">
    @if($fuentes->isEmpty())<x-ui.empty-state illustration="empty_ingresos" title="No hay fuentes de ingreso registradas" />@endif
    @foreach($fuentes as $source)
        @php($linked = $source->tieneIngresos())
        <x-ui.card>
            <div class="income-source-head"><div><h2><span data-source-visual data-type-name="{{ $source->tipoFuente?->nombre ?? '' }}" data-icono="{{ $source->tipoFuente?->icono ?? '' }}" data-color_hex="{{ $source->tipoFuente?->color_hex ?? '' }}" aria-hidden="true"></span> {{ $source->nombre }}</h2><p>{{ $source->tipoFuente?->nombre ?? '—' }} · <x-income.classification-label :code="$source->clasificacion_tributaria" /></p>@if($source->pagador_entidad)<p>{{ $source->pagador_entidad }}</p>@endif</div><x-ui.badge :tone="$source->activa ? 'success' : 'neutral'">{{ $source->activa ? 'Activa' : 'Inactiva' }}</x-ui.badge></div>
            @if($linked)<p class="ui-help">Tiene ingresos asociados. Solo se puede inactivar.</p>@endif
            <div class="ui-inline">
                <form method="POST" action="{{ route('ingresos.fuentes.toggle', $source->id) }}" data-income-action>@csrf @method('PATCH')<x-ui.button type="submit" variant="secondary">{{ $source->activa ? 'Inactivar' : 'Activar' }}</x-ui.button><x-income.form-status /></form>
                @if(!$linked)<form method="POST" action="{{ route('ingresos.fuentes.destroy', $source->id) }}" data-income-action data-confirm-delete data-confirm-description="¿Deseas eliminar esta fuente de ingreso?" data-confirm-source="{{ $source->nombre }}">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger">Eliminar</x-ui.button><x-income.form-status /></form>@endif
            </div>
            <details><summary>Editar fuente</summary>
                <form method="POST" action="{{ route('ingresos.fuentes.update', $source->id) }}" data-source-form data-source-id="{{ $source->id }}" novalidate>
                    @csrf @method('PUT')
                    <x-income.source-form :types="$tiposFuente" :classifications="$clasificaciones" :record="$source" :prefix="'source-'.$source->id" />
                    <x-income.form-status /><div class="income-actions"><x-ui.button type="submit">Guardar cambios</x-ui.button></div>
                </form>
            </details>
        </x-ui.card>
    @endforeach
</section>
@endsection
