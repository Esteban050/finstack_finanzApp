@extends('layouts.app')
@section('title', 'Vencimiento de la declaración · ' . $anio)
@section('content')
<div class="finance-page renta-page" x-data="{}">
    <header class="income-page-header"><div><h1>Vencimiento de la declaración · {{ $anio }}</h1><p>Consulta tu fecha límite y el estado de presentación.</p></div>@if($vencimiento['estado_declaracion'] !== 'PRESENTADA')<x-ui.button @click="$refs.presentationDialog.showModal()">Marcar declaración presentada</x-ui.button>@else<span class="ui-badge ui-badge--success">Declaración presentada · {{ $vencimiento['fecha_presentacion_formateada'] }}</span>@endif</header>
    <div class="mk-grid-2">
        <x-ui.card title="Fecha límite de presentación"><p class="mk-amount">{{ $vencimiento['fecha_limite_formateada'] ?? 'Pendiente de calendario' }}</p>@if($vencimiento['fecha_limite_formateada'] ?? null)<p>@if($vencimiento['dias_restantes'] > 0)Faltan {{ $vencimiento['dias_restantes'] }} días.@elseif($vencimiento['dias_restantes'] === 0)Vence hoy.@else Venció hace {{ abs($vencimiento['dias_restantes']) }} días.@endif</p>@endif<p class="ui-help">Últimos dígitos del documento: {{ $vencimiento['ultimos_digitos'] }}.</p><span class="ui-badge">{{ ['OBLIGADO'=>'Obligado','INCOMPLETO'=>'Incompleto','NO_OBLIGADO'=>'No obligado'][$vencimiento['estado_evaluacion']] ?? 'Pendiente de evaluación' }}</span></x-ui.card>
        <x-ui.card title="Recordatorios programados"><ol class="mk-tl">@forelse($vencimiento['recordatorios'] as $recordatorio)<li><div><p>{{ $recordatorio['etiqueta'] }}</p><p class="ui-help">{{ $recordatorio['enviado'] ? 'Enviado' : ($recordatorio['programado'] ? 'Programado' : 'Cancelado') }}</p></div><span class="ui-help">{{ $recordatorio['fecha_formateada'] }}</span></li>@empty<li>No hay recordatorios programados.</li>@endforelse</ol></x-ui.card>
    </div>
    <p class="ui-help">Los recordatorios se detienen al marcar la declaración como presentada o cuando la evaluación deja de requerirlos.</p>
    <x-ui.calculation-notice />
    <dialog x-ref="presentationDialog" class="ui-dialog" aria-labelledby="presentation-title" @if($errors->any()) data-open-on-load @endif>
        <h2 id="presentation-title">Marcar la declaración como presentada</h2><p>Año gravable {{ $anio }}. Después de confirmar no recibirás más recordatorios de este año.</p>
        <form action="{{ route('renta.marcarPresentada',['anio'=>$anio]) }}" method="POST">@csrf
            <x-ui.date-input name="fecha_presentacion" label="Fecha de presentación" :value="old('fecha_presentacion',now('America/Bogota')->toDateString())" />
            <x-ui.field name="numero_radicado_dian" label="Número de radicado DIAN" help="Opcional. Máximo 100 caracteres."><input id="numero_radicado_dian" name="numero_radicado_dian" maxlength="100" value="{{ old('numero_radicado_dian','') }}" aria-describedby="numero_radicado_dian-help numero_radicado_dian-error"></x-ui.field>
            <div class="ui-inline"><x-ui.button variant="secondary" @click="$refs.presentationDialog.close()">Cancelar</x-ui.button><x-ui.button type="submit">Confirmar presentación</x-ui.button></div>
        </form>
    </dialog>
</div>
@endsection
