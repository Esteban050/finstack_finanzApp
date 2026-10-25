@props(['name', 'label' => 'Monto', 'currencyName' => 'moneda', 'currency' => 'COP', 'value' => '', 'required' => true, 'original' => null, 'trm' => null, 'converted' => null])
<x-ui.field :name="$name" :label="$label" :required="$required" help="RNG-09: mayor que 0 y hasta $ 999.999.999.999,99. Máximo dos decimales.">
    <div class="ui-money" x-data="moneyInput" data-value="{{ old($name, $value) }}">
        <label class="sr-only" for="{{ $name }}-currency">Moneda</label>
        <select id="{{ $name }}-currency" name="{{ $currencyName }}" aria-label="Moneda">
            @foreach(['COP', 'USD'] as $code)<option value="{{ $code }}" @selected(old($currencyName, $currency) === $code)>{{ $code }}</option>@endforeach
        </select>
        <input id="{{ $name }}" type="text" inputmode="decimal" x-model="display" @input="validate" @blur="validate" @required($required) aria-describedby="{{ $name }}-help {{ $name }}-error" :aria-invalid="invalid" autocomplete="off" {{ $attributes }}>
        <input type="hidden" name="{{ $name }}" :value="canonical">
    </div>
    @if($currency === 'USD' && $original !== null && $trm !== null && $converted !== null)
        <dl class="ui-conversion"><dt>Monto original</dt><dd data-money="{{ $original }}" data-currency="USD">{{ $original }}</dd><dt>TRM usada</dt><dd data-trm="{{ $trm }}">{{ $trm }}</dd><dt>Monto convertido a COP</dt><dd data-money="{{ $converted }}">{{ $converted }}</dd></dl>
    @endif
</x-ui.field>
