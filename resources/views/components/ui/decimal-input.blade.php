@props(['name', 'label', 'value' => '', 'required' => false, 'min' => '0', 'max' => null, 'precision' => 2, 'id' => null, 'help' => null])
@php($inputId = $id ?? $name)
<x-ui.field :name="$name" :id="$inputId" :label="$label" :required="$required" :help="$help">
    <input id="{{ $inputId }}" name="{{ $name }}" type="text" inputmode="decimal" value="{{ old($name, str_replace('.', ',', (string) $value)) }}" data-decimal-input data-min="{{ $min }}" @if($max !== null) data-max="{{ $max }}" @endif data-precision="{{ $precision }}" @required($required) aria-describedby="{{ $inputId }}-error{{ $help ? ' '.$inputId.'-help' : '' }}" {{ $attributes }}>
</x-ui.field>
