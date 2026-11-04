@props(['percent' => '', 'months' => '', 'capital' => '', 'prefix' => 'investment'])
<fieldset class="finance-alternative"><legend>Meta de rentabilidad (opcional)</legend>
<x-ui.field name="rentabilidad_esperada_porc" :id="$prefix.'-goal'" label="Ganancia esperada" help="Meta sobre el monto inicial. Puedes indicar pesos o porcentaje; no garantiza rendimientos.">
<div data-investment-goal data-capital="{{ $capital }}"><div class="ui-inline" role="group" aria-label="Unidad de la meta"><x-ui.button type="button" variant="secondary" data-goal-mode="percent" aria-pressed="true">%</x-ui.button><x-ui.button type="button" variant="secondary" data-goal-mode="money" aria-pressed="false">COP</x-ui.button></div>
<div class="ui-money"><input id="{{ $prefix }}-goal" type="text" inputmode="decimal" data-goal-input aria-describedby="{{ $prefix }}-goal-help {{ $prefix }}-goal-error"><span class="ui-money-unit" data-goal-unit>%</span></div><input type="hidden" name="rentabilidad_esperada_porc" value="{{ old('rentabilidad_esperada_porc',$percent) }}"><output class="ui-help" data-goal-equivalent aria-live="polite"></output></div>
</x-ui.field>
<x-ui.field name="plazo_meses_esperado" :id="$prefix.'-months'" label="Plazo de la meta (meses)" help="De 1 a 360 meses. Si no indicas un plazo, la referencia visual usa 12 meses."><input id="{{ $prefix }}-months" name="plazo_meses_esperado" type="number" min="1" max="360" step="1" value="{{ old('plazo_meses_esperado',$months) }}"></x-ui.field>
</fieldset>
