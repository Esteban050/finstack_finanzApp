@extends('layouts.app')
@section('title', 'Reporte Anual de Retenciones – Inversiones')
@section('content')
<div class="finance-page">
@php
    $labels = require resource_path('views/components/ui/investment-labels.php');
@endphp

<header class="income-page-header">
    <div>
        <a href="{{ route('inversiones.index') }}" class="ui-button" style="text-decoration:none; margin-bottom: 0.5rem; display: inline-block;">&larr; Volver a inversiones</a>
        <h1>Reporte Anual de Retenciones Practicadas (RF-REP-02)</h1>
        <p>Conciliación y cruce de retención en la fuente y GMF con los certificados emitidos por entidades financieras.</p>
    </div>
    <div class="ui-inline" style="gap: 0.5rem; flex-wrap: wrap;">
        <x-ui.button :href="route('inversiones.reportes.retenciones.exportar', ['anio' => $anio])" variant="primary">Exportar a CSV</x-ui.button>
        <x-ui.button :href="route('inversiones.reportes.utilidades', ['anio' => $anio])" variant="secondary">Ver Utilidades</x-ui.button>
        <x-ui.button :href="route('inversiones.portafolio')" variant="secondary">Portafolio</x-ui.button>
    </div>
</header>

<x-ui.card>
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
        <div>
            <span class="ui-help" style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Años con actividad tributaria:</span>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                @foreach($aniosDisponibles as $a)
                    <a href="{{ route('inversiones.reportes.retenciones', ['anio' => $a]) }}" 
                       class="ui-button {{ (int)$a === (int)$anio ? 'ui-button--primary' : 'ui-button--secondary' }}" 
                       style="padding: 0.4rem 0.9rem; font-size: 0.9rem; text-decoration: none; border-radius: 6px; font-weight: {{ (int)$a === (int)$anio ? '700' : '500' }};">
                        Año {{ $a }} {{ (int)$a === (int)$anio ? '✓' : '' }}
                    </a>
                @endforeach
            </div>
        </div>

        <form method="GET" action="{{ route('inversiones.reportes.retenciones') }}" class="ui-inline" style="align-items: flex-end; margin: 0;">
            <x-ui.field name="anio" label="Otro año">
                <input type="number" name="anio" min="2000" max="2100" value="{{ $anio }}" style="width: 100px;">
            </x-ui.field>
            <x-ui.button type="submit" variant="secondary">Ir</x-ui.button>
        </form>
    </div>
</x-ui.card>

@if($movimientos->isEmpty() && count($aniosDisponibles) > 1)
    <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(37, 99, 235, 0.08); border: 1px solid rgba(37, 99, 235, 0.25); margin-bottom: 1.25rem;">
        <p style="margin: 0; color: #1e40af; font-size: 0.95rem;">
            ℹ️ No tienes retenciones practicadas en el año <strong>{{ $anio }}</strong>. Recuerda que si simulaste la redención futura de un CDT u otra operación programada, los descuentos se registraron en su año de vencimiento:
            @foreach($aniosDisponibles as $a)
                @if((int)$a !== (int)$anio)
                    <a href="{{ route('inversiones.reportes.retenciones', ['anio' => $a]) }}" style="font-weight: 700; text-decoration: underline; color: #1e40af; margin-left: 0.25rem;">Ver Año {{ $a }} &rarr;</a>
                @endif
            @endforeach
        </p>
    </div>
@endif

<!-- TARJETAS DE TOTALES ANUALES -->
<div class="finance-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
    <x-ui.card>
        <span class="ui-help">Total Bruto Sujeto a Descuento</span>
        <h2 style="margin: 0.25rem 0;">$ {{ number_format((float) $totales['total_bruto'], 2) }}</h2>
        <span class="ui-help">Año gravable {{ $anio }}</span>
    </x-ui.card>

    <x-ui.card>
        <span class="ui-help">Retención en la Fuente Practicada</span>
        <h2 style="margin: 0.25rem 0; color: #dc2626;">-$ {{ number_format((float) $totales['total_retencion'], 2) }}</h2>
        <span class="ui-help">Anticipo acreditable en renta</span>
    </x-ui.card>

    <x-ui.card>
        <span class="ui-help">GMF 4x1000 Total Descontado</span>
        <h2 style="margin: 0.25rem 0; color: #d97706;">-$ {{ number_format((float) $totales['total_gmf'], 2) }}</h2>
        <span class="ui-help">Gravamen en retiros bancarios</span>
    </x-ui.card>

    <x-ui.card>
        <span class="ui-help">Neto Efectivamente Desembolsado</span>
        <h2 style="margin: 0.25rem 0; color: #16a34a;">$ {{ number_format((float) $totales['total_neto'], 2) }}</h2>
        <span class="ui-help">Recibido en cuenta</span>
    </x-ui.card>
</div>

<!-- RESUMEN POR ENTIDAD FINANCIERA (PARA CRUCE CON CERTIFICADOS) -->
<x-ui.card title="Resumen por Entidad Financiera (Para cruce con Certificados de Retención)">
    @if($resumenPorEntidad->isEmpty())
        <x-ui.empty-state title="No se registraron retenciones ni GMF en el año {{ $anio }}" />
    @else
        <table class="ui-table">
            <thead>
                <tr>
                    <th>Entidad Emisora</th>
                    <th data-align="center">Transacciones</th>
                    <th data-align="right">Total Rendimientos / Bruto</th>
                    <th data-align="right">Retención Practicada</th>
                    <th data-align="right">GMF Descontado</th>
                    <th data-align="right">Total Neto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resumenPorEntidad as $ent)
                    <tr>
                        <td><strong>{{ $ent['entidad'] }}</strong></td>
                        <td style="text-align: center;">{{ $ent['transacciones_count'] }}</td>
                        <td class="ui-number">$ {{ number_format((float) $ent['total_bruto'], 2) }}</td>
                        <td class="ui-number" style="color: #dc2626; font-weight: 600;">-$ {{ number_format((float) $ent['total_retencion'], 2) }}</td>
                        <td class="ui-number" style="color: #d97706;">-$ {{ number_format((float) $ent['total_gmf'], 2) }}</td>
                        <td class="ui-number" style="font-weight: 600;">$ {{ number_format((float) $ent['total_neto'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-ui.card>

<!-- RESUMEN POR PRODUCTO FINANCIERO -->
<x-ui.card title="Resumen por Tipo de Producto Financiero">
    @if($resumenPorProducto->isEmpty())
        <x-ui.empty-state title="Sin datos por producto" />
    @else
        <table class="ui-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th data-align="right">Monto Bruto</th>
                    <th data-align="right">Retención en la Fuente</th>
                    <th data-align="right">GMF (4x1000)</th>
                    <th data-align="right">Neto Percibido</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resumenPorProducto as $prod)
                    <tr>
                        <td><x-ui.badge tone="primary">{{ $labels[$prod['tipo']] ?? $prod['tipo'] }}</x-ui.badge></td>
                        <td class="ui-number">$ {{ number_format((float) $prod['total_bruto'], 2) }}</td>
                        <td class="ui-number" style="color: #dc2626;">-$ {{ number_format((float) $prod['total_retencion'], 2) }}</td>
                        <td class="ui-number" style="color: #d97706;">-$ {{ number_format((float) $prod['total_gmf'], 2) }}</td>
                        <td class="ui-number" style="font-weight: 600;">$ {{ number_format((float) $prod['total_neto'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-ui.card>

<!-- DETALLE CRONOLÓGICO DE RETENCIONES DEL AÑO -->
<x-ui.card title="Detalle Cronológico de Retenciones y GMF en {{ $anio }}">
    @if($movimientos->isEmpty())
        <x-ui.empty-state title="No hay movimientos con retención registrados en {{ $anio }}" />
    @else
        <table class="ui-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Inversión</th>
                    <th>Entidad</th>
                    <th>Transacción</th>
                    <th data-align="right">Bruto (COP)</th>
                    <th data-align="right">Retención</th>
                    <th data-align="right">GMF</th>
                    <th data-align="right">Neto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($movimientos as $m)
                    <tr>
                        <td>{{ $m->fecha->format('d/m/Y') }}</td>
                        <td><a href="{{ route('inversiones.show', $m->inversion_id) }}"><strong>{{ $m->inversion?->nombre }}</strong></a></td>
                        <td>{{ $m->inversion?->entidadFinanciera?->nombre ?? $m->inversion?->entidad ?? '—' }}</td>
                        <td><x-ui.badge tone="neutral">{{ $labels[$m->tipo] ?? $m->tipo }}</x-ui.badge></td>
                        <td class="ui-number">$ {{ number_format((float) ($m->monto_bruto ?? $m->monto), 2) }}</td>
                        <td class="ui-number" style="color: #dc2626;">
                            {{ (float)$m->retencion_aplicada > 0 ? '-$' . number_format((float)$m->retencion_aplicada, 2) : '—' }}
                        </td>
                        <td class="ui-number" style="color: #d97706;">
                            {{ (float)$m->gmf_aplicado > 0 ? '-$' . number_format((float)$m->gmf_aplicado, 2) : '—' }}
                        </td>
                        <td class="ui-number" style="font-weight: 600;">$ {{ number_format((float) $m->monto, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-ui.card>

<x-ui.calculation-notice />
</div>
@endsection
