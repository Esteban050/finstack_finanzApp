@extends('layouts.app')
@section('title', 'Liquidaciones del independiente')
@section('content')
<div class="finance-page">
<header class="income-page-header"><div><h1>Liquidaciones del independiente</h1><p>Consulta el ingreso base, el IBC y el desglose de aportes mensuales.</p></div></header>
<div class="finance-grid">
<x-ui.card title="Ingresos registrados"><form method="POST" action="{{ route('seguridad-social.liquidaciones.store') }}" data-finance-form="liquidation" data-finance-result="liquidation-result">@csrf
<p class="ui-help">Se usan los ingresos confirmados de rentas de trabajo no asalariado del periodo seleccionado.</p>
<x-ui.field name="periodo" label="Periodo a liquidar" :required="true"><input id="periodo" name="periodo" type="month" required value="{{ old('periodo', now('America/Bogota')->format('Y-m')) }}"></x-ui.field>
<label class="ui-check"><input name="es_voluntario" type="checkbox" value="1" @checked(old('es_voluntario'))> Cotización voluntaria</label>
<x-ui.module-status /><x-ui.button type="submit">Calcular y guardar liquidación estimada</x-ui.button></form></x-ui.card>
<x-ui.card title="Simulación manual"><form method="POST" action="{{ route('seguridad-social.liquidaciones.simular') }}" data-finance-form="simulation-income" data-finance-result="liquidation-result">@csrf
<p class="ui-help">La simulación no reemplaza ni altera la liquidación guardada.</p>
<x-ui.field name="periodo" id="sim-period" label="Periodo" :required="true"><input id="sim-period" name="periodo" type="month" required value="{{ old('periodo', now('America/Bogota')->format('Y-m')) }}"></x-ui.field>
<x-ui.money-input name="ingreso_base" label="Ingreso bruto mensual" :allow-zero="true" />
<label class="ui-check"><input name="es_voluntario" type="checkbox" value="1" @checked(old('es_voluntario'))> Cotización voluntaria</label>
<x-ui.module-status /><x-ui.button type="submit" variant="secondary">Simular aportes</x-ui.button></form></x-ui.card>
</div>
@php($recent = session('calculo_reciente'))
<x-ui.card id="liquidation-result" title="Resultado estimado" :hidden="!$recent" tabindex="-1"><div data-result-notices role="status">@foreach(($recent['avisos'] ?? []) as $notice)<p><x-ui.safe-message :value="$notice" /></p>@endforeach</div><dl class="finance-stats"><div class="finance-stat"><dt>Ingreso base</dt><dd data-result-key="ingreso_base" @if($recent) data-money="{{ $recent['ingreso_base'] }}" @endif>{{ $recent['ingreso_base'] ?? '—' }}</dd></div><div class="finance-stat"><dt>IBC</dt><dd data-result-key="ibc" @if($recent) data-money="{{ $recent['ibc'] }}" @endif>{{ $recent['ibc'] ?? '—' }}</dd></div><div class="finance-stat"><dt>Total de aportes</dt><dd data-result-key="total" @if($recent) data-money="{{ $recent['total'] }}" @endif>{{ $recent['total'] ?? '—' }}</dd></div></dl><dl class="finance-breakdown">@foreach(['salud'=>'Salud','pension'=>'Pensión','fsp'=>'Fondo de Solidaridad Pensional','arl'=>'Riesgos laborales'] as $key=>$label)<dt>{{ $label }}</dt><dd data-result-key="{{ $key }}" @if($recent) data-money="{{ $recent[$key] }}" @endif>{{ $recent[$key] ?? '—' }}</dd>@endforeach</dl><div data-result-parameters>@if($recent)<dl class="finance-breakdown">@foreach(($recent['parametros_usados'] ?? []) as $key=>$value)@if(is_scalar($value))<dt>{{ ucfirst(str_replace('_',' ', $key)) }}</dt><dd>{{ is_bool($value) ? ($value ? 'Sí' : 'No') : $value }}</dd>@endif @endforeach</dl>@endif</div><x-ui.calculation-notice /></x-ui.card>
<x-ui.card title="Histórico de liquidaciones estimadas">
@if($liquidaciones->isEmpty())<x-ui.empty-state title="No tienes liquidaciones estimadas guardadas" />@else
<table class="ui-table"><caption>Liquidaciones guardadas, de la más reciente a la más antigua</caption><thead><tr><th>Periodo</th><th>Origen</th>@foreach(['Ingreso base','IBC','Salud','Pensión','FSP','ARL','Total'] as $label)<th data-align="right">{{ $label }}</th>@endforeach<th>Parámetros</th></tr></thead><tbody>
@foreach($liquidaciones as $liq)<tr><td>{{ $liq->periodo }}</td><td>{{ $liq->origen_ingreso === 'MANUAL' ? 'Manual' : 'Ingresos registrados' }}</td>@foreach(['ingreso_base','ibc','salud','pension','fsp','arl','total'] as $key)<td class="ui-number"><span data-money="{{ $liq->$key }}">{{ $liq->$key }}</span></td>@endforeach<td><details><summary>Ver parámetros usados</summary><dl class="finance-breakdown">@foreach(($liq->parametros_usados ?? []) as $key=>$value)@if(is_scalar($value))<dt>{{ ucfirst(str_replace('_',' ', $key)) }}</dt><dd>{{ is_bool($value) ? ($value ? 'Sí' : 'No') : $value }}</dd>@endif @endforeach</dl></details></td></tr>@endforeach
</tbody></table>@endif
<x-ui.pagination :paginator="$liquidaciones" /></x-ui.card>
<x-ui.calculation-notice />
</div>
@endsection
