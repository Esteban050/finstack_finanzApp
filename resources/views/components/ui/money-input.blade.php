@props(['name', 'label' => 'Monto', 'currencyName' => null, 'value' => '', 'required' => true, 'allowZero' => false, 'id' => null, 'deduction' => false, 'editing' => false])
@php($inputId = $id ?? $name)
<x-ui.field :name="$name" :id="$inputId" :label="$label" :required="$required" :help="$deduction ? 'Porcentaje entre 0 y 100 o valor en COP; máximo dos decimales.' : ($allowZero ? 'Valor mayor o igual a $ 0. Máximo dos decimales.' : 'El monto debe ser mayor que $ 0 y no superar $ 999.999.999.999,99. Máximo dos decimales.')">
    <div x-data="moneyInput" data-value="{{ old($name, $value) }}" data-allow-zero="{{ $allowZero ? 'true' : 'false' }}" data-deduction="{{ $deduction ? 'true' : 'false' }}" data-editing="{{ $editing ? 'true' : 'false' }}" @deduction-mode="setMode($event.detail)">
    @if($deduction)<div class="ui-inline ui-deduction-mode" role="group" aria-label="Unidad de {{ $label }}"><button type="button" class="ui-button ui-button--secondary" @click="$dispatch('deduction-mode', 'percent')" :aria-pressed="mode === 'percent'">%</button><button type="button" class="ui-button ui-button--secondary" @click="$dispatch('deduction-mode', 'money')" :aria-pressed="mode === 'money'">$</button></div>@endif
    <div class="ui-money">
        <input id="{{ $inputId }}" type="text" inputmode="decimal" x-model="display" @input="validate" @blur="validate" @required($required) aria-describedby="{{ $inputId }}-help {{ $inputId }}-error {{ $inputId }}-unit" :aria-invalid="invalid" autocomplete="off" {{ $attributes }}>
        @if($deduction)<span id="{{ $inputId }}-unit" class="ui-money-unit" x-text="mode === 'percent' ? '%' : 'COP'">COP</span>@else<span id="{{ $inputId }}-unit" class="ui-money-unit">COP</span>@endif
        <input type="hidden" name="{{ $name }}" :value="canonical" @disabled($attributes->has('disabled'))>
        @if($currencyName)<input type="hidden" name="{{ $currencyName }}" value="COP" @disabled($attributes->has('disabled'))>@endif
    </div>
    @if($deduction)<output class="ui-help" aria-live="polite" x-text="preview"></output>@endif
    </div>
</x-ui.field>
