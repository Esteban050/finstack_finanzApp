@props(['name', 'label' => 'Monto', 'currencyName' => null, 'value' => '', 'required' => true, 'allowZero' => false, 'id' => null])
@php($inputId = $id ?? $name)
<x-ui.field :name="$name" :id="$inputId" :label="$label" :required="$required" :help="$allowZero ? 'Valor mayor o igual a $ 0. Máximo dos decimales.' : 'El monto debe ser mayor que $ 0 y no superar $ 999.999.999.999,99. Máximo dos decimales.'">
    <div class="ui-money" x-data="moneyInput" data-value="{{ old($name, $value) }}" data-allow-zero="{{ $allowZero ? 'true' : 'false' }}">
        <input id="{{ $inputId }}" type="text" inputmode="decimal" x-model="display" @input="validate" @blur="validate" @required($required) aria-describedby="{{ $inputId }}-help {{ $inputId }}-error {{ $inputId }}-unit" :aria-invalid="invalid" autocomplete="off" {{ $attributes }}>
        <span id="{{ $inputId }}-unit" class="ui-money-unit">COP</span>
        <input type="hidden" name="{{ $name }}" :value="canonical" @disabled($attributes->has('disabled'))>
        @if($currencyName)<input type="hidden" name="{{ $currencyName }}" value="COP" @disabled($attributes->has('disabled'))>@endif
    </div>
</x-ui.field>
