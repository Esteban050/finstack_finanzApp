@props(['categories', 'prefix' => 'expense', 'record' => [], 'recurrent' => false])
<div class="income-editor expense-editor" data-expense-editor>
    <div class="income-form-main">
        <x-ui.card title="Identificación del gasto">
            <input type="hidden" name="moneda" value="COP">
            <x-ui.money-input name="monto_original" :id="$prefix.'-amount'" label="Monto" :value="(string) ($record['monto_original'] ?? '')" />
            @if(!$recurrent)<x-ui.field name="fecha" :id="$prefix.'-date'" label="Fecha" :required="true" help="No puede ser posterior a hoy."><input type="date" name="fecha" id="{{ $prefix }}-date" data-expense-date value="{{ old('fecha', $record['fecha'] ?? '') }}" required aria-describedby="{{ $prefix }}-date-help {{ $prefix }}-date-error"></x-ui.field>@endif
            <x-expense.select name="medio_pago" label="Medio de pago" :prefix="$prefix" :options="['EFECTIVO', 'TARJETA_DEBITO', 'TARJETA_CREDITO', 'TRANSFERENCIA', 'BILLETERA_DIGITAL', 'OTRO']" :value="$record['medio_pago'] ?? ''" :required="true" />
            <x-ui.field name="comercio_beneficiario" :id="$prefix.'-merchant'" label="Comercio o beneficiario" help="Opcional. Máximo 80 caracteres."><input id="{{ $prefix }}-merchant" name="comercio_beneficiario" value="{{ old('comercio_beneficiario', $record['comercio_beneficiario'] ?? '') }}" maxlength="80" aria-describedby="{{ $prefix }}-merchant-help {{ $prefix }}-merchant-error"></x-ui.field>
        </x-ui.card>
        <x-ui.card title="Clasificación del gasto">
            <x-expense.category-fields :categories="$categories" :prefix="$prefix" :record="$record" />
            <div class="income-two-fields">
                <x-expense.select name="naturaleza" label="Naturaleza" :prefix="$prefix" :options="['FIJO', 'VARIABLE']" :value="$record['naturaleza'] ?? ''" :required="true" />
                <x-expense.select name="necesidad" label="Necesidad" :prefix="$prefix" :options="['ESENCIAL', 'DISCRECIONAL']" :value="$record['necesidad'] ?? ''" :required="true" />
            </div>
            <div class="ui-inline expense-tax-heading"><span>Relevancia tributaria</span><x-ui.help-tip label="Ayuda sobre relevancia tributaria" text="Marca Potencialmente deducible cuando el gasto corresponda a uno de los subtipos disponibles. Esta clasificación no garantiza una deducción." /></div>
            <x-expense.select name="relevancia_tributaria" label="Relevancia tributaria" :prefix="$prefix" :options="['NO_DEDUCIBLE', 'POTENCIALMENTE_DEDUCIBLE']" :value="$record['relevancia_tributaria'] ?? 'NO_DEDUCIBLE'" :required="true" />
            <div data-deduction-panel><x-expense.select name="subtipo_deduccion" label="Subtipo de deducción" :prefix="$prefix" :options="['INTERESES_VIVIENDA', 'MEDICINA_PREPAGADA', 'DEPENDIENTES', 'AFC_VOLUNTARIOS', 'GMF', 'OTROS']" :value="$record['subtipo_deduccion'] ?? ''" :required="true" /></div>
        </x-ui.card>
        <x-ui.card title="Descripción y etiquetas">
            <x-ui.field name="descripcion" :id="$prefix.'-description'" label="Descripción" help="Opcional. Máximo 255 caracteres."><textarea id="{{ $prefix }}-description" name="descripcion" maxlength="255" rows="3" aria-describedby="{{ $prefix }}-description-help {{ $prefix }}-description-error">{{ old('descripcion', $record['descripcion'] ?? '') }}</textarea></x-ui.field>
            <x-ui.tag-input :id="$prefix.'-tags'" :value="$record['etiquetas'] ?? []" :data-tags-url="route('gastos.etiquetas')" />
        </x-ui.card>
        {{ $slot }}
    </div>
    <aside class="income-summary" aria-label="Resumen de clasificación"><x-ui.card title="Resumen de clasificación">
        <div class="income-net"><span>Monto del gasto</span><output data-expense-amount aria-live="polite">—</output><span class="ui-help">COP</span></div>
        <dl>@foreach(['categoria_id' => 'Categoría', 'subcategoria_id' => 'Subcategoría', 'naturaleza' => 'Naturaleza', 'necesidad' => 'Necesidad', 'medio_pago' => 'Medio de pago', 'relevancia_tributaria' => 'Relevancia tributaria'] as $key => $label)<div><dt>{{ $label }}</dt><dd data-expense-summary="{{ $key }}">—</dd></div>@endforeach</dl>
    </x-ui.card></aside>
</div>
