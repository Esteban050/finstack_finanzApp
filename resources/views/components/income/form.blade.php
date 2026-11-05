@props(['sources', 'classifications', 'record' => [], 'prefix' => 'income', 'recurrent' => false])
<div class="income-editor" data-income-editor data-recurrent="{{ $recurrent ? 'true' : 'false' }}">
    <div class="income-form-main">
        <x-ui.card title="Origen del ingreso">
            @if(count($sources) === 0)<x-ui.empty-state illustration="empty_ingresos" title="Crea una fuente de ingreso para continuar" />@endif
            <x-ui.field name="fuente_id" :id="$prefix.'-source'" label="Fuente de ingreso" :required="true">
                <select id="{{ $prefix }}-source" name="fuente_id" data-income-source required aria-describedby="{{ $prefix }}-source-error">
                    <option value="">Selecciona una fuente activa</option>
                    @foreach($sources as $source)@if($source->activa)<option value="{{ $source->id }}" data-source-classification="{{ $source->clasificacion_tributaria }}" @selected(old('fuente_id', $record['fuente_id'] ?? '') === $source->id)>{{ $source->nombre }}</option>@endif @endforeach
                </select>
            </x-ui.field>
            <x-ui.button :href="route('ingresos.fuentes.index')" variant="secondary"><x-icon name="ui-mas" :size="18" />Nueva fuente</x-ui.button>
            @if(!$recurrent)
                <x-ui.field name="fecha_recepcion" :id="$prefix.'-date'" label="Fecha de recepción" :required="true" help="No puede ser posterior a hoy. Para una fecha futura, crea un ingreso recurrente.">
                    <input id="{{ $prefix }}-date" type="date" name="fecha_recepcion" data-income-date value="{{ old('fecha_recepcion', $record['fecha_recepcion'] ?? '') }}" required aria-describedby="{{ $prefix }}-date-help {{ $prefix }}-date-error">
                </x-ui.field>
            @endif
        </x-ui.card>
        <x-ui.card title="Valores del ingreso">
            <div class="income-two-fields">
                <x-ui.field name="moneda" :id="$prefix.'-currency'" label="Moneda" :required="true">
                    <div class="ui-inline" role="group" aria-label="Moneda del ingreso">
                        <label class="ui-check"><input type="radio" name="moneda" value="COP" data-currency-radio @checked(old('moneda', $record['moneda'] ?? 'COP') === 'COP')> COP (Pesos)</label>
                        <label class="ui-check"><input type="radio" name="moneda" value="USD" data-currency-radio @checked(old('moneda', $record['moneda'] ?? '') === 'USD')> USD (Dólares)</label>
                    </div>
                </x-ui.field>
                <div data-exchange-rate-field @if(old('moneda', $record['moneda'] ?? 'COP') !== 'USD') hidden @endif>
                    <x-ui.field name="tasa_cambio_usada" :id="$prefix.'-rate'" label="Tasa de cambio (TRM)" help="Opcional. Si se deja vacía, se aplicará automáticamente la TRM oficial de la fecha de recepción.">
                        <input id="{{ $prefix }}-rate" type="text" inputmode="decimal" name="tasa_cambio_usada" data-decimal-input data-precision="4" data-min="1" placeholder="Ej: 4150.00" value="{{ old('tasa_cambio_usada', $record['tasa_cambio_usada'] ?? '') }}">
                    </x-ui.field>
                </div>
            </div>
            <div data-trm-suggestion class="ui-notice" @if(old('moneda', $record['moneda'] ?? 'COP') !== 'USD') hidden @endif>
                <p class="ui-help" data-trm-hint>TRM aplicable: Si no indicas una tasa personalizada, se usará la TRM oficial de la fecha consultada automáticamente.</p>
            </div>
            <x-ui.money-input name="monto_original" :id="$prefix.'-gross'" label="Monto bruto" :value="(string) ($record['monto_original'] ?? '')" :currency="old('moneda', $record['moneda'] ?? 'COP')" />
            <div class="income-two-fields">
                <x-ui.money-input name="retencion_fuente" :id="$prefix.'-retention'" label="Retención en la fuente" :value="(string) ($record['retencion_fuente'] ?? '0')" :allow-zero="true" :required="false" :deduction="true" :editing="count($record) > 0" :currency="old('moneda', $record['moneda'] ?? 'COP')" />
                <x-ui.money-input name="aportes_descontados" :id="$prefix.'-contributions'" label="Aportes obligatorios descontados" :value="(string) ($record['aportes_descontados'] ?? '0')" :allow-zero="true" :required="false" :deduction="true" :editing="count($record) > 0" :currency="old('moneda', $record['moneda'] ?? 'COP')" />
            </div>
        </x-ui.card>
        <x-ui.card title="Detalle y clasificación">
            <x-income.classification :options="$classifications" :value="$record['clasificacion_tributaria'] ?? ''" :id="$prefix.'-classification'" />
            <x-ui.field name="descripcion" :id="$prefix.'-description'" label="Descripción" help="Opcional. Máximo 255 caracteres."><textarea id="{{ $prefix }}-description" name="descripcion" maxlength="255" rows="3" aria-describedby="{{ $prefix }}-description-help {{ $prefix }}-description-error">{{ old('descripcion', $record['descripcion'] ?? '') }}</textarea></x-ui.field>
        </x-ui.card>
        {{ $slot }}
    </div>
    <aside class="income-summary" aria-label="Resumen del ingreso">
        <x-ui.card title="Resumen del ingreso">
            <dl><div><dt>Monto bruto</dt><dd><output data-income-summary="gross">—</output></dd></div><div><dt>Retención en la fuente</dt><dd><output data-income-summary="retention">—</output></dd></div><div><dt>Aportes descontados</dt><dd><output data-income-summary="contributions">—</output></dd></div></dl>
            <div class="income-net"><span>Monto neto</span><output data-income-summary="net" aria-live="polite">—</output><span class="ui-help" data-currency-summary-unit>COP · Solo lectura</span></div>
            <p class="ui-help">El monto neto es lo que recibes después de restar la retención y los aportes.</p>
        </x-ui.card>
    </aside>
</div>
