@props(['name', 'label', 'options', 'prefix', 'value' => '', 'required' => false, 'placeholder' => 'Selecciona una opción'])
@php($labels = require resource_path('views/components/expense/labels.php'))
<x-ui.field :name="$name" :id="$prefix.'-'.$name" :label="$label" :required="$required">
    <select id="{{ $prefix }}-{{ $name }}" name="{{ $name }}" @required($required) aria-describedby="{{ $prefix }}-{{ $name }}-error" {{ $attributes }}>
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $option)<option value="{{ $option }}" @selected((string) old($name, $value) === (string) $option)>{{ $labels[$option] ?? $option }}</option>@endforeach
    </select>
</x-ui.field>
