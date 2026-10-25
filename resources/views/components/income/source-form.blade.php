@props(['types', 'classifications', 'record' => null, 'prefix' => 'source'])
<x-ui.field name="nombre" :id="$prefix.'-name'" label="Nombre de la fuente" :required="true" help="Entre 3 y 60 caracteres; único entre tus fuentes.">
    <input id="{{ $prefix }}-name" name="nombre" value="{{ $record?->nombre ?? '' }}" minlength="3" maxlength="60" required aria-describedby="{{ $prefix }}-name-help {{ $prefix }}-name-error">
</x-ui.field>
<div class="income-two-fields">
    <x-ui.field name="tipo_fuente_id" :id="$prefix.'-type'" label="Tipo de fuente" :required="true">
        <div class="income-type-control"><span data-type-visual hidden aria-hidden="true"></span>
        <select id="{{ $prefix }}-type" name="tipo_fuente_id" data-source-type required aria-describedby="{{ $prefix }}-type-error"><option value="">Selecciona el tipo</option>@foreach($types as $type)<option value="{{ $type->id }}" data-type-name="{{ $type->nombre }}" data-icono="{{ $type->icono ?? '' }}" data-color_hex="{{ $type->color_hex ?? '' }}" @selected($record?->tipo_fuente_id === $type->id)>{{ $type->nombre }}</option>@endforeach</select>
        </div>
    </x-ui.field>
    <x-income.classification :options="$classifications" :value="$record?->clasificacion_tributaria ?? ''" :id="$prefix.'-classification'" :required="true" />
</div>
<x-ui.field name="pagador_entidad" :id="$prefix.'-payer'" label="Pagador o entidad" help="Opcional. Máximo 80 caracteres."><input id="{{ $prefix }}-payer" name="pagador_entidad" value="{{ $record?->pagador_entidad ?? '' }}" maxlength="80" aria-describedby="{{ $prefix }}-payer-help {{ $prefix }}-payer-error"></x-ui.field>
