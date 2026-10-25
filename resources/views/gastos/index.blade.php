@extends('layouts.app')
@section('title', 'Gastos')
@section('content')
<header class="income-page-header"><div><h1>Gastos</h1><p>Clasificación y totales del conjunto filtrado, en pesos colombianos.</p></div><x-ui.button :href="route('gastos.create')"><x-icon name="ui-mas" :size="18" />Registrar gasto</x-ui.button></header>
<x-expense.filters :categories="$categorias" />
<x-expense.bulk :categories="$categorias" />
<x-expense.table :records="$gastos" :totals="$totales" :pending="$pendientes" />
@endsection
