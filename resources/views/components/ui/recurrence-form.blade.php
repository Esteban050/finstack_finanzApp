@props(['periodicities', 'modes', 'record' => null, 'prefix' => 'recurrence'])
@php($frozen = ($record?->ocurrencias_generadas ?? 0) > 0)
<section data-recurrence-fields data-limit="{{ $record?->fecha_fin ? 'date' : ($record?->max_ocurrencias ? 'count' : 'none') }}">
    <h2>Programación</h2><p class="ui-help">Editar la plantilla solo cambia las ocurrencias futuras</p>
    @if($frozen)<p class="ui-help">La fecha de inicio y la periodicidad se conservan porque ya existen ocurrencias generadas.</p>@endif
    <div class="income-two-fields">
        <x-ui.field name="periodicidad" :id="$prefix.'-period'" label="Periodicidad" :required="true"><select id="{{ $prefix }}-period" name="periodicidad" required @disabled($frozen) aria-describedby="{{ $prefix }}-period-error">@foreach($periodicities as $period)<option value="{{ $period }}" @selected(($record?->periodicidad ?? 'MENSUAL') === $period)>{{ ucfirst(strtolower($period)) }}</option>@endforeach</select></x-ui.field>
        <x-ui.field name="fecha_inicio" :id="$prefix.'-start'" label="Fecha de inicio" :required="true"><input id="{{ $prefix }}-start" type="date" name="fecha_inicio" value="{{ $record?->fecha_inicio?->format('Y-m-d') ?? '' }}" required @disabled($frozen) aria-describedby="{{ $prefix }}-start-error"></x-ui.field>
    </div>
    <div class="ui-inline" role="group" aria-label="Límite de la recurrencia"><x-ui.button variant="secondary" data-limit-choice="none">Sin límite</x-ui.button><x-ui.button variant="secondary" data-limit-choice="date">Fecha de fin</x-ui.button><x-ui.button variant="secondary" data-limit-choice="count">Número de ocurrencias</x-ui.button></div>
    <div data-limit-panel="date"><x-ui.field name="fecha_fin" :id="$prefix.'-end'" label="Fecha de fin" :required="true"><input id="{{ $prefix }}-end" type="date" name="fecha_fin" value="{{ $record?->fecha_fin?->format('Y-m-d') ?? '' }}" aria-describedby="{{ $prefix }}-end-error"></x-ui.field></div>
    <div data-limit-panel="count"><x-ui.field name="max_ocurrencias" :id="$prefix.'-count'" label="Número de ocurrencias" :required="true" help="Entre 1 y 360."><input id="{{ $prefix }}-count" type="number" name="max_ocurrencias" value="{{ $record?->max_ocurrencias ?? '' }}" min="1" max="360" step="1" aria-describedby="{{ $prefix }}-count-help {{ $prefix }}-count-error"></x-ui.field></div>
    <x-ui.field name="modo_confirmacion" :id="$prefix.'-mode'" label="Modo de confirmación" :required="true"><select id="{{ $prefix }}-mode" name="modo_confirmacion" required aria-describedby="{{ $prefix }}-mode-error">@foreach($modes as $mode)<option value="{{ $mode }}" @selected(($record?->modo_confirmacion ?? 'REQUIERE_CONFIRMACION') === $mode)>{{ $mode === 'AUTOMATICO' ? 'Registrar automáticamente' : 'Requiere confirmación' }}</option>@endforeach</select></x-ui.field>
    {{ $slot }}
</section>
