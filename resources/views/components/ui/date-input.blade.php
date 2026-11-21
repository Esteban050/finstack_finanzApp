@props(['name', 'label' => 'Fecha', 'required' => true, 'value' => '', 'allowFuture' => false, 'id' => null])
@php
    $inputId = $id ?? $attributes->get('id') ?? $name;
@endphp
<x-ui.field :name="$name" :id="$inputId" :label="$label" :required="$required" help="DD/MM/AAAA">
    <input id="{{ $inputId }}" name="{{ $name }}" type="date" value="{{ old($name, $value) }}" @required($required) @if(!$allowFuture) data-date-no-future @else data-allow-future="true" @endif aria-describedby="{{ $inputId }}-help {{ $inputId }}-error" {{ $attributes->except('id') }}>
</x-ui.field>
