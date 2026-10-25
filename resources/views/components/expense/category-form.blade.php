@props(['categories', 'allowedIcons', 'allowedColors', 'record' => null, 'prefix' => 'category'])
@php($visualOptions = require resource_path('views/components/expense/visual-options.php'))
<form method="POST" action="{{ $record ? route('gastos.categorias.update', $record->id) : route('gastos.categorias.store') }}" data-expense-category-form data-category-id="{{ $record?->id }}" data-parent-id="{{ $record?->categoria_padre_id }}" data-allowed-icons="{{ json_encode($allowedIcons) }}" data-allowed-colors="{{ json_encode($allowedColors) }}" data-original-icon="{{ $record?->icono }}" data-original-color="{{ $record?->color_hex }}" novalidate>
    @csrf @if($record)@method('PUT')@endif
    <x-ui.field name="nombre" :id="$prefix.'-name'" label="Nombre (2-40 caracteres)" :required="true"><input id="{{ $prefix }}-name" name="nombre" value="{{ $record?->nombre }}" minlength="2" maxlength="40" required aria-describedby="{{ $prefix }}-name-error"></x-ui.field>
    @if(!$record)<x-ui.field name="categoria_padre_id" :id="$prefix.'-parent'" label="Categoría superior"><select id="{{ $prefix }}-parent" name="categoria_padre_id" data-category-parent aria-describedby="{{ $prefix }}-parent-error"><option value="">Crear categoría de primer nivel</option>@foreach($categories as $parent)<option value="{{ $parent->id }}">{{ $parent->nombre }}</option>@endforeach</select></x-ui.field>@endif
    <div data-category-suggestions class="income-two-fields">
        <x-expense.select name="naturaleza_sugerida" label="Naturaleza sugerida" :prefix="$prefix" :options="['FIJO','VARIABLE']" :value="$record?->naturaleza_sugerida ?? ''" :required="true" />
        <x-expense.select name="necesidad_sugerida" label="Necesidad sugerida" :prefix="$prefix" :options="['ESENCIAL','DISCRECIONAL']" :value="$record?->necesidad_sugerida ?? ''" :required="true" />
    </div>
    <details class="expense-visual-options"><summary>Ícono y color</summary>
        @if($record)<p class="ui-help">Si no eliges otra opción, se conservan el ícono y el color actuales.</p>@endif
        <x-ui.field name="icono" :id="$prefix.'-icon'" label="Ícono"><select id="{{ $prefix }}-icon" name="icono" data-icon-picker aria-describedby="{{ $prefix }}-icon-error"><option value="">{{ $record ? 'Conservar ícono actual' : 'Sin ícono' }}</option>@foreach($visualOptions['icons'] as $icon)<option value="{{ $icon }}">{{ ucfirst(str_replace('-', ' ', preg_replace('/^(cat|src|pay|cls|act|ui)-/', '', $icon))) }}</option>@endforeach</select></x-ui.field>
        <x-ui.field name="color_hex" :id="$prefix.'-color'" label="Color"><select id="{{ $prefix }}-color" name="color_hex" aria-describedby="{{ $prefix }}-color-error"><option value="">{{ $record ? 'Conservar color actual' : 'Sin color' }}</option>@foreach($visualOptions['palette'] as $name => $hex)<option value="{{ $hex }}">{{ $visualOptions['colorLabels'][$name] }} · {{ $hex }}</option>@endforeach</select></x-ui.field>
    </details>
    <x-expense.status /><div class="income-actions"><x-ui.button type="submit">{{ $record ? 'Guardar cambios' : 'Crear categoría' }}</x-ui.button></div>
</form>
