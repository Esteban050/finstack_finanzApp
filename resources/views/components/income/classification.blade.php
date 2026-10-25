@props(['value' => '', 'options' => [], 'id' => 'clasificacion_tributaria', 'required' => false, 'placeholder' => null])
@php($labels = require resource_path('views/components/income/classification-labels.php'))
<x-ui.field name="clasificacion_tributaria" :id="$id" label="Clasificación tributaria" :required="$required">
    <select name="clasificacion_tributaria" id="{{ $id }}" data-classification @required($required) aria-describedby="{{ $id }}-error">
        <option value="">{{ $placeholder ?? ($required ? 'Selecciona la clasificación' : 'Heredar de la fuente') }}</option>
        @foreach($options as $option)<option value="{{ $option }}" @selected(old('clasificacion_tributaria', $value) === $option)>{{ $labels[$option] ?? $option }}</option>@endforeach
    </select>
</x-ui.field>
