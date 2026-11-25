@props(['category', 'categories', 'allowedIcons', 'allowedColors'])
@php
    $owned = !$category->es_predeterminada && $category->usuario_id !== null;
    $linked = $owned && \App\Services\CategoriaGastoService::tieneGastos($category);
    $hasChildren = $category->categoria_padre_id === null && $category->subcategorias->isNotEmpty();
    $destinations = $category->categoria_padre_id === null ? $categories : collect();
@endphp
<x-ui.card class="catalog-card">
    <div class="catalog-card__head">
        <span class="catalog-card__icon" data-source-visual data-nombre="{{ $category->nombre }}" data-icono="{{ $category->icono }}" data-color_hex="{{ $category->color_hex }}" aria-hidden="true"></span>
        <div>
            <div class="catalog-card__title">
                <h2>{{ $category->nombre }}</h2>
                <div class="catalog-card__badges">
                    @if($category->es_predeterminada)<x-ui.badge>Predeterminada</x-ui.badge>@endif
                    <x-ui.badge class="catalog-card__state" :tone="$category->activa ? 'success' : 'neutral'">{{ $category->activa ? 'Activa' : 'Inactiva' }}</x-ui.badge>
                </div>
            </div>
            @if($category->categoria_padre_id)
                <p class="catalog-card__meta">{{ $category->naturaleza_sugerida === 'FIJO' ? 'Fijo' : 'Variable' }} · {{ $category->necesidad_sugerida === 'ESENCIAL' ? 'Esencial' : 'Discrecional' }}</p>
            @endif
            @if($linked)
                <p class="ui-help catalog-card__note">Tiene gastos asociados. Puedes inactivarla{{ $category->categoria_padre_id === null ? ' o fusionarla' : '' }}; no se puede eliminar.</p>
            @elseif($owned && $hasChildren)
                <p class="ui-help catalog-card__note">Contiene subcategorías. Puedes inactivarla o fusionarla.</p>
            @endif
        </div>
    </div>
    @if($owned)
        <div class="catalog-card__actions">
            <form method="POST" action="{{ route('gastos.categorias.toggle', $category->id) }}" data-expense-action>
                @csrf @method('PATCH')
                <x-ui.button class="catalog-card__action" type="submit" variant="ghost">{{ $category->activa ? 'Inactivar' : 'Activar' }}</x-ui.button>
                <x-expense.status />
            </form>
            @if($category->categoria_padre_id === null)
                <x-ui.button class="catalog-card__action" variant="ghost" :data-open-expense-dialog="'merge-category-'.$category->id">Fusionar</x-ui.button>
            @endif
            @if(!$linked && !$hasChildren)
                <form method="POST" action="{{ route('gastos.categorias.destroy', $category->id) }}" data-expense-action data-confirm-delete data-confirm-description="¿Deseas eliminar esta categoría?">
                    @csrf @method('DELETE')
                    <x-ui.button class="catalog-card__action catalog-card__delete" type="submit" variant="danger">Eliminar</x-ui.button>
                    <x-expense.status />
                </form>
            @endif
            <x-ui.button class="catalog-card__action" variant="secondary" :data-open-expense-dialog="'edit-category-'.$category->id">Editar</x-ui.button>
        </div>
        <dialog id="edit-category-{{ $category->id }}" class="expense-category-dialog" aria-labelledby="edit-title-{{ $category->id }}"><header><h2 id="edit-title-{{ $category->id }}">Editar {{ $category->nombre }}</h2><x-ui.button variant="ghost" data-close-expense-dialog aria-label="Cerrar edición"><x-icon name="ui-cerrar" /></x-ui.button></header><x-expense.category-form :categories="$categories" :allowed-icons="$allowedIcons" :allowed-colors="$allowedColors" :record="$category" :prefix="'edit-'.$category->id" /></dialog>
        @if($category->categoria_padre_id === null)
        <dialog id="merge-category-{{ $category->id }}" class="expense-category-dialog" aria-labelledby="merge-title-{{ $category->id }}"><header><h2 id="merge-title-{{ $category->id }}">Fusionar {{ $category->nombre }}</h2><x-ui.button variant="ghost" data-close-expense-dialog aria-label="Cerrar fusión"><x-icon name="ui-cerrar" /></x-ui.button></header><p>Esta categoría pasará a ser una subcategoría del destino y todos sus gastos pasarán a la categoría destino.</p>
            <form method="POST" action="{{ route('gastos.categorias.fusionar', $category->id) }}" data-expense-merge data-confirm-delete data-confirm-title="Confirmar fusión" data-confirm-label="Fusionar" data-confirm-description="Esta categoría pasará a ser una subcategoría del destino y sus gastos quedarán agrupados en la categoría destino. Esta acción no se puede deshacer.">@csrf
                <x-ui.field name="destino_id" :id="'destination-'.$category->id" label="Destino de la fusión" :required="true"><select name="destino_id" id="destination-{{ $category->id }}" required aria-describedby="destination-{{ $category->id }}-error"><option value="">Selecciona el destino</option>@foreach($destinations as $destination)@if($destination->id !== $category->id && $destination->activa)<option value="{{ $destination->id }}">{{ $destination->nombre }}</option>@endif @endforeach</select></x-ui.field>
                <x-expense.status /><x-ui.button type="submit" variant="danger">Fusionar</x-ui.button>
            </form>
        </dialog>
        @endif
    @endif
</x-ui.card>
