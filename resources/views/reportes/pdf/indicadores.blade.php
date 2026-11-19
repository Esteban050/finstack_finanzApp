<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico de Salud Financiera</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1e293b; margin: 20px; line-height: 1.4; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 15px; }
        .title { font-size: 16px; font-weight: bold; color: #0f172a; margin: 0; }
        .subtitle { font-size: 10px; color: #64748b; margin-top: 4px; }
        .card { border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 10px; padding: 10px; page-break-inside: avoid; }
        .card-header { font-size: 12px; font-weight: bold; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 5px; margin-bottom: 8px; display: flex; justify-content: space-between; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; text-transform: uppercase; background: #e2e8f0; color: #334155; }
        .badge.OPTIMO, .badge.EN_CONTROL, .badge.VERDE, .badge.DIVERSIFICADO { background: #dcfce7; color: #166534; }
        .badge.MODERADO, .badge.EN_RIESGO, .badge.AMARILLO { background: #fef9c3; color: #854d0e; }
        .badge.DEFICIT, .badge.EXCEDIDO, .badge.ROJO, .badge.ALTA { background: #fee2e2; color: #991b1b; }
        .metric-value { font-size: 14px; font-weight: bold; margin-bottom: 6px; color: #0f172a; }
        .details-table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 10px; }
        .details-table td { padding: 3px 0; }
        .details-table td:last-child { text-align: right; font-weight: bold; }
        .interpretation { font-style: italic; color: #475569; font-size: 9.5px; margin-top: 6px; }
        .legal-notice { margin-top: 25px; padding: 8px; background: #f8fafc; border-left: 3px solid #64748b; font-size: 9px; color: #475569; }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">SCFP — Diagnóstico de Indicadores de Salud Financiera</h1>
        <div class="subtitle">
            Usuario: <strong>{{ $usuario->name ?? $usuario->correo }}</strong> | 
            Mes evaluado: <strong>{{ $reporte['mes_nombre'] ?? $reporte['mes'] }}</strong> | 
            Generado: {{ now()->format('Y-m-d H:i:s') }}
        </div>
    </div>

    @foreach($reporte['indicadores'] ?? [] as $ind)
        <div class="card">
            <div class="card-header">
                <span>{{ $ind['nombre'] }}</span>
                @if(!empty($ind['etiqueta_estado']))
                    <span class="badge {{ $ind['estado'] ?? '' }}">{{ $ind['etiqueta_estado'] }}</span>
                @endif
            </div>
            <div class="metric-value">
                {{ $ind['valor_formateado'] ?? ($ind['valor'] ?? 'N/A') }}
            </div>
            @if(!empty($ind['detalle']['variables']))
                <table class="details-table">
                    @foreach($ind['detalle']['variables'] as $varNombre => $varValor)
                        <tr>
                            <td>{{ $varNombre }}</td>
                            <td>{{ $varValor }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
            @if(!empty($ind['detalle']['interpretacion']))
                <div class="interpretation">
                    {{ $ind['detalle']['interpretacion'] }}
                </div>
            @endif
        </div>
    @endforeach

    <div class="legal-notice">
        <strong>Aviso Legal (RNG-08):</strong> {{ $reporte['aviso_legal'] }}
    </div>
</body>
</html>
