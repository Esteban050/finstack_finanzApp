Hola {{ $nombre }},

{{ $titulo }}

{{ $detalle }}
@if($cifras)

@foreach($cifras as $cifra)
- {{ $cifra['etiqueta'] }}: {{ $cifra['valor'] }}
@endforeach
@endif

Ver en FinanzApp: {{ $url }}

{{ $motivo }} Puedes cambiarlo en Configurar alertas: {{ $urlAlertas }}
Enviado el {{ $fecha }} · Valores informativos: no constituyen asesoría tributaria, contable ni financiera.
