@props(['categories'])
<x-ui.card><details open><summary>Filtrar gastos</summary>
<form method="GET" action="{{ route('gastos.index') }}" data-expense-filters novalidate>
    <div class="expense-filter-grid">
        <x-ui.field name="fecha_desde" label="Desde"><input id="fecha_desde" type="date" name="fecha_desde" value="{{ request('fecha_desde') }}" aria-describedby="fecha_desde-error"></x-ui.field>
        <x-ui.field name="fecha_hasta" label="Hasta"><input id="fecha_hasta" type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}" aria-describedby="fecha_hasta-error"></x-ui.field>
    </div>
    <x-expense.category-fields :categories="$categories" prefix="filter" :record="request()->all()" :required="false" :active-only="false" />
    <div class="expense-filter-grid">
        <x-expense.select name="naturaleza" label="Naturaleza" prefix="filter" :options="['FIJO', 'VARIABLE']" :value="request('naturaleza')" placeholder="Todas" />
        <x-expense.select name="necesidad" label="Necesidad" prefix="filter" :options="['ESENCIAL', 'DISCRECIONAL']" :value="request('necesidad')" placeholder="Todas" />
        <x-expense.select name="medio_pago" label="Medio de pago" prefix="filter" :options="['EFECTIVO', 'TARJETA_DEBITO', 'TARJETA_CREDITO', 'TRANSFERENCIA', 'BILLETERA_DIGITAL', 'OTRO']" :value="request('medio_pago')" placeholder="Todos" />
        <x-expense.select name="relevancia_tributaria" label="Relevancia tributaria" prefix="filter" :options="['NO_DEDUCIBLE', 'POTENCIALMENTE_DEDUCIBLE']" :value="request('relevancia_tributaria')" placeholder="Todas" />
        <x-ui.field name="etiqueta" label="Etiqueta"><input id="etiqueta" name="etiqueta" value="{{ request('etiqueta') }}" maxlength="30" aria-describedby="etiqueta-error"></x-ui.field>
        <x-ui.field name="texto" label="Descripción o comercio"><input id="texto" name="texto" value="{{ request('texto') }}" maxlength="255" aria-describedby="texto-error"></x-ui.field>
        <x-ui.money-input name="monto_min" label="Monto mínimo" :value="(string) request('monto_min', '')" :required="false" :allow-zero="true" />
        <x-ui.money-input name="monto_max" label="Monto máximo" :value="(string) request('monto_max', '')" :required="false" :allow-zero="true" />
    </div>
    <div class="income-actions"><x-ui.button :href="route('gastos.index')" variant="secondary">Limpiar filtros</x-ui.button><x-ui.button type="submit">Aplicar filtros</x-ui.button></div>
</form></details></x-ui.card>
