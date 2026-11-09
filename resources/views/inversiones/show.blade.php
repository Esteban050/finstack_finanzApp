@extends('layouts.app')
@section('title', 'Detalle de Inversión – ' . $inversion->nombre)
@section('content')
<div class="finance-page">
@php
    $labels = require resource_path('views/components/ui/investment-labels.php');
    $det = $presentacion['detalle_especifico'] ?? [];
    $vnr = $presentacion['valoracion_no_realizada'] ?? null;

    $interesPeriodicoCdt = '0.00';
    if ($inversion->tipo === 'CDT' && !empty($det['tasa_valor'])) {
        $cap = (float) ($det['capital'] ?? $inversion->monto_inicial);
        $tasa = (float) $det['tasa_valor'];
        $periodicidad = $det['periodicidad_pago'] ?? 'MENSUAL';
        $diasPeriodo = match(strtoupper($periodicidad)) {
            'MENSUAL' => 30,
            'TRIMESTRAL' => 90,
            'SEMESTRAL' => 180,
            'ANUAL' => 360,
            default => (int) ($det['plazo_dias'] ?? 360),
        };
        $base = (int) ($det['base_dias'] ?? 365);
        $interesPeriodicoCdt = number_format($cap * (pow(1 + ($tasa / 100), $diasPeriodo / $base) - 1), 2, '.', '');
    }

    $interesMensualCuenta = '0.00';
    if ($inversion->tipo === 'CUENTA_ALTO_RENDIMIENTO' && !empty($det['tasa_ea'])) {
        $saldoAct = (float) ($presentacion['valor_actual'] ?? $det['saldo_inicial'] ?? $inversion->monto_inicial);
        $tasa = (float) $det['tasa_ea'];
        $base = (int) ($det['base_dias'] ?? 365);
        $interesMensualCuenta = number_format($saldoAct * (pow(1 + ($tasa / 100), 30 / $base) - 1), 2, '.', '');
    }
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
    <div class="ui-inline" style="gap: 0.5rem; flex-wrap: wrap;">
        <x-ui.button :href="route('inversiones.portafolio')" variant="secondary">Portafolio</x-ui.button>
        <x-ui.button :href="route('inversiones.reportes.retenciones')" variant="secondary">Retenciones</x-ui.button>
        <x-ui.button :href="route('inversiones.reportes.utilidades')" variant="secondary">Utilidades</x-ui.button>
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
            $ {{ number_format((float) ($presentacion['valor_actual'] ?? $inversion->monto_inicial), 2) }}
            <small style="font-size: 0.9rem; color: #666;">{{ $inversion->moneda }}</small>
        </h2>
        @if($presentacion['unidades_actuales'] !== null)
            <p class="ui-help" style="margin: 0;">Unidades acumuladas: <strong>{{ number_format((float) $presentacion['unidades_actuales'], 8) }}</strong></p>
        @endif
    </x-ui.card>

    <x-ui.card>
        <span class="ui-help">Capital Aportado Neto</span>
        <h2 style="margin: 0.25rem 0;">
            $ {{ number_format((float) ($inversion->estaLiquidada() ? 0 : ((float)$indicadores['capital_aportado_neto'] < 0 ? 0 : $indicadores['capital_aportado_neto'])), 2) }}
        </h2>
        <p class="ui-help" style="margin: 0;">
            @if($inversion->estaLiquidada())
                Posición cerrada · Capital 100% recuperado
            @elseif((float)$indicadores['capital_aportado_neto'] < 0)
                Capital 100% recuperado (Retiros superan aportes)
            @else
                Total Aportes: ${{ number_format((float) $indicadores['total_aportes'], 2) }}
            @endif
        </p>
    </x-ui.card>

    <x-ui.card>
        <span class="ui-help">Ganancia / Rendimiento Neto</span>
        <h2 style="margin: 0.25rem 0; color: {{ (float)$indicadores['ganancia'] >= 0 ? '#16a34a' : '#dc2626' }};">
            {{ (float)$indicadores['ganancia'] >= 0 ? '+' : '' }}$ {{ number_format((float) $indicadores['ganancia'], 2) }}
        </h2>
        <p class="ui-help" style="margin: 0;">ROI: <strong>{{ $indicadores['roi'] ?? '0.00' }}%</strong> · XIRR: <strong>{{ $indicadores['xirr'] }}</strong></p>
    </x-ui.card>

    @if($vnr)
        <x-ui.card>
            <span class="ui-help">Valorización No Realizada (RF-VAL-02)</span>
            <h2 style="margin: 0.25rem 0; color: {{ (float)$vnr['valor'] >= 0 ? '#16a34a' : '#dc2626' }};">
                {{ (float)$vnr['valor'] >= 0 ? '+' : '' }}$ {{ number_format((float)$vnr['valor'], 2) }}
            </h2>
            <p class="ui-help" style="margin: 0;">Variación: <strong>{{ (float)$vnr['porcentaje'] >= 0 ? '+' : '' }}{{ $vnr['porcentaje'] }}%</strong> (No genera impuesto)</p>
        </x-ui.card>
    @elseif(isset($presentacion['rendimiento_estimado']))
        <x-ui.card>
            <span class="ui-help">
                {{ !empty($presentacion['intereses_cobrados_cdt']) && (float)$presentacion['intereses_cobrados_cdt'] > 0 ? 'Ganancia Bruta Pendiente por Cobrar' : 'Rendimiento Bruto al Vencimiento' }}
            </span>
            <h2 style="margin: 0.25rem 0; color: #16a34a;">
                +$ {{ number_format((float) $presentacion['rendimiento_estimado'], 2) }}
            </h2>
            <p class="ui-help" style="margin: 0;">
                @if(!empty($presentacion['intereses_cobrados_cdt']) && (float)$presentacion['intereses_cobrados_cdt'] > 0)
                    Cobrado: ${{ number_format((float) $presentacion['intereses_cobrados_cdt'], 2) }} · Total pactado: ${{ number_format((float) ($presentacion['rendimiento_bruto_total_cdt'] ?? '0'), 2) }}
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
                <p style="margin: 0.25rem 0; font-weight: 600;">$ {{ number_format((float) ($det['costo_promedio_unitario'] ?? '0'), 4) }}</p>
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
                    $ {{ number_format((float) ($det['valor_dividendo_por_unidad'] ?? '0'), 4) }} {{ $det['moneda_dividendo'] ?? 'COP' }}
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
                <span class="ui-help">Fondo / Catálogo:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">{{ $inversion->detalleFic?->catalogo?->nombre ?? 'FIC' }}</p>
            </div>
            <div>
                <span class="ui-help">Perfil de Riesgo del Fondo:</span>
                <p style="margin: 0.25rem 0; font-weight: 600;">{{ $inversion->detalleFic?->catalogo?->perfil ?? 'CONSERVADOR' }}</p>
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

<!-- FORMULARIO DE VALORACIÓN MANUAL DE MERCADO (GANANCIA / PÉRDIDA NO REALIZADA) -->
@if(!$inversion->estaLiquidada())
<x-ui.card title="Actualizar Valoración de Mercado (Cotización Actual)">
    <p class="ui-help" style="margin-bottom: 1rem;">Registra el precio actual de mercado para actualizar el valor total y calcular tu ganancia o pérdida no realizada.</p>
    <form method="POST" action="{{ route('inversiones.valoraciones.store', $inversion->id) }}">
        @csrf
        <div class="income-two-fields">
            <x-ui.date-input name="fecha" label="Fecha de la cotización" :min="$inversion->fecha_apertura->format('Y-m-d')" :max="now('America/Bogota')->format('Y-m-d')" :value="now('America/Bogota')->format('Y-m-d')" />

            @if($inversion->tieneUnidades())
                @php
                    $precioActual = (float) ($det['valor_actual_unidad'] ?? $det['costo_promedio_unitario'] ?? $inversion->precio_unitario_compra ?? 0);
                    $unidadesActuales = (float) ($presentacion['unidades_actuales'] ?? $det['unidades_total'] ?? $det['unidades'] ?? 0);
                    $costoBaseUnit = (float) ($det['costo_promedio_unitario'] ?? $det['valor_unidad_compra'] ?? $inversion->precio_unitario_compra ?? 0);
                @endphp
                <x-ui.field name="precio_unitario" label="Nuevo precio por unidad ({{ $inversion->moneda }}) *" :required="true">
                    <input id="val_precio_unitario" name="precio_unitario" type="number" step="0.00000001" min="0.00000001" value="{{ $precioActual > 0 ? $precioActual : '' }}" required placeholder="Ej: 105.50" oninput="calcularImpactoValoracion(this.value)">
                </x-ui.field>

                <div id="val-feedback" style="grid-column: 1 / -1; padding: 0.75rem 1rem; border-radius: 8px; background: rgba(0,0,0,0.03); border: 1px solid var(--ui-border); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.5rem;">
                    <div>
                        <span class="ui-help">Valor Total Proyectado:</span>
                        <strong id="val-total-calculado" style="font-size: 1.1rem; margin-left: 0.25rem;">$ {{ number_format($unidadesActuales * $precioActual, 2) }} {{ $inversion->moneda }}</strong>
                    </div>
                    <div id="val-variacion-texto">
                        @if($costoBaseUnit > 0 && $precioActual > 0)
                            @php
                                $difU = $precioActual - $costoBaseUnit;
                                $pctU = ($difU / $costoBaseUnit) * 100;
                                $ganU = $difU * $unidadesActuales;
                            @endphp
                            <span style="color: {{ $pctU >= 0 ? '#16a34a' : '#dc2626' }}; font-weight: 700;">
                                {{ $pctU >= 0 ? '▲ Ganancia:' : '▼ Pérdida:' }} {{ $pctU >= 0 ? '+' : '' }}{{ number_format($pctU, 2) }}% 
                                (${{ number_format($ganU, 2) }} {{ $inversion->moneda }})
                            </span>
                        @endif
                    </div>
                </div>
            @elseif($inversion->tipo === 'CDT')
                @php
                    $valorCausadoHoy = \App\Services\InversionService::valorCausado($inversion, \Carbon\CarbonImmutable::today());
                @endphp
                <x-ui.field name="valor" label="Valor causado o de mercado (COP) *" :required="true">
                    <input id="val_valor" name="valor" type="number" step="0.01" min="0.01" value="{{ $valorCausadoHoy['valor_causado'] ?? $inversion->monto_inicial }}" required placeholder="Monto total">
                </x-ui.field>
                <div style="grid-column: 1 / -1; display: flex; align-items: center; gap: 0.5rem;">
                    <label class="ui-check">
                        <input type="checkbox" name="usar_valor_causado" value="1" checked>
                        Aceptar valor causado sugerido por fórmula ($ {{ number_format((float) ($valorCausadoHoy['valor_causado'] ?? 0), 2) }})
                    </label>
                </div>
            @else
                <x-ui.field name="valor" label="Nuevo saldo / valor total de mercado (COP) *" :required="true">
                    <input id="val_valor" name="valor" type="number" step="0.01" min="0.01" value="{{ $presentacion['valor_actual'] ?? $inversion->monto_inicial }}" required placeholder="Monto total">
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

            <x-ui.date-input name="fecha" label="Fecha de la transacción" :min="$inversion->fecha_apertura->format('Y-m-d')" :value="now('America/Bogota')->format('Y-m-d')" />

            <div id="trx-field-monto">
                <x-ui.field name="monto" label="Monto total bruto / operación" :required="true">
                    <input id="trx_monto" name="monto" type="number" step="0.01" min="0.01" value="{{ old('monto') }}" required placeholder="Monto en moneda base">
                </x-ui.field>
            </div>

            <div id="trx-field-unidades" style="{{ $inversion->tieneUnidades() ? '' : 'display: none;' }}">
                <x-ui.field name="unidades" label="Cantidad de unidades / títulos">
                    <input id="trx_unidades" name="unidades" type="number" step="0.00000001" min="0.00000001" value="{{ old('unidades') }}" placeholder="Ej: 10.00000000">
                </x-ui.field>
            </div>

            <div id="trx-field-valor-unidad" style="{{ $inversion->tieneUnidades() ? '' : 'display: none;' }}">
                <x-ui.field name="valor_unidad" label="Valor / Precio de la unidad">
                    <input id="trx_valor_unidad" name="valor_unidad" type="number" step="0.00000001" min="0.00000001" value="{{ old('valor_unidad', $det['valor_actual_unidad'] ?? '') }}" placeholder="Precio unitario">
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
                        <input id="trx_trm" name="tasa_cambio_usada" type="number" step="0.0001" value="{{ old('tasa_cambio_usada', $det['trm_dividendo'] ?? '') }}" placeholder="Vacío = toma TRM de BD">
                    </x-ui.field>
                </div>
            @endif

            <div id="trx-field-comision" style="{{ in_array($inversion->tipo, ['ACCION_ETF', 'FIC'], true) ? '' : 'display: none;' }}">
                <x-ui.field name="comision" label="Comisión de la operación (si aplica)">
                    <input id="trx_comision" name="comision" type="number" step="0.01" min="0" value="{{ old('comision', '0.00') }}" placeholder="0.00">
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
                    <tr>
                        <td>{{ $mov->fecha->format('d/m/Y') }}</td>
                        <td>
                            <x-ui.badge tone="primary">{{ $labels[$mov->tipo] ?? $mov->tipo }}</x-ui.badge>
                        </td>
                        <td class="ui-number">
                            $ {{ number_format((float) ($mov->monto_bruto ?? $mov->monto), 2) }}
                        </td>
                        <td class="ui-number" style="color: {{ (float)$mov->retencion_aplicada > 0 ? '#dc2626' : '#666' }};">
                            {{ (float)$mov->retencion_aplicada > 0 ? '-$' . number_format((float)$mov->retencion_aplicada, 2) : '—' }}
                        </td>
                        <td class="ui-number" style="color: {{ (float)$mov->gmf_aplicado > 0 ? '#dc2626' : '#666' }};">
                            {{ (float)$mov->gmf_aplicado > 0 ? '-$' . number_format((float)$mov->gmf_aplicado, 2) : '—' }}
                        </td>
                        <td class="ui-number" style="font-weight: 600;">
                            $ {{ number_format((float) $mov->monto_cop, 2) }}
                        </td>
                        <td>{{ $mov->nota ?? '—' }}</td>
                        <td>
                            @if(!$mov->es_inicial)
                                <form method="POST" action="{{ route('inversiones.movimientos.destroy', [$inversion->id, $mov->id]) }}" onsubmit="return confirm('¿Eliminar esta transacción?');">
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
    const p = parseFloat(nuevoPrecio) || 0;
    const unidades = parseFloat('{{ $unidadesActuales ?? 0 }}') || 0;
    const costoUnit = parseFloat('{{ $costoBaseUnit ?? 0 }}') || 0;
    const moneda = '{{ $inversion->moneda }}';
    const totalEl = document.getElementById('val-total-calculado');
    const varEl = document.getElementById('val-variacion-texto');
    if (!totalEl || !varEl) return;

    const total = p * unidades;
    totalEl.innerText = '$ ' + total.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + moneda;

    if (costoUnit > 0 && p > 0) {
        const gananciaUnit = p - costoUnit;
        const gananciaTotal = gananciaUnit * unidades;
        const pct = ((p - costoUnit) / costoUnit) * 100;
        const signo = pct >= 0 ? '+' : '';
        const color = pct >= 0 ? '#16a34a' : '#dc2626';
        varEl.innerHTML = `<span style="color: ${color}; font-weight: 700;">${pct >= 0 ? '▲ Ganancia:' : '▼ Pérdida:'} ${signo}${pct.toFixed(2)}% ($ ${gananciaTotal.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${moneda})</span>`;
    } else {
        varEl.innerHTML = '';
    }
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
        const u = parseFloat(unidadesEl.value) || 0;
        const p = parseFloat(precioEl.value) || 0;
        if (u > 0 && p > 0) {
            montoEl.value = (u * p).toFixed(2);
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
            if (montoEl) montoEl.value = '{{ $interesPeriodicoCdt }}';
            if (notaEl) notaEl.value = 'Cobro de intereses periódicos pactados';
        } else if (tipo === 'REDENCION') {
            if (montoLabel) montoLabel.innerText = 'Capital a liquidar / redimir (COP) *';
            if (montoEl) montoEl.value = '{{ $det['capital'] ?? $inversion->monto_inicial }}';
            if (notaEl) notaEl.value = 'Redención y liquidación final del CDT';
        }
    } else if (tipoInv === 'ACCION_ETF') {
        if (comisionWrap) comisionWrap.style.display = 'block';
        if (fpvPermanencia) fpvPermanencia.style.display = 'none';

        if (tipo === 'COMPRA') {
            if (unidadesWrap) unidadesWrap.style.display = 'block';
            if (unidadesLabel) unidadesLabel.innerText = 'Cantidad de unidades a comprar';
            if (precioWrap) precioWrap.style.display = 'block';
            if (precioLabel) precioLabel.innerText = 'Precio unitario de compra';
            if (precioEl) precioEl.value = '{{ $det['valor_actual_unidad'] ?? $det['costo_promedio_unitario'] ?? '' }}';
            if (fieldMoneda) fieldMoneda.style.display = 'none';
            if (fieldTrm) fieldTrm.style.display = 'none';
            if (notaEl) notaEl.value = 'Compra adicional de títulos bursátiles';
            autoCalcular();
        } else if (tipo === 'VENTA') {
            if (unidadesWrap) unidadesWrap.style.display = 'block';
            if (unidadesLabel) unidadesLabel.innerText = 'Cantidad de unidades a vender';
            if (unidadesEl) unidadesEl.value = '{{ $det['unidades_total'] ?? '' }}';
            if (precioWrap) precioWrap.style.display = 'block';
            if (precioLabel) precioLabel.innerText = 'Precio unitario de venta';
            if (precioEl) precioEl.value = '{{ $det['valor_actual_unidad'] ?? $det['costo_promedio_unitario'] ?? '' }}';
            if (fieldMoneda) fieldMoneda.style.display = 'none';
            if (fieldTrm) fieldTrm.style.display = 'none';
            if (notaEl) notaEl.value = 'Venta de títulos en mercado';
            autoCalcular();
        } else if (tipo === 'DIVIDENDO') {
            const monedaActual = document.getElementById('trx_moneda')?.value || '{{ $det['moneda_dividendo'] ?? 'COP' }}';
            if (unidadesWrap) unidadesWrap.style.display = 'block';
            if (unidadesLabel) unidadesLabel.innerText = 'Acciones con derecho a dividendo';
            if (unidadesEl) unidadesEl.value = '{{ $det['unidades_total'] ?? '' }}';
            if (precioWrap) precioWrap.style.display = 'block';
            if (precioLabel) precioLabel.innerText = 'Dividendo por acción decretado (' + monedaActual + ')';
            if (precioEl) precioEl.value = '{{ $det['valor_dividendo_por_unidad'] ?? '' }}';
            if (fieldMoneda) fieldMoneda.style.display = 'block';
            if (fieldTrm) fieldTrm.style.display = (monedaActual === 'USD') ? 'block' : 'none';
            if (notaEl) notaEl.value = 'Cobro / Pago de dividendos decretados';
            autoCalcular();
        }
    } else if (tipoInv === 'FIC') {
        if (fieldMoneda) fieldMoneda.style.display = 'none';
        if (fieldTrm) fieldTrm.style.display = 'none';
        if (fpvPermanencia) fpvPermanencia.style.display = 'none';
        if (comisionWrap) comisionWrap.style.display = 'block';

        if (tipo === 'APORTE') {
            if (unidadesWrap) unidadesWrap.style.display = 'block';
            if (unidadesLabel) unidadesLabel.innerText = 'Cuotas / Unidades a suscribir';
            if (precioWrap) precioWrap.style.display = 'block';
            if (precioLabel) precioLabel.innerText = 'Valor de la cuota / unidad';
            if (precioEl) precioEl.value = '{{ $det['valor_unidad_actual'] ?? $det['valor_unidad_compra'] ?? '' }}';
            if (notaEl) notaEl.value = 'Aporte adicional al fondo de inversión';
            autoCalcular();
        } else if (tipo === 'REDENCION') {
            if (unidadesWrap) unidadesWrap.style.display = 'block';
            if (unidadesLabel) unidadesLabel.innerText = 'Cuotas / Unidades a redimir';
            if (unidadesEl) unidadesEl.value = '{{ $det['unidades'] ?? '' }}';
            if (precioWrap) precioWrap.style.display = 'block';
            if (precioLabel) precioLabel.innerText = 'Valor de la cuota / unidad actual';
            if (precioEl) precioEl.value = '{{ $det['valor_unidad_actual'] ?? $det['valor_unidad_compra'] ?? '' }}';
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
            if (montoEl) montoEl.value = '{{ $interesMensualCuenta }}';
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

        const tieneUnidadesFpv = {{ (!empty($det['unidades']) && (float)$det['unidades'] > 0) ? 'true' : 'false' }};
        if (tieneUnidadesFpv) {
            if (unidadesWrap) unidadesWrap.style.display = 'block';
            if (unidadesLabel) unidadesLabel.innerText = 'Unidades aportadas (opcional si digita monto)';
            if (precioWrap) precioWrap.style.display = 'block';
            if (precioLabel) precioLabel.innerText = 'Valor de la cuota / unidad';
            if (precioEl) precioEl.value = '{{ $det['valor_unidad'] ?? '' }}';
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
            if (montoEl) montoEl.value = '{{ $det['saldo_actual'] ?? $inversion->monto_inicial }}';
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
            if (montoEl) montoEl.value = '{{ $det['saldo'] ?? $inversion->monto_inicial }}';
            if (notaEl) notaEl.value = 'Retiro de cesantías';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const trxUnidades = document.getElementById('trx_unidades');
    const trxPrecio = document.getElementById('trx_valor_unidad');
    const trxMonto = document.getElementById('trx_monto');

    function autoCalcularTrxMonto() {
        if (!trxUnidades || !trxPrecio || !trxMonto) return;
        const u = parseFloat(trxUnidades.value) || 0;
        const p = parseFloat(trxPrecio.value) || 0;
        if (u > 0 && p > 0) {
            trxMonto.value = (u * p).toFixed(2);
        }
    }

    if (trxUnidades) trxUnidades.addEventListener('input', autoCalcularTrxMonto);
    if (trxPrecio) trxPrecio.addEventListener('input', autoCalcularTrxMonto);

    const trxTipo = document.getElementById('trx_tipo');
    if (trxTipo) {
        adaptarFormularioTrx(trxTipo.value);
    }
});
</script>
</div>
@endsection
