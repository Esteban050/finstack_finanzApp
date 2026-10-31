@extends('layouts.app')
@section('title', $inversion->nombre . ' · Detalle y Seguimiento de Inversión')
@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="space-y-6">
    <header class="income-page-header">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('inversiones.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                    <x-icon name="nav-atras" :size="16" />
                    Volver a Inversiones
                </a>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900">{{ $inversion->nombre }}</h1>
                @if($inversion->estado === 'ACTIVA')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">ACTIVA</span>
                @elseif($inversion->estado === 'VENCIDA')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-300">VENCIDA</span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 border border-gray-300">LIQUIDADA</span>
                @endif
                <span class="px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">{{ str_replace('_', ' ', $inversion->tipo) }}</span>
                <span class="px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">{{ $inversion->moneda }}</span>
                @if($inversion->nivel_riesgo)
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200">Riesgo {{ ucfirst(strtolower($inversion->nivel_riesgo)) }}</span>
                @endif
            </div>
            <p class="text-sm text-gray-600 mt-1">
                Apertura: {{ \Carbon\CarbonImmutable::parse($inversion->fecha_apertura)->format('d/m/Y') }}
                @if($inversion->entidad) · Emisor/Entidad: {{ $inversion->entidad }} @endif
                @if($inversion->tasa_ea) · Tasa pactada: {{ number_format($inversion->tasa_ea, 2) }}% EA @endif
                @if($inversion->fecha_vencimiento) · Vencimiento: {{ \Carbon\CarbonImmutable::parse($inversion->fecha_vencimiento)->format('d/m/Y') }} @endif
                @if($inversion->simbolo) · Símbolo / Ticker: <span class="font-mono font-bold">{{ $inversion->simbolo }}</span> @endif
                @if($inversion->tieneUnidades() && $inversion->precio_unitario_compra) · Precio compra: {{ $inversion->moneda === 'COP' ? '$' : 'US$' }} {{ number_format((float)$inversion->precio_unitario_compra, 4) }} @endif
            </p>
        </div>
        <div class="ui-inline">
            <x-ui.button :href="route('inversiones.portafolio')" variant="secondary">
                <x-icon name="act-balance" :size="18" />
                Portafolio
            </x-ui.button>
        </div>
    </header>

    @if(session('status'))
        <div class="p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-center gap-2">
            <x-icon name="ui-exito" :size="20" class="text-green-600" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm space-y-1">
            <div class="flex items-center gap-2 font-bold">
                <x-icon name="ui-error" :size="20" class="text-red-600" />
                <span>Ocurrió un error:</span>
            </div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($presentacion['valoracion_desactualizada'] ?? false)
        <div class="p-4 rounded-lg bg-amber-50 border border-amber-300 text-amber-900 text-sm flex items-start gap-3">
            <x-icon name="ui-alerta" :size="20" class="text-amber-600 shrink-0 mt-0.5" />
            <div>
                <p class="font-bold">Valoración desactualizada (RN-039.4)</p>
                <p class="text-xs text-amber-800">Esta inversión activa lleva más de 35 días sin registrar un nuevo valor de mercado. Se recomienda actualizar su valoración para mantener los cálculos de rentabilidad al día.</p>
            </div>
        </div>
    @endif

    {{-- ========================================================================= --}}
    {{-- CASO 1: PRODUCTO VARIABLE (ACCIONES, ETF, CRIPTO) - DIAGRAMA Y SEGUIMIENTO --}}
    {{-- ========================================================================= --}}
    @if($inversion->tieneUnidades())
        @php
            $precioCompra = (float) ($inversion->precio_unitario_compra ?? 0);
            $unidadesActuales = (float) (\App\Services\InversionService::unidadesActuales($inversion) ?? 0);
            $ultimaVal = $valoraciones->first();
            $precioActual = (float) ($ultimaVal?->precio_unitario ?? $precioCompra);
            $diffPrecio = $precioActual - $precioCompra;
            $pctCambio = $precioCompra > 0 ? (($diffPrecio / $precioCompra) * 100) : 0;
            $gananciaTotalUnit = $diffPrecio * $unidadesActuales;

            $metaPorc = (float) ($inversion->rentabilidad_esperada_porc ?? 15.0);
            $metaMeses = (int) ($inversion->plazo_meses_esperado ?? 3);
            $precioObjetivo = $precioCompra * (1 + ($metaPorc / 100));
            $progresoMeta = $metaPorc > 0 ? min(100, max(0, ($pctCambio / $metaPorc) * 100)) : 0;

            // Histórico ordenado cronológicamente para el gráfico
            $labelsGrafica = [];
            $datosGraficaPrecios = [];
            $datosGraficaCompra = [];
            $datosGraficaMeta = [];

            foreach ($valoracionesCronologicas as $v) {
                $f = \Carbon\CarbonImmutable::parse($v->fecha)->format('d/m');
                $labelsGrafica[] = $f;
                $datosGraficaPrecios[] = (float) ($v->precio_unitario ?? 0);
                $datosGraficaCompra[] = $precioCompra;
                $datosGraficaMeta[] = $precioObjetivo;
            }

            // Si solo hay una valoración, agregar fecha proyectada de meta
            if (count($labelsGrafica) <= 1) {
                $labelsGrafica[] = \Carbon\CarbonImmutable::parse($inversion->fecha_apertura)->addMonths($metaMeses)->format('d/m') . ' (Meta)';
                $datosGraficaPrecios[] = null;
                $datosGraficaCompra[] = $precioCompra;
                $datosGraficaMeta[] = $precioObjetivo;
            }
        @endphp

        {{-- TARJETA DESTACADA: REGISTRO DIARIO DE NUEVO PRECIO --}}
        @if(! $inversion->estaLiquidada())
            <x-ui.card class="bg-gradient-to-r from-purple-50/50 via-white to-indigo-50/40 border border-purple-200">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-purple-600 animate-pulse"></span>
                            <h2 class="text-base font-bold text-gray-900">Actualizar Cotización / Precio Actual de la Acción</h2>
                        </div>
                        <p class="text-xs text-gray-600">
                            Ingresa el precio de hoy para registrar si subió o bajó. El sistema actualizará automáticamente el diagrama de rentabilidad y guardará el histórico.
                        </p>
                        <div class="flex flex-wrap items-center gap-3 pt-2 text-xs">
                            <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded font-mono">
                                Compra: <strong>{{ $inversion->moneda === 'COP' ? '$' : 'US$' }} {{ number_format($precioCompra, 4) }}</strong>
                            </span>
                            <span class="bg-purple-100 text-purple-800 px-2 py-0.5 rounded font-mono">
                                Meta esperada: <strong>+{{ number_format($metaPorc, 2) }}%</strong> (Obj: {{ $inversion->moneda === 'COP' ? '$' : 'US$' }} {{ number_format($precioObjetivo, 4) }})
                            </span>
                            <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-mono">
                                Unidades: <strong>{{ number_format($unidadesActuales, 4) }}</strong>
                            </span>
                        </div>
                    </div>

                    {{-- FORMULARIO RÁPIDO DE REGISTRO --}}
                    <div class="w-full lg:w-auto bg-white p-4 rounded-lg border border-purple-200 shadow-sm">
                        <form method="POST" action="{{ route('inversiones.valoraciones.store', $inversion->id) }}" class="space-y-3">
                            @csrf
                            <div class="flex flex-col sm:flex-row items-center gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 uppercase mb-0.5">Fecha de Cotización</label>
                                    <input type="date" name="fecha" required max="{{ date('Y-m-d') }}" min="{{ $inversion->fecha_apertura->toDateString() }}" value="{{ date('Y-m-d') }}" class="text-sm rounded-md border-gray-300 shadow-sm" />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 uppercase mb-0.5">Nuevo Precio Unitario ({{ $inversion->moneda }})*</label>
                                    <input type="number" step="0.00000001" min="0.00000001" name="precio_unitario" id="input_nuevo_precio" required placeholder="Ej: 10.80" value="{{ $precioActual > 0 ? $precioActual : '' }}" class="text-sm rounded-md border-gray-300 shadow-sm font-mono w-40" oninput="calcularImpactoNuevoPrecio(this.value)" />
                                </div>
                                <div class="self-end pt-2 sm:pt-0">
                                    <x-ui.button type="submit" variant="primary" class="text-xs py-2 whitespace-nowrap">
                                        <x-icon name="act-confirmar" :size="16" />
                                        Registrar Cotización
                                    </x-ui.button>
                                </div>
                            </div>

                            {{-- FEEDBACK REACTIVO EN VIVO --}}
                            <div id="box-feedback-precio" class="p-2 rounded text-xs border transition-colors flex items-center justify-between {{ $pctCambio >= 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900' }}">
                                <div class="flex items-center gap-1.5 font-bold" id="txt-variacion-precio">
                                    <span>{{ $pctCambio >= 0 ? '▲ GANANCIA:' : '▼ PÉRDIDA:' }}</span>
                                    <span>{{ ($pctCambio >= 0 ? '+' : '') . number_format($pctCambio, 2) }}%</span>
                                    <span>({{ ($gananciaTotalUnit >= 0 ? '+' : '') . ($inversion->moneda === 'COP' ? '$' : 'US$') . ' ' . number_format($gananciaTotalUnit, 2) }})</span>
                                </div>
                                <div class="text-[11px] text-gray-600" id="txt-valor-total-estimado">
                                    Valor total: {{ $inversion->moneda === 'COP' ? '$' : 'US$' }} {{ number_format($precioActual * $unidadesActuales, 2) }}
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </x-ui.card>
        @endif

        {{-- DIAGRAMA / GRÁFICA INTERACTIVA DE RENTA VARIABLE (PRECIO COMPRA vs REAL vs META) --}}
        <x-ui.card>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b gap-3">
                <div>
                    <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <span>Diagrama de Trayectoria y Rendimiento de la Acción</span>
                        <span class="text-xs px-2 py-0.5 rounded font-mono font-bold {{ $pctCambio >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                            {{ ($pctCambio >= 0 ? '+' : '') . number_format($pctCambio, 2) }}% Actual
                        </span>
                    </h2>
                    <p class="text-xs text-gray-500">Compara el precio de compra inicial con las subidas y bajadas registradas en el histórico y la meta objetivo esperada.</p>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span> Compra (${{ number_format($precioCompra, 2) }})</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-purple-500 inline-block"></span> Meta (${{ number_format($precioObjetivo, 2) }})</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full {{ $pctCambio >= 0 ? 'bg-emerald-500' : 'bg-rose-500' }} inline-block"></span> Histórico Real</span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 pt-4">
                {{-- GRÁFICA DE LÍNEAS INTERACTIVA --}}
                <div class="lg:col-span-3 h-72">
                    <canvas id="graficaRentaVariable"></canvas>
                </div>

                {{-- PANEL LATERAL DE INDICADORES DE META Y P&L --}}
                <div class="space-y-4 bg-gray-50/70 p-4 rounded-lg border border-gray-200 flex flex-col justify-between">
                    <div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-1">Resultado Acumulado</span>
                        <div class="text-2xl font-black font-mono {{ $pctCambio >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ ($pctCambio >= 0 ? '+' : '') . number_format($pctCambio, 2) }}%
                        </div>
                        <div class="text-xs text-gray-600 font-semibold mt-0.5">
                            {{ ($gananciaTotalUnit >= 0 ? '+' : '') . ($inversion->moneda === 'COP' ? '$' : 'US$') . ' ' . number_format($gananciaTotalUnit, 2) }}
                        </div>
                    </div>

                    <div class="space-y-1.5 border-t pt-3">
                        <span class="text-xs font-bold text-gray-700 block">Progreso hacia tu meta</span>
                        <div class="flex justify-between text-xs text-purple-900 font-semibold">
                            <span>Meta: +{{ number_format($metaPorc, 2) }}% ({{ $metaMeses }} meses)</span>
                            <span class="font-bold font-mono">{{ number_format($progresoMeta, 1) }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                            <div class="bg-purple-600 h-2.5 rounded-full transition-all duration-500" style="width: {{ $progresoMeta }}%"></div>
                        </div>
                        <p class="text-[11px] text-gray-500 pt-0.5">
                            Objetivo: <strong>{{ $inversion->moneda === 'COP' ? '$' : 'US$' }} {{ number_format($precioObjetivo, 2) }}</strong>
                        </p>
                    </div>

                    <div class="border-t pt-3 text-xs text-gray-500 space-y-1">
                        <div class="flex justify-between">
                            <span>Cotizaciones registradas:</span>
                            <span class="font-bold text-gray-800">{{ count($valoraciones) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Última fecha:</span>
                            <span class="font-bold text-gray-800">{{ $ultimaVal ? \Carbon\CarbonImmutable::parse($ultimaVal->fecha)->format('d/m/Y') : 'Hoy' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui.card>
    @endif

    {{-- ========================================================================= --}}
    {{-- CASO 2: PRODUCTO FIJO (CDT / BONOS) - DIAGRAMA Y CAUSACIÓN AL VENCIMIENTO --}}
    {{-- ========================================================================= --}}
    @if($inversion->tieneTasa())
        @php
            $tasaEa = (float) ($inversion->tasa_ea ?? 0);
            $plazoDias = (int) ($inversion->plazo_dias ?? 365);
            $montoInicial = (float) $inversion->monto_inicial;
            $diasTranscurridos = $valorCausadoPropuesto ? $valorCausadoPropuesto['dias_transcurridos'] : 0;
            $valorCausadoHoy = $valorCausadoPropuesto ? (float) $valorCausadoPropuesto['valor_causado'] : $montoInicial;
            $rendimientoEstimadoTotal = (float) ($presentacion['rendimiento_estimado_vencimiento'] ?? 0);
            $totalAlVencimiento = $montoInicial + $rendimientoEstimadoTotal;
        @endphp

        <x-ui.card>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b gap-3">
                <div>
                    <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <span>Diagrama de Rendimiento y Causación (CDT / Renta Fija)</span>
                        <span class="text-xs px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-mono font-bold">{{ number_format($tasaEa, 2) }}% EA</span>
                    </h2>
                    <p class="text-xs text-gray-500">Evolución del capital pactado con capitalización continua desde la apertura hasta el vencimiento.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if($valorCausadoPropuesto && ! $inversion->estaLiquidada())
                        <form method="POST" action="{{ route('inversiones.valoraciones.store', $inversion->id) }}">
                            @csrf
                            <input type="hidden" name="fecha" value="{{ $valorCausadoPropuesto['fecha'] }}" />
                            <input type="hidden" name="usar_valor_causado" value="1" />
                            <x-ui.button type="submit" variant="primary" class="text-xs py-1.5">
                                <x-icon name="ui-exito" :size="14" />
                                Aceptar Valor Causado a Hoy (${{ number_format($valorCausadoHoy, 0, ',', '.') }})
                            </x-ui.button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 pt-4">
                {{-- GRÁFICA DE CAUSACIÓN --}}
                <div class="lg:col-span-3 h-72">
                    <canvas id="graficaRentaFija"></canvas>
                </div>

                {{-- PANEL DE RESUMEN Y CAUSACIÓN --}}
                <div class="space-y-4 bg-gray-50/70 p-4 rounded-lg border border-gray-200 flex flex-col justify-between">
                    <div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-1">Rendimiento al Vencimiento</span>
                        <div class="text-2xl font-black font-mono text-emerald-700">
                            +${{ number_format($rendimientoEstimadoTotal, 0, ',', '.') }}
                        </div>
                        <div class="text-xs text-gray-600 font-semibold mt-0.5">
                            Total: ${{ number_format($totalAlVencimiento, 0, ',', '.') }} {{ $inversion->moneda }}
                        </div>
                    </div>

                    <div class="space-y-2 border-t pt-3">
                        <span class="text-xs font-bold text-gray-700 block">Tiempo transcurrido</span>
                        <div class="flex justify-between text-xs text-gray-600">
                            <span>{{ $diasTranscurridos }} de {{ $plazoDias }} días</span>
                            <span class="font-bold">{{ $plazoDias > 0 ? number_format(min(100, ($diasTranscurridos / $plazoDias) * 100), 1) : 0 }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ $plazoDias > 0 ? min(100, ($diasTranscurridos / $plazoDias) * 100) : 0 }}%"></div>
                        </div>
                    </div>

                    <div class="border-t pt-3 text-xs text-gray-600 space-y-1">
                        <div class="flex justify-between">
                            <span>Capital base:</span>
                            <span class="font-bold text-gray-900">${{ number_format($montoInicial, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Valor causado hoy:</span>
                            <span class="font-black text-indigo-700">${{ number_format($valorCausadoHoy, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Tasa mensual eq.:</span>
                            <span class="font-bold text-gray-800">{{ number_format(((pow(1 + ($tasaEa / 100), 1 / 12) - 1) * 100), 2) }}% E.M.</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui.card>
    @endif

    {{-- TARJETAS KPI DE RENTABILIDAD GENERAL (RF-040) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ui.card class="border-l-4 border-l-indigo-500">
            <span class="text-xs uppercase font-bold text-gray-500 tracking-wider">Valor Actual de Mercado</span>
            <div class="text-2xl font-black text-gray-900 mt-1">
                {{ $inversion->moneda === 'COP' ? '$' : 'US$' }} {{ number_format((float)$indicadores['valor_actual'], 2, ',', '.') }}
            </div>
            <div class="text-xs text-gray-500 mt-1">
                @if($indicadores['fecha_valor_actual'])
                    A fecha: {{ \Carbon\CarbonImmutable::parse($indicadores['fecha_valor_actual'])->format('d/m/Y') }}
                @else
                    Sin valoración
                @endif
                @if($inversion->tieneUnidades())
                    · {{ number_format((float) \App\Services\InversionService::unidadesActuales($inversion), 4) }} unidades
                @endif
            </div>
        </x-ui.card>

        <x-ui.card class="border-l-4 border-l-blue-500">
            <span class="text-xs uppercase font-bold text-gray-500 tracking-wider">Capital Aportado Neto</span>
            <div class="text-2xl font-black text-gray-900 mt-1">
                {{ $inversion->moneda === 'COP' ? '$' : 'US$' }} {{ number_format((float)$indicadores['capital_aportado_neto'], 2, ',', '.') }}
            </div>
            <div class="text-xs text-gray-500 mt-1">
                Aportes: {{ number_format((float)$indicadores['total_aportes'], 2, ',', '.') }} · Retiros: {{ number_format((float)$indicadores['total_retiros'], 2, ',', '.') }}
            </div>
        </x-ui.card>

        <x-ui.card class="border-l-4 {{ (float)$indicadores['ganancia'] >= 0 ? 'border-l-emerald-500' : 'border-l-rose-500' }}">
            <span class="text-xs uppercase font-bold text-gray-500 tracking-wider">Ganancia / Pérdida Total</span>
            <div class="text-2xl font-black mt-1 {{ (float)$indicadores['ganancia'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ (float)$indicadores['ganancia'] >= 0 ? '+' : '' }}{{ $inversion->moneda === 'COP' ? '$' : 'US$' }} {{ number_format((float)$indicadores['ganancia'], 2, ',', '.') }}
            </div>
            <div class="text-xs text-gray-500 mt-1">
                ROI: <span class="font-bold {{ (float)($indicadores['roi'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $indicadores['roi'] !== null ? number_format((float)$indicadores['roi'], 2, ',', '.') . '%' : 'N/A' }}</span>
            </div>
        </x-ui.card>

        <x-ui.card class="border-l-4 border-l-purple-500">
            <span class="text-xs uppercase font-bold text-gray-500 tracking-wider">Tasa TIR (XIRR) y Real</span>
            <div class="text-2xl font-black text-purple-900 mt-1">
                {{ $indicadores['xirr'] }}
            </div>
            <div class="text-xs text-gray-500 mt-1">
                Rentabilidad real: <span class="font-bold text-gray-800">{{ $indicadores['rentabilidad_real'] }}</span>
                @if($indicadores['inflacion_vigencia'])
                    (IPC: {{ $indicadores['inflacion_vigencia'] }}%)
                @endif
            </div>
        </x-ui.card>
    </div>

    @if(!empty($indicadores['mensajes']))
        <div class="p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-900 text-xs space-y-1">
            @foreach($indicadores['mensajes'] as $msg)
                <p class="flex items-center gap-1.5"><x-icon name="ui-info" :size="16" class="text-blue-600 shrink-0" /> {{ $msg }}</p>
            @endforeach
        </div>
    @endif

    {{-- SECCIÓN DE HISTÓRICO DE VALORACIONES Y COTIZACIONES (RF-039) --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Histórico de Cotizaciones y Variaciones de Mercado (RF-039)</h2>
                <p class="text-xs text-gray-600">Registro cronológico de las subidas y bajadas del precio. El último valor registrado determina el saldo actual.</p>
            </div>
        </div>

        {{-- TABLA DE VALORACIONES CON SUBIDAS Y BAJADAS --}}
        <x-ui.card class="p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 border-b text-xs uppercase font-semibold text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3 text-right">Valor Total ({{ $inversion->moneda }})</th>
                            @if($inversion->tieneUnidades())
                                <th class="px-4 py-3 text-right">Precio Unitario</th>
                                <th class="px-4 py-3 text-right">Variación vs Compra</th>
                            @endif
                            <th class="px-4 py-3">Origen</th>
                            <th class="px-4 py-3 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php
                            $compraRef = (float) ($inversion->precio_unitario_compra ?? 0);
                        @endphp
                        @forelse($valoraciones as $val)
                            @php
                                $pUnit = (float) ($val->precio_unitario ?? 0);
                                $varPct = $compraRef > 0 && $pUnit > 0 ? ((($pUnit - $compraRef) / $compraRef) * 100) : 0;
                            @endphp
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700 font-medium">
                                    {{ \Carbon\CarbonImmutable::parse($val->fecha)->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right font-mono text-sm font-semibold text-gray-900">
                                    {{ $inversion->moneda === 'COP' ? '$' : 'US$' }} {{ number_format((float)$val->valor, 2, ',', '.') }}
                                </td>
                                @if($inversion->tieneUnidades())
                                    <td class="px-4 py-3 whitespace-nowrap text-right font-mono text-xs font-bold text-gray-900">
                                        {{ $val->precio_unitario ? ($inversion->moneda === 'COP' ? '$' : 'US$') . ' ' . number_format((float)$val->precio_unitario, 4, ',', '.') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right font-mono text-xs font-bold">
                                        @if($val->precio_unitario && $compraRef > 0)
                                            <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-xs {{ $varPct >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                                {{ $varPct >= 0 ? '▲ +' : '▼ ' }}{{ number_format($varPct, 2) }}%
                                            </span>
                                        @else
                                            <span class="text-gray-400">Base</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-xs text-gray-600">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-700">
                                        {{ str_replace('_', ' ', $val->origen) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center text-xs">
                                    @if(! in_array($val->origen, ['APORTE_INICIAL', 'LIQUIDACION']))
                                        <form method="POST" action="{{ route('inversiones.valoraciones.destroy', [$inversion->id, $val->id]) }}" onsubmit="return confirm('¿Está seguro de eliminar esta valoración?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:text-rose-800 font-medium">
                                                Eliminar
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-gray-400 text-xs italic">Fija</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 4 + ($inversion->tieneUnidades() ? 2 : 0) }}" class="px-4 py-6 text-center text-gray-500 text-sm">
                                    No hay valoraciones de mercado registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>

    {{-- SECCIÓN DE MOVIMIENTOS (RF-038) --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Movimientos de Flujo de Caja (RF-038)</h2>
                <p class="text-xs text-gray-600">Aportes, retiros, dividendos cobrados y liquidaciones.</p>
            </div>
        </div>

        @if(! $inversion->estaLiquidada())
            <x-ui.card>
                <details>
                    <summary class="font-bold text-sm text-indigo-700 cursor-pointer flex items-center gap-2 py-1">
                        <x-icon name="ui-mas" :size="18" />
                        Registrar nuevo flujo (Aporte de capital, Retiro, Dividendo/Rendimiento)
                    </summary>
                    <form method="POST" action="{{ route('inversiones.movimientos.store', $inversion->id) }}" class="mt-4 space-y-4 border-t pt-4" id="form-movimiento">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <x-ui.field name="tipo" label="Tipo de movimiento*">
                                <select name="tipo" id="mov_tipo" required class="w-full rounded-md border-gray-300 shadow-sm" onchange="toggleCamposMovimiento(this.value)">
                                    <option value="APORTE">Aporte de capital</option>
                                    <option value="RETIRO_PARCIAL">Retiro parcial</option>
                                    <option value="RENDIMIENTO">Rendimiento / Dividendo cobrado</option>
                                    <option value="COMISION">Comisión o costo de administración</option>
                                    <option value="RETENCION">Retención en la fuente sobre rendimientos</option>
                                    <option value="LIQUIDACION_TOTAL">Liquidación total (cierra la inversión)</option>
                                </select>
                            </x-ui.field>

                            <x-ui.field name="fecha" label="Fecha del movimiento*">
                                <input type="date" name="fecha" id="mov_fecha" required max="{{ date('Y-m-d') }}" min="{{ $inversion->fecha_apertura->toDateString() }}" value="{{ date('Y-m-d') }}" class="w-full rounded-md border-gray-300 shadow-sm" />
                            </x-ui.field>

                            <x-ui.field name="monto" label="Monto en {{ $inversion->moneda }}*">
                                <input type="number" name="monto" id="mov_monto" step="0.01" min="0" required placeholder="0.00" class="w-full rounded-md border-gray-300 shadow-sm font-mono" />
                            </x-ui.field>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            @if($inversion->tieneUnidades())
                                <x-ui.field name="unidades" label="Unidades asociadas (Opcional)">
                                    <input type="number" name="unidades" id="mov_unidades" step="0.00000001" min="0" placeholder="0.00000000" class="w-full rounded-md border-gray-300 shadow-sm font-mono" />
                                </x-ui.field>
                            @endif

                            <div id="col_retencion" class="hidden">
                                <x-ui.field name="retencion" label="Retención informada en {{ $inversion->moneda }}">
                                    <input type="number" name="retencion" id="mov_retencion" step="0.01" min="0" value="0.00" class="w-full rounded-md border-gray-300 shadow-sm font-mono" />
                                </x-ui.field>
                            </div>

                            <div class="{{ $inversion->tieneUnidades() ? 'sm:col-span-2' : 'sm:col-span-3' }}">
                                <x-ui.field name="nota" label="Nota explicativa (Opcional)">
                                    <input type="text" name="nota" maxlength="255" placeholder="Observaciones del movimiento" class="w-full rounded-md border-gray-300 shadow-sm" />
                                </x-ui.field>
                            </div>
                        </div>

                        <div id="aviso_rendimiento" class="hidden p-3 rounded bg-blue-50 border border-blue-200 text-xs text-blue-800">
                            <strong>RN-038.4:</strong> Al registrar este rendimiento, el sistema creará automáticamente un registro de ingreso con clasificación "Rentas de capital", con la retención informada, asegurando su trazabilidad para la declaración de renta sin duplicar el flujo.
                        </div>

                        <div class="flex justify-end pt-2">
                            <x-ui.button type="submit" variant="primary">
                                Guardar Movimiento
                            </x-ui.button>
                        </div>
                    </form>
                </details>
            </x-ui.card>
        @endif

        {{-- TABLA DE MOVIMIENTOS --}}
        <x-ui.card class="p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 border-b text-xs uppercase font-semibold text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3 text-right">Monto ({{ $inversion->moneda }})</th>
                            @if($inversion->moneda === 'USD')
                                <th class="px-4 py-3 text-right">Monto (COP)</th>
                            @endif
                            @if($inversion->tieneUnidades())
                                <th class="px-4 py-3 text-right">Unidades</th>
                            @endif
                            <th class="px-4 py-3">Nota / Detalle</th>
                            <th class="px-4 py-3 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($movimientos as $mov)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-700">
                                    {{ \Carbon\CarbonImmutable::parse($mov->fecha)->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold border bg-gray-100 text-gray-800">
                                        {{ str_replace('_', ' ', $mov->tipo) }}
                                    </span>
                                    @if($mov->es_inicial)
                                        <span class="text-[10px] text-gray-500 ml-1 font-bold">(Inicial)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right font-mono text-sm font-semibold text-gray-900">
                                    {{ $inversion->moneda === 'COP' ? '$' : 'US$' }} {{ number_format((float)$mov->monto, 2, ',', '.') }}
                                </td>
                                @if($inversion->moneda === 'USD')
                                    <td class="px-4 py-3 whitespace-nowrap text-right font-mono text-xs text-gray-600">
                                        ${{ number_format((float)$mov->monto_cop, 0, ',', '.') }}
                                    </td>
                                @endif
                                @if($inversion->tieneUnidades())
                                    <td class="px-4 py-3 whitespace-nowrap text-right font-mono text-xs text-gray-700">
                                        {{ $mov->unidades ? number_format((float)$mov->unidades, 4, ',', '.') : '—' }}
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-xs text-gray-600">
                                    {{ $mov->nota ?? '—' }}
                                    @if($mov->ingreso_asociado_id)
                                        <span class="inline-block mt-0.5 text-[10px] text-emerald-700 font-semibold bg-emerald-50 px-1 rounded">Rentas de capital integrada</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center text-xs">
                                    @if(! $mov->es_inicial)
                                        <form method="POST" action="{{ route('inversiones.movimientos.destroy', [$inversion->id, $mov->id]) }}" onsubmit="return confirm('¿Está seguro de eliminar este movimiento?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:text-rose-800 font-medium">
                                                Eliminar
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-gray-400 text-xs italic">Fijo</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 5 + ($inversion->moneda === 'USD' ? 1 : 0) + ($inversion->tieneUnidades() ? 1 : 0) }}" class="px-4 py-6 text-center text-gray-500 text-sm">
                                    No hay movimientos registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>

    {{-- AVISO LEGAL PEDAGÓGICO (RNG-08) --}}
    <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-600">
        <p class="font-bold text-slate-700 mb-1">Aviso legal y normativo (RNG-08):</p>
        <p>{{ $indicadores['aviso_legal'] }}</p>
    </div>
</div>

<script>
    const precioCompraRef = {{ (float) ($inversion->precio_unitario_compra ?? 0) }};
    const unidadesRef = {{ (float) (\App\Services\InversionService::unidadesActuales($inversion) ?? 0) }};
    const monedaSimbolo = '{{ $inversion->moneda === "COP" ? "$" : "US$" }}';

    function calcularImpactoNuevoPrecio(nuevoPrecio) {
        const p = parseFloat(nuevoPrecio);
        if (isNaN(p) || p <= 0 || precioCompraRef <= 0) return;

        const diff = p - precioCompraRef;
        const pct = (diff / precioCompraRef) * 100;
        const gananciaTotal = diff * unidadesRef;
        const valorTotal = p * unidadesRef;

        const box = document.getElementById('box-feedback-precio');
        const txtVar = document.getElementById('txt-variacion-precio');
        const txtTotal = document.getElementById('txt-valor-total-estimado');

        if (pct >= 0) {
            box.className = 'p-2 rounded text-xs border transition-colors flex items-center justify-between bg-emerald-50 border-emerald-200 text-emerald-900';
            txtVar.innerHTML = `<span>▲ GANANCIA:</span> <span>+${pct.toFixed(2)}%</span> <span>(+${monedaSimbolo} ${gananciaTotal.toFixed(2)})</span>`;
        } else {
            box.className = 'p-2 rounded text-xs border transition-colors flex items-center justify-between bg-rose-50 border-rose-200 text-rose-900';
            txtVar.innerHTML = `<span>▼ PÉRDIDA:</span> <span>${pct.toFixed(2)}%</span> <span>(-${monedaSimbolo} ${Math.abs(gananciaTotal).toFixed(2)})</span>`;
        }

        if (txtTotal) {
            txtTotal.textContent = `Valor total: ${monedaSimbolo} ${valorTotal.toFixed(2)}`;
        }
    }

    function toggleCamposMovimiento(tipo) {
        const colRetencion = document.getElementById('col_retencion');
        const avisoRendimiento = document.getElementById('aviso_rendimiento');
        
        if (tipo === 'RENDIMIENTO') {
            if (colRetencion) colRetencion.classList.remove('hidden');
            if (avisoRendimiento) avisoRendimiento.classList.remove('hidden');
        } else {
            if (colRetencion) colRetencion.classList.add('hidden');
            if (avisoRendimiento) avisoRendimiento.classList.add('hidden');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        @if($inversion->tieneUnidades())
            // RENDERIZAR GRÁFICA DE RENTA VARIABLE CON CHART.JS
            const ctxVar = document.getElementById('graficaRentaVariable');
            if (ctxVar) {
                const labels = @json($labelsGrafica);
                const dataPrecios = @json($datosGraficaPrecios);
                const dataCompra = @json($datosGraficaCompra);
                const dataMeta = @json($datosGraficaMeta);

                const esGanancia = {{ $pctCambio >= 0 ? 'true' : 'false' }};
                const colorLinea = esGanancia ? '#10b981' : '#f43f5e';
                const colorFondo = esGanancia ? 'rgba(16, 185, 129, 0.1)' : 'rgba(244, 63, 94, 0.1)';

                new Chart(ctxVar, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Cotización Real Histórica',
                                data: dataPrecios,
                                borderColor: colorLinea,
                                backgroundColor: colorFondo,
                                fill: true,
                                tension: 0.3,
                                borderWidth: 3,
                                pointRadius: 5,
                                pointHoverRadius: 7,
                                pointBackgroundColor: colorLinea
                            },
                            {
                                label: 'Precio Compra Base (${{ number_format($precioCompra, 2) }})',
                                data: dataCompra,
                                borderColor: '#3b82f6',
                                borderDash: [6, 6],
                                borderWidth: 2,
                                fill: false,
                                pointRadius: 0
                            },
                            {
                                label: 'Meta Proyectada (+{{ number_format($metaPorc, 2) }}%)',
                                data: dataMeta,
                                borderColor: '#9333ea',
                                borderDash: [4, 4],
                                borderWidth: 2,
                                fill: false,
                                pointRadius: 0
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
                        scales: {
                            y: {
                                beginAtZero: false,
                                grid: { color: '#f3f4f6' },
                                ticks: {
                                    callback: function(val) { return monedaSimbolo + ' ' + val; }
                                }
                            },
                            x: {
                                grid: { display: false }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) label += ': ';
                                        if (context.parsed.y !== null) {
                                            label += monedaSimbolo + ' ' + context.parsed.y.toFixed(4);
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        @endif

        @if($inversion->tieneTasa())
            // RENDERIZAR GRÁFICA DE RENTA FIJA (CDT / BONOS)
            const ctxFijo = document.getElementById('graficaRentaFija');
            if (ctxFijo) {
                const plazoTotal = {{ $plazoDias }};
                const montoBase = {{ $montoInicial }};
                const tasa = {{ $tasaEa }};
                const diasHoy = {{ $diasTranscurridos }};

                const labelsFijo = ['Apertura (Día 0)'];
                const dataCausado = [montoBase];
                const pasos = 6;
                for (let i = 1; i <= pasos; i++) {
                    const diaPaso = Math.round((plazoTotal / pasos) * i);
                    labelsFijo.push('Día ' + diaPaso);
                    const factor = Math.pow(1 + (tasa / 100), diaPaso / 365);
                    dataCausado.push(Math.round(montoBase * factor));
                }

                new Chart(ctxFijo, {
                    type: 'line',
                    data: {
                        labels: labelsFijo,
                        datasets: [
                            {
                                label: 'Causación de Capital + Intereses',
                                data: dataCausado,
                                borderColor: '#4f46e5',
                                backgroundColor: 'rgba(79, 70, 229, 0.08)',
                                fill: true,
                                tension: 0.25,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointBackgroundColor: '#4f46e5'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                grid: { color: '#f3f4f6' },
                                ticks: {
                                    callback: function(val) { return '$ ' + val.toLocaleString('es-CO'); }
                                }
                            },
                            x: { grid: { display: false } }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Saldo Acumulado: $ ' + context.parsed.y.toLocaleString('es-CO');
                                    }
                                }
                            }
                        }
                    }
                });
            }
        @endif
    });
</script>
@endsection
