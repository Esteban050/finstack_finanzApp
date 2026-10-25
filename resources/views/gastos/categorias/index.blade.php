@extends('layouts.app')
@section('title', 'Categorías de gasto')
@section('content')
<header class="income-page-header"><div><h1>Categorías de gasto</h1><p>Organiza tus categorías y subcategorías. Las predeterminadas son de solo lectura.</p></div><x-ui.button data-open-expense-dialog="create-expense-category">Nueva categoría o subcategoría</x-ui.button></header>
<div data-category-catalog="{{ json_encode($categorias) }}"></div>
<p>{{ $categorias->where('es_predeterminada', false)->count() }} de 50 categorías propias · Hasta 30 subcategorías propias por categoría</p>
<dialog id="create-expense-category" class="expense-category-dialog" aria-labelledby="create-category-title"><header><h2 id="create-category-title">Nueva categoría o subcategoría</h2><x-ui.button variant="ghost" data-close-expense-dialog aria-label="Cerrar creación"><x-icon name="ui-cerrar" /></x-ui.button></header><x-expense.category-form :categories="$categorias" :allowed-icons="$iconos" :allowed-colors="$paleta" /></dialog>
<section class="income-source-list" aria-label="Catálogo de categorías">
@forelse($categorias as $category)
    <div><x-expense.category-node :category="$category" :categories="$categorias" :allowed-icons="$iconos" :allowed-colors="$paleta" />
    @if($category->subcategorias->isNotEmpty())<div class="expense-category-children">@foreach($category->subcategorias as $child)<x-expense.category-node :category="$child" :categories="$categorias" :allowed-icons="$iconos" :allowed-colors="$paleta" />@endforeach</div>@endif</div>
@empty<x-ui.empty-state illustration="empty_gastos" title="No hay categorías de gasto" />@endforelse
</section>
@endsection
