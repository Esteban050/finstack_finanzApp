@extends('layouts.app')
@section('title', 'Detalle de inversión')
@section('content')
<div class="finance-page">
@php
    $labels = require resource_path('views/components/ui/investment-labels.php');
@endphp
<header class="income-page-header"><div><a href="{{ route('inversiones.index') }}">Volver a inversiones</a><h1>{{ $inversion->nombre }}</h1><p>{{ $labels[$inversion->tipo] }} · {{ $labels[$inversion->estado] }} · {{ $labels[$inversion->nivel_riesgo ?? 'SIN_DECLARAR'] }}</p></div></header>
@if(!in_array($inversion->moneda, ['COP', 'USD'], true))<p class="ui-notice" role="status">Este registro histórico no está en la moneda admitida (COP o USD). Su tratamiento debe confirmarse antes de gestionarlo.</p>
@else
@if($presentacion['valoracion_desactualizada'])<p class="ui-notice">Valoración desactualizada. Registra un valor de mercado para actualizar el seguimiento.</p>@endif
<x-ui.card title="Consultar rentabilidad"><form method="GET" action="{{ route('inversiones.rentabilidad',$inversion->id) }}" data-finance-form="indicators" data-finance-result="investment-indicators"><x-ui.date-input name="fecha_corte" label="Fecha de corte" :min="$inversion->fecha_apertura->format('Y-m-d')" :value="now('America/Bogota')->format('Y-m-d')" /><x-ui.module-status /><x-ui.button type="submit" variant="secondary">Consultar rentabilidad</x-ui.button></form></x-ui.card>
<x-ui.card id="investment-indicators" title="Rentabilidad" tabindex="-1"><dl class="finance-stats"><x-ui.finance-stat label="Valor actual" :value="$indicadores['valor_actual']" result-key="valor_actual" /><x-ui.finance-stat label="Capital aportado neto" :value="$indicadores['capital_aportado_neto']" result-key="capital_aportado_neto" /><x-ui.finance-stat label="Ganancia o pérdida" :value="$indicadores['ganancia']" result-key="ganancia" format="sign-money" /><x-ui.finance-stat label="ROI" :value="$indicadores['roi']" result-key="roi" format="percent" /><x-ui.finance-stat label="XIRR" :value="$indicadores['xirr']" result-key="xirr" format="percent" /><x-ui.finance-stat label="Rentabilidad real" :value="$indicadores['rentabilidad_real']" result-key="rentabilidad_real" format="percent" /></dl>
<div data-result-notices>@foreach($indicadores['mensajes'] as $message)<p class="ui-help"><x-ui.safe-message :value="$message" /></p>@endforeach</div>
</x-ui.card>
@if(isset($presentacion['rendimiento_estimado']))<x-ui.card><dl class="finance-stats"><x-ui.finance-stat label="Rendimiento estimado al vencimiento" :value="$presentacion['rendimiento_estimado']" /></dl></x-ui.card>@endif
<x-ui.card title="Datos de la inversión"><dl class="finance-breakdown"><dt>Fecha de apertura</dt><dd>{{ $inversion->fecha_apertura->format('d/m/Y') }}</dd><dt>Monto inicial</dt><dd data-money="{{ $inversion->monto_inicial }}">{{ $inversion->monto_inicial }}</dd>@if($inversion->moneda === 'USD') <dd class="ui-help">Moneda: Dólares (USD)</dd>@endif<dt>Entidad o emisor</dt><dd>{{ $inversion->entidad ?? '—' }}</dd>@if($inversion->fecha_vencimiento)<dt>Vencimiento</dt><dd>{{ $inversion->fecha_vencimiento->format('d/m/Y') }}</dd>@endif @if($inversion->tasa_ea !== null)<dt>Tasa pactada EA</dt><dd data-percent="{{ $inversion->tasa_ea }}">{{ $inversion->tasa_ea }}</dd>@endif @if($presentacion['unidades_actuales'] !== null)<dt>Unidades actuales</dt><dd data-quantity="{{ $presentacion['unidades_actuales'] }}">{{ $presentacion['unidades_actuales'] }}</dd>@endif</dl>
@if(!$inversion->estaLiquidada())<details><summary>Editar datos descriptivos</summary><form method="POST" action="{{ route('inversiones.update',$inversion->id) }}" data-finance-form="investment-edit">@csrf @method('PUT')<div class="income-two-fields"><x-ui.field name="nombre" label="Nombre" :required="true"><input id="nombre" name="nombre" required minlength="3" maxlength="60" value="{{ old('nombre',$inversion->nombre) }}"></x-ui.field><x-ui.field name="entidad" label="Entidad o emisor"><input id="entidad" name="entidad" maxlength="80" value="{{ old('entidad',$inversion->entidad) }}"></x-ui.field><x-ui.field name="nivel_riesgo" label="Nivel de riesgo"><select id="nivel_riesgo" name="nivel_riesgo"><option value="">Sin declarar</option>@foreach(\App\Models\Inversion::NIVELES_RIESGO as $code)<option value="{{ $code }}" @selected(old('nivel_riesgo',$inversion->nivel_riesgo)===$code)>{{ $labels[$code] }}</option>@endforeach</select></x-ui.field>@if($inversion->tieneUnidades())<x-ui.field name="simbolo" label="Símbolo"><input id="simbolo" name="simbolo" maxlength="15" value="{{ old('simbolo',$inversion->simbolo) }}"></x-ui.field>@endif @if($inversion->fecha_vencimiento)<x-ui.date-input name="fecha_vencimiento" label="Fecha de vencimiento" :allow-future="true" :value="$inversion->fecha_vencimiento->format('Y-m-d')" />@endif</div><x-ui.investment-goal :percent="(string) ($inversion->rentabilidad_esperada_porc ?? '')" :months="$inversion->plazo_meses_esperado ?? ''" :capital="(string) $inversion->monto_inicial" prefix="edit-investment" /><x-ui.module-status /><x-ui.button type="submit">Guardar cambios</x-ui.button></form></details>@endif
</x-ui.card>
@if(!$inversion->estaLiquidada())
<div class="finance-grid">
<x-ui.card title="Registrar movimiento">
@if(in_array($inversion->tipo, ['CDT', 'BONOS_TES'], true))
<div class="ui-notice" style="margin-bottom: 1rem;"><p><strong>Título a plazo fijo:</strong> En un {{ $labels[$inversion->tipo] }}, el capital está inmutablemente pactado hasta el vencimiento ({{ $inversion->fecha_vencimiento?->format('d/m/Y') ?? 'pactado' }}). No se permiten retiros parciales ni aportes adicionales. Puedes registrar pagos de intereses periódicos (Rendimiento) o la Liquidación total al vencimiento.</p></div>
@elseif($inversion->tipo === 'INMUEBLE_RENTA')
<p class="ui-help" style="margin-bottom: 1rem;">Los cánones de arrendamiento recibidos se registran como <em>Rendimiento</em> y las reformas o mejoras que incrementan el costo fiscal como <em>Aporte</em>.</p>
@elseif($inversion->tipo === 'CUENTA_AHORRO_ALTO_RENDIMIENTO')
<p class="ui-help" style="margin-bottom: 1rem;">Los intereses diarios capitalizados en la cuenta se reflejan en el saldo (actualizando el valor de mercado con tu extracto), no como movimiento individual.</p>
@endif
<form method="POST" action="{{ route('inversiones.movimientos.store',$inversion->id) }}" data-finance-form="movement">@csrf
<x-ui.field name="tipo" label="Tipo de movimiento" :required="true"><select id="tipo" name="tipo" required data-movement-type>@foreach($inversion->movimientosPermitidos() as $code)<option value="{{ $code }}" @selected(old('tipo')===$code)>{{ $labels[$code] }}</option>@endforeach</select></x-ui.field>
<x-ui.date-input name="fecha" label="Fecha del movimiento" :min="$inversion->fecha_apertura->format('Y-m-d')" :value="now('America/Bogota')->format('Y-m-d')" />
<x-ui.money-input name="monto" label="Monto del movimiento" :allow-zero="true" />
@if($inversion->tieneUnidades())
<x-ui.decimal-input name="unidades" label="Cantidad de unidades asociadas" min="0.00000001" :precision="8" help="Opcional. Si se deja vacío, se calculará automáticamente al último precio unitario." />
<p class="ui-help" data-movement-units-preview hidden style="margin-top: 0.25rem; font-weight: 500;"></p>
@endif
<div data-movement-retention><x-ui.money-input name="retencion" label="Retención informada sobre el rendimiento" :required="false" :allow-zero="true" /><p class="ui-help">El rendimiento genera automáticamente un ingreso con su retención según su cédula tributaria. No lo registres otra vez en Ingresos.</p></div>
<x-ui.field name="nota" label="Nota" help="Opcional. Hasta 255 caracteres."><input id="nota" name="nota" maxlength="255" value="{{ old('nota') }}"></x-ui.field><x-ui.module-status /><x-ui.button type="submit">Guardar movimiento</x-ui.button></form></x-ui.card>
<x-ui.card title="Actualizar valor de mercado"><form method="POST" action="{{ route('inversiones.valoraciones.store',$inversion->id) }}" data-finance-form="valuation">@csrf
<x-ui.field name="fecha" id="valuation-date" label="Fecha de valoración" help="Cada fecha admite a lo sumo una valoración de cierre de mercado. Si registras otra para la misma fecha, reemplaza la anterior (RN-039.1)." :required="true"><input id="valuation-date" type="date" name="fecha" data-date-no-future min="{{ $inversion->fecha_apertura->format('Y-m-d') }}" required value="{{ old('fecha',now('America/Bogota')->format('Y-m-d')) }}"></x-ui.field>
@if($inversion->tieneUnidades())<x-ui.decimal-input name="precio_unitario" label="Precio unitario (COP)" :required="true" min="0.00000001" :precision="8" />@else<x-ui.money-input name="valor" label="Valor total" :allow-zero="true" />@endif
<x-ui.module-status /><x-ui.button type="submit">Guardar valoración</x-ui.button></form>
@if($valorCausadoPropuesto)<div class="ui-notice"><p>Valor causado propuesto para hoy: <strong data-money="{{ $valorCausadoPropuesto['valor_causado'] }}">{{ $valorCausadoPropuesto['valor_causado'] }}</strong></p><form method="POST" action="{{ route('inversiones.valoraciones.store',$inversion->id) }}" data-finance-form="valuation-caused">@csrf<input type="hidden" name="fecha" value="{{ $valorCausadoPropuesto['fecha'] }}"><input type="hidden" name="usar_valor_causado" value="1"><x-ui.module-status /><x-ui.button type="submit" variant="secondary">Aceptar valor causado</x-ui.button></form></div>@endif
</x-ui.card></div>@endif

@php
    $projectionData = [
        'opening' => $inversion->fecha_apertura->format('Y-m-d'),
        'initial' => (string)$inversion->monto_inicial,
        'goalPercent' => $inversion->rentabilidad_esperada_porc,
        'months' => $inversion->plazo_meses_esperado,
        'rate' => $inversion->tieneTasa() ? $inversion->tasa_ea : null,
        'type' => $inversion->tipo,
        'isFixedRate' => $inversion->tieneTasa(),
        'maturity' => $inversion->fecha_vencimiento?->format('Y-m-d'),
        'flows' => $movimientos->map(fn($m)=>['date'=>$m->fecha->format('Y-m-d'),'type'=>$m->tipo,'amount'=>(string)$m->monto_cop])->values(),
        'valuations' => $valoracionesCronologicas->map(fn($v)=>['date'=>$v->fecha->format('Y-m-d'),'value'=>(string)$v->valor])->values()
    ];
@endphp
<x-ui.card title="Seguimiento diario y proyección" data-projection-view>
<p class="ui-help">El seguimiento usa tus movimientos y la última valoración registrada de cada día. Los días sin valoración no representan una cotización nueva. La trayectoria hacia la meta es una referencia lineal, no una predicción.</p>
@if($inversion->rentabilidad_esperada_porc === null)<p class="ui-notice">Define una meta en «Editar datos descriptivos» para comparar tu avance.</p>@endif
<x-ui.field name="projection_end" id="projection-end" label="Comparar hasta" help="Puedes elegir una fecha pasada o futura, hasta 30 años desde la apertura."><input id="projection-end" type="date" data-projection-end></x-ui.field><x-ui.button type="button" variant="secondary" data-update-projection>Actualizar comparación</x-ui.button><p data-projection-error class="ui-error" role="alert" hidden></p>
<dl class="finance-stats"><div class="finance-stat"><dt>Ganancia registrada al corte</dt><dd data-projection-earned>—</dd></div><div class="finance-stat"><dt>Meta de ganancia</dt><dd data-projection-goal>—</dd></div><div class="finance-stat"><dt>Falta para la meta</dt><dd data-projection-remaining>—</dd></div></dl><p class="ui-help" data-projection-caption></p>
<h3>Evolución de ganancia y referencia</h3><div class="finance-chart"><canvas data-projection-line role="img" aria-label="Ganancia registrada y referencia de meta; valores en la tabla diaria."></canvas></div>
<div class="finance-grid"><section><h3>Dispersión de valoraciones registradas</h3><p class="ui-help">Cada punto corresponde a una fecha con valoración registrada.</p><div class="finance-chart"><canvas data-projection-scatter role="img" aria-label="Dispersión de ganancias en fechas con valoración; datos en la tabla diaria."></canvas></div>
</section><section data-projection-bar-panel><h3>Avance hacia la meta</h3><div class="finance-chart"><canvas data-projection-bar role="img" aria-label="Ganancia registrada frente a meta de ganancia; cifras en el resumen anterior."></canvas></div></section></div>
<details><summary>Comparación día a día</summary><div class="ui-table-scroll" tabindex="0" role="region" aria-label="Comparación diaria"><table class="ui-table"><caption>Datos del seguimiento y escenarios estimados</caption><thead><tr><th>Fecha</th><th data-align="right">Ganancia registrada</th><th data-align="right">Referencia de meta</th><th data-align="right">Proyección por tasa pactada</th></tr></thead><tbody data-projection-rows></tbody></table></div><nav class="ui-pagination" aria-label="Días de la proyección"><x-ui.button type="button" variant="secondary" data-projection-previous>Anterior</x-ui.button><p data-projection-page aria-live="polite"></p><x-ui.button type="button" variant="secondary" data-projection-next>Siguiente</x-ui.button></nav></details>
<script type="application/json" data-projection-data>@json($projectionData)</script><x-ui.calculation-notice />
</x-ui.card>

<x-ui.card title="Histórico de valoraciones">
@if($valoraciones->isEmpty())<x-ui.empty-state title="No hay valoraciones registradas" />@else
@php($chartRows = $valoracionesCronologicas->map(fn($val)=>['label'=>$val->fecha->format('d/m/Y'),'value'=>(string)$val->valor])->values())
<div class="finance-chart"><canvas data-finance-chart="valuation-series" data-chart-label="Valor de mercado" data-chart-cost="{{ $indicadores['capital_aportado_neto'] ?? $inversion->monto_inicial }}" role="img" aria-label="Histórico de valor de mercado. Consulta los datos en la tabla siguiente."></canvas></div><script type="application/json" id="valuation-series">@json($chartRows)</script>
<table class="ui-table"><caption>Alternativa tabular del gráfico de valoraciones</caption><thead><tr><th>Fecha</th><th data-align="right">Valor total</th>@if($inversion->tieneUnidades())<th data-align="right">Precio unitario (COP)</th>@endif<th>Acción</th></tr></thead><tbody>@foreach($valoraciones as $val)<tr><td>{{ $val->fecha->format('d/m/Y') }}</td><td class="ui-number"><span data-money="{{ $val->valor }}">{{ $val->valor }}</span></td>@if($inversion->tieneUnidades())<td class="ui-number"><span data-unit-price="{{ $val->precio_unitario }}">{{ $val->precio_unitario }}</span></td>@endif<td><form method="POST" action="{{ route('inversiones.valoraciones.destroy',[$inversion->id,$val->id]) }}" data-finance-form="action" data-confirm-description="Se eliminará esta valoración del histórico.">@csrf @method('DELETE')<x-ui.module-status /><x-ui.button type="submit" variant="ghost">Eliminar</x-ui.button></form></td></tr>@endforeach</tbody></table>@endif
</x-ui.card>
<x-ui.card title="Movimientos"><table class="ui-table"><caption>Movimientos que componen la rentabilidad</caption><thead><tr><th>Fecha</th><th>Tipo</th><th data-align="right">Monto</th><th>Nota</th><th>Acción</th></tr></thead><tbody>@foreach($movimientos as $mov)<tr><td>{{ $mov->fecha->format('d/m/Y') }}</td><td>{{ $labels[$mov->tipo] }}</td><td class="ui-number"><span data-money="{{ $mov->monto_cop }}">{{ $mov->monto_cop }}</span></td><td><x-ui.safe-message :value="$mov->nota ?? '—'" /></td><td>@if($mov->es_inicial)<x-ui.badge>Aporte inicial</x-ui.badge>@elseif($mov->tipo === 'RETENCION' && $mov->ingreso_id)<p class="ui-help">Se gestiona con el rendimiento asociado.</p>@else<form method="POST" action="{{ route('inversiones.movimientos.destroy',[$inversion->id,$mov->id]) }}" data-finance-form="action" data-confirm-date="{{ $mov->fecha->format('Y-m-d') }}" data-confirm-source="{{ $labels[$mov->tipo] }}" data-confirm-amount="{{ $mov->monto_cop }}" data-confirm-description="Si generó un ingreso, también se eliminará ese ingreso.">@csrf @method('DELETE')<x-ui.module-status /><x-ui.button type="submit" variant="ghost">Eliminar</x-ui.button></form>@endif</td></tr>@endforeach</tbody></table></x-ui.card>
<form method="POST" action="{{ route('inversiones.destroy',$inversion->id) }}" data-finance-form="action" data-return-url="{{ route('inversiones.index') }}" data-confirm-description="Se eliminará esta inversión y sus registros relacionados según las validaciones del sistema.">@csrf @method('DELETE')<x-ui.module-status /><x-ui.button type="submit" variant="danger">Eliminar inversión</x-ui.button></form>
<x-ui.calculation-notice />
@endif
</div>
@endsection
