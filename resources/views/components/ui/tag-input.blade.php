@props(['name' => 'etiquetas', 'label' => 'Etiquetas', 'value' => []])
<x-ui.field :name="$name" :label="$label" help="RN-026.1: hasta 10 etiquetas; de 2 a 30 caracteres por etiqueta.">
    <div x-data="tagInput" data-tags="{{ json_encode(old($name, $value)) }}" class="ui-tags">
        <ul aria-label="Etiquetas seleccionadas"><template x-for="(tag, index) in tags" :key="tag"><li><span x-text="tag"></span><button type="button" @click="tags.splice(index, 1)" :aria-label="'Eliminar etiqueta ' + tag">×</button><input type="hidden" name="{{ $name }}[]" :value="tag"></li></template></ul>
        <div class="ui-inline"><input id="{{ $name }}" x-model="draft" @keydown.enter.prevent="add" maxlength="30" aria-describedby="{{ $name }}-help {{ $name }}-error"><x-ui.button @click="add" variant="secondary">Agregar</x-ui.button></div>
    </div>
</x-ui.field>
