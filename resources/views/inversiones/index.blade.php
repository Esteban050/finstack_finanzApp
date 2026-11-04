@extends('layouts.app')
@section('title', 'Inversiones')
@section('content')
<div class="finance-page">
@php($labels = require resource_path('views/components/ui/investment-labels.php'))
<header class="income-page-header"><div><h1>Inversiones</h1><p>Registra tus inversiones y consulta su valor y rentabilidad.</p></div><x-ui.button :href="route('inversiones.portafolio')" variant="secondary">Ver portafolio</x-ui.button></header>
<x-ui.card><details @if($errors->any()) open @endif><summary>Registrar nueva inversión</summary>
<form method="POST" action="{{ route('inversiones.store') }}" data-finance-form="investment">@csrf<input type="hidden" name="moneda" value="COP">
<h2>Identificación de la inversión</h2><div class="income-two-fields">
<x-ui.field name="nombre" label="Nombre de la inversión" :required="true" help="Entre 3 y 60 caracteres."><input id="nombre" name="nombre" minlength="3" maxlength="60" value="{{ old('nombre') }}" required></x-ui.field>
<x-ui.field name="tipo" label="Tipo de inversión" :required="true"><select id="tipo" name="tipo" data-investment-type required><option value="">Selecciona un tipo</option>@foreach($tipos as $type)<option value="{{ $type }}" @selected(old('tipo')===$type)>{{ $labels[$type] }}</option>@endforeach</select></x-ui.field>
<x-ui.field name="entidad" label="Entidad o emisor" help="Opcional. Hasta 80 caracteres."><input id="entidad" name="entidad" maxlength="80" value="{{ old('entidad') }}"></x-ui.field>
<x-ui.field name="nivel_riesgo" label="Nivel de riesgo declarado"><select id="nivel_riesgo" name="nivel_riesgo"><option value="">Sin declarar</option>@foreach(\App\Models\Inversion::NIVELES_RIESGO as $code)<option value="{{ $code }}" @selected(old('nivel_riesgo')===$code)>{{ $labels[$code] }}</option>@endforeach</select></x-ui.field>
<x-ui.date-input name="fecha_apertura" label="Fecha de apertura" :value="now('America/Bogota')->format('Y-m-d')" />
<x-ui.money-input name="monto_inicial" label="Monto inicial" />
</div>
<fieldset data-investment-fields="CDT BONOS_TES" class="finance-alternative"><legend>Tasa y vencimiento</legend><div class="income-two-fields">
<x-ui.decimal-input name="tasa_ea" label="Tasa pactada (% EA)" :required="true" min="0" max="100" :precision="4" help="Tasa efectiva anual entre 0 y 100 %." />
<x-ui.field name="plazo_dias" label="Plazo en días" :required="true"><input id="plazo_dias" name="plazo_dias" type="number" min="1" max="3650" step="1" required value="{{ old('plazo_dias') }}"></x-ui.field>
<x-ui.field name="periodicidad_intereses" label="Pago de intereses" :required="true"><select id="periodicidad_intereses" name="periodicidad_intereses" required>@foreach(\App\Models\Inversion::PERIODICIDADES as $code)<option value="{{ $code }}" @selected(old('periodicidad_intereses')===$code)>{{ $labels[$code] }}</option>@endforeach</select></x-ui.field>
<x-ui.date-input name="fecha_vencimiento" label="Fecha de vencimiento" :required="false" :allow-future="true" />
</div><p class="ui-help">Si no indicas vencimiento, se calcula con la fecha de apertura y el plazo. Puedes ajustarlo.</p></fieldset>
<fieldset data-investment-fields="ACCIONES ETF CRIPTOACTIVO" class="finance-alternative"><legend>Unidades y precio de compra</legend><div class="income-two-fields">
<x-ui.field name="simbolo" label="Símbolo" help="Opcional. Hasta 15 caracteres."><input id="simbolo" name="simbolo" maxlength="15" value="{{ old('simbolo') }}"></x-ui.field>
<x-ui.decimal-input name="unidades" label="Cantidad de unidades" :required="true" min="0.00000001" :precision="8" help="Mayor que cero. Hasta ocho decimales." />
<x-ui.decimal-input name="precio_unitario_compra" label="Precio unitario de compra (COP)" :required="true" min="0.00000001" :precision="8" help="Mayor que cero. Hasta ocho decimales." />
</div></fieldset>
<x-ui.investment-goal />
<p class="ui-help">El monto inicial se registra como primer aporte. Los movimientos posteriores se gestionan desde el detalle.</p><x-ui.module-status /><x-ui.button type="submit">Guardar inversión</x-ui.button></form></details></x-ui.card>
<x-ui.card><form method="GET" action="{{ route('inversiones.index') }}" class="income-filters"><x-ui.field name="estado" id="filter-state" label="Estado"><select id="filter-state" name="estado"><option value="">Todos</option>@foreach($estados as $code)<option value="{{ $code }}" @selected(request('estado')===$code)>{{ $labels[$code] }}</option>@endforeach</select></x-ui.field><x-ui.field name="tipo" id="filter-type" label="Tipo"><select id="filter-type" name="tipo"><option value="">Todos</option>@foreach($tipos as $code)<option value="{{ $code }}" @selected(request('tipo')===$code)>{{ $labels[$code] }}</option>@endforeach</select></x-ui.field><div class="ui-inline"><x-ui.button type="submit" variant="secondary">Aplicar filtros</x-ui.button><a href="{{ route('inversiones.index') }}">Limpiar filtros</a></div></form></x-ui.card>
<x-ui.card title="Mis inversiones">
@if($inversiones->isEmpty())<x-ui.empty-state title="No hay inversiones para los filtros seleccionados" />@else
<table class="ui-table"><caption>Inversiones, de la apertura más reciente a la más antigua</caption><thead><tr><th>Nombre</th><th>Tipo</th><th data-align="right">Aporte inicial</th><th data-align="right">Valor actual</th><th>Última valoración</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
@foreach($inversionesPresentadas as $inv)<tr><td>{{ $inv['nombre'] }}</td><td>{{ $labels[$inv['tipo']] }}</td><td class="ui-number">@if($inv['moneda'] === 'COP')<span data-money="{{ $inv['monto_inicial'] }}">{{ $inv['monto_inicial'] }}</span>@else Moneda no admitida @endif</td><td class="ui-number">@if($inv['moneda'] === 'COP')<span data-money="{{ $inv['valor_actual'] ?? '' }}">{{ $inv['valor_actual'] ?? '—' }}</span>@else Moneda no admitida @endif</td><td>@if($inv['fecha_ultima_valoracion'])<time data-date="{{ $inv['fecha_ultima_valoracion'] }}">{{ $inv['fecha_ultima_valoracion'] }}</time>@else Sin valoración @endif @if($inv['valoracion_desactualizada'])<x-ui.badge tone="warning">Valoración desactualizada</x-ui.badge>@endif</td><td><x-ui.badge>{{ $labels[$inv['estado']] }}</x-ui.badge></td><td><a href="{{ route('inversiones.show',$inv['id']) }}">Ver detalle</a></td></tr>@endforeach
</tbody></table>@endif
<x-ui.pagination :paginator="$inversiones" /></x-ui.card><x-ui.calculation-notice />
</div>
@endsection
