@props(['name', 'label', 'options' => [], 'value' => '', 'required' => false, 'help' => null])
<x-ui.field :name="$name" :label="$label" :required="$required" :help="$help">
    <select id="{{ $name }}" name="{{ $name }}" @required($required) aria-describedby="{{ $name }}-error {{ $help ? $name.'-help' : '' }}" {{ $attributes }}>
        @foreach($options as $key => $text)<option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>@endforeach
        {{ $slot }}
    </select>
</x-ui.field>
