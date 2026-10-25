@extends('layouts.app')
@section('title', 'Registrar gasto')
@section('content')
<header class="income-page-header"><div><h1>Registrar gasto</h1><p>Identifica el gasto y organiza su clasificación.</p></div></header>
<form method="POST" action="{{ route('gastos.store') }}" data-expense-form data-return-url="{{ route('gastos.index') }}" novalidate>
    @csrf
    <x-expense.form :categories="$categorias" />
    <x-expense.status /><div class="income-actions"><x-ui.button :href="route('gastos.index')" variant="secondary">Cancelar</x-ui.button><x-ui.button type="submit">Guardar gasto</x-ui.button></div>
</form>
@endsection
