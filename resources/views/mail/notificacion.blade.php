@php
    // Correo: estilos en línea y maquetación con tablas, que es lo que respetan Gmail y Outlook.
    $tonos = [
        'CRITICA' => ['fondo' => '#FEE2E2', 'texto' => '#991B1B', 'borde' => '#B91C1C', 'etiqueta' => 'Crítica'],
        'ADVERTENCIA' => ['fondo' => '#FEF3C7', 'texto' => '#92400E', 'borde' => '#B45309', 'etiqueta' => 'Advertencia'],
        'INFORMATIVA' => ['fondo' => '#F1F5F9', 'texto' => '#334155', 'borde' => '#64748B', 'etiqueta' => 'Informativa'],
    ];
    $tono = $tonos[$prioridad] ?? $tonos['INFORMATIVA'];
    $colorCifra = ['danger' => '#B91C1C', 'success' => '#15803D', 'warning' => '#B45309'];
    $colorBarra = ['danger' => '#B91C1C', 'warning' => '#D97706', 'success' => '#15803D'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $asunto }}</title>
</head>
<body style="margin:0;padding:0;background:#F8FAFC;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0F172A;">
{{-- Texto de vista previa en la bandeja de entrada --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $detalle }}</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F8FAFC;">
<tr><td align="center" style="padding:32px 16px;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;">
        {{-- Marca --}}
        <tr><td style="padding:0 4px 20px;">
            <table role="presentation" cellspacing="0" cellpadding="0"><tr>
                <td style="width:36px;height:36px;background:#C2410C;border-radius:10px;color:#FFFFFF;font-weight:700;font-size:18px;text-align:center;vertical-align:middle;">F</td>
                <td style="padding-left:12px;">
                    <div style="font-size:18px;font-weight:700;color:#C2410C;line-height:1.2;">FinanzApp</div>
                    <div style="font-size:13px;color:#64748B;line-height:1.4;">Control financiero personal</div>
                </td>
            </tr></table>
        </td></tr>

        {{-- Tarjeta principal --}}
        <tr><td style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:16px;overflow:hidden;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                <tr><td style="height:6px;background:{{ $tono['borde'] }};font-size:0;line-height:0;">&nbsp;</td></tr>
                <tr><td style="padding:28px 28px 8px;">
                    <span style="display:inline-block;padding:4px 10px;border-radius:999px;background:{{ $tono['fondo'] }};color:{{ $tono['texto'] }};font-size:13px;font-weight:600;">Alerta {{ mb_strtolower($tono['etiqueta']) }}</span>
                    <p style="margin:20px 0 4px;font-size:16px;color:#334155;">Hola {{ $nombre }},</p>
                    <h1 style="margin:0 0 12px;font-size:24px;line-height:1.3;color:#0F172A;font-weight:700;">{{ $titulo }}</h1>
                    <p style="margin:0;font-size:16px;line-height:1.6;color:#334155;">{{ $detalle }}</p>
                </td></tr>

                @if($cifras)
                <tr><td style="padding:20px 28px 4px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#FFF7ED;border-radius:12px;">
                        <tr>
                        @foreach($cifras as $cifra)
                            <td valign="top" style="padding:16px;{{ $loop->first ? '' : 'border-left:1px solid #FED7AA;' }}">
                                <div style="font-size:13px;color:#7C2D12;margin-bottom:6px;">{{ $cifra['etiqueta'] }}</div>
                                <div style="font-size:20px;font-weight:700;color:{{ $colorCifra[$cifra['tono'] ?? ''] ?? '#0F172A' }};white-space:nowrap;">{{ $cifra['valor'] }}</div>
                            </td>
                        @endforeach
                        </tr>
                    </table>
                </td></tr>
                @endif

                @if($barra)
                <tr><td style="padding:16px 28px 0;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#E2E8F0;border-radius:999px;">
                        <tr><td style="font-size:0;line-height:0;">
                            <table role="presentation" width="{{ max(2, $barra['ancho']) }}%" cellspacing="0" cellpadding="0"><tr>
                                <td style="height:10px;background:{{ $colorBarra[$barra['tono']] ?? '#C2410C' }};border-radius:999px;font-size:0;line-height:0;">&nbsp;</td>
                            </tr></table>
                        </td></tr>
                    </table>
                    <p style="margin:8px 0 0;font-size:13px;color:#64748B;">{{ $barra['texto'] }}</p>
                </td></tr>
                @endif

                <tr><td style="padding:28px 28px 32px;">
                    <table role="presentation" cellspacing="0" cellpadding="0"><tr>
                        <td style="background:#C2410C;border-radius:12px;">
                            <a href="{{ $url }}" style="display:inline-block;padding:14px 24px;color:#FFFFFF;font-size:16px;font-weight:600;text-decoration:none;">Ver en FinanzApp</a>
                        </td>
                    </tr></table>
                </td></tr>
            </table>
        </td></tr>

        {{-- Pie --}}
        <tr><td style="padding:20px 8px 0;font-size:13px;line-height:1.6;color:#64748B;">
            <p style="margin:0 0 8px;">{{ $motivo }} Puedes cambiarlo en <a href="{{ $urlAlertas }}" style="color:#C2410C;">Configurar alertas</a>.</p>
            <p style="margin:0;">Enviado el {{ $fecha }} · Valores informativos: no constituyen asesoría tributaria, contable ni financiera.</p>
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
