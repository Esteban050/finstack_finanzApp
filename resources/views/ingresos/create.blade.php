@extends('layouts.app')
@section('title', 'Registrar ingreso')
@section('content')
<header class="income-page-header"><div><h1>Registrar ingreso</h1><p>Selecciona la fuente y registra el valor recibido.</p></div></header>
@include('ingresos.partials.authoritative-errors')
<form method="POST" action="{{ route('ingresos.store') }}" data-income-form data-return-url="{{ route('ingresos.index') }}" novalidate>
    @csrf
    <x-income.form :sources="$fuentes" :classifications="$clasificaciones" />
    <x-income.form-status />
    <div class="income-actions"><x-ui.button :href="route('ingresos.index')" variant="secondary">Cancelar</x-ui.button><x-ui.button type="submit">Guardar ingreso</x-ui.button></div>
</form>
@endsection
