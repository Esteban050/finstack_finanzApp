@props(['name', 'label' => 'Fecha', 'required' => true, 'value' => '', 'allowFuture' => false])
<x-ui.field :name="$name" :label="$label" :required="$required" help="DD/MM/AAAA · America/Bogota">
    <input id="{{ $name }}" name="{{ $name }}" type="date" value="{{ old($name, $value) }}" @required($required) @if(!$allowFuture) data-date-no-future @endif aria-describedby="{{ $name }}-help {{ $name }}-error" {{ $attributes }}>
</x-ui.field>
