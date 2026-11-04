@extends('layouts.app')
@section('title', 'Pagos PILA')
@section('content')
<div class="finance-page">
<header class="income-page-header"><div><h1>Pagos PILA</h1><p>Registra tus pagos y compáralos con las liquidaciones estimadas.</p></div></header>
<x-ui.card title="Aportes pagados en el año"><dl class="finance-stats"><x-ui.finance-stat label="Salud" :value="$resumenAnual['salud']" /><x-ui.finance-stat label="Pensión" :value="$resumenAnual['pension']" /><x-ui.finance-stat label="Ingreso no constitutivo de renta" :value="$resumenAnual['ingreso_no_constitutivo_pila']" /></dl><p class="ui-help">Incluye únicamente los pagos por PILA del año {{ $anioActual }}.</p></x-ui.card>
<x-ui.card title="Registrar pago"><form method="POST" action="{{ route('seguridad-social.pagos.store') }}" enctype="multipart/form-data" data-finance-form="pila">@csrf
<p class="ui-notice">Al guardar el pago se crea su gasto en Seguridad social. Edita o elimina ese gasto desde aquí para mantener ambos registros relacionados.</p>
<x-ui.pila-fields />
<x-ui.field name="soporte" label="Soporte de pago" help="Opcional. PDF, JPG o PNG de hasta 5 MB."><input id="soporte" type="file" name="soporte" accept=".pdf,.jpg,.jpeg,.png"></x-ui.field>
<x-ui.module-status /><x-ui.button type="submit">Guardar pago</x-ui.button></form></x-ui.card>
<x-ui.card title="Histórico de pagos">
@if($pagos->isEmpty())<x-ui.empty-state title="No tienes pagos PILA registrados" />@else
<table class="ui-table"><caption>Pagos registrados y comparación con la liquidación estimada</caption><thead><tr><th>Periodo</th><th>Fecha de pago</th><th>Planilla</th><th data-align="right">Total pagado</th><th>Comparación</th><th>Soporte</th><th>Acciones</th></tr></thead><tbody>
@foreach($pagosPresentados as $payment)<tr><td>{{ $payment['periodo'] }}@if($payment['es_correccion'])<x-ui.badge>Corrección</x-ui.badge>@endif</td><td><time data-date="{{ substr($payment['fecha_pago'],0,10) }}">{{ substr($payment['fecha_pago'],0,10) }}</time></td><td>{{ $payment['numero_planilla'] ?? '—' }}</td><td class="ui-number"><strong data-money="{{ $payment['total'] }}">{{ $payment['total'] }}</strong></td><td><x-ui.safe-message :value="$payment['comparacion']['mensaje']" />@if($payment['comparacion']['hay_diferencias'])<dl class="finance-breakdown">@foreach($payment['comparacion']['diferencias'] as $difference)<dt>{{ ucfirst($difference['concepto']) }}</dt><dd data-sign-money="{{ $difference['diferencia'] }}">{{ $difference['diferencia'] }}</dd>@endforeach</dl>@endif</td><td>@if($payment['soporte'])<a href="{{ $payment['soporte']['url_descarga'] }}">Descargar {{ $payment['soporte']['nombre_original'] }}</a><p class="ui-help">El enlace vence en 5 minutos. Recarga para obtener otro.</p>@else Sin soporte @endif</td><td><a href="#payment-{{ $payment['id'] }}">Gestionar pago</a></td></tr>@endforeach
</tbody></table>@endif
<x-ui.pagination :paginator="$pagos" /></x-ui.card>
@foreach($pagosPresentados as $payment)
<x-ui.card id="payment-{{ $payment['id'] }}"><details><summary>Gestionar pago · {{ $payment['periodo'] }} · {{ $payment['numero_planilla'] ?? 'Sin número de planilla' }}</summary>
<form method="POST" action="{{ route('seguridad-social.pagos.update',$payment['id']) }}" data-finance-form="pila">@csrf @method('PUT')<x-ui.pila-fields :payment="$payment" :prefix="'pila-'.$payment['id']" /><x-ui.module-status /><x-ui.button type="submit">Guardar cambios</x-ui.button></form>
<form method="POST" action="{{ route('seguridad-social.pagos.soporte.store',$payment['id']) }}" enctype="multipart/form-data" data-finance-form="pila-support">@csrf<x-ui.field name="soporte" :id="'support-'.$payment['id']" :label="$payment['soporte'] ? 'Reemplazar soporte' : 'Agregar soporte'" :required="true" help="PDF, JPG o PNG de hasta 5 MB."><input id="support-{{ $payment['id'] }}" name="soporte" type="file" accept=".pdf,.jpg,.jpeg,.png" required></x-ui.field><x-ui.module-status /><x-ui.button type="submit" variant="secondary">Guardar soporte</x-ui.button></form>
<div class="ui-inline">@if($payment['soporte'])<form method="POST" action="{{ route('seguridad-social.pagos.soporte.destroy',$payment['id']) }}" data-finance-form="action" data-confirm-description="Se eliminará el soporte de este pago.">@csrf @method('DELETE')<x-ui.module-status /><x-ui.button type="submit" variant="secondary">Eliminar soporte</x-ui.button></form>@endif
<form method="POST" action="{{ route('seguridad-social.pagos.destroy',$payment['id']) }}" data-finance-form="action" data-confirm-date="{{ substr($payment['fecha_pago'],0,10) }}" data-confirm-source="Pago PILA {{ $payment['periodo'] }}" data-confirm-amount="{{ $payment['total'] }}" data-confirm-description="También se eliminará el gasto generado por este pago.">@csrf @method('DELETE')<x-ui.module-status /><x-ui.button type="submit" variant="danger">Eliminar pago</x-ui.button></form></div>
</details></x-ui.card>@endforeach
<x-ui.calculation-notice />
</div>
@endsection
