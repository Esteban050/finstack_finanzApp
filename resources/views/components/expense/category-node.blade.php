@props(['category', 'categories', 'allowedIcons', 'allowedColors'])
@php
    $owned = !$category->es_predeterminada && $category->usuario_id !== null;
    $linked = $owned && \App\Services\CategoriaGastoService::tieneGastos($category);
    $hasChildren = $category->categoria_padre_id === null && $category->subcategorias->isNotEmpty();
    $destinations = $category->categoria_padre_id === null ? $categories : $categories->flatMap(fn($parent) => $parent->subcategorias);
@endphp
<div class="expense-category-node"><x-ui.card>
    <div class="income-source-head"><div><h2 class="ui-inline"><span data-source-visual data-nombre="{{ $category->nombre }}" data-icono="{{ $category->icono }}" data-color_hex="{{ $category->color_hex }}" aria-hidden="true"></span>{{ $category->nombre }}</h2><p>@if($category->categoria_padre_id){{ $category->naturaleza_sugerida === 'FIJO' ? 'Fijo' : 'Variable' }} · {{ $category->necesidad_sugerida === 'ESENCIAL' ? 'Esencial' : 'Discrecional' }}@endif</p></div><div class="ui-inline">@if($category->es_predeterminada)<x-ui.badge>Predeterminada</x-ui.badge>@endif<x-ui.badge :tone="$category->activa ? 'success' : 'neutral'">{{ $category->activa ? 'Activa' : 'Inactiva' }}</x-ui.badge></div></div>
    @if($owned)
        @if($linked)<p class="ui-help">Tiene gastos asociados. Puedes inactivarla o fusionarla; no se puede eliminar.</p>@elseif($hasChildren)<p class="ui-help">Contiene subcategorías. Puedes inactivarla o fusionarla.</p>@endif
        <div class="ui-inline">
            <x-ui.button variant="secondary" :data-open-expense-dialog="'edit-category-'.$category->id">Editar</x-ui.button>
            <form method="POST" action="{{ route('gastos.categorias.toggle', $category->id) }}" data-expense-action>@csrf @method('PATCH')<x-ui.button type="submit" variant="secondary">{{ $category->activa ? 'Inactivar' : 'Activar' }}</x-ui.button><x-expense.status /></form>
            <x-ui.button variant="secondary" :data-open-expense-dialog="'merge-category-'.$category->id">Fusionar</x-ui.button>
            @if(!$linked && !$hasChildren)<form method="POST" action="{{ route('gastos.categorias.destroy', $category->id) }}" data-expense-action data-confirm-delete data-confirm-description="¿Deseas eliminar esta categoría?">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger">Eliminar</x-ui.button><x-expense.status /></form>@endif
        </div>
        <dialog id="edit-category-{{ $category->id }}" class="expense-category-dialog" aria-labelledby="edit-title-{{ $category->id }}"><header><h2 id="edit-title-{{ $category->id }}">Editar {{ $category->nombre }}</h2><x-ui.button variant="ghost" data-close-expense-dialog aria-label="Cerrar edición"><x-icon name="ui-cerrar" /></x-ui.button></header><x-expense.category-form :categories="$categories" :allowed-icons="$allowedIcons" :allowed-colors="$allowedColors" :record="$category" :prefix="'edit-'.$category->id" /></dialog>
        <dialog id="merge-category-{{ $category->id }}" class="expense-category-dialog" aria-labelledby="merge-title-{{ $category->id }}"><header><h2 id="merge-title-{{ $category->id }}">Fusionar {{ $category->nombre }}</h2><x-ui.button variant="ghost" data-close-expense-dialog aria-label="Cerrar fusión"><x-icon name="ui-cerrar" /></x-ui.button></header><p>Los gastos pasarán al destino que elijas y la categoría de origen desaparecerá.</p>
            <form method="POST" action="{{ route('gastos.categorias.fusionar', $category->id) }}" data-expense-merge data-confirm-delete data-confirm-title="Confirmar fusión" data-confirm-label="Fusionar" data-confirm-description="Los gastos se reclasificarán en el destino seleccionado y la categoría de origen desaparecerá. Esta acción no se puede deshacer.">@csrf
                <x-ui.field name="destino_id" :id="'destination-'.$category->id" label="Destino de la fusión" :required="true"><select name="destino_id" id="destination-{{ $category->id }}" required aria-describedby="destination-{{ $category->id }}-error"><option value="">Selecciona el destino</option>@foreach($destinations as $destination)@if($destination->id !== $category->id && $destination->activa)<option value="{{ $destination->id }}">{{ $destination->nombre }}</option>@endif @endforeach</select></x-ui.field>
                <x-expense.status /><x-ui.button type="submit" variant="danger">Fusionar</x-ui.button>
            </form>
        </dialog>
    @endif
</x-ui.card></div>
