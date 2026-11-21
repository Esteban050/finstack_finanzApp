@php
    $formatValue = require resource_path('views/components/ui/format-value.php');
@endphp
@extends('layouts.app')
@section('title', 'Detalle de Inversión – ' . $inversion->nombre)
@section('content')
<div class="finance-page">
@php
    $labels = require resource_path('views/components/ui/investment-labels.php');
    $det = $presentacion['detalle_especifico'] ?? [];
    $vnr = $presentacion['valoracion_no_realizada'] ?? null;

    $diasPeriodo = match(strtoupper($det['periodicidad_pago'] ?? 'MENSUAL')) {
        'MENSUAL'=>30, 'TRIMESTRAL'=>90, 'SEMESTRAL'=>180, 'ANUAL'=>365,
        default=>(int)($det['plazo_dias'] ?? 365),
    };
    $puntosAccion = [];
@endphp

<header class="income-page-header">
    <div>
        <a href="{{ route('inversiones.index') }}" class="ui-button" style="text-decoration:none; margin-bottom: 0.5rem; display: inline-block;">&larr; Volver a inversiones</a>
        <h1>{{ $inversion->nombre }}</h1>
        <p>
            <x-ui.badge tone="primary">{{ $labels[$inversion->tipo] ?? $inversion->tipo }}</x-ui.badge>
            <x-ui.badge tone="{{ $inversion->estado === 'ACTIVA' ? 'success' : 'neutral' }}">{{ $labels[$inversion->estado] ?? $inversion->estado }}</x-ui.badge>
            <span class="ui-help">· Emisor: {{ $presentacion['entidad_relacion']['nombre'] ?? $inversion->entidad ?? 'Sin entidad' }} · Moneda: {{ $inversion->moneda }}</span>
        </p>
    </div>
    @php
        $anioReporte = $inversion->fecha_vencimiento?->year ?? ($movimientos->first()?->fecha?->year ?? now()->year);
    @endphp
    <div class="ui-inline" style="gap: 0.5rem; flex-wrap: wrap;">
        <x-ui.button :href="route('inversiones.portafolio')" variant="secondary">Portafolio</x-ui.button>
        <x-ui.button :href="route('inversiones.reportes.retenciones', ['anio' => $anioReporte])" variant="secondary">Retenciones</x-ui.button>
        <x-ui.button :href="route('inversiones.reportes.utilidades', ['anio' => $anioReporte])" variant="secondary">Utilidades</x-ui.button>
    </div>
</header>

@if(session('status'))
    <div class="ui-notice" style="margin-bottom: 1.5rem; border-left: 4px solid var(--color-primary);">
        <p>{{ session('status') }}</p>
    </div>
@endif

@if($presentacion['valoracion_desactualizada'])
    <div class="ui-notice" style="border-left: 4px solid #f59e0b; margin-bottom: 1rem;">
        <p><strong>Valoración desactualizada:</strong> Han transcurrido más de 35 días sin una nueva valoración registrada para este activo.</p>
    </div>
@endif

<!-- TARJETAS DE INDICADORES PRINCIPALES Y VALOR ACTUAL -->
<div class="finance-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
    <x-ui.card>
        <span class="ui-help">Valor Actual de la Posición</span>
        <h2 style="margin: 0.25rem 0; color: var(--color-primary);">
            $ {{ $formatValue((string) ($inversion->estaLiquidada() ? 0 : ($presentacion['valor_actual'] ?? $inversion->monto_inicial)), 2) }}
            <small style="font-size: 0.9rem; color: #666;">{{ $inversion->moneda }}</small>
        </h2>
        @if($inversion->estaLiquidada())
            <p class="ui-help" style="margin: 0; color: #16a34a; font-weight: 600;">Posición redimida y cerrada</p>
        @elseif($presentacion['unidades_actuales'] !== null)
            <p class="ui-help" style="margin: 0;">Unidades acumuladas: <strong>{{ $formatValue((string) $presentacion['unidades_actuales'], 8) }}</strong></p>
        @endif
    </x-ui.card>

    <x-ui.card>
        <span class="ui-help">Capital Aportado Neto</span>
        <h2 style="margin: 0.25rem 0;">
            $ {{ $formatValue((string) ($inversion->estaLiquidada() ? 0 : ((string)$indicadores['capital_aportado_neto'] < 0 ? 0 : $indicadores['capital_aportado_neto'])), 2) }}
        </h2>
        <p class="ui-help" style="margin: 0;">
            @if($inversion->estaLiquidada())
                Posición cerrada · Capital 100% recuperado
            @elseif((string)$indicadores['capital_aportado_neto'] < 0)
                Capital 100% recuperado (Retiros superan aportes)
            @else
                Total Aportes: ${{ $formatValue((string) $indicadores['total_aportes'], 2) }}
            @endif
        </p>
    </x-ui.card>

    <x-ui.card>
        <span class="ui-help">Ganancia / Rendimiento Neto</span>
        <h2 style="margin: 0.25rem 0; color: {{ (string)$indicadores['ganancia'] >= 0 ? '#16a34a' : '#dc2626' }};">
            {{ (string)$indicadores['ganancia'] >= 0 ? '+' : '' }}$ {{ $formatValue((string) $indicadores['ganancia'], 2) }}
        </h2>
        <p class="ui-help" style="margin: 0;">ROI: <strong>{{ $indicadores['roi'] ?? '0.00' }}%</strong> · XIRR: <strong>{{ $indicadores['xirr'] }}</strong></p>
    </x-ui.card>

    @if($vnr)
        <x-ui.card>
            <span class="ui-help">Valorización No Realizada (RF-VAL-02)</span>
            <h2 style="margin: 0.25rem 0; color: {{ (string)$vnr['valor'] >= 0 ? '#16a34a' : '#dc2626' }};">
                {{ (string)$vnr['valor'] >= 0 ? '+' : '' }}$ {{ $formatValue((string)$vnr['valor'], 2) }}
            </h2>
            <p class="ui-help" style="margin: 0;">Variación: <strong>{{ (string)$vnr['porcentaje'] >= 0 ? '+' : '' }}{{ $vnr['porcentaje'] }}%</strong> (No genera impuesto)</p>
        </x-ui.card>
    @elseif(isset($presentacion['rendimiento_estimado']))
        <x-ui.card>
            <span class="ui-help">
                {{ !empty($presentacion['intereses_cobrados_cdt']) && (string)$presentacion['intereses_cobrados_cdt'] > 0 ? 'Ganancia Bruta Pendiente por Cobrar' : 'Rendimiento Bruto al Vencimiento' }}
            </span>
            <h2 style="margin: 0.25rem 0; color: #16a34a;">
                +$ {{ $formatValue((string) $presentacion['rendimiento_estimado'], 2) }}
            </h2>
            <p class="ui-help" style="margin: 0;">
                @if(!empty($presentacion['intereses_cobrados_cdt']) && (string)$presentacion['intereses_cobrados_cdt'] > 0)
                    Cobrado: ${{ $formatValue((string) $presentacion['intereses_cobrados_cdt'], 2) }} · Total pactado: ${{ $formatValue((string) ($presentacion['rendimiento_bruto_total_cdt'] ?? '0'), 2) }}
                @else
                    Vencimiento: {{ $inversion->fecha_vencimiento?->format('d/m/Y') }}
                @endif
            </p>
        </x-ui.card>
    @endif
</div>

<!-- INFORMACIÓN NORMATIVA ESPECÍFICA SEGÚN PRODUCTO -->
<x-ui.card title="Ficha Técnica Tributaria del Producto">
    <div class="finance-breakdown" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div>
            <span class="ui-help">Fecha de Apertura:</span>
            <p style="margin: 0.25rem 0; font-weight: 600;">{{ $inversion->fecha_apertura->format('d/m/Y') }}</p>
        </div>

        @if($inversion->tipo === 'CDT')
            <div>
                <span class="ui-help">Tasa Pactada:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">{{ $det['tasa_valor'] ?? $inversion->tasa_ea }}% {{ $det['tipo_tasa'] ?? 'EA' }}</p>
            </div>
            <div>
                <span class="ui-help">Plazo y Vencimiento:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">{{ $det['plazo_dias'] ?? $inversion->plazo_dias }} días ({{ $inversion->fecha_vencimiento?->format('d/m/Y') }})</p>
            </div>
            <div>
                <span class="ui-help">Modalidad de Pago:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">{{ $labels[$det['periodicidad_pago'] ?? 'VENCIMIENTO'] ?? 'Al vencimiento' }}</p>
            </div>
            <div>
                <span class="ui-help">Retención en la Fuente:</span>
                <p style="margin: 0.25rem 0; font-weight: 600; color: #16a34a;">4% Renta Fija (Decreto 572/2025)</p>
            </div>
        @elseif($inversion->tipo === 'CUENTA_ALTO_RENDIMIENTO')
            <div>
                <span class="ui-help">Tasa Fija E.A.:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">{{ $det['tasa_ea'] ?? $inversion->tasa_ea }}% E.A.</p>
            </div>
            <div>
                <span class="ui-help">Retención sobre Abonos:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">7% Rendimientos Financieros (Art. 395 ET)</p>
            </div>
            <div>
                <span class="ui-help">Tratamiento GMF (4x1000):</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">
                    @if(!empty($det['es_cuenta_gmf_exenta']))
                        <x-ui.badge tone="success">Cuenta Exenta (Hasta 350 UVT / $18.3M mes)</x-ui.badge>
                    @else
                        <x-ui.badge tone="warning">Gravada 0,4% pleno en retiros</x-ui.badge>
                    @endif
                </p>
            </div>
        @elseif($inversion->tipo === 'ACCION_ETF')
            <div>
                <span class="ui-help">Instrumento / Ticker:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">{{ $det['simbolo'] ?? $inversion->simbolo }} ({{ $det['tipo'] ?? 'ACCION' }})</p>
            </div>
            <div>
                <span class="ui-help">Costo Promedio Ponderado:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">$ {{ $formatValue((string) ($det['costo_promedio_unitario'] ?? '0'), 4) }}</p>
            </div>
            <div>
                <span class="ui-help">Primera Compra / Antigüedad:</span>
                @php
                    $antiguedad = !empty($det['fecha_primera_compra']) ? \Carbon\CarbonImmutable::parse($det['fecha_primera_compra'])->diffInYears(now()) : 0;
                @endphp
                <p style="margin: 0.25rem 0; font-weight: 600;">
                    {{ $det['fecha_primera_compra'] ?? $inversion->fecha_apertura->format('Y-m-d') }}
                    <span class="ui-help">({{ $antiguedad }} {{ $antiguedad == 1 ? 'año' : 'años' }} de tenencia)</span>
                </p>
            </div>
            <div>
                <span class="ui-help">Dividendo por Acción Decretado:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">
                    $ {{ $formatValue((string) ($det['valor_dividendo_por_unidad'] ?? '0'), 4) }} {{ $det['moneda_dividendo'] ?? 'COP' }}
                    @if(($det['moneda_dividendo'] ?? 'COP') === 'USD')
                        <span class="ui-help">(TRM: {{ $det['trm_dividendo'] ?? 'Auto BD' }})</span>
                    @endif
                </p>
            </div>
            <div>
                <span class="ui-help">Clasificación Fiscal Venta:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">
                    @if(!empty($det['esta_inscrito_bvc']))
                        <span style="color: #16a34a;">Art. 36-1 ET (0% No gravada en BVC ≤ 3%)</span>
                    @elseif($antiguedad >= 2)
                        <span style="color: #2563eb;">Ganancia Ocasional 15% (≥ 2 años)</span>
                    @else
                        <span style="color: #d97706;">Renta Ordinaria a tarifa marginal (< 2 años)</span>
                    @endif
                </p>
            </div>
        @elseif($inversion->tipo === 'FIC')
            <div>
                <span class="ui-help">Tipo de Portafolio:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">{{ $inversion->detalleFic?->catalogo?->nombre ?? ('Portafolio ' . ucfirst(strtolower($inversion->nivel_riesgo ?? 'Conservador'))) }}</p>
            </div>
            <div>
                <span class="ui-help">Nivel de Riesgo:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">
                    <x-ui.badge tone="{{ ($inversion->nivel_riesgo ?? 'BAJO') === 'ALTO' ? 'danger' : (($inversion->nivel_riesgo ?? 'BAJO') === 'MEDIO' ? 'warning' : 'success') }}">
                        {{ $inversion->nivel_riesgo ?? 'BAJO' }}
                    </x-ui.badge>
                </p>
            </div>
            <div>
                <span class="ui-help">Tarifa Ponderada Retención:</span>
                <p style="margin: 0.25rem 0; font-weight: 600; color: #16a34a;">{{ $inversion->detalleFic?->catalogo?->tarifaPonderada() ?? '6.10' }}% al redimir</p>
            </div>
            <div>
                <span class="ui-help">Regla de Imputación:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">Prioridad a utilidad acumulada pendiente</p>
            </div>
        @elseif($inversion->tipo === 'FPV')
            <div>
                <span class="ui-help">Administradora AFP:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">{{ $det['administradora'] ?? $inversion->entidad }}</p>
            </div>
            <div>
                <span class="ui-help">Tipo Vinculación:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">{{ $det['tipo_vinculacion'] ?? 'DIRECTO' }}</p>
            </div>
            <div>
                <span class="ui-help">Condición de Retiro:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">10 años permanencia o compra de vivienda</p>
            </div>
            <div>
                <span class="ui-help">Retiro Anticipado:</span>
                <p style="margin: 0.25rem 0; font-weight: 600; color: #dc2626;">7% aporte + 7% rendimiento retirado</p>
            </div>
        @elseif($inversion->tipo === 'CESANTIAS')
            <div>
                <span class="ui-help">Fondo Administrador:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">{{ $det['fondo'] ?? $inversion->entidad }}</p>
            </div>
            <div>
                <span class="ui-help">Régimen:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">{{ $det['regimen'] ?? 'ANUALIZADO' }}</p>
            </div>
            <div>
                <span class="ui-help">Retención al Retirar:</span>
                <p style="margin: 0.25rem 0; font-weight: 600; color: #16a34a;">0% en todos los casos</p>
            </div>
            <div>
                <span class="ui-help">Exención Art. 206 #4:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">100% si salario 6m ≤ 350 UVT ($ 18.3M)</p>
            </div>
        @endif
    </div>
</x-ui.card>

@if($inversion->tipo === 'CDT')
    @php
        $capCdt = (string) ($det['capital'] ?? $inversion->monto_inicial ?? 0);
        $tasaCdt = (string) ($det['tasa_valor'] ?? $inversion->tasa_ea ?? 0);
        $plazoCdt = (int) ($det['plazo_dias'] ?? $inversion->plazo_dias ?? 180);
        $baseCdt = (int) ($det['base_dias'] ?? 365);
        $fechaAperturaCdt = \Carbon\CarbonImmutable::parse($inversion->fecha_apertura);

        $numPasos = min(12, max(6, (int) ceil($plazoCdt / 30)));
        $intervaloDias = (int) floor($plazoCdt / $numPasos);

        $puntosProyeccion = [];
        for ($i = 0; $i <= $numPasos; $i++) {
            $d = ($i === $numPasos) ? $plazoCdt : ($i * $intervaloDias);
            $fechaPunto = $fechaAperturaCdt->addDays($d);
            $puntosProyeccion[] = [
                'dia' => $d,
                'fecha' => $fechaPunto->format('d/m/Y'),
                'label' => "Día $d (" . $fechaPunto->format('d/m') . ")",
                'capital' => $capCdt,
                'rendimiento_bruto' => '0',
                'retencion_4pct' => '0',
                'rendimiento_neto' => '0',
                'total_bruto' => '0',
                'total_neto' => '0',
            ];
        }
        $puntoFinal = end($puntosProyeccion);
    @endphp

    <!-- GRÁFICA DE PROYECCIÓN DE GANANCIA DEL CDT -->
    <x-ui.card title="Proyección de Ganancia y Rendimiento del CDT">
        <div style="margin-bottom: 1.25rem;">
            <p class="ui-help" style="margin: 0;">
                Curva de causación de intereses a tasa pactada de <strong>{{ $formatValue($tasaCdt, 2) }}% E.A.</strong> durante los <strong>{{ $plazoCdt }} días</strong> de plazo, ilustrando el rendimiento bruto vs. el rendimiento neto tras descontar la <strong>retención en la fuente del 4%</strong> (Decreto 572 de 2025 / Art. 395 E.T.).
            </p>
        </div>

        <div class="finance-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; align-items: stretch; margin-bottom: 1.25rem;">
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); border: 1px solid var(--ui-border); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="font-weight: 600;">Capital Invertido:</span>
                    <p style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem;">$ {{ $formatValue($capCdt, 2) }}</p>
                </div>
                <span class="ui-help">Base inicial pactada</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(22, 163, 74, 0.05); border: 1px solid rgba(22, 163, 74, 0.25); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="color: #16a34a; font-weight: 600;">Rendimiento Bruto Total:</span>
                    <p style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem; color: #16a34a;">+$ <span data-cdt-projection-key="rendimiento_bruto">—</span></p>
                </div>
                <span class="ui-help" style="color: #16a34a;">Intereses antes de impuestos</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(220, 38, 38, 0.05); border: 1px solid rgba(220, 38, 38, 0.25); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="color: #dc2626; font-weight: 600;">Retención 4% (D. 572/2025):</span>
                    <p style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem; color: #dc2626;">-$ <span data-cdt-projection-key="retencion_4pct">—</span></p>
                </div>
                <span class="ui-help" style="color: #dc2626;">Deducción tributaria oficial</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(37, 99, 235, 0.05); border: 1px solid rgba(37, 99, 235, 0.25); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="color: #2563eb; font-weight: 600;">Rendimiento Neto a Recibir:</span>
                    <p style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem; color: #2563eb;">+$ <span data-cdt-projection-key="rendimiento_neto">—</span></p>
                </div>
                <span class="ui-help" style="color: #2563eb;">Ganancia neta efectiva</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.04); border: 1px solid var(--ui-border); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="font-weight: 600;">Total al Vencimiento:</span>
                    <p style="margin: 0.25rem 0; font-weight: 800; font-size: 1.05rem;">$ <span data-cdt-projection-key="total_neto">—</span></p>
                </div>
                <span class="ui-help">Capital + Rendimiento neto</span>
            </div>
        </div>

        <div style="position: relative; height: 320px; width: 100%; margin-bottom: 1rem;">
            <canvas id="cdtProjectionCanvas" role="img" aria-label="Gráfica de proyección de rendimientos brutos y netos del CDT"></canvas>
        </div>

        <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); font-size: 0.85rem; color: #555; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.5rem;">
            <span>
                <strong>Regla de causación:</strong> Interés compuesto $C \times ((1 + EA)^{d/{{ $baseCdt }}} - 1)$. La retención del 4% en renta fija aplica sobre los intereses al momento de la redención o cobro.
            </span>
            <span>
                Vencimiento pactado: <strong>{{ $inversion->fecha_vencimiento?->format('d/m/Y') }}</strong> ({{ $plazoCdt }} días)
            </span>
        </div>
    </x-ui.card>
@endif

@if($inversion->tipo === 'CUENTA_ALTO_RENDIMIENTO')
    @php
        $saldoCuenta = (string) ($presentacion['valor_actual'] ?? $det['saldo_inicial'] ?? $inversion->monto_inicial ?? 0);
        $tasaCuenta = (string) ($det['tasa_ea'] ?? $inversion->tasa_ea ?? 0);
        $baseCuenta = (int) ($det['base_dias'] ?? 365);
        $uvtCuenta = (string) ($uvtCuenta ?? \App\Services\ParametroTributarioService::obtenerUvt(now('America/Bogota')->toDateString()));
    @endphp

    <!-- GRÁFICA INTERACTIVA DE PROYECCIÓN DE RENDIMIENTO - CUENTA DE ALTO RENDIMIENTO -->
    <x-ui.card title="Proyección y Simulador de Rendimiento — Cuenta de Alto Rendimiento">
        <div style="margin-bottom: 1.25rem;">
            <p class="ui-help" style="margin: 0;">
                En las cuentas de ahorro de alto rendimiento el capital no fluctúa por cotización de bolsa, sino que genera rendimientos garantizados a una tasa de <strong>{{ $formatValue($tasaCuenta, 2) }}% E.A.</strong> con interés compuesto. Ajusta los días que planeas dejar tu dinero quieto para simular los intereses brutos, la retención en la fuente (Art. 1.2.4.2.1 Dec. 1625/2016) y el saldo total que acumularás.
            </p>
        </div>

        <!-- CONTROLES DEL SIMULADOR: BOTONES RÁPIDOS Y SELECTOR DE DÍAS -->
        <div style="padding: 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); border: 1px solid var(--ui-border); margin-bottom: 1.25rem; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <span style="font-weight: 600; font-size: 0.9rem;">Días con dinero quieto:</span>
                <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                    <button type="button" class="btn-dias-cuenta" data-dias="30" style="padding: 0.35rem 0.75rem; border-radius: 6px; border: 1px solid var(--ui-border); background: var(--color-primary); color: #fff; font-size: 0.85rem; font-weight: 600; cursor: pointer;">30 días (1 mes)</button>
                    <button type="button" class="btn-dias-cuenta" data-dias="60" style="padding: 0.35rem 0.75rem; border-radius: 6px; border: 1px solid var(--ui-border); background: #fff; color: inherit; font-size: 0.85rem; font-weight: 600; cursor: pointer;">60 días (2 m)</button>
                    <button type="button" class="btn-dias-cuenta" data-dias="90" style="padding: 0.35rem 0.75rem; border-radius: 6px; border: 1px solid var(--ui-border); background: #fff; color: inherit; font-size: 0.85rem; font-weight: 600; cursor: pointer;">90 días (3 m)</button>
                    <button type="button" class="btn-dias-cuenta" data-dias="180" style="padding: 0.35rem 0.75rem; border-radius: 6px; border: 1px solid var(--ui-border); background: #fff; color: inherit; font-size: 0.85rem; font-weight: 600; cursor: pointer;">180 días (6 m)</button>
                    <button type="button" class="btn-dias-cuenta" data-dias="365" style="padding: 0.35rem 0.75rem; border-radius: 6px; border: 1px solid var(--ui-border); background: #fff; color: inherit; font-size: 0.85rem; font-weight: 600; cursor: pointer;">365 días (1 año)</button>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label for="sim_cuenta_dias" style="font-size: 0.85rem; font-weight: 600;">Personalizar días:</label>
                <input id="sim_cuenta_dias" type="number" min="1" max="1825" value="30" style="width: 90px; padding: 0.35rem 0.5rem; border-radius: 6px; border: 1px solid var(--ui-border); text-align: center; font-weight: 700;">
            </div>
        </div>

        <!-- TARJETAS DE RESULTADOS PROYECTADOS -->
        <div class="finance-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; align-items: stretch; margin-bottom: 1.25rem;">
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); border: 1px solid var(--ui-border); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="font-weight: 600;">Saldo Actual en Cuenta:</span>
                    <p style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem;">$ {{ $formatValue($saldoCuenta, 2) }}</p>
                </div>
                <span class="ui-help">Capital disponible hoy</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(22, 163, 74, 0.05); border: 1px solid rgba(22, 163, 74, 0.25); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="color: #16a34a; font-weight: 600;">Rendimiento Bruto:</span>
                    <p id="cuenta_bruto_preview" style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem; color: #16a34a;">+$ 0.00</p>
                </div>
                <span class="ui-help" style="color: #16a34a;">Intereses antes de impuestos</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(220, 38, 38, 0.05); border: 1px solid rgba(220, 38, 38, 0.25); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="color: #dc2626; font-weight: 600;">Retención Estimada:</span>
                    <p id="cuenta_ret_preview" style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem; color: #dc2626;">-$ 0.00</p>
                </div>
                <span id="cuenta_ret_nota" class="ui-help" style="color: #dc2626;">Exento (0,055 UVT/día)</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(37, 99, 235, 0.05); border: 1px solid rgba(37, 99, 235, 0.25); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="color: #2563eb; font-weight: 600;">Rendimiento Neto:</span>
                    <p id="cuenta_neto_preview" style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem; color: #2563eb;">+$ 0.00</p>
                </div>
                <span class="ui-help" style="color: #2563eb;">Interés neto a capitalizar</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.04); border: 1px solid var(--ui-border); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="font-weight: 600;">Saldo Total Estimado:</span>
                    <p id="cuenta_total_preview" style="margin: 0.25rem 0; font-weight: 800; font-size: 1.05rem;">$ {{ $formatValue($saldoCuenta, 2) }}</p>
                </div>
                <span class="ui-help">Saldo final al terminar los días</span>
            </div>
        </div>

        <!-- CANVAS GRÁFICA -->
        <div style="position: relative; height: 320px; width: 100%; margin-bottom: 1rem;">
            <canvas id="cuentaProjectionCanvas" role="img" aria-label="Gráfica interactiva de proyección de rendimientos de la cuenta de alto rendimiento"></canvas>
        </div>

        <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); font-size: 0.85rem; color: #555; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.5rem;">
            <span>
                Si el rendimiento promedio diario no supera 0,055 UVT/día ($ \approx \$ {{ $formatValue($uvtCuenta * 0.055, 2) }} \text{ COP/día}$ según Dec. 1625/2016), la retención en la fuente es 0%.
            </span>
            <span>
                Tasa garantizada: <strong>{{ $formatValue($tasaCuenta, 2) }}% E.A.</strong> · Saldo base: <strong>$ {{ $formatValue($saldoCuenta, 2) }} COP</strong>
            </span>
        </div>
    </x-ui.card>
@endif

@if($inversion->tipo === 'ACCION_ETF')
    @php
        $costoBaseUnit = (string) ($det['costo_promedio_unitario'] ?? $det['valor_unidad_compra'] ?? $inversion->precio_unitario_compra ?? 0);
        $precioActual = (string) ($det['valor_actual_unidad'] ?? $det['costo_promedio_unitario'] ?? $inversion->precio_unitario_compra ?? 0);
        $unidadesActuales = (string) ($presentacion['unidades_actuales'] ?? $det['unidades_total'] ?? $det['unidades'] ?? 0);
        $monedaAccion = $inversion->moneda ?? 'COP';

        // Recopilar serie histórica de valoraciones cronológicas
        $puntosAccion = [];
        $valCronologicasFiltradas = $valoracionesCronologicas->whereNotNull('precio_unitario')->values();

        if ($valCronologicasFiltradas->isEmpty()) {
            $puntosAccion[] = [
                'fecha' => $inversion->fecha_apertura->format('d/m/Y'),
                'fecha_raw' => $inversion->fecha_apertura->format('Y-m-d'),
                'precio' => (string) $costoBaseUnit,
                'unidades' => (string) $unidadesActuales,
                'valor_total' => (string) \Brick\Math\BigDecimal::of($costoBaseUnit)->multipliedBy($unidadesActuales)->toScale(2, \Brick\Math\RoundingMode::HalfUp),
                'origen' => 'Compra inicial',
            ];
        } else {
            foreach ($valCronologicasFiltradas as $v) {
                $pUnit = (string) $v->precio_unitario;
                $u = (string) ($v->unidades > 0 ? $v->unidades : $unidadesActuales);
                $puntosAccion[] = [
                    'fecha' => $v->fecha ? $v->fecha->format('d/m/Y') : '',
                    'fecha_raw' => $v->fecha ? $v->fecha->format('Y-m-d') : '',
                    'precio' => $pUnit,
                    'unidades' => $u,
                    'valor_total' => (string)$v->valor,
                    'origen' => $v->origen === 'INICIAL' ? 'Compra inicial' : 'Valoración mercado',
                ];
            }
        }

        $difActual = (string) \Brick\Math\BigDecimal::of($precioActual)->minus($costoBaseUnit);
        $pctActual = $costoBaseUnit > 0 ? (string) \Brick\Math\BigDecimal::of($difActual)->multipliedBy('100')->dividedBy($costoBaseUnit,8,\Brick\Math\RoundingMode::HalfUp) : '0';
        $gananciaTotalActual = (string) \Brick\Math\BigDecimal::of($difActual)->multipliedBy($unidadesActuales);
    @endphp

    <!-- GRÁFICA DE EVOLUCIÓN Y VALORACIÓN DE MERCADO DE ACCIONES / ETFS -->
    <x-ui.card title="Evolución y Valoración de Mercado (Cotización vs. Costo Base)">
        <div style="margin-bottom: 1.25rem;">
            <p class="ui-help" style="margin: 0;">
                Curva continua de cotización y valor de mercado para <strong>{{ $inversion->nombre }}</strong> ({{ $inversion->simbolo ?? 'ETF / Acción' }}).
                La línea muestra los precios registrados por ti en el tiempo: <span style="color: #16a34a; font-weight: 700;">● Verde</span> cuando la cotización sube o está en ganancia no realizada, y <span style="color: #dc2626; font-weight: 700;">● Roja</span> cuando la cotización cae o está en pérdida frente a tu costo base de compra.
            </p>
        </div>

        <div class="finance-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; align-items: stretch; margin-bottom: 1.25rem;">
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); border: 1px solid var(--ui-border); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="font-weight: 600;">Costo Base de Compra:</span>
                    <p style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem;">$ {{ $formatValue($costoBaseUnit, 2) }}</p>
                </div>
                <span class="ui-help">{{ $formatValue($unidadesActuales, 2) }} títulos en cartera</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); border: 1px solid var(--ui-border); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="font-weight: 600;">Precio Actual Cotizado:</span>
                    <p style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem;">$ {{ $formatValue($precioActual, 2) }}</p>
                </div>
                <span class="ui-help">Último valor registrado</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: {{ $difActual >= 0 ? 'rgba(22, 163, 74, 0.05)' : 'rgba(220, 38, 38, 0.05)' }}; border: 1px solid {{ $difActual >= 0 ? 'rgba(22, 163, 74, 0.25)' : 'rgba(220, 38, 38, 0.25)' }}; display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="color: {{ $difActual >= 0 ? '#16a34a' : '#dc2626' }}; font-weight: 600;">Variación Unitaria:</span>
                    <p style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem; color: {{ $difActual >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $difActual >= 0 ? '▲ +' : '▼ -' }}$ {{ $formatValue((string) \Brick\Math\BigDecimal::of($difActual)->abs(), 2) }}
                    </p>
                </div>
                <span class="ui-help" style="color: {{ $difActual >= 0 ? '#16a34a' : '#dc2626' }}; font-weight: 600;">
                    {{ $pctActual >= 0 ? '+' : '' }}{{ $formatValue($pctActual, 2) }}% vs compra
                </span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: {{ $gananciaTotalActual >= 0 ? 'rgba(22, 163, 74, 0.05)' : 'rgba(220, 38, 38, 0.05)' }}; border: 1px solid {{ $gananciaTotalActual >= 0 ? 'rgba(22, 163, 74, 0.25)' : 'rgba(220, 38, 38, 0.25)' }}; display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="color: {{ $gananciaTotalActual >= 0 ? '#16a34a' : '#dc2626' }}; font-weight: 600;">Ganancia / Pérdida Total:</span>
                    <p style="margin: 0.25rem 0; font-weight: 800; font-size: 1.05rem; color: {{ $gananciaTotalActual >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $gananciaTotalActual >= 0 ? '+$' : '-$' }} {{ $formatValue((string) \Brick\Math\BigDecimal::of($gananciaTotalActual)->abs(), 2) }}
                    </p>
                </div>
                <span class="ui-help">{{ $gananciaTotalActual >= 0 ? 'Ganancia no realizada' : 'Pérdida no realizada' }}</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); border: 1px solid var(--ui-border); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="font-weight: 600;">Valor Total Posición:</span>
                    <p style="margin: 0.25rem 0; font-weight: 800; font-size: 1.05rem;">$ {{ $formatValue((string) \Brick\Math\BigDecimal::of($unidadesActuales)->multipliedBy($precioActual), 2) }}</p>
                </div>
                <span class="ui-help">Valuación actual ({{ $monedaAccion }})</span>
            </div>
        </div>

        <div style="position: relative; height: 340px; width: 100%; margin-bottom: 1rem;">
            <canvas id="accionValoracionCanvas" role="img" aria-label="Gráfica de evolución y valoración de mercado de acciones o ETFs"></canvas>
        </div>

        <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); font-size: 0.85rem; color: #555; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.5rem;">
            <span>
                💡 <strong>Pasa el cursor por cada punto</strong> para consultar la cotización, variación porcentual y ganancia o pérdida no realizada en cada fecha.
            </span>
            <span>
                Cotizaciones registradas: <strong>{{ count($puntosAccion) }}</strong>
            </span>
        </div>
    </x-ui.card>
@endif

<!-- GRÁFICA DE EVOLUCIÓN Y RENDIMIENTO DE FONDOS ADMINISTRADOS (FIC, FPV, CESANTÍAS) -->
@if(in_array($inversion->tipo, ['FIC', 'FPV', 'CESANTIAS'], true) && !empty($datosAdministrados))
    <x-ui.card title="Evolución y Rendimiento de Cartera (Proyección Esperada vs. Real Observado)">
        <div style="margin-bottom: 1.25rem;">
            <p class="ui-help" style="margin: 0;">
                Curva continua de rendimiento para <strong>{{ $inversion->nombre }}</strong> ({{ $inversion->tipo }} · Portafolio {{ $datosAdministrados['portafolio']['titulo'] ?? 'Administrado' }}).
                La línea discontinua muestra la <strong>proyección esperada inicial</strong> ({{ $formatValue($datosAdministrados['tasa_esperada'], 2) }}% E.A.). La línea continua refleja el <strong>rendimiento real reportado por el fondo</strong>: <span style="color: #16a34a; font-weight: 700;">● Verde</span> cuando supera o iguala la expectativa, y <span style="color: #dc2626; font-weight: 700;">● Roja</span> cuando rinde por debajo de lo proyectado.
            </p>
        </div>

        <!-- 5 TARJETAS DE MÉTRICAS CON EXACTAMENTE EL MISMO TAMAÑO -->
        <div class="finance-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; align-items: stretch; margin-bottom: 1.25rem;">
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); border: 1px solid var(--ui-border); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="font-weight: 600;">Rentabilidad Esperada:</span>
                    <p style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem;">{{ $formatValue($datosAdministrados['tasa_esperada'], 2) }}% E.A.</p>
                </div>
                <span class="ui-help">Horizonte {{ $datosAdministrados['meses_proyeccion'] }} meses</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); border: 1px solid var(--ui-border); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="font-weight: 600;">Rendimiento Real Observado:</span>
                    <p style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem; color: {{ $datosAdministrados['sobre_rendimiento'] ? '#16a34a' : '#dc2626' }};">
                        {{ $formatValue($datosAdministrados['ea_real_actual'], 2) }}% E.A.
                    </p>
                </div>
                <span class="ui-help">Último corte reportado</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: {{ $datosAdministrados['sobre_rendimiento'] ? 'rgba(22, 163, 74, 0.05)' : 'rgba(220, 38, 38, 0.05)' }}; border: 1px solid {{ $datosAdministrados['sobre_rendimiento'] ? 'rgba(22, 163, 74, 0.25)' : 'rgba(220, 38, 38, 0.25)' }}; display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="color: {{ $datosAdministrados['sobre_rendimiento'] ? '#16a34a' : '#dc2626' }}; font-weight: 600;">Desviación vs Expectativa:</span>
                    <p style="margin: 0.25rem 0; font-weight: 700; font-size: 1.05rem; color: {{ $datosAdministrados['sobre_rendimiento'] ? '#16a34a' : '#dc2626' }};">
                        {{ $datosAdministrados['desviacion_ea'] >= 0 ? '▲ +' : '▼ ' }}{{ $formatValue($datosAdministrados['desviacion_ea'], 2) }}% E.A.
                    </p>
                </div>
                <span class="ui-help" style="color: {{ $datosAdministrados['sobre_rendimiento'] ? '#16a34a' : '#dc2626' }}; font-weight: 600;">
                    {{ $datosAdministrados['sobre_rendimiento'] ? 'Sobre la expectativa' : 'Bajo la expectativa' }}
                </span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); border: 1px solid var(--ui-border); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="font-weight: 600;">Saldo Proyectado Teórico:</span>
                    <p style="margin: 0.25rem 0; font-weight: 800; font-size: 1.05rem;">$ {{ $formatValue($datosAdministrados['saldo_esperado_teorico'], 2) }}</p>
                </div>
                <span class="ui-help">Valor estimado al corte</span>
            </div>
            <div style="padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.02); border: 1px solid var(--ui-border); display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; height: 100%; box-sizing: border-box;">
                <div>
                    <span class="ui-help" style="font-weight: 600;">Saldo Real en Cartera:</span>
                    <p style="margin: 0.25rem 0; font-weight: 800; font-size: 1.05rem; color: {{ $datosAdministrados['sobre_rendimiento'] ? '#16a34a' : '#dc2626' }};">
                        $ {{ $formatValue($datosAdministrados['saldo_real_actual'], 2) }}
                    </p>
                </div>
                <span class="ui-help">{{ $datosAdministrados['ganancia_neta_acumulada'] >= 0 ? '+$ ' : '-$ ' }}{{ $formatValue((string) \Brick\Math\BigDecimal::of($datosAdministrados['ganancia_neta_acumulada'])->abs(), 2) }} rendimiento neto</span>
            </div>
        </div>

        <!-- CANVAS GRÁFICA -->
        <div style="position: relative; height: 340px; width: 100%; margin-bottom: 1rem;">
            <canvas id="administradosValoracionCanvas" role="img" aria-label="Gráfica de evolución esperada vs real del fondo administrado"></canvas>
        </div>

        <!-- DISTRIBUCIÓN DE ACTIVOS DEL PORTAFOLIO -->
        @if(!empty($datosAdministrados['portafolio']['activos']))
        <div style="margin-top: 1.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.5rem;">
                <strong style="color: #0f172a; font-size: 0.95rem;">Distribución de Activos del Portafolio: {{ $datosAdministrados['portafolio']['titulo'] }}</strong>
                <span style="background: {{ $datosAdministrados['portafolio']['rango_ea']['badge_bg'] ?? '#f1f5f9' }}; color: {{ $datosAdministrados['portafolio']['rango_ea']['color'] ?? '#0f172a' }}; border: 1px solid {{ $datosAdministrados['portafolio']['rango_ea']['badge_border'] ?? '#cbd5e1' }}; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: 700;">
                    Perfil {{ $datosAdministrados['portafolio']['rango_ea']['nombre'] ?? 'Moderado' }} ({{ $datosAdministrados['portafolio']['rango_ea']['min_ea'] }}% – {{ $datosAdministrados['portafolio']['rango_ea']['max_ea'] }}% E.A.)
                </span>
            </div>
            <p class="ui-help" style="margin: 0 0 0.75rem 0; font-size: 0.85rem;">{{ $datosAdministrados['portafolio']['enfoque'] }}</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 0.75rem;">
                @foreach($datosAdministrados['portafolio']['activos'] as $act)
                    <div style="display: flex; flex-direction: column; gap: 0.25rem; background: #ffffff; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem;">
                            <span style="font-weight: 600; color: #334155;">{{ $act['nombre'] }}</span>
                            <span style="font-weight: 700; color: #0f172a;">{{ $act['porcentaje'] }}% <span style="font-weight: normal; color: #64748b; font-size: 0.8125rem;">({{ $act['categoria'] }})</span></span>
                        </div>
                        <div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                            <div style="width: {{ $act['porcentaje'] }}%; height: 100%; background: #c2410c; border-radius: 3px;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </x-ui.card>
@endif

<!-- FORMULARIO DE VALORACIÓN MANUAL DE MERCADO (GANANCIA / PÉRDIDA NO REALIZADA) -->
@if(!$inversion->estaLiquidada() && !in_array($inversion->tipo, ['CDT', 'CUENTA_ALTO_RENDIMIENTO'], true))
<x-ui.card title="Actualizar Valoración de Mercado {{ in_array($inversion->tipo, ['FIC', 'FPV', 'CESANTIAS'], true) ? '(Rentabilidad Real Observada)' : '(Cotización Actual)' }}">
    <p class="ui-help" style="margin-bottom: 1rem;">
        @if(in_array($inversion->tipo, ['FIC', 'FPV', 'CESANTIAS'], true))
            Registra la rentabilidad efectiva anual (% E.A.) reportada en el extracto periódico de tu fondo. El sistema calculará automáticamente tu saldo de mercado acumulado y actualizará la curva de rendimiento real.
        @else
            Registra el precio actual de mercado para actualizar el valor total y calcular tu ganancia o pérdida no realizada.
        @endif
    </p>
    <form method="POST" action="{{ route('inversiones.valoraciones.store', $inversion->id) }}">
        @csrf
        <div class="income-two-fields">
            <x-ui.date-input name="fecha" id="val_fecha" label="Fecha de la cotización / extracto" :min="$inversion->fecha_apertura->format('Y-m-d')" :value="now('America/Bogota')->format('Y-m-d')" :allowFuture="true" data-allow-future="true" />

            @if(in_array($inversion->tipo, ['FIC', 'FPV', 'CESANTIAS'], true))
                @php
                    $eaActual = (string) ($datosAdministrados['ea_real_actual'] ?? $inversion->rentabilidad_esperada_porc ?? 7.00);
                @endphp
                <x-ui.field name="rentabilidad_real_ea" label="Rentabilidad observada (% E.A.) *" :required="true" help="Tasa anualizada que reportó el fondo a la fecha de corte (Tu expectativa inicial fue {{ $formatValue($inversion->rentabilidad_esperada_porc ?? 7.00, 2) }}% E.A.).">
                    <input id="val_rentabilidad_ea" name="rentabilidad_real_ea" type="text" data-value-input data-precision="8" min="-100" max="500" inputmode="decimal" value="{{ $eaActual }}" required placeholder="Ej: 5,50" oninput="calcularImpactoPorcentajeAdministrado(this.value)">
                </x-ui.field>

                <div id="val-feedback-admin" style="grid-column: 1 / -1; padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.03); border: 1px solid var(--ui-border); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.5rem;">
                    <div>
                        <span class="ui-help">Nuevo Saldo Proyectado en Cartera:</span>
                        <strong id="admin-val-total-calculado" style="font-size: 1.1rem; margin-left: 0.25rem;">$ {{ $formatValue($datosAdministrados['saldo_real_actual'] ?? $inversion->monto_inicial, 2) }} {{ $inversion->moneda }}</strong>
                    </div>
                    <div id="admin-val-variacion-texto">
                        <span style="font-weight: 700; color: {{ ($datosAdministrados['desviacion_ea'] ?? 0) >= 0 ? '#16a34a' : '#dc2626' }};">
                            {{ ($datosAdministrados['desviacion_ea'] ?? 0) >= 0 ? '▲ Sobre la expectativa' : '▼ Bajo la expectativa' }}
                            ({{ ($datosAdministrados['desviacion_ea'] ?? 0) >= 0 ? '+' : '' }}{{ $formatValue($datosAdministrados['desviacion_ea'] ?? 0, 2) }}% E.A.)
                        </span>
                    </div>
                </div>
            @elseif($inversion->tieneUnidades())
                @php
                    $precioActual = (string) ($det['valor_actual_unidad'] ?? $det['costo_promedio_unitario'] ?? $inversion->precio_unitario_compra ?? 0);
                    $unidadesActuales = (string) ($presentacion['unidades_actuales'] ?? $det['unidades_total'] ?? $det['unidades'] ?? 0);
                    $costoBaseUnit = (string) ($det['costo_promedio_unitario'] ?? $det['valor_unidad_compra'] ?? $inversion->precio_unitario_compra ?? 0);
                @endphp
                <x-ui.field name="precio_unitario" label="Nuevo precio por unidad ({{ $inversion->moneda }}) *" :required="true" help="Cotización en mercado de UNA sola acción (Tu costo de compra base fue $ {{ $formatValue($costoBaseUnit, 2) }} {{ $inversion->moneda }}).">
                    <input id="val_precio_unitario" data-localized-value data-precision="8" name="precio_unitario" type="text" inputmode="decimal" value="{{ $precioActual > 0 ? $formatValue($precioActual, 8, ',', '.') : '' }}" required placeholder="Ej: {{ $formatValue($costoBaseUnit > 0 ? (string) \Brick\Math\BigDecimal::of($costoBaseUnit)->multipliedBy('1.05') : 105.50, 2, ',', '.') }}" oninput="calcularImpactoValoracion(this.value)">
                </x-ui.field>

                <div id="val-feedback" style="grid-column: 1 / -1; padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.03); border: 1px solid var(--ui-border); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.5rem;">
                    <div>
                        <span class="ui-help">Valor Total Proyectado:</span>
                        <strong id="val-total-calculado" style="font-size: 1.1rem; margin-left: 0.25rem;">$ {{ $formatValue($unidadesActuales * $precioActual, 2) }} {{ $inversion->moneda }}</strong>
                    </div>
                    <div id="val-variacion-texto">
                        @if($costoBaseUnit > 0 && $precioActual > 0)
                            @php
                                $difU = (string) \Brick\Math\BigDecimal::of($precioActual)->minus($costoBaseUnit);
                                $pctU = (string) \Brick\Math\BigDecimal::of($difU)->multipliedBy('100')->dividedBy($costoBaseUnit,8,\Brick\Math\RoundingMode::HalfUp);
                                $ganU = (string) \Brick\Math\BigDecimal::of($difU)->multipliedBy($unidadesActuales);
                            @endphp
                            <span style="color: {{ $pctU > 0 ? '#16a34a' : ($pctU < 0 ? '#dc2626' : '#64748b') }}; font-weight: 700;">
                                @if((string) \Brick\Math\BigDecimal::of($pctU)->abs() < 0.0001)
                                    — Sin variación (0.00%) respecto al costo base
                                @else
                                    {{ $pctU > 0 ? '▲ Ganancia:' : '▼ Pérdida:' }} {{ $pctU > 0 ? '+' : '' }}{{ $formatValue($pctU, 2) }}%
                                    ({{ $pctU > 0 ? '+' : '' }}${{ $formatValue($ganU, 2) }} {{ $inversion->moneda }})
                                @endif
                            </span>
                        @endif
                    </div>
                </div>
            @elseif($inversion->tipo === 'CDT')
                @php
                    $valorCausadoHoy = \App\Services\InversionService::valorCausado($inversion, \Carbon\CarbonImmutable::today());
                @endphp
                <x-ui.field name="valor" label="Valor causado o de mercado (COP) *" :required="true">
                    <input id="val_valor" name="valor" type="number" data-value-input data-precision="2" step="0.01" min="0.01" value="{{ $valorCausadoHoy['valor_causado'] ?? $inversion->monto_inicial }}" required placeholder="Monto total">
                </x-ui.field>
                <div style="grid-column: 1 / -1; display: flex; align-items: center; gap: 0.5rem;">
                    <label class="ui-check">
                        <input type="checkbox" name="usar_valor_causado" value="1" checked>
                        Aceptar valor causado sugerido por fórmula ($ {{ $formatValue((string) ($valorCausadoHoy['valor_causado'] ?? 0), 2) }})
                    </label>
                </div>
            @else
                <x-ui.field name="valor" label="Nuevo saldo / valor total de mercado (COP) *" :required="true">
                    <input id="val_valor" name="valor" type="number" data-value-input data-precision="2" step="0.01" min="0.01" value="{{ $presentacion['valor_actual'] ?? $inversion->monto_inicial }}" required placeholder="Monto total">
                </x-ui.field>
            @endif
        </div>

        <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
            <x-ui.button type="submit" variant="primary">Guardar Valoración de Mercado</x-ui.button>
        </div>
    </form>
</x-ui.card>
@endif

<!-- SECCIÓN DE REGISTRO DE TRANSACCIÓN REAL -->
@if(!$inversion->estaLiquidada())
<x-ui.card title="Registrar Transacción / Movimiento Oficial">
    <form method="POST" action="{{ route('inversiones.movimientos.store', $inversion->id) }}">
        @csrf
        <div class="income-two-fields">
            <x-ui.field name="tipo" label="Tipo de transacción" :required="true">
                <select id="trx_tipo" name="tipo" required onchange="adaptarFormularioTrx(this.value)">
                    @foreach($inversion->movimientosPermitidos() as $movTipo)
                        <option value="{{ $movTipo }}" @selected(old('tipo') === $movTipo)>{{ $labels[$movTipo] ?? $movTipo }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.date-input name="fecha" id="trx_fecha" label="Fecha de la transacción" :min="$inversion->fecha_apertura->format('Y-m-d')" :value="now('America/Bogota')->format('Y-m-d')" :allowFuture="true" data-allow-future="true" />

            <div id="trx-field-monto">
                <x-ui.field name="monto" label="Monto total bruto / operación" :required="true">
                    <input id="trx_monto" data-localized-value data-precision="2" name="monto" type="text" inputmode="decimal" value="{{ old('monto') }}" required placeholder="Ej: 1.050.700,00" autocomplete="off">
                </x-ui.field>
            </div>

            <div id="trx-field-unidades" style="{{ $inversion->tieneUnidades() ? '' : 'display: none;' }}">
                <x-ui.field name="unidades" label="Cantidad de unidades / títulos">
                    <input id="trx_unidades" data-localized-value data-precision="8" name="unidades" type="text" inputmode="decimal" value="{{ old('unidades') }}" placeholder="Ej: 10,00000000" autocomplete="off">
                </x-ui.field>
            </div>

            <div id="trx-field-valor-unidad" style="{{ $inversion->tieneUnidades() ? '' : 'display: none;' }}">
                <x-ui.field name="valor_unidad" label="Valor / Precio de la unidad">
                    <input id="trx_valor_unidad" data-localized-value data-precision="8" name="valor_unidad" type="text" inputmode="decimal" value="{{ old('valor_unidad', !empty($det['valor_actual_unidad']) ? $formatValue((string)$det['valor_actual_unidad'], 8, ',', '.') : '') }}" placeholder="Ej: 105.500,00" autocomplete="off">
                    <div id="trx_precio_preview" class="ui-help" style="margin-top: 0.35rem; font-weight: 600; color: var(--color-primary); font-size: 0.85rem; display: none;"></div>
                </x-ui.field>
            </div>

            @if($inversion->tipo === 'ACCION_ETF')
                <div id="trx-field-moneda" style="display: none;">
                    <x-ui.field name="moneda" label="Moneda del dividendo">
                        <select id="trx_moneda" name="moneda" onchange="toggleTrxTrm(this.value)">
                            <option value="COP" @selected(old('moneda', $det['moneda_dividendo'] ?? 'COP') === 'COP')>COP</option>
                            <option value="USD" @selected(old('moneda', $det['moneda_dividendo'] ?? 'COP') === 'USD')>USD</option>
                        </select>
                    </x-ui.field>
                </div>
                <div id="trx-field-trm" style="display: none;">
                    <x-ui.field name="tasa_cambio_usada" label="TRM (Opcional: Vacío = BD)">
                        <input id="trx_trm" name="tasa_cambio_usada" type="text" data-value-input data-precision="4" min="0.0001" inputmode="decimal" value="{{ old('tasa_cambio_usada', $det['trm_dividendo'] ?? '') }}" placeholder="Vacío = toma TRM de BD" autocomplete="off">
                    </x-ui.field>
                </div>
            @endif

            <div id="trx-field-comision" style="{{ in_array($inversion->tipo, ['ACCION_ETF', 'FIC'], true) ? '' : 'display: none;' }}">
                <x-ui.field name="comision" label="Comisión de la operación (si aplica)">
                    <input id="trx_comision" data-localized-value data-precision="2" name="comision" type="text" inputmode="decimal" value="{{ old('comision', '0,00') }}" placeholder="0,00" autocomplete="off">
                    <div id="trx_comision_preview" class="ui-help" style="margin-top: 0.35rem; font-weight: 600; color: var(--color-primary); font-size: 0.85rem; display: none;"></div>
                </x-ui.field>
            </div>

            @if($inversion->tipo === 'FPV')
                <div style="grid-column: 1 / -1; display: none;" id="trx-fpv-permanencia">
                    <label class="ui-check">
                        <input type="checkbox" name="cumple_permanencia" value="1" @checked(old('cumple_permanencia', false))>
                        El retiro cumple permanencia (≥ 10 años) o destino permitido de vivienda/pensión (Exento de retención contingente)
                    </label>
                </div>
            @endif

            <div style="grid-column: 1 / -1;">
                <x-ui.field name="nota" label="Nota explicativa o soporte">
                    <input name="nota" maxlength="255" value="{{ old('nota') }}" placeholder="Opcional. Ej: Extracto mensual, certificado de dividendo, venta en bolsa.">
                </x-ui.field>
            </div>
        </div>

        <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
            <x-ui.button type="submit" variant="primary">Guardar Transacción Oficial</x-ui.button>
        </div>
    </form>
</x-ui.card>
@endif

<!-- HISTORIAL DE TRANSACCIONES / MOVIMIENTOS -->
<x-ui.card title="Historial de Transacciones Oficiales">
    @if($movimientos->isEmpty())
        <x-ui.empty-state title="No hay transacciones registradas para esta inversión." />
    @else
        <table class="ui-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th data-align="right">Monto Bruto</th>
                    <th data-align="right">Retención</th>
                    <th data-align="right">GMF (4x1000)</th>
                    <th data-align="right">Neto Flujo</th>
                    <th>Nota / Detalle</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach($movimientos as $mov)
                    @php
                        $ingresoTrx = isset($ingresosGenerados) ? $ingresosGenerados->firstWhere('transaccion_id', $mov->id) : null;
                        $deducibleTrx = isset($deduciblesGenerados) ? $deduciblesGenerados->firstWhere('transaccion_id', $mov->id) : null;
                        $esRetiroFpv = $inversion->tipo === 'FPV' && in_array($mov->tipo, ['RETIRO', 'RETIRO_PARCIAL'], true);
                    @endphp
                    <tr>
                        <td>{{ $mov->fecha->format('d/m/Y') }}</td>
                        <td>
                            <x-ui.badge tone="primary">{{ $labels[$mov->tipo] ?? $mov->tipo }}</x-ui.badge>
                        </td>
                        <td class="ui-number">
                            <div>$ {{ $formatValue((string) ($mov->monto_bruto ?? $mov->monto), 2) }}</div>
                            @if($esRetiroFpv && ((string)$mov->retencion_aplicada > 0 || $ingresoTrx || $deducibleTrx))
                                @php
                                    $rendBruto = $ingresoTrx ? (string)$ingresoTrx->valor_bruto : 0.0;
                                    $capBruto = (string) \Brick\Math\BigDecimal::max('0', \Brick\Math\BigDecimal::of((string)($mov->monto_bruto ?? $mov->monto))->minus((string)$rendBruto));
                                @endphp
                                <div style="font-size: 0.8125rem; color: #64748b; line-height: 1.25; margin-top: 0.25rem; font-weight: normal; text-align: right;">
                                    <div>Capital: ${{ $formatValue($capBruto, 2) }}</div>
                                    <div>Rendimientos: ${{ $formatValue($rendBruto, 2) }}</div>
                                </div>
                            @endif
                        </td>
                        <td class="ui-number" style="color: {{ (string)$mov->retencion_aplicada > 0 ? '#dc2626' : '#666' }};">
                            <div>{{ (string)$mov->retencion_aplicada > 0 ? '-$' . $formatValue((string)$mov->retencion_aplicada, 2) : '—' }}</div>
                            @if($esRetiroFpv && (string)$mov->retencion_aplicada > 0)
                                @php
                                    $retRend = $ingresoTrx ? (string)$ingresoTrx->retencion : 0.0;
                                    $retAportes = (string) \Brick\Math\BigDecimal::of((string)$mov->retencion_aplicada)->minus((string)$retRend);
                                @endphp
                                <div style="font-size: 0.8125rem; color: #64748b; line-height: 1.25; margin-top: 0.25rem; font-weight: normal; text-align: right;">
                                    <div>Aportes (7%): <span style="color: #b91c1c;">-${{ $formatValue($retAportes, 2) }}</span></div>
                                    <div>Rendimientos (7%): <span style="color: #b91c1c;">-${{ $formatValue($retRend, 2) }}</span></div>
                                </div>
                            @endif
                        </td>
                        <td class="ui-number" style="color: {{ (string)$mov->gmf_aplicado > 0 ? '#dc2626' : '#666' }};">
                            {{ (string)$mov->gmf_aplicado > 0 ? '-$' . $formatValue((string)$mov->gmf_aplicado, 2) : '—' }}
                        </td>
                        <td class="ui-number" style="font-weight: 600;">
                            $ {{ $formatValue((string) $mov->monto_cop, 2) }}
                        </td>
                        <td>{{ $mov->nota ?? '—' }}</td>
                        <td>
                            @if(!$mov->es_inicial)
                                <form method="POST" action="{{ route('inversiones.movimientos.destroy', [$inversion->id, $mov->id]) }}" data-confirm-description="Se eliminará esta transacción de la inversión.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ui-button" style="color: #dc2626; background: transparent; border: none; cursor: pointer; padding: 0.25rem 0.5rem;">Eliminar</button>
                                </form>
                            @else
                                <span class="ui-help">Inicial</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-ui.card>

<x-ui.calculation-notice />

<script>
function calcularImpactoValoracion(nuevoPrecio) {
    const {decimal, formatMoney, formatQuantity} = window.FinanzMoney;
    const totalEl = document.getElementById('val-total-calculado'), variation = document.getElementById('val-variacion-texto');
    if (!totalEl || !variation) return;
    try {
        const price = decimal(desformatearMiles(nuevoPrecio) || '0');
        const units = decimal(@json((string)($unidadesActuales ?? '0')));
        const cost = decimal(@json((string)($costoBaseUnit ?? '0')));
        totalEl.textContent = formatMoney(price.times(units).toFixed(2)) + ' {{ $inversion->moneda }}';
        if (cost.gt('0') && price.gt('0')) {
            const difference = price.minus(cost), percentage = difference.div(cost).times('100');
            variation.textContent = (difference.gt('0') ? 'Ganancia: ' : difference.lt('0') ? 'Pérdida: ' : 'Sin variación: ') + formatQuantity(percentage.toFixed(2)) + ' % · ' + formatMoney(difference.times(units).toFixed(2));
            variation.className = difference.lt('0') ? 'ui-error' : 'ui-help';
        } else variation.textContent = '';
    } catch { totalEl.textContent = '—'; variation.textContent = 'Revisa el precio y su precisión antes de continuar.'; }
}

function formatearMiles(value) {
    return window.FinanzMoney.formatMoneyDraft(String(value ?? ''));
}
function desformatearMiles(value) {
    if (value === '' || value === null || value === undefined) return '';
    try { return window.FinanzMoney.parseLocalizedValue(String(value), {precision: 8}); }
    catch { return ''; }
}
function enlazarMascaraNumero(input, onUpdate) {
    if (!input || input.hasAttribute('data-value-bound')) return;
    input.type = 'text'; input.inputMode = 'decimal';
    const precision = /unidades|unitario|valor_unidad/.test(input.id) ? 8 : /trm/.test(input.id) ? 4 : 2;
    const update = () => {
        let message = '';
        try {
            if (input.value) window.FinanzMoney.parseLocalizedValue(input.value, {precision, min: '0', max: '999999999999.99'});
            else if (input.required) message = 'Este campo es obligatorio.';
        } catch (error) { message = error.message; }
        input.setCustomValidity(message); input.setAttribute('aria-invalid', String(Boolean(message)));
        const error = input.closest('[data-field]')?.querySelector('[data-field-error]');
        if (error) { error.textContent = message; error.hidden = !message; }
        if (onUpdate) onUpdate();
    };
    window.FinanzMoney.bindMoneyGrouping(input);
    input.addEventListener('input', update); input.addEventListener('blur', update);
    if (input.value) {
        // old() entrega el decimal canónico del último envío.
        if (/^-?\d+(?:\.\d+)?$/.test(input.value)) input.value = window.FinanzMoney.formatCanonicalValue(input.value);
        update();
    }
}

function actualizarPreviewCampo(input, previewId, label = 'Valor') {
    if (!input) return;
    const preview = document.getElementById(previewId);
    if (!preview) return;
    const raw = input.value;
    if (!raw) {
        preview.style.display = 'none';
        return;
    }
    const fmt = formatearMiles(raw);
    preview.textContent = label + ': $ ' + fmt;
    preview.style.display = 'block';
}

function toggleTrxTrm(val) {
    const trmField = document.getElementById('trx-field-trm');
    const precioEl = document.getElementById('trx_valor_unidad');
    const precioLabel = precioEl ? precioEl.closest('.ui-field')?.querySelector('label') : null;
    if (trmField) trmField.style.display = (val === 'USD') ? 'block' : 'none';
    if (precioLabel) precioLabel.innerText = 'Dividendo por acción decretado (' + val + ')';
}

function adaptarFormularioTrx(tipo) {
    const tipoInv = '{{ $inversion->tipo }}';
    const montoEl = document.getElementById('trx_monto');
    const montoLabel = document.querySelector('#trx-field-monto label');
    const unidadesEl = document.getElementById('trx_unidades');
    const unidadesWrap = document.getElementById('trx-field-unidades');
    const unidadesLabel = unidadesWrap ? unidadesWrap.querySelector('label') : null;
    const precioEl = document.getElementById('trx_valor_unidad');
    const precioWrap = document.getElementById('trx-field-valor-unidad');
    const precioLabel = precioWrap ? precioWrap.querySelector('label') : null;
    const comisionWrap = document.getElementById('trx-field-comision');
    const fieldMoneda = document.getElementById('trx-field-moneda');
    const fieldTrm = document.getElementById('trx-field-trm');
    const fpvPermanencia = document.getElementById('trx-fpv-permanencia');
    const notaEl = document.querySelector('input[name="nota"]');

    function autoCalcular() {
        if (!unidadesEl || !precioEl || !montoEl) return;
        const u = window.FinanzMoney.decimal(desformatearMiles(unidadesEl.value) || '0');
        const p = window.FinanzMoney.decimal(desformatearMiles(precioEl.value) || '0');
        if (u.gt('0') && p.gt('0')) {
            montoEl.value = window.FinanzMoney.formatCanonicalValue(u.times(p).toFixed(2));
        }
    }

    if (tipoInv === 'CDT') {
        if (unidadesWrap) unidadesWrap.style.display = 'none';
        if (precioWrap) precioWrap.style.display = 'none';
        if (comisionWrap) comisionWrap.style.display = 'none';
        if (fieldMoneda) fieldMoneda.style.display = 'none';
        if (fieldTrm) fieldTrm.style.display = 'none';
        if (fpvPermanencia) fpvPermanencia.style.display = 'none';

        if (tipo === 'ABONO_INTERES') {
            if (montoLabel) montoLabel.innerText = 'Interés bruto a cobrar (COP) *';
            if (montoEl) {
                montoEl.value = window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.projectedInterest(@json((string)($det['capital'] ?? $inversion->monto_inicial)), @json((string)($det['tasa_valor'] ?? $inversion->tasa_ea ?? '0')), @json((string)$diasPeriodo), @json((string)($det['base_dias'] ?? '365'))));
            }
            if (notaEl) notaEl.value = 'Cobro de intereses periódicos pactados';
        } else if (tipo === 'REDENCION') {
            const periodicidad = '{{ strtoupper((string) ($det['periodicidad_pago'] ?? 'VENCIMIENTO')) }}';
            if (periodicidad === 'VENCIMIENTO' || periodicidad === 'AL_VENCIMIENTO') {
                if (montoLabel) montoLabel.innerText = 'Monto bruto total al vencimiento (Capital + Rendimientos) *';
                if (montoEl) {
                    montoEl.value = window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(@json((string)($det['capital'] ?? $inversion->monto_inicial))).plus(@json((string)($presentacion['rendimiento_estimado'] ?? '0'))).toFixed(2));
                }
                if (notaEl) notaEl.value = 'Redención total y liquidación de intereses al vencimiento';
            } else {
                if (montoLabel) montoLabel.innerText = 'Capital a liquidar / redimir (COP) *';
                if (montoEl) {
                    montoEl.value = window.FinanzMoney.formatCanonicalValue('{{ $det['capital'] ?? $inversion->monto_inicial }}');
                }
                if (notaEl) notaEl.value = 'Redención y liquidación final del CDT';
            }
            const fechaEl = document.getElementById('fecha') || document.getElementById('trx_fecha');
            if (fechaEl) {
                fechaEl.removeAttribute('max');
                if ('{{ $inversion->fecha_vencimiento?->format('Y-m-d') }}') {
                    fechaEl.value = '{{ $inversion->fecha_vencimiento?->format('Y-m-d') }}';
                }
            }
        }
    } else if (tipoInv === 'ACCION_ETF') {
        if (comisionWrap) comisionWrap.style.display = 'block';
        if (fpvPermanencia) fpvPermanencia.style.display = 'none';

        if (tipo === 'COMPRA') {
            if (unidadesWrap) unidadesWrap.style.display = 'block';
            if (unidadesLabel) unidadesLabel.innerText = 'Cantidad de unidades a comprar';
            if (precioWrap) precioWrap.style.display = 'block';
            if (precioLabel) precioLabel.innerText = 'Precio unitario de compra';
            if (precioEl) {
                const rawP = '{{ $det['valor_actual_unidad'] ?? $det['costo_promedio_unitario'] ?? '' }}';
                precioEl.value = rawP ? window.FinanzMoney.formatCanonicalValue(rawP) : '';
            }
            if (fieldMoneda) fieldMoneda.style.display = 'none';
            if (fieldTrm) fieldTrm.style.display = 'none';
            if (notaEl) notaEl.value = 'Compra adicional de títulos bursátiles';
            autoCalcular();
        } else if (tipo === 'VENTA') {
            if (unidadesWrap) unidadesWrap.style.display = 'block';
            if (unidadesLabel) unidadesLabel.innerText = 'Cantidad de unidades a vender';
            if (unidadesEl) {
                const rawU = '{{ $det['unidades_total'] ?? '' }}';
                unidadesEl.value = rawU ? window.FinanzMoney.formatCanonicalValue(rawU) : '';
            }
            if (precioWrap) precioWrap.style.display = 'block';
            if (precioLabel) precioLabel.innerText = 'Precio unitario de venta';
            if (precioEl) {
                const rawP = '{{ $det['valor_actual_unidad'] ?? $det['costo_promedio_unitario'] ?? '' }}';
                precioEl.value = rawP ? window.FinanzMoney.formatCanonicalValue(rawP) : '';
            }
            if (fieldMoneda) fieldMoneda.style.display = 'none';
            if (fieldTrm) fieldTrm.style.display = 'none';
            if (notaEl) notaEl.value = 'Venta de títulos en mercado';
            autoCalcular();
        } else if (tipo === 'DIVIDENDO') {
            const monedaActual = document.getElementById('trx_moneda')?.value || '{{ $det['moneda_dividendo'] ?? 'COP' }}';
            if (unidadesWrap) unidadesWrap.style.display = 'block';
            if (unidadesLabel) unidadesLabel.innerText = 'Acciones con derecho a dividendo';
            if (unidadesEl) {
                const rawU = '{{ $det['unidades_total'] ?? '' }}';
                unidadesEl.value = rawU ? window.FinanzMoney.formatCanonicalValue(rawU) : '';
            }
            if (precioWrap) precioWrap.style.display = 'block';
            if (precioLabel) precioLabel.innerText = 'Dividendo por acción decretado (' + monedaActual + ')';
            if (precioEl) {
                const rawP = '{{ $det['valor_dividendo_por_unidad'] ?? '' }}';
                precioEl.value = rawP ? window.FinanzMoney.formatCanonicalValue(rawP) : '';
            }
            if (fieldMoneda) fieldMoneda.style.display = 'block';
            if (fieldTrm) fieldTrm.style.display = (monedaActual === 'USD') ? 'block' : 'none';
            if (notaEl) notaEl.value = 'Cobro / Pago de dividendos decretados';
            autoCalcular();
        }
    } else if (tipoInv === 'FIC') {
        if (unidadesWrap) unidadesWrap.style.display = 'none';
        if (precioWrap) precioWrap.style.display = 'none';
        if (fieldMoneda) fieldMoneda.style.display = 'none';
        if (fieldTrm) fieldTrm.style.display = 'none';
        if (fpvPermanencia) fpvPermanencia.style.display = 'none';
        if (comisionWrap) comisionWrap.style.display = 'block';

        if (tipo === 'APORTE') {
            if (montoLabel) montoLabel.innerText = 'Monto a aportar / consignar (COP) *';
            if (notaEl) notaEl.value = 'Aporte adicional al fondo de inversión';
            autoCalcular();
        } else if (tipo === 'REDENCION') {
            if (montoLabel) montoLabel.innerText = 'Monto a redimir / liquidar (COP) *';
            if (notaEl) notaEl.value = 'Redención de participaciones del fondo';
            autoCalcular();
        }
    } else if (tipoInv === 'CUENTA_ALTO_RENDIMIENTO') {
        if (unidadesWrap) unidadesWrap.style.display = 'none';
        if (precioWrap) precioWrap.style.display = 'none';
        if (fieldMoneda) fieldMoneda.style.display = 'none';
        if (fieldTrm) fieldTrm.style.display = 'none';
        if (fpvPermanencia) fpvPermanencia.style.display = 'none';
        if (comisionWrap) comisionWrap.style.display = 'none';

        if (tipo === 'ABONO_INTERES') {
            if (montoLabel) montoLabel.innerText = 'Interés bruto abonado (COP) *';
            if (montoEl) montoEl.value = window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.projectedInterest(@json((string)($presentacion['valor_actual'] ?? $det['saldo_inicial'] ?? $inversion->monto_inicial)), @json((string)($det['tasa_ea'] ?? '0')), '30', @json((string)($det['base_dias'] ?? '365'))));
            if (notaEl) notaEl.value = 'Abono de intereses del periodo';
        } else if (tipo === 'APORTE') {
            if (montoLabel) montoLabel.innerText = 'Monto a consignar / depositar (COP) *';
            if (notaEl) notaEl.value = 'Consignación / Depósito en cuenta de alto rendimiento';
        } else if (tipo === 'RETIRO') {
            if (montoLabel) montoLabel.innerText = 'Monto a retirar (COP) *';
            if (notaEl) notaEl.value = 'Retiro de fondos';
        }
    } else if (tipoInv === 'FPV') {
        if (fieldMoneda) fieldMoneda.style.display = 'none';
        if (fieldTrm) fieldTrm.style.display = 'none';
        if (comisionWrap) comisionWrap.style.display = 'none';

        const tieneUnidadesFpv = {{ (!empty($det['unidades']) && (string)$det['unidades'] > 0) ? 'true' : 'false' }};
        if (tieneUnidadesFpv) {
            if (unidadesWrap) unidadesWrap.style.display = 'block';
            if (unidadesLabel) unidadesLabel.innerText = 'Unidades aportadas (opcional si digita monto)';
            if (precioWrap) precioWrap.style.display = 'block';
            if (precioLabel) precioLabel.innerText = 'Valor de la cuota / unidad';
            if (precioEl) {
                const rawP = '{{ $det['valor_unidad'] ?? '' }}';
                precioEl.value = rawP ? window.FinanzMoney.formatCanonicalValue(rawP) : '';
            }
        } else {
            if (unidadesWrap) unidadesWrap.style.display = 'none';
            if (precioWrap) precioWrap.style.display = 'none';
        }

        if (tipo === 'APORTE') {
            if (montoLabel) montoLabel.innerText = 'Monto del aporte voluntario (COP) *';
            if (fpvPermanencia) fpvPermanencia.style.display = 'none';
            if (notaEl) notaEl.value = 'Aporte voluntario a pensión (Art. 126-1 ET)';
        } else if (tipo === 'RETIRO') {
            if (montoLabel) montoLabel.innerText = 'Monto a retirar (COP) *';
            if (montoEl) montoEl.value = window.FinanzMoney.formatCanonicalValue('{{ $det['saldo_actual'] ?? $inversion->monto_inicial }}');
            if (fpvPermanencia) fpvPermanencia.style.display = 'block';
            if (notaEl) notaEl.value = 'Retiro de fondos de pensión voluntaria';
        }
    } else if (tipoInv === 'CESANTIAS') {
        if (unidadesWrap) unidadesWrap.style.display = 'none';
        if (precioWrap) precioWrap.style.display = 'none';
        if (fieldMoneda) fieldMoneda.style.display = 'none';
        if (fieldTrm) fieldTrm.style.display = 'none';
        if (fpvPermanencia) fpvPermanencia.style.display = 'none';
        if (comisionWrap) comisionWrap.style.display = 'none';

        if (tipo === 'APORTE') {
            if (montoLabel) montoLabel.innerText = 'Monto de cesantías consignadas (COP) *';
            if (notaEl) notaEl.value = 'Consignación anual de cesantías por empleador';
        } else if (tipo === 'RETIRO') {
            if (montoLabel) montoLabel.innerText = 'Monto a retirar (COP) *';
            if (montoEl) montoEl.value = window.FinanzMoney.formatCanonicalValue('{{ $det['saldo'] ?? $inversion->monto_inicial }}');
            if (notaEl) notaEl.value = 'Retiro de cesantías';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // 1. Remover restricciones de fecha máxima para permitir simulación futura
    const inputsFecha = document.querySelectorAll('input[type="date"][data-allow-future], #trx_fecha');
    inputsFecha.forEach(input => {
        input.removeAttribute('max');
    });

    const trxUnidades = document.getElementById('trx_unidades');
    const trxPrecio = document.getElementById('trx_valor_unidad');
    const trxMonto = document.getElementById('trx_monto');
    const trxComision = document.getElementById('trx_comision');
    const valPrecioUnit = document.getElementById('val_precio_unitario');
    const valValor = document.getElementById('val_valor');

    function autoCalcularTrxMonto() {
        if (!trxUnidades || !trxPrecio || !trxMonto) return;
        const u = window.FinanzMoney.decimal(desformatearMiles(trxUnidades.value) || '0');
        const p = window.FinanzMoney.decimal(desformatearMiles(trxPrecio.value) || '0');
        if (u.gt('0') && p.gt('0')) {
            trxMonto.value = window.FinanzMoney.formatCanonicalValue(u.times(p).toFixed(2));
        }
    }

    // 2. Enlazar máscaras numéricas con separadores de miles (.) y decimales (,)
    enlazarMascaraNumero(trxMonto);
    enlazarMascaraNumero(trxPrecio, function() {
        actualizarPreviewCampo(trxPrecio, 'trx_precio_preview', 'Precio');
        autoCalcularTrxMonto();
    });
    enlazarMascaraNumero(trxUnidades, autoCalcularTrxMonto);
    enlazarMascaraNumero(trxComision, function() {
        actualizarPreviewCampo(trxComision, 'trx_comision_preview', 'Comisión');
    });
    if (valPrecioUnit) {
        enlazarMascaraNumero(valPrecioUnit, function() {
            calcularImpactoValoracion(valPrecioUnit.value);
        });
    }
    if (valValor) {
        enlazarMascaraNumero(valValor);
    }

    // 3. Normalizar números al enviar los formularios
    const trxForm = document.querySelector('form[action*="movimientos"]');
    if (trxForm) {
        trxForm.addEventListener('submit', function() {
            if (trxMonto && trxMonto.value) trxMonto.value = desformatearMiles(trxMonto.value);
            if (trxUnidades && trxUnidades.value) trxUnidades.value = desformatearMiles(trxUnidades.value);
            if (trxPrecio && trxPrecio.value) trxPrecio.value = desformatearMiles(trxPrecio.value);
            if (trxComision && trxComision.value) trxComision.value = desformatearMiles(trxComision.value);
            const trxTrm = document.getElementById('trx_trm');
            if (trxTrm && trxTrm.value && !trxTrm.hasAttribute('data-value-bound')) trxTrm.value = desformatearMiles(trxTrm.value);
        });
    }

    const valForm = document.querySelector('form[action*="valoraciones"]');
    if (valForm) {
        valForm.addEventListener('submit', function() {
            if (valPrecioUnit && valPrecioUnit.value) valPrecioUnit.value = desformatearMiles(valPrecioUnit.value);
            if (valValor && valValor.value && !valValor.hasAttribute('data-value-bound')) valValor.value = desformatearMiles(valValor.value);
        });
    }

    const trxTipo = document.getElementById('trx_tipo');
    if (trxTipo) {
        adaptarFormularioTrx(trxTipo.value);
    }

    @if($inversion->tipo === 'CDT')
    const cdtCanvas = document.getElementById('cdtProjectionCanvas');
    if (cdtCanvas) {
        const puntos = @json($puntosProyeccion).map(point => {
            const {decimal, projectedInterest} = window.FinanzMoney;
            const gross = projectedInterest(String(point.capital), @json((string)$tasaCdt), String(point.dia), @json((string)$baseCdt));
            const retention = decimal(gross).times('0.04').toFixed(2), net = decimal(gross).minus(retention).toFixed(2);
            return {...point, rendimiento_bruto:gross, retencion_4pct:retention, rendimiento_neto:net, total_bruto:decimal(String(point.capital)).plus(gross).toFixed(2), total_neto:decimal(String(point.capital)).plus(net).toFixed(2)};
        });
        for (const host of document.querySelectorAll('[data-cdt-projection-key]')) host.textContent = window.FinanzMoney.formatQuantity(puntos.at(-1)[host.dataset.cdtProjectionKey]);
        const labels = puntos.map(p => p.label);
        const dataBruto = puntos.map(p => p.rendimiento_bruto);
        const dataNeto = puntos.map(p => p.rendimiento_neto);
        const dataRetencion = puntos.map(p => p.retencion_4pct);

        const renderCdtChart = () => {
            const ChartConstructor = window.Chart || (typeof Chart !== 'undefined' ? Chart : null);
            if (!ChartConstructor) {
                setTimeout(renderCdtChart, 100);
                return;
            }

            new ChartConstructor(cdtCanvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Rendimiento Bruto Acumulado',
                            data: dataBruto,
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22, 163, 74, 0.1)',
                            fill: true,
                            tension: 0.25,
                            pointRadius: 4,
                            pointHoverRadius: 7,
                            borderWidth: 2.5
                        },
                        {
                            label: 'Rendimiento Neto (después de Retención 4%)',
                            data: dataNeto,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.12)',
                            fill: true,
                            tension: 0.25,
                            pointRadius: 4,
                            pointHoverRadius: 7,
                            borderWidth: 2.5
                        },
                        {
                            label: 'Retención en la Fuente 4% (D. 572/2025)',
                            data: dataRetencion,
                            borderColor: '#dc2626',
                            backgroundColor: 'transparent',
                            borderDash: [5, 4],
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            borderWidth: 1.8
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 14,
                                padding: 12,
                                font: { size: 13, weight: '600' }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const val = window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(context.raw)).toFixed(2));
                                    return ` ${context.dataset.label}: $ ${val}`;
                                },
                                afterBody: function(tooltipItems) {
                                    const idx = tooltipItems[0].dataIndex;
                                    const p = puntos[idx];
                                    const cap = window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(p.capital)).toFixed(2));
                                    const totNeto = window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(p.total_neto)).toFixed(2));
                                    return [
                                        `— Capital base: $ ${cap}`,
                                        `— Total acumulado a recibir: $ ${totNeto}`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return '$ ' + window.FinanzMoney.formatQuantity(String(value));
                                }
                            },
                            title: {
                                display: true,
                                text: 'Rendimiento / Ganancia (COP)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Línea de tiempo del CDT'
                            }
                        }
                    }
                }
            });
        };

        renderCdtChart();
    }
    @endif

    @if($inversion->tipo === 'CUENTA_ALTO_RENDIMIENTO')
    const cuentaCanvas = document.getElementById('cuentaProjectionCanvas');
    if (cuentaCanvas) {
        const saldoBase = @json((string)$saldoCuenta);
        const tasaEa = @json((string)$tasaCuenta);
        const baseDias = {{ $baseCuenta }};
        const uvtValor = @json((string)$uvtCuenta);
        const diasInput = document.getElementById('sim_cuenta_dias');
        const btnsDias = document.querySelectorAll('.btn-dias-cuenta');

        let chartCuenta = null;

        function simularProyeccionCuenta(diasTotales) {
            const dTot = Math.max(1, parseInt(diasTotales) || 30);
            const numPasos = Math.min(15, Math.max(6, Math.ceil(dTot / 5)));
            const step = Math.max(1, Math.floor(dTot / numPasos));

            const labels = [];
            const dataBruto = [];
            const dataNeto = [];

            let rBrutoFin = 0;
            let retFin = 0;
            let rNetoFin = 0;
            let totFin = saldoBase;

            for (let i = 0; i <= numPasos; i++) {
                const d = (i === numPasos) ? dTot : (i * step);
                const {decimal, projectedInterest} = window.FinanzMoney;
                const rB = projectedInterest(saldoBase, tasaEa, String(d), String(baseDias));
                const minimum = decimal(uvtValor).times('0.055').times(String(d));
                const ret = decimal(rB).gte(minimum) ? decimal(rB).times('0.07').toFixed(2) : '0.00';
                const rN = decimal(rB).minus(ret).toFixed(2);
                const tot = decimal(saldoBase).plus(rN).toFixed(2);

                labels.push('Día ' + d);
                dataBruto.push(rB);
                dataNeto.push(rN);

                if (i === numPasos) {
                    rBrutoFin = rB;
                    retFin = ret;
                    rNetoFin = rN;
                    totFin = tot;
                }
            }

            const bEl = document.getElementById('cuenta_bruto_preview');
            const rEl = document.getElementById('cuenta_ret_preview');
            const rNota = document.getElementById('cuenta_ret_nota');
            const nEl = document.getElementById('cuenta_neto_preview');
            const tEl = document.getElementById('cuenta_total_preview');

            if (bEl) bEl.innerText = '+$ ' + window.FinanzMoney.formatQuantity(String(rBrutoFin));
            if (rEl) rEl.innerText = '-$ ' + window.FinanzMoney.formatQuantity(String(retFin));
            if (rNota) rNota.innerText = retFin > 0 ? '7% Art. 395 ET (Supera base 0,055 UVT/día)' : 'Exento (inferior a 0,055 UVT/día)';
            if (nEl) nEl.innerText = '+$ ' + window.FinanzMoney.formatQuantity(String(rNetoFin));
            if (tEl) tEl.innerText = '$ ' + window.FinanzMoney.formatQuantity(String(totFin));

            const ChartConstructor = window.Chart || (typeof Chart !== 'undefined' ? Chart : null);
            if (!ChartConstructor) return;

            if (chartCuenta) {
                chartCuenta.data.labels = labels;
                chartCuenta.data.datasets[0].data = dataBruto;
                chartCuenta.data.datasets[1].data = dataNeto;
                chartCuenta.update();
                return;
            }

            chartCuenta = new ChartConstructor(cuentaCanvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Rendimiento Bruto Acumulado',
                            data: dataBruto,
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22, 163, 74, 0.1)',
                            fill: true,
                            tension: 0.25,
                            pointRadius: 4,
                            pointHoverRadius: 7,
                            borderWidth: 2.5
                        },
                        {
                            label: 'Rendimiento Neto (después de Retención)',
                            data: dataNeto,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.12)',
                            fill: true,
                            tension: 0.25,
                            pointRadius: 4,
                            pointHoverRadius: 7,
                            borderWidth: 2.5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 14,
                                padding: 12,
                                font: { size: 13, weight: '600' }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return `📅 Proyección: ${context[0].label} con fondos quietos`;
                                },
                                label: function(context) {
                                    const val = window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(context.raw)).toFixed(2));
                                    return ` ${context.dataset.label}: +$ ${val} COP`;
                                },
                                afterBody: function(tooltipItems) {
                                    const idx = tooltipItems[0].dataIndex;
                                    const neto = dataNeto[idx];
                                    const tot = window.FinanzMoney.decimal(saldoBase).plus(String(neto)).toFixed(2);
                                    return [
                                        `— Saldo base actual: $ ${window.FinanzMoney.formatQuantity(String(saldoBase))} COP`,
                                        `— Saldo total estimado: $ ${window.FinanzMoney.formatQuantity(String(tot))} COP`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return '$ ' + window.FinanzMoney.formatQuantity(String(value));
                                }
                            },
                            title: {
                                display: true,
                                text: 'Intereses Generados (COP)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Línea de tiempo proyectada'
                            }
                        }
                    }
                }
            });
        }

        if (diasInput) {
            diasInput.addEventListener('input', function() {
                btnsDias.forEach(b => {
                    b.style.background = '#fff';
                    b.style.color = 'inherit';
                });
                simularProyeccionCuenta(diasInput.value);
            });
        }

        btnsDias.forEach(btn => {
            btn.addEventListener('click', function() {
                btnsDias.forEach(b => {
                    b.style.background = '#fff';
                    b.style.color = 'inherit';
                });
                this.style.background = 'var(--color-primary)';
                this.style.color = '#fff';
                const d = this.getAttribute('data-dias');
                if (diasInput) diasInput.value = d;
                simularProyeccionCuenta(d);
            });
        });

        const inicializarCuentaChart = () => {
            const ChartConstructor = window.Chart || (typeof Chart !== 'undefined' ? Chart : null);
            if (!ChartConstructor) {
                setTimeout(inicializarCuentaChart, 100);
                return;
            }
            simularProyeccionCuenta(diasInput ? diasInput.value : 30);
        };

        inicializarCuentaChart();
    }
    @endif

    @if($inversion->tipo === 'ACCION_ETF')
    const accionCanvas = document.getElementById('accionValoracionCanvas');
    if (accionCanvas) {
        const puntos = @json($puntosAccion);
        const costoBase = @json((string)$costoBaseUnit);
        const moneda = '{{ $monedaAccion }}';

        const labels = puntos.map(p => p.fecha);
        const dataPrecios = puntos.map(p => p.precio);
        const dataCostoBase = Array(puntos.length).fill(costoBase);

        const renderAccionChart = () => {
            const ChartConstructor = window.Chart || (typeof Chart !== 'undefined' ? Chart : null);
            if (!ChartConstructor) {
                setTimeout(renderAccionChart, 100);
                return;
            }

            if (ChartConstructor.getChart(accionCanvas)) {
                ChartConstructor.getChart(accionCanvas).destroy();
            }

            const pointColors = puntos.map(p => p.precio >= costoBase ? '#16a34a' : '#dc2626');
            const pointHoverColors = puntos.map(p => p.precio >= costoBase ? '#15803d' : '#b91c1c');

            new ChartConstructor(accionCanvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Cotización Acción / ETF (' + moneda + ')',
                            data: dataPrecios,
                            borderWidth: 3,
                            tension: 0.2,
                            spanGaps: true,
                            pointRadius: 6,
                            pointHoverRadius: 9,
                            pointBackgroundColor: pointColors,
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointHoverBackgroundColor: pointHoverColors,
                            pointHoverBorderColor: '#ffffff',
                            pointHoverBorderWidth: 3,
                            segment: {
                                borderColor: ctx => {
                                    const y0 = ctx.p0.parsed.y;
                                    const y1 = ctx.p1.parsed.y;
                                    // Si la cotización cayó o está por debajo del costo base: rojo; si subió y está en ganancia: verde
                                    if (y1 < costoBase || y1 < y0) {
                                        return '#dc2626';
                                    }
                                    return '#16a34a';
                                },
                                backgroundColor: ctx => {
                                    const y0 = ctx.p0.parsed.y;
                                    const y1 = ctx.p1.parsed.y;
                                    return (y1 < costoBase || y1 < y0)
                                        ? 'rgba(220, 38, 38, 0.08)'
                                        : 'rgba(22, 163, 74, 0.08)';
                                }
                            },
                            fill: {
                                target: 'origin',
                                above: 'rgba(22, 163, 74, 0.06)',
                                below: 'rgba(220, 38, 38, 0.06)'
                            }
                        },
                        {
                            label: 'Costo Base de Compra ($ ' + window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(costoBase)).toFixed(4)) + ' ' + moneda + ')',
                            data: dataCostoBase,
                            borderColor: '#64748b',
                            borderWidth: 2,
                            borderDash: [6, 4],
                            pointRadius: 0,
                            pointHoverRadius: 0,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 14,
                                padding: 12,
                                font: { size: 13, weight: '600' }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    const idx = context[0].dataIndex;
                                    const p = puntos[idx];
                                    return `📅 Cotización: ${p.fecha} (${p.origen})`;
                                },
                                label: function(context) {
                                    if (context.datasetIndex === 1) {
                                        return ` 🏷️ Costo Base: $ ${window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(context.raw)).toFixed(4))} ${moneda}`;
                                    }
                                    const val = window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(context.raw)).toFixed(4));
                                    return ` 📈 Precio Acción/ETF: $ ${val} ${moneda}`;
                                },
                                afterBody: function(tooltipItems) {
                                    const idx = tooltipItems[0].dataIndex;
                                    const p = puntos[idx];
                                    const dif = window.FinanzMoney.decimal(String(p.precio)).minus(costoBase);
                                    const pct = window.FinanzMoney.decimal(costoBase).gt('0') ? dif.div(costoBase).times('100') : window.FinanzMoney.decimal('0');
                                    const signo = pct >= 0 ? '+' : '';
                                    const estado = pct >= 0 ? '▲ Ganancia No Realizada' : '▼ Pérdida No Realizada';
                                    const valorTotalFmt = window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(p.valor_total)).toFixed(2));
                                    const unidadesFmt = window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(p.unidades)).toFixed(4));

                                    return [
                                        `— Variación vs Compra: ${signo}${pct.toFixed(2)}% ($ ${signo}${window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(dif)).toFixed(4))} ${moneda})`,
                                        `— Estado de la posición: ${estado}`,
                                        `— Valor Total Posición: $ ${valorTotalFmt} ${moneda} (${unidadesFmt} títulos)`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return '$ ' + window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(value)).toFixed(2));
                                }
                            },
                            title: {
                                display: true,
                                text: 'Precio por Acción / ETF (' + moneda + ')'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Línea de tiempo de cotizaciones'
                            }
                        }
                    }
                }
            });
        };

        renderAccionChart();
    }
    @endif

    @if(in_array($inversion->tipo, ['FIC', 'FPV', 'CESANTIAS'], true) && !empty($datosAdministrados))
    const adminCanvas = document.getElementById('administradosValoracionCanvas');
    if (adminCanvas) {
        const datosAdmin = @json($datosAdministrados);
        const montoInicial = @json((string)$inversion->monto_inicial);
        const tasaEsperada = @json((string)($datosAdministrados['tasa_esperada'] ?? '7'));
        const moneda = '{{ $inversion->moneda }}';
        const fechaAperturaStr = '{{ $inversion->fecha_apertura->format('Y-m-d') }}';

        // Unificar fechas cronológicas (esperada y real)
        const mapaFechas = new Map();

        // Agregar puntos esperados
        (datosAdmin.curva_esperada || []).forEach(p => {
            mapaFechas.set(p.fecha, { fecha: p.fecha, etiqueta: p.etiqueta, valorEsperado: p.valor, valorReal: null, eaReal: null });
        });

        // Agregar o cruzar puntos reales
        (datosAdmin.curva_real || []).forEach(r => {
            if (mapaFechas.has(r.fecha)) {
                const entry = mapaFechas.get(r.fecha);
                entry.valorReal = r.valor;
                entry.eaReal = r.ea;
            } else {
                // Calcular valor esperado teórico para esa fecha exacta
                const d1 = new Date(fechaAperturaStr);
                const d2 = new Date(r.fecha);
                const dias = Math.max(0, Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24)));
                const vEsp = window.FinanzMoney.decimal(montoInicial).plus(window.FinanzMoney.projectedInterest(montoInicial,tasaEsperada,String(dias),'365')).toFixed(2);

                mapaFechas.set(r.fecha, { fecha: r.fecha, etiqueta: r.etiqueta, valorEsperado: vEsp, valorReal: r.valor, eaReal: r.ea });
            }
        });

        const timelineOrdenada = Array.from(mapaFechas.values()).sort((a, b) => new Date(a.fecha) - new Date(b.fecha));

        const labels = timelineOrdenada.map(t => t.etiqueta || t.fecha);
        const dataEsperada = timelineOrdenada.map(t => t.valorEsperado);
        const dataReal = timelineOrdenada.map(t => t.valorReal);

        const eaActual = datosAdmin.ea_real_actual ?? tasaEsperada;
        const esSobreRendimiento = eaActual >= tasaEsperada;
        const colorReal = esSobreRendimiento ? '#16a34a' : '#dc2626';
        const bgReal = esSobreRendimiento ? 'rgba(22, 163, 74, 0.08)' : 'rgba(220, 38, 38, 0.08)';

        const renderAdminChart = () => {
            const ChartConstructor = window.Chart || (typeof Chart !== 'undefined' ? Chart : null);
            if (!ChartConstructor) {
                setTimeout(renderAdminChart, 100);
                return;
            }

            if (ChartConstructor.getChart(adminCanvas)) {
                ChartConstructor.getChart(adminCanvas).destroy();
            }

            new ChartConstructor(adminCanvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Rendimiento Real Observado (' + eaActual.toFixed(2) + '% E.A.)',
                            data: dataReal,
                            borderColor: colorReal,
                            backgroundColor: bgReal,
                            borderWidth: 3,
                            tension: 0.25,
                            spanGaps: true,
                            pointRadius: 6,
                            pointHoverRadius: 9,
                            pointBackgroundColor: colorReal,
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            fill: {
                                target: 'origin',
                                above: bgReal
                            }
                        },
                        {
                            label: 'Proyección Esperada (' + tasaEsperada.toFixed(2) + '% E.A.)',
                            data: dataEsperada,
                            borderColor: '#64748b',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            tension: 0.2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#64748b',
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 14,
                                padding: 12,
                                font: { size: 13, weight: '600' }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    const idx = context[0].dataIndex;
                                    const item = timelineOrdenada[idx];
                                    return `📅 Fecha: ${item.etiqueta || item.fecha}`;
                                },
                                label: function(context) {
                                    const val = window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(context.raw)).toFixed(2));
                                    return ` ${context.dataset.label.split('(')[0].trim()}: $ ${val} ${moneda}`;
                                },
                                afterBody: function(tooltipItems) {
                                    const idx = tooltipItems[0].dataIndex;
                                    const item = timelineOrdenada[idx];
                                    const lineas = [];

                                    if (item.valorReal !== null) {
                                        const difValor = window.FinanzMoney.decimal(String(item.valorReal)).minus(String(item.valorEsperado));
                                        const signoVal = difValor >= 0 ? '+' : '';
                                        const estado = difValor >= 0 ? '▲ Sobre la expectativa' : '▼ Bajo la expectativa';
                                        lineas.push(`— Estado: ${estado}`);
                                        lineas.push(`— Brecha vs Proyección: ${signoVal}$ ${window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(difValor)).toFixed(2))} ${moneda}`);
                                        if (item.eaReal !== null) {
                                            lineas.push(`— Tasa observada en extracto: ${window.FinanzMoney.decimal(String(item.eaReal)).toFixed(2).replace('.', ',')}% E.A.`);
                                        }
                                    } else {
                                        lineas.push(`— Proyección esperada a futuro: $ ${window.FinanzMoney.formatCanonicalValue(window.FinanzMoney.decimal(String(item.valorEsperado)).toFixed(2))} ${moneda}`);
                                    }

                                    return lineas;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return '$ ' + window.FinanzMoney.formatQuantity(String(value));
                                }
                            },
                            title: {
                                display: true,
                                text: 'Saldo en Cartera (' + moneda + ')'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Línea de tiempo del fondo'
                            }
                        }
                    }
                }
            });
        };

        renderAdminChart();
    }
    @endif
});

// Función de feedback reactivo para valoración porcentual en productos administrados
window.calcularImpactoPorcentajeAdministrado = function(valStr) {
    const {decimal, parseLocalizedValue, formatMoney, formatQuantity} = window.FinanzMoney;
    const totalEl = document.getElementById('admin-val-total-calculado'), variation = document.getElementById('admin-val-variacion-texto');
    try {
        const rate = decimal(String(valStr));
        const capital = decimal(@json((string)$inversion->monto_inicial));
        const expected = decimal(@json((string)($datosAdministrados['tasa_esperada'] ?? $inversion->rentabilidad_esperada_porc ?? '7')));
        const from = new Date('{{ $inversion->fecha_apertura->format('Y-m-d') }}T12:00:00Z');
        const value = document.getElementById('val_fecha')?.value;
        const until = value ? new Date(value + 'T12:00:00Z') : new Date();
        const days = String(Math.max(0, Math.ceil((until - from) / 86400000)));
        const factor = rate.div('100').plus('1');
        if (factor.lte('0')) throw new Error();
        const total = capital.times(factor.pow(decimal(days).div('365')));
        if (totalEl) totalEl.textContent = formatMoney(total.toFixed(2)) + ' {{ $inversion->moneda }}';
        if (variation) { const diff = rate.minus(expected); variation.textContent = (diff.gte('0') ? 'Sobre la expectativa: ' : 'Bajo la expectativa: ') + formatQuantity(diff.toFixed(2)) + ' % E.A.'; variation.className = 'ui-help'; }
    } catch { if (totalEl) totalEl.textContent = '—'; if (variation) variation.textContent = 'Revisa la tasa y su precisión.'; }
};
</script>
</div>
@endsection
