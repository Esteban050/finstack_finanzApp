@extends('layouts.app')
@section('title', 'Portafolio')
@section('content')
<div class="finance-page">
@php
    $labels = require resource_path('views/components/ui/investment-labels.php');
@endphp
<header class="income-page-header">
    <div>
        <h1>Portafolio</h1>
        <p>Valor y rentabilidad de tus inversiones a la fecha de corte.</p>
    </div>
    <div class="ui-inline" style="gap: 0.5rem; flex-wrap: wrap;">
        <x-ui.button :href="route('inversiones.index')" variant="secondary">Ver inversiones</x-ui.button>
        <x-ui.button :href="route('inversiones.reportes.retenciones')" variant="secondary">Reporte Retenciones</x-ui.button>
        <x-ui.button :href="route('inversiones.reportes.utilidades')" variant="secondary">Reporte Utilidades</x-ui.button>
    </div>
</header>

<x-ui.card>
    <form method="GET" action="{{ route('inversiones.portafolio') }}" class="ui-inline">
        <x-ui.date-input name="fecha_corte" label="Fecha de corte" :value="$fechaCorte" />
        <x-ui.button type="submit">Consultar</x-ui.button>
    </form>
</x-ui.card>

@if(collect($portafolio['detalle'])->contains(fn($row) => !in_array($row['moneda'] ?? 'COP', ['COP', 'USD'], true)))
    <p class="ui-notice" role="status">El portafolio contiene registros históricos fuera de la moneda admitida (COP y USD). Falta confirmar su tratamiento antes de mostrar los totales.</p>
@else
    <x-ui.card title="Resumen del portafolio">
        <dl class="finance-stats">
            <x-ui.finance-stat label="Valor total" :value="$portafolio['valor_total']" />
            <x-ui.finance-stat label="Capital aportado neto" :value="$portafolio['capital_aportado_neto']" />
            <x-ui.finance-stat label="Ganancia o pérdida" :value="$portafolio['ganancia']" format="sign-money" />
            <x-ui.finance-stat label="ROI" :value="$portafolio['roi']" format="percent" />
            <x-ui.finance-stat label="XIRR" :value="$portafolio['xirr']" format="percent" />
        </dl>
    </x-ui.card>

    @if(empty($portafolio['detalle']))
        <x-ui.empty-state title="No hay inversiones en el portafolio para esta fecha" />
    @else
        <div class="finance-grid">
            @foreach(['por_tipo' => 'Distribución por tipo', 'por_riesgo' => 'Distribución por riesgo'] as $key => $title)
                @php
                    $items = $portafolio['distribucion'][$key] ?? [];
                    $chartData = collect($items)->map(fn($item) => [
                        'key' => $item['clave'],
                        'label' => $labels[$item['clave']] ?? $item['clave'],
                        'value' => (float)$item['valor'],
                        'percentage' => (float)$item['porcentaje'],
                    ])->values();
                @endphp
                <x-ui.card :title="$title">
                    @if(empty($items))
                        <p class="ui-help">Sin datos para esta distribución.</p>
                    @else
                        <div class="portfolio-pie-card" data-portfolio-distribution="{{ $key }}">
                            <div class="portfolio-pie-wrapper">
                                <canvas data-portfolio-pie="{{ $key }}" role="img" aria-label="{{ $title }}; gráfica interactiva de pastel"></canvas>
                            </div>
                            <script type="application/json" id="pie-data-{{ $key }}">@json($chartData)</script>
                            <ul class="portfolio-pie-legend" data-portfolio-legend="{{ $key }}">
                                @foreach($items as $idx => $item)
                                    <li class="portfolio-pie-item" data-key="{{ $item['clave'] }}" data-index="{{ $idx }}">
                                        <div class="portfolio-pie-item-info">
                                            <span class="portfolio-pie-badge" data-badge-index="{{ $idx }}"></span>
                                            <span class="portfolio-pie-item-name">{{ $labels[$item['clave']] ?? $item['clave'] }}</span>
                                        </div>
                                        <div class="portfolio-pie-item-values">
                                            <strong class="portfolio-pie-item-pct">{{ number_format((float)$item['porcentaje'], 2, ',', '.') }} %</strong>
                                            <span class="portfolio-pie-item-val" data-money="{{ $item['valor'] }}">{{ $item['valor'] }}</span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </x-ui.card>
            @endforeach
        </div>

        <x-ui.card title="Detalle de inversiones">
            <table class="ui-table">
                <caption>Valores e indicadores a la fecha de corte</caption>
                <thead>
                    <tr>
                        <th>Inversión</th>
                        <th>Tipo / Riesgo</th>
                        <th data-align="right">Valor actual</th>
                        <th data-align="right">Participación</th>
                        <th data-align="right">ROI</th>
                        <th data-align="right">XIRR</th>
                        <th>Última valoración</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($portafolio['detalle'] as $inv)
                        <tr>
                            <td><a href="{{ route('inversiones.show', $inv['id']) }}">{{ $inv['nombre'] }}</a></td>
                            <td>{{ $labels[$inv['tipo']] }} · {{ $labels[$inv['nivel_riesgo'] ?? 'SIN_DECLARAR'] }}</td>
                            <td class="ui-number"><span data-money="{{ $inv['valor_actual_cop'] }}">{{ $inv['valor_actual_cop'] }}</span></td>
                            <td class="ui-number"><span data-percent="{{ $inv['porcentaje_portafolio'] }}">{{ $inv['porcentaje_portafolio'] }}</span></td>
                            <td class="ui-number"><span data-percent="{{ $inv['roi'] }}">{{ $inv['roi'] ?? 'No calculable' }}</span></td>
                            <td class="ui-number"><span data-percent="{{ $inv['xirr'] }}">{{ $inv['xirr'] }}</span></td>
                            <td>
                                @if($inv['fecha_ultima_valoracion'])
                                    <time data-date="{{ $inv['fecha_ultima_valoracion'] }}">{{ $inv['fecha_ultima_valoracion'] }}</time>
                                @else
                                    Sin valoración
                                @endif
                                @if($inv['valoracion_desactualizada'])
                                    <x-ui.badge tone="warning">Valoración desactualizada</x-ui.badge>
                                @endif
                            </td>
                            <td>{{ $labels[$inv['estado']] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2">Valor total del portafolio</th>
                        <td class="ui-number"><strong data-money="{{ $portafolio['valor_total'] }}">{{ $portafolio['valor_total'] }}</strong></td>
                        <td colspan="5"></td>
                    </tr>
                </tfoot>
            </table>
        </x-ui.card>
    @endif
@endif
<x-ui.calculation-notice />
</div>
@endsection
