@props(['categories', 'prefix' => 'expense', 'record' => [], 'recurrent' => false, 'cards' => [], 'cuentas' => []])
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
            @if(!$recurrent && count($cuentas) > 0)
                <div data-expense-account @if(!in_array(old('medio_pago', $record['medio_pago'] ?? ''), \App\Models\Gasto::MEDIOS_CON_CUENTA, true)) hidden @endif>
                    <x-ui.field name="cuenta_id" :id="$prefix.'-account'" label="Cuenta de la que sale el dinero" help="Opcional. Resta el gasto de lo disponible en la cuenta; si no es exenta, también se registra el GMF (4x1000).">
                        <select id="{{ $prefix }}-account" name="cuenta_id" data-expense-account-select aria-describedby="{{ $prefix }}-account-help {{ $prefix }}-account-error">
                            <option value="">Sin cuenta</option>
                            @foreach($cuentas as $cuenta)<option value="{{ $cuenta->id }}" @selected(old('cuenta_id', $record['cuenta_id'] ?? '') === $cuenta->id)>{{ $cuenta->nombre_banco }}@if($cuenta->alias) · {{ $cuenta->alias }}@endif</option>@endforeach
                        </select>
                    </x-ui.field>
                </div>
            @endif
            @unless($recurrent)
                @php($creditoVinculado = ($record['genera_credito'] ?? false) ? ($record['credito'] ?? null) : null)
                <div data-expense-card @if(old('medio_pago', $record['medio_pago'] ?? '') !== 'TARJETA_CREDITO') hidden @endif>
                    @if($creditoVinculado)
                        <p class="ui-help">Esta compra ya generó su crédito. Para ver el plan de cuotas o modificarlo, ve a <a href="{{ route('creditos.show', $creditoVinculado['id']) }}">{{ $creditoVinculado['banco'] }} · Tarjeta de crédito</a>.</p>
                    @else
                        @php($seleccion = old('credito_id', $record['credito_id'] ?? ($eraConsumoSinVinculo ? '' : (count($cards) === 1 ? collect($cards)->first()->id : (count($cards) === 0 ? 'NUEVA' : '')))))
                        <x-ui.field name="credito_id" :id="$prefix.'-card'" label="Tarjeta de crédito" :required="!$eraConsumoSinVinculo" help="Elige una tarjeta registrada para sumarle este consumo, o registra la tarjeta con esta compra.">
                            <select id="{{ $prefix }}-card" name="credito_id" data-card-select @if(!$eraConsumoSinVinculo) data-card-required @endif>
                                @if($eraConsumoSinVinculo)<option value="">Sin tarjeta vinculada</option>@elseif(count($cards) > 1)<option value="">Selecciona la tarjeta</option>@endif
                                @foreach($cards as $card)
                                    <option value="{{ $card->id }}" @selected($seleccion === $card->id)>{{ $card->banco }} · {{ $card->proposito }}</option>
                                @endforeach
                                <option value="NUEVA" @selected($seleccion === 'NUEVA')>Registrar tarjeta nueva</option>
                            </select>
                        </x-ui.field>
                        <fieldset data-credit-card-fields hidden>
                            <legend>Datos de la tarjeta de crédito</legend>
                            <p class="ui-help">Al guardar, la tarjeta queda registrada en Créditos con esta compra y su plan de cuotas. Los próximos gastos con esta tarjeta se suman a ella.</p>
                            <div class="income-two-fields">
                                <x-ui.field name="banco_tarjeta" :id="$prefix.'-card-bank'" label="Banco" :required="true"><input id="{{ $prefix }}-card-bank" name="banco_tarjeta" maxlength="80" value="{{ old('banco_tarjeta') }}" disabled></x-ui.field>
                                <x-ui.field name="numero_cuotas" :id="$prefix.'-card-installments'" label="Número de cuotas" :required="true" help="Entre 1 y 72."><input id="{{ $prefix }}-card-installments" type="number" name="numero_cuotas" min="1" max="72" step="1" inputmode="numeric" value="{{ old('numero_cuotas') }}" disabled></x-ui.field>
                            </div>
                            <div class="income-two-fields">
                                <x-ui.field name="fecha_corte" :id="$prefix.'-card-cutoff'" label="Fecha de corte" :required="true"><input id="{{ $prefix }}-card-cutoff" type="date" name="fecha_corte" value="{{ old('fecha_corte') }}" disabled></x-ui.field>
                                <x-ui.field name="fecha_pago" :id="$prefix.'-card-due'" label="Fecha de pago" :required="true" help="Vencimiento de la primera cuota; posterior a la fecha del gasto."><input id="{{ $prefix }}-card-due" type="date" name="fecha_pago" value="{{ old('fecha_pago') }}" disabled></x-ui.field>
                            </div>
                            <div class="income-two-fields">
                                <x-ui.field name="tasa_interes_anual" :id="$prefix.'-card-rate'" label="Interés anual (% E.A.)" :required="true" help="Tasa efectiva anual, entre 0 y 100."><input id="{{ $prefix }}-card-rate" type="number" name="tasa_interes_anual" data-value-input data-precision="6" min="0" max="100" step="any" inputmode="decimal" value="{{ old('tasa_interes_anual') }}" disabled></x-ui.field>
                                <x-ui.field name="cupo_tarjeta" :id="$prefix.'-card-limit'" label="Cupo de la tarjeta (COP)" :required="true" help="Cupo total aprobado, en pesos enteros. No se pueden registrar consumos que lo superen."><input id="{{ $prefix }}-card-limit" type="text" name="cupo_tarjeta" data-value-input data-precision="0" min="1" data-positive="true" inputmode="numeric" value="{{ old('cupo_tarjeta') }}" disabled></x-ui.field>
                            </div>
                        </fieldset>
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
