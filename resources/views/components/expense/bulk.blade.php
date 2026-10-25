@props(['categories'])
<div class="ui-inline"><label><input type="checkbox" data-select-page> Seleccionar esta página</label><span data-selected-count aria-live="polite">0 de 100 gastos seleccionados</span></div>
<p data-selection-error class="ui-error" role="alert"></p>
<form method="POST" action="{{ route('gastos.edicion-masiva') }}" data-expense-bulk novalidate>
    @csrf @method('PATCH')
    <div data-bulk-panel hidden class="expense-bulk-panel">
        <h2>Editar gastos seleccionados</h2><p class="ui-help">Solo se aplican los cambios que indiques. Las etiquetas reemplazan las de los gastos seleccionados.</p>
        <p class="ui-help">Para cambiar la categoría, elige también su subcategoría.</p>
        <x-expense.category-fields :categories="$categories" prefix="bulk" :required="false" placeholder="Conservar categoría" />
        <div class="income-two-fields"><x-expense.select name="naturaleza" label="Naturaleza" prefix="bulk" :options="['FIJO', 'VARIABLE']" placeholder="Conservar" /><x-expense.select name="necesidad" label="Necesidad" prefix="bulk" :options="['ESENCIAL', 'DISCRECIONAL']" placeholder="Conservar" /></div>
        <label><input type="checkbox" data-change-tags> Reemplazar etiquetas</label>
        <x-ui.tag-input id="bulk-tags" :data-tags-url="route('gastos.etiquetas')" />
        <x-expense.status /><x-ui.button type="submit">Aplicar cambios a la selección</x-ui.button>
    </div>
</form>
