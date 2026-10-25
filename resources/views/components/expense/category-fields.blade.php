@props(['categories', 'prefix', 'record' => [], 'required' => true, 'activeOnly' => true, 'placeholder' => null])
<div data-expense-categories data-catalog="{{ json_encode($categories) }}" data-active-only="{{ $activeOnly ? 'true' : 'false' }}" class="income-two-fields">
    <x-ui.field name="categoria_id" :id="$prefix.'-category'" label="Categoría" :required="$required">
        <div class="income-type-control"><span data-expense-category-icon aria-hidden="true" hidden></span>
            <select id="{{ $prefix }}-category" name="categoria_id" data-expense-category @required($required) aria-describedby="{{ $prefix }}-category-error">
                <option value="">{{ $placeholder ?? ($required ? 'Selecciona la categoría' : 'Todas las categorías') }}</option>
                @foreach($categories as $category)@if(!$activeOnly || $category->activa)<option value="{{ $category->id }}" @selected((string) old('categoria_id', $record['categoria_id'] ?? '') === (string) $category->id)>{{ $category->nombre }}</option>@endif @endforeach
            </select>
        </div>
    </x-ui.field>
    <x-ui.field name="subcategoria_id" :id="$prefix.'-subcategory'" label="Subcategoría" :required="$required">
        <select id="{{ $prefix }}-subcategory" name="subcategoria_id" data-expense-subcategory data-selected="{{ old('subcategoria_id', $record['subcategoria_id'] ?? '') }}" @required($required) aria-describedby="{{ $prefix }}-subcategory-help {{ $prefix }}-subcategory-error"><option value="">Selecciona primero una categoría</option></select>
        <p id="{{ $prefix }}-subcategory-help" data-subcategory-help class="ui-help" role="status"></p>
    </x-ui.field>
</div>
