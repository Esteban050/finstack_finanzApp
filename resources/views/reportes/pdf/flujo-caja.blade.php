@php
    $formatValue = require resource_path('views/components/ui/format-value.php');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Flujo de Caja</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #1e293b; margin: 15px; line-height: 1.3; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 12px; }
        .title { font-size: 15px; font-weight: bold; color: #0f172a; margin: 0; }
        .subtitle { font-size: 9px; color: #64748b; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9.5px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; }
        th { background: #1e293b; color: #ffffff; font-weight: bold; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .negative { color: #dc2626; font-weight: bold; }
        .positive { color: #16a34a; }
        .row-total { background: #f1f5f9; font-weight: bold; border-top: 2px solid #0f172a; }
        .note { margin-top: 15px; font-size: 8.5px; color: #64748b; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">SCFP — Reporte de Flujo de Caja Histórico</h1>
        <div class="subtitle">
            Usuario: <strong>{{ $usuario->name ?? $usuario->correo }}</strong> | 
            Periodo: <strong>{{ $reporte['parametros']['rango_texto'] ?? '' }}</strong> (Agrupación: {{ ucfirst($reporte['parametros']['agrupacion'] ?? '') }}) | 
            Generado: {{ now()->format('Y-m-d H:i:s') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Periodo</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
                <th>Total Ingresos</th>
                <th>Total Gastos</th>
                <th>Flujo Neto</th>
                <th>Flujo Acumulado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reporte['periodos'] ?? [] as $p)
                <tr>
                    <td>{{ $p['etiqueta'] }}</td>
                    <td class="text-center">{{ $p['inicio'] }}</td>
                    <td class="text-center">{{ $p['fin'] }}</td>
                    <td class="text-right">$ {{ $formatValue((string) $p['total_ingresos'], 0, ',', '.') }}</td>
                    <td class="text-right">$ {{ $formatValue((string) $p['total_gastos'], 0, ',', '.') }}</td>
                    <td class="text-right {{ $p['es_negativo'] ? 'negative' : 'positive' }}">
                        $ {{ $formatValue((string) $p['flujo_neto'], 0, ',', '.') }}
                    </td>
                    <td class="text-right text-bold">
                        $ {{ $formatValue((string) $p['flujo_acumulado'], 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
            <tr class="row-total">
                <td colspan="3">TOTAL GENERAL</td>
                <td class="text-right">$ {{ $formatValue((string) ($reporte['totales']['total_ingresos'] ?? 0), 0, ',', '.') }}</td>
                <td class="text-right">$ {{ $formatValue((string) ($reporte['totales']['total_gastos'] ?? 0), 0, ',', '.') }}</td>
                <td class="text-right {{ ((string) ($reporte['totales']['balance_neto'] ?? 0)) < 0 ? 'negative' : 'positive' }}">
                    $ {{ $formatValue((string) ($reporte['totales']['balance_neto'] ?? 0), 0, ',', '.') }}
                </td>
                <td class="text-right">$ {{ $formatValue((string) ($reporte['totales']['flujo_acumulado_final'] ?? 0), 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="note">
        <strong>Nota explicativa:</strong> {{ $reporte['nota_explicativa'] ?? '' }}
    </div>
</body>
</html>
