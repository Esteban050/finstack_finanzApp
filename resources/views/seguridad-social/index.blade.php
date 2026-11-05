@extends('layouts.app')
@section('title', 'Seguridad social')
@section('content')
<div class="finance-page">
<header class="income-page-header"><div><h1>Seguridad social</h1><p>Aportes, pagos y fechas límite según tu perfil.</p></div><x-ui.button :href="route('seguridad-social.pagos.index')">Registrar pago PILA</x-ui.button></header>
<x-ui.module-status />
@if($esDobleObligacion)<p class="ui-notice">Tienes ingresos de salario y de trabajo independiente. Consulta por separado los aportes de cada actividad.</p>@elseif($esVoluntario)<p class="ui-notice">Puedes simular una cotización voluntaria según tu perfil.</p>@endif
<div class="finance-grid">
<x-ui.card title="Liquidación del independiente"><p class="finance-card-description">Calcula con tus ingresos registrados o simula con un monto manual.</p><x-ui.button :href="route('seguridad-social.liquidaciones.index')" variant="secondary">Consultar liquidaciones</x-ui.button></x-ui.card>
<x-ui.card title="Aportes del asalariado"><p class="finance-card-description">Consulta el desglose de descuentos y aplica los aportes a un ingreso de salario.</p><x-ui.button :href="route('seguridad-social.asalariado.index')" variant="secondary">Calcular aportes</x-ui.button></x-ui.card>
</div>
<x-ui.card title="Fechas límite de pago PILA">
@if(!$hayCalendario)<p role="status">No hay calendario disponible para esta vigencia.</p>
@elseif($digitos === null)<p role="status">Completa tu perfil para consultar las fechas de pago.</p><a href="{{ route('profile.show') }}">Ir a mi perfil</a>
@elseif(empty($periodos))<x-ui.empty-state title="No hay fechas de pago disponibles" />
@else
<table class="ui-table"><caption>Periodos cotizados y sus fechas límite</caption><thead><tr><th>Periodo cotizado</th><th>Fecha límite</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
@foreach($periodos as $periodo)<tr><td>{{ $periodo['periodo'] }}</td><td><time data-date="{{ $periodo['fecha_limite'] }}">{{ $periodo['fecha_limite'] }}</time></td><td><x-ui.badge :tone="($periodo['estado'] === 'VENCIDO') ? 'danger' : 'neutral'">{{ ucfirst(strtolower(str_replace('_', ' ', $periodo['estado']))) }}</x-ui.badge>@if($periodo['estado'] === 'VENCIDO')<p class="ui-help">Pagar después de la fecha límite puede generar intereses de mora.</p>@endif</td><td><a href="{{ route('seguridad-social.pagos.index') }}">Ver pagos</a></td></tr>@endforeach
</tbody></table>
@endif
</x-ui.card>
<x-ui.card title="Aportes pagados por PILA en el año"><dl class="finance-stats"><x-ui.finance-stat label="Salud" :value="$resumenAnual['salud']" /><x-ui.finance-stat label="Pensión" :value="$resumenAnual['pension']" /><x-ui.finance-stat label="Ingreso no constitutivo de renta" :value="$resumenAnual['ingreso_no_constitutivo_pila']" /></dl><p class="ui-help">Este resumen incluye solo los pagos por PILA; no incluye los aportes descontados del salario.</p></x-ui.card>
<x-ui.calculation-notice />
</div>
@endsection

