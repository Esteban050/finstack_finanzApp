@extends('layouts.app')
@section('title', 'Aportes del asalariado')
@section('content')
<div class="finance-page">
<header class="income-page-header"><div><h1>Aportes del asalariado</h1><p>Consulta los descuentos de salud, pensión y Fondo de Solidaridad Pensional.</p></div></header>
<x-ui.card title="Calcular aportes"><form method="POST" action="{{ route('seguridad-social.asalariado.calcular') }}" data-finance-form="salary" data-finance-result="salary-result">@csrf
<x-ui.money-input name="salario" label="Salario mensual" />
<x-ui.money-input name="retencion_fuente" label="Retención en la fuente informada" :required="false" :allow-zero="true" />
<x-ui.field name="periodo" label="Periodo" :required="false"><input id="periodo" name="periodo" type="month" value="{{ old('periodo', now('America/Bogota')->format('Y-m')) }}"></x-ui.field>
<p class="ui-help">Salario integral: {{ $perfil->es_salario_integral ? 'Sí' : 'No' }}. Se toma de tu perfil. El empleador asume la ARL; no se descuenta de tu salario.</p>
<x-ui.module-status /><x-ui.button type="submit">Calcular aportes</x-ui.button></form></x-ui.card>
<x-ui.card id="salary-result" title="Salario neto estimado" hidden tabindex="-1"><dl class="finance-stats"><div class="finance-stat"><dt>Neto después de aportes y retención</dt><dd data-result-key="salario_neto_estimado">—</dd></div></dl><dl class="finance-breakdown">@foreach(['salario'=>'Salario','ibc'=>'IBC','salud'=>'Salud','pension'=>'Pensión','fsp'=>'Fondo de Solidaridad Pensional','total_aportes'=>'Total de aportes','retencion_fuente'=>'Retención en la fuente','salario_neto_sin_retencion'=>'Neto sin retención'] as $key=>$label)<dt>{{ $label }}</dt><dd data-result-key="{{ $key }}">—</dd>@endforeach</dl><div data-result-notices role="status"></div><div data-result-parameters></div><x-ui.calculation-notice /></x-ui.card>
<x-ui.card title="Aplicar aportes a un ingreso de salario">
<p>Elige un ingreso confirmado. Se calcularán sus aportes según el salario registrado y los parámetros del periodo.</p>
@if($ingresosSalario->isEmpty())<x-ui.empty-state illustration="empty_ingresos" title="No tienes ingresos de salario disponibles" /><x-ui.button :href="route('ingresos.create')" variant="secondary">Registrar ingreso</x-ui.button>
@else<form method="POST" action="{{ route('seguridad-social.asalariado.aplicar') }}" data-finance-form="apply-salary" data-return-url="{{ route('ingresos.index') }}">@csrf
<x-ui.field name="ingreso_id" label="Ingreso de salario confirmado" :required="true"><select name="ingreso_id" id="ingreso_id" required><option value="">Selecciona un ingreso</option>@foreach($ingresosSalario as $ing)<option value="{{ $ing->id }}" @selected(old('ingreso_id')===$ing->id)>{{ $ing->fecha_recepcion->format('d/m/Y') }} · {{ $ing->fuente?->nombre }}</option>@endforeach</select></x-ui.field><x-ui.module-status /><x-ui.button type="submit" variant="secondary">Aplicar aportes al ingreso</x-ui.button></form>@endif
</x-ui.card><x-ui.calculation-notice />
</div>
@endsection
