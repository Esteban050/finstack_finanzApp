@props(['name' => 'etiquetas', 'label' => 'Etiquetas', 'value' => [], 'id' => null])
@php($inputId = $id ?? $name)
<x-ui.field :name="$name" :id="$inputId" :label="$label" help="Hasta 10 etiquetas, de 2 a 30 caracteres cada una.">
    <div x-data="tagInput" data-tags="{{ json_encode(old($name, $value)) }}" class="ui-tags" {{ $attributes }}>
        <ul aria-label="Etiquetas seleccionadas"><template x-for="(tag, index) in tags" :key="tag"><li><span x-text="tag"></span><button type="button" @click="tags.splice(index, 1)" :aria-label="'Eliminar etiqueta ' + tag" @disabled($attributes->has('disabled'))>×</button><input type="hidden" name="{{ $name }}[]" :value="tag" @disabled($attributes->has('disabled'))></li></template></ul>
        <div class="ui-inline"><input id="{{ $inputId }}" x-model="draft" @keydown.enter.prevent="add" aria-describedby="{{ $inputId }}-help {{ $inputId }}-error {{ $inputId }}-count" @disabled($attributes->has('disabled'))><x-ui.button @click="add" variant="secondary" :disabled="$attributes->has('disabled')">Agregar</x-ui.button></div>
        <p id="{{ $inputId }}-count" class="ui-help" aria-live="polite" x-text="tags.length + ' de 10 etiquetas'"></p>
    </div>
</x-ui.field>
