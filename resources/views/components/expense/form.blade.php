@props(['categories', 'prefix' => 'expense', 'record' => [], 'recurrent' => false, 'cards' => []])
@php($eraConsumoSinVinculo = ($record['medio_pago'] ?? null) === 'TARJETA_CREDITO' && empty($record['credito_id']))
<div class="income-editor expense-editor" data-expense-editor>
    <div class="income-form-main">
        <x-ui.card title="Identificación del gasto">
            <div class="income-two-fields">
                <x-ui.field name="moneda" :id="$prefix.'-currency'" label="Moneda" :required="true">
                    <div class="ui-inline" role="group" aria-label="Moneda del gasto">
                        <label class="ui-check"><input type="radio" name="moneda" value="COP" data-currency-radio @checked(old('moneda', $record['moneda'] ?? 'COP') === 'COP')> COP (Pesos)</label>
                        <label class="ui-check"><input type="radio" name="moneda" value="USD" data-currency-radio @checked(old('moneda', $record['moneda'] ?? '') === 'USD')> USD (Dólares)</label>
                    </div>
                </x-ui.field>
                <div data-exchange-rate-field @if(old('moneda', $record['moneda'] ?? 'COP') !== 'USD') hidden @endif>
                    <x-ui.field name="tasa_cambio_usada" :id="$prefix.'-rate'" label="Tasa de cambio (TRM)" help="Si hay una TRM disponible para la fecha, puedes dejar la tasa manual vacía.">
                        <input id="{{ $prefix }}-rate" type="text" inputmode="decimal" data-value-input data-precision="4" min="0.0001" data-positive="true" name="tasa_cambio_usada" placeholder="Ej: 4.150,00" value="{{ old('tasa_cambio_usada', $record['tasa_cambio_usada'] ?? '') }}">
                    </x-ui.field>
                </div>
            </div>
            <div data-trm-suggestion class="ui-notice" @if(old('moneda', $record['moneda'] ?? 'COP') !== 'USD') hidden @endif>
                <p class="ui-help" data-trm-hint role="status">Selecciona la fecha para consultar la TRM.</p>
            </div>
            <x-ui.money-input name="monto_original" :id="$prefix.'-amount'" label="Monto" :value="(string) ($record['monto_original'] ?? '')" :currency="old('moneda', $record['moneda'] ?? 'COP')" />
            @if(!$recurrent)<x-ui.field name="fecha" :id="$prefix.'-date'" label="Fecha" :required="true" help="No puede ser posterior a hoy."><input type="date" name="fecha" id="{{ $prefix }}-date" data-expense-date value="{{ old('fecha', $record['fecha'] ?? '') }}" required aria-describedby="{{ $prefix }}-date-help {{ $prefix }}-date-error"></x-ui.field>@endif
            <x-expense.select name="medio_pago" label="Medio de pago" :prefix="$prefix" :options="['EFECTIVO', 'TARJETA_DEBITO', 'TARJETA_CREDITO', 'TRANSFERENCIA', 'BILLETERA_DIGITAL', 'OTRO']" :value="$record['medio_pago'] ?? ''" :required="true" />
            @unless($recurrent)
                <div data-expense-card @if(old('medio_pago', $record['medio_pago'] ?? '') !== 'TARJETA_CREDITO') hidden @endif>
                    @if(count($cards))
                        <x-ui.field name="credito_id" :id="$prefix.'-card'" label="Tarjeta de crédito" :required="!$eraConsumoSinVinculo">
                            <select id="{{ $prefix }}-card" name="credito_id" @unless($eraConsumoSinVinculo) data-card-required @endunless>
                                <option value="">{{ $eraConsumoSinVinculo ? 'Sin tarjeta vinculada' : 'Selecciona la tarjeta' }}</option>
                                @foreach($cards as $card)
                                    <option value="{{ $card->id }}" @selected(old('credito_id', $record['credito_id'] ?? (!$eraConsumoSinVinculo && count($cards) === 1 ? $card->id : '')) === $card->id)>{{ $card->banco }} · {{ $card->proposito }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    @else
                        <p class="ui-help">No tienes tarjetas registradas. Puedes guardar el gasto sin vincularlo a una tarjeta.</p>
                    @endif
                </div>
            @endunless
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
        <div class="income-net"><span>Monto del gasto</span><output data-expense-amount aria-live="polite">—</output><span class="ui-help" data-currency-summary-unit>COP</span></div>
        <dl>@foreach(['categoria_id' => 'Categoría', 'subcategoria_id' => 'Subcategoría', 'naturaleza' => 'Naturaleza', 'necesidad' => 'Necesidad', 'medio_pago' => 'Medio de pago', 'relevancia_tributaria' => 'Relevancia tributaria'] as $key => $label)<div><dt>{{ $label }}</dt><dd data-expense-summary="{{ $key }}">—</dd></div>@endforeach</dl>
    </x-ui.card></aside>
</div>
