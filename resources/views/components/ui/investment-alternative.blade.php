@props(['index' => 0, 'alternative' => []])
<fieldset class="finance-alternative" data-alternative><legend>Alternativa <span data-alternative-number>{{ $index + 1 }}</span></legend>
<x-ui.button type="button" variant="ghost" data-remove-alternative>Quitar alternativa</x-ui.button>
<div class="income-two-fields">
<x-ui.field :name="'alternativas.'.$index.'.nombre'" :id="'alt-'.$index.'-nombre'" label="Nombre de la alternativa" :required="true"><input id="alt-{{ $index }}-nombre" name="alternativas[{{ $index }}][nombre]" maxlength="40" required value="{{ $alternative['nombre'] ?? '' }}"></x-ui.field>
<x-ui.money-input :name="'alternativas['.$index.'][monto_inicial]'" :id="'alt-'.$index.'-monto'" label="Monto inicial" :allow-zero="true" :value="(string) ($alternative['monto_inicial'] ?? '')" />
<x-ui.money-input :name="'alternativas['.$index.'][aporte_periodico]'" :id="'alt-'.$index.'-aporte'" label="Aporte periódico" :allow-zero="true" :required="false" :value="(string) ($alternative['aporte_periodico'] ?? '')" />
<x-ui.field :name="'alternativas.'.$index.'.periodicidad_aporte'" :id="'alt-'.$index.'-frecuencia'" label="Frecuencia del aporte"><select id="alt-{{ $index }}-frecuencia" name="alternativas[{{ $index }}][periodicidad_aporte]">@foreach(['MENSUAL'=>'Mensual','TRIMESTRAL'=>'Trimestral','SEMESTRAL'=>'Semestral','ANUAL'=>'Anual'] as $key=>$label)<option value="{{ $key }}" @selected(($alternative['periodicidad_aporte'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></x-ui.field>
<x-ui.decimal-input :name="'alternativas['.$index.'][tasa_ea]'" :id="'alt-'.$index.'-tasa'" label="Tasa esperada (% EA)" :required="true" min="-50" max="100" :precision="4" :value="$alternative['tasa_ea'] ?? ''" help="Entre −50 y 100 %. No garantiza rendimientos futuros." />
<x-ui.field :name="'alternativas.'.$index.'.plazo_meses'" :id="'alt-'.$index.'-plazo'" label="Plazo en meses" :required="true"><input id="alt-{{ $index }}-plazo" name="alternativas[{{ $index }}][plazo_meses]" type="number" min="1" max="480" step="1" required value="{{ $alternative['plazo_meses'] ?? '' }}"></x-ui.field>
<x-ui.decimal-input :name="'alternativas['.$index.'][retencion_porc]'" :id="'alt-'.$index.'-retencion'" label="Retención sobre rendimientos (%)" min="0" max="100" :precision="4" :value="$alternative['retencion_porc'] ?? ''" />
<x-ui.decimal-input :name="'alternativas['.$index.'][comision_anual_porc]'" :id="'alt-'.$index.'-comision'" label="Comisión anual (%)" min="0" max="10" :precision="4" :value="$alternative['comision_anual_porc'] ?? ''" />
</div></fieldset>
