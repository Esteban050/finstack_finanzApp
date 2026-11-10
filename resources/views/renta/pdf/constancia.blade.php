<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Constancia de Evaluación de Renta {{ $anio }} - {{ $usuario->numero_documento }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.5cm;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 13px;
            line-height: 1.45;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .hdr {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #ea580c;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .hdr h2 {
            margin: 4px 0 2px 0;
            font-size: 18px;
            color: #111827;
        }
        .hdr strong {
            color: #c2410c;
            font-size: 14px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .hdr .meta-right {
            text-align: right;
            font-size: 11px;
            color: #4b5563;
        }
        .verdict-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 14px;
        }
        .verdict-box.success {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }
        .verdict-box.warning {
            background: #fffbeb;
            border-color: #fde68a;
            color: #92400e;
        }
        .verdict-title {
            font-size: 15px;
            font-weight: 700;
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 16px;
            font-size: 12px;
        }
        th, td {
            padding: 7px 9px;
            border: 1px solid #e5e7eb;
            text-align: left;
        }
        th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        td.num, th.num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 10.5px;
            font-weight: 600;
            border-radius: 4px;
        }
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-success {
            background: #dcfce7;
            color: #166534;
        }
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-neutral {
            background: #f3f4f6;
            color: #4b5563;
        }
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 14px;
            font-size: 12px;
            line-height: 1.5;
        }
        .disclaimer-box {
            font-size: 11px;
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            background: #f9fafb;
            color: #4b5563;
            border-radius: 4px;
            margin-top: 14px;
        }
        .ftr {
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            font-size: 10.5px;
            color: #6b7280;
        }
    </style>
</head>
<body>

<div class="hdr">
    <div>
        <strong>FinanzApp · SCFP</strong>
        <h2>Constancia de evaluación y estimación de declaración de renta</h2>
        <span style="color:#4b5563">Año gravable {{ $anio }} · Contribuyente: {{ $usuario->name ?? $usuario->nombre ?? 'Usuario' }} (Doc: {{ $usuario->numero_documento ?? 'No registrado' }})</span>
    </div>
    <div class="meta-right">
        Generada el {{ now()->format('d/m/Y, h:i a') }}<br>
        Hora legal de Colombia
    </div>
</div>

{{-- BANNER DE RESULTADO --}}
@php
    $veredicto = $evaluacion['veredicto'];
    $isObligado = ($veredicto === 'OBLIGADO');
    $isIncompleto = ($veredicto === 'INCOMPLETO');
    $isNoObligado = ($veredicto === 'NO_OBLIGADO');
@endphp

<div class="verdict-box @if($isObligado) danger @elseif($isIncompleto) warning @else success @endif">
    <div class="verdict-title">RESULTADO: {{ $evaluacion['veredicto_label'] }}</div>
    <div style="font-size:12px">
        @if($isObligado)
            @if($evaluacion['criterios']['c6']['cumple'] && $evaluacion['resumen']['criterios_cumplidos'] === 1)
                El contribuyente es responsable de IVA (C6), condición suficiente para estar obligado a declarar.
            @else
                Cumple {{ $evaluacion['resumen']['criterios_cumplidos'] }} de los 6 criterios tributarios (Arts. 592, 593 y 594-3 del E.T.). Basta con cumplir uno solo.
            @endif
        @elseif($isIncompleto)
            Información complementaria pendiente: No se ha confirmado el total de consignaciones bancarias de {{ $anio }} para descartar o confirmar el criterio C5.
        @else
            No se supera ninguno de los topes legales fijados para el año gravable {{ $anio }} (UVT $ {{ number_format($evaluacion['parametros']['uvt'], 0, ',', '.') }}).
        @endif
    </div>
</div>

{{-- TABLA DE CRITERIOS --}}
<table>
    <thead>
        <tr>
            <th>Criterio y condición legal</th>
            <th>Fuente de información</th>
            <th class="num">Valor (COP)</th>
            <th class="num">Valor (UVT)</th>
            <th class="num">Tope legal</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        {{-- C1 --}}
        @php $c1 = $evaluacion['criterios']['c1']; @endphp
        <tr>
            <td>C1 · Patrimonio bruto al 31/12 (&gt; 4.500 UVT)</td>
            <td>Módulo Inversiones (Saldos 31/12)</td>
            <td class="num">$ {{ number_format($c1['valor_cop'], 0, ',', '.') }}</td>
            <td class="num">{{ number_format($c1['valor_uvt'], 2, ',', '.') }}</td>
            <td class="num">&gt; 4.500 UVT</td>
            <td><span class="badge {{ $c1['cumple'] ? 'badge-danger' : ($c1['cerca'] ? 'badge-warning' : 'badge-neutral') }}">{{ $c1['cumple'] ? 'Cumple' : 'No cumple' }}</span></td>
        </tr>

        {{-- C2 --}}
        @php $c2 = $evaluacion['criterios']['c2']; @endphp
        <tr>
            <td><strong>C2 · Ingresos brutos del año (&ge; 1.400 UVT)</strong></td>
            <td>Ingresos + Rendimientos Inversiones</td>
            <td class="num">$ {{ number_format($c2['valor_cop'], 0, ',', '.') }}</td>
            <td class="num">{{ number_format($c2['valor_uvt'], 2, ',', '.') }}</td>
            <td class="num">&ge; 1.400 UVT</td>
            <td><span class="badge {{ $c2['cumple'] ? 'badge-danger' : ($c2['cerca'] ? 'badge-warning' : 'badge-neutral') }}">{{ $c2['cumple'] ? 'Cumple' : 'No cumple' }}</span></td>
        </tr>

        {{-- C3 --}}
        @php $c3 = $evaluacion['criterios']['c3']; @endphp
        <tr>
            <td>C3 · Consumos con tarjeta de crédito (&gt; 1.400 UVT)</td>
            <td>Info. complementaria</td>
            <td class="num">$ {{ number_format($c3['valor_cop'], 0, ',', '.') }}</td>
            <td class="num">{{ number_format($c3['valor_uvt'], 2, ',', '.') }}</td>
            <td class="num">&gt; 1.400 UVT</td>
            <td><span class="badge {{ $c3['cumple'] ? 'badge-danger' : ($c3['cerca'] ? 'badge-warning' : 'badge-neutral') }}">{{ $c3['cumple'] ? 'Cumple' : 'No cumple' }}</span></td>
        </tr>

        {{-- C4 --}}
        @php $c4 = $evaluacion['criterios']['c4']; @endphp
        <tr>
            <td>C4 · Compras y consumos totales (&gt; 1.400 UVT)</td>
            <td>Info. complementaria</td>
            <td class="num">$ {{ number_format($c4['valor_cop'], 0, ',', '.') }}</td>
            <td class="num">{{ number_format($c4['valor_uvt'], 2, ',', '.') }}</td>
            <td class="num">&gt; 1.400 UVT</td>
            <td><span class="badge {{ $c4['cumple'] ? 'badge-danger' : ($c4['cerca'] ? 'badge-warning' : 'badge-neutral') }}">{{ $c4['cumple'] ? 'Cumple' : 'No cumple' }}</span></td>
        </tr>

        {{-- C5 --}}
        @php $c5 = $evaluacion['criterios']['c5']; @endphp
        <tr>
            <td><strong>C5 · Consignaciones, depósitos o inversiones (&gt; 1.400 UVT)</strong></td>
            <td>Inversiones ($ {{ number_format($c5['piso_inversiones_cop'] / 1000000, 1, ',', '.') }}M) + Bancos</td>
            <td class="num">$ {{ number_format($c5['valor_cop'], 0, ',', '.') }}</td>
            <td class="num">{{ number_format($c5['valor_uvt'], 2, ',', '.') }}</td>
            <td class="num">&gt; 1.400 UVT</td>
            <td><span class="badge {{ $c5['cumple'] ? 'badge-danger' : ($c5['cerca'] ? 'badge-warning' : 'badge-neutral') }}">{{ $c5['cumple'] ? 'Cumple' : 'No cumple' }}</span></td>
        </tr>

        {{-- C6 --}}
        @php $c6 = $evaluacion['criterios']['c6']; @endphp
        <tr>
            <td>C6 · Responsable de IVA (Art. 592 num. 2 E.T.)</td>
            <td>Perfil de usuario</td>
            <td class="num">{{ $c6['cumple'] ? 'Sí' : 'No' }}</td>
            <td class="num">—</td>
            <td class="num">= Sí</td>
            <td><span class="badge {{ $c6['cumple'] ? 'badge-danger' : 'badge-neutral' }}">{{ $c6['cumple'] ? 'Cumple' : 'No cumple' }}</span></td>
        </tr>
    </tbody>
</table>

{{-- RESUMEN DE ESTIMACIÓN CÉDULA GENERAL --}}
<div class="summary-box">
    <strong>Resumen de liquidación estimada (Cédula General · Art. 336 y 241 E.T.):</strong><br>
    Ingresos brutos $ {{ number_format($estimacion['paso1']['ingresos_brutos_cop'], 0, ',', '.') }}
    − INCRNGO $ {{ number_format($estimacion['paso2']['incrngo_total_cop'], 0, ',', '.') }}
    (incluye {{ number_format($evaluacion['parametros']['pct_componente_inflacionario'] * 100, 2, ',', '.') }} % de componente inflacionario y beneficio Art. 36-1 E.T.)
    − Rentas exentas aceptadas $ {{ number_format($estimacion['paso5']['total_exentas_aceptadas_cop'], 0, ',', '.') }}
    (aportes FPV, cesantías y 25 % laboral con límite del 40 % o 1.340 UVT)
    = Renta líquida gravable <strong>$ {{ number_format($estimacion['paso6']['rlg_cop'], 0, ',', '.') }}</strong> ({{ number_format($estimacion['paso6']['rlg_uvt'], 2, ',', '.') }} UVT).<br>
    Impuesto sobre la renta según tabla Art. 241 E.T.: $ {{ number_format($estimacion['paso7']['impuesto_redondeado_cop'], 0, ',', '.') }}
    − Retenciones practicadas del año: $ {{ number_format($estimacion['paso8']['retenciones_totales_cop'], 0, ',', '.') }}
    = <strong>{{ $estimacion['paso8']['saldo_label'] }}: $ {{ number_format($estimacion['paso8']['saldo_valor_mostrado_cop'], 0, ',', '.') }}</strong>.
</div>

<div class="disclaimer-box">
    <strong>Aviso legal:</strong> Valores estimados automáticamente por FinanzApp con base en los registros de Ingresos e Inversiones y la información complementaria suministrada por el usuario. Tienen fines estrictamente informativos y de planeación financiera personal, no constituyen asesoría tributaria, contable ni jurídica, ni sustituyen la presentación formal del Formulario 210 ante la Dirección de Impuestos y Aduanas Nacionales (DIAN).
</div>

<div class="ftr">
    <span>FinanzApp · Constancia de evaluación y estimación de renta {{ $anio }}</span>
    <span>Página 1 de 1</span>
</div>

</body>
</html>

