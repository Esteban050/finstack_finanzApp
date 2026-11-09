@extends('layouts.app')
@section('title', 'Reporte Anual de Utilidades – Inversiones')
@section('content')
<div class="finance-page">
@php
    $labels = require resource_path('views/components/ui/investment-labels.php');
@endphp

<header class="income-page-header">
    <div>
        <a href="{{ route('inversiones.index') }}" class="ui-button" style="text-decoration:none; margin-bottom: 0.5rem; display: inline-block;">&larr; Volver a inversiones</a>
        <h1>Reporte de Utilidad Realizada y Clasificación Fiscal (RF-REP-03)</h1>
        <p>Clasificación tributaria de rendimientos, dividendos y enajenaciones de activos para la declaración de renta.</p>
    </div>
    <div class="ui-inline" style="gap: 0.5rem; flex-wrap: wrap;">
        <x-ui.button :href="route('inversiones.reportes.utilidades.exportar', ['anio' => $anio])" variant="primary">Exportar a CSV</x-ui.button>
        <x-ui.button :href="route('inversiones.reportes.retenciones', ['anio' => $anio])" variant="secondary">Ver Retenciones</x-ui.button>
        <x-ui.button :href="route('inversiones.portafolio')" variant="secondary">Portafolio</x-ui.button>
    </div>
</header>

<x-ui.card>
    <form method="GET" action="{{ route('inversiones.reportes.utilidades') }}" class="ui-inline" style="align-items: flex-end;">
        <x-ui.field name="anio" label="Año Gravable a Consultar">
            <input type="number" name="anio" min="2000" max="2100" value="{{ $anio }}" style="width: 120px;">
        </x-ui.field>
        <x-ui.button type="submit" variant="secondary">Consultar Año</x-ui.button>
    </form>
</x-ui.card>

<!-- RESUMEN FISCAL DEL AÑO -->
<div class="finance-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
    <x-ui.card>
        <span class="ui-help">Total Ingresos / Utilidades Brutas</span>
        <h2 style="margin: 0.25rem 0; color: var(--color-primary);">$ {{ number_format((float) $totales['utilidad_total'], 2) }}</h2>
        <span class="ui-help">Año gravable {{ $anio }}</span>
    </x-ui.card>

    <x-ui.card>
        <span class="ui-help">Total Retenciones Imputables</span>
        <h2 style="margin: 0.25rem 0; color: #dc2626;">-$ {{ number_format((float) $totales['retencion_total'], 2) }}</h2>
        <span class="ui-help">Retenciones practicadas</span>
    </x-ui.card>

    <x-ui.card>
        <span class="ui-help">Total Valores Deducibles Generados</span>
        <h2 style="margin: 0.25rem 0; color: #16a34a;">$ {{ number_format((float) $totales['deducibles_total'], 2) }}</h2>
        <span class="ui-help">Aportes FPV + Cesantías exentas + Art. 36-1</span>
    </x-ui.card>
</div>

<!-- DETALLE DE INGRESOS GENERADOS EN INGRESO_INVERSION -->
<x-ui.card title="Ingresos de Capital Registrados (ingreso_inversion)">
    <p class="ui-help" style="margin-bottom: 0.75rem;">Estos ingresos alimentan directamente el módulo de Ingresos para la declaración de renta (Sección 9.3 y 9.5.7).</p>
    @if($ingresos->isEmpty())
        <x-ui.empty-state title="No hay ingresos por inversiones liquidados en {{ $anio }}" />
    @else
        <table class="ui-table">
            <thead>
                <tr>
                    <th>Inversión</th>
                    <th>Tipo Producto</th>
                    <th>Tipo de Ingreso</th>
                    <th data-align="right">Valor Bruto (COP)</th>
                    <th data-align="right">Retención en la Fuente</th>
                    <th data-align="right">Neto Percibido</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ingresos as $ing)
                    <tr>
                        <td><strong>{{ $ing->inversion?->nombre }}</strong></td>
                        <td>{{ $labels[$ing->inversion?->tipo] ?? $ing->inversion?->tipo }}</td>
                        <td><x-ui.badge tone="primary">{{ $ing->tipo }}</x-ui.badge></td>
                        <td class="ui-number">$ {{ number_format((float) $ing->valor_bruto, 2) }}</td>
                        <td class="ui-number" style="color: #dc2626;">
                            {{ (float)$ing->retencion > 0 ? '-$' . number_format((float)$ing->retencion, 2) : '—' }}
                        </td>
                        <td class="ui-number" style="font-weight: 600;">
                            $ {{ number_format((float) bcsub((string) $ing->valor_bruto, (string) $ing->retencion, 2), 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-ui.card>

<!-- DETALLE DE VALORES DEDUCIBLES REGISTRADOS EN VALOR_DEDUCIBLE_REGISTRO -->
<x-ui.card title="Valores Deducibles y Rentas Exentas (valor_deducible_registro)">
    <p class="ui-help" style="margin-bottom: 0.75rem;">Conceptos transferidos al liquidador tributario de Ingresos (Sección 9.3 y 9.5.6).</p>
    @if($deducibles->isEmpty())
        <x-ui.empty-state title="No hay valores deducibles registrados en {{ $anio }}" />
    @else
        <table class="ui-table">
            <thead>
                <tr>
                    <th>Inversión</th>
                    <th>Tipo Producto</th>
                    <th>Concepto Deducible</th>
                    <th data-align="right">Monto Deducible (COP)</th>
                    <th>Norma Legal / Beneficio</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deducibles as $ded)
                    <tr>
                        <td><strong>{{ $ded->inversion?->nombre }}</strong></td>
                        <td>{{ $labels[$ded->inversion?->tipo] ?? $ded->inversion?->tipo }}</td>
                        <td><x-ui.badge tone="{{ (float)$ded->valor < 0 ? 'warning' : 'success' }}">{{ $ded->concepto }}</x-ui.badge></td>
                        <td class="ui-number" style="font-weight: 600; color: {{ (float)$ded->valor < 0 ? '#dc2626' : '#16a34a' }};">
                            $ {{ number_format((float)$ded->valor, 2) }}
                        </td>
                        <td class="ui-help">
                            @if($ded->concepto === 'APORTE_FPV')
                                Art. 126-1 ET: Renta exenta de ahorro voluntario pensional
                            @elseif($ded->concepto === 'CESANTIAS_EXENTA')
                                Art. 206 Num. 4 ET: Exención por tabla de salarios UVT
                            @elseif($ded->concepto === 'UTILIDAD_NO_GRAVADA_BVC')
                                Art. 36-1 ET: Utilidad en enajenación de acciones BVC ≤ 3%
                            @elseif($ded->concepto === 'RETENCION_DIVIDENDOS')
                                Art. 242 ET: Descuento tributario de retención practicada
                            @elseif($ded->concepto === 'AJUSTE_FPV_RETIRO')
                                Reversión deducible por retiro anticipado sin permanencia
                            @else
                                Deducción tributaria autorizada
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-ui.card>

<x-ui.calculation-notice />
</div>
@endsection
