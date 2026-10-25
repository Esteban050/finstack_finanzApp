@extends('layouts.app')
@section('title', 'Editar gasto')
@section('content')
<header class="income-page-header"><div><h1>Editar gasto</h1><p>Actualiza los datos y la clasificación del gasto.</p></div></header>
@php($record = array_merge($gasto->toArray(), ['fecha' => $gasto->fecha->format('Y-m-d'), 'monto_original' => (string) $gasto->monto_cop]))
<form method="POST" action="{{ route('gastos.update', $gasto->id) }}" data-expense-form data-return-url="{{ route('gastos.index') }}" novalidate>
    @csrf @method('PUT')
    <x-expense.form :categories="$categorias" :record="$record" />
    <x-expense.status /><div class="income-actions"><x-ui.button :href="route('gastos.index')" variant="secondary">Cancelar</x-ui.button><x-ui.button type="submit">Guardar cambios</x-ui.button></div>
</form>
@endsection
