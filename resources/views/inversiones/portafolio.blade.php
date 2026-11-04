@extends('layouts.app')
@section('title', 'Portafolio Consolidado de Inversiones')
@section('content')
<div class="space-y-6">
    <header class="income-page-header">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('inversiones.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                    <x-icon name="nav-atras" :size="16" />
                    Volver a Inversiones
                </a>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Consolidado del Portafolio</h1>
            <p class="text-sm text-gray-600">RF-041: Visión integral de activos, distribución patrimonial, ROI consolidado y TIR (XIRR) en pesos colombianos (COP).</p>
        </div>
        <div class="ui-inline">
            <x-ui.button :href="route('inversiones.simulaciones.index')" variant="secondary">
                <x-icon name="ui-buscar" :size="18" />
                Simulador de inversión
            </x-ui.button>
            <x-ui.button :href="route('inversiones.index')" variant="primary">
                <x-icon name="ui-mas" :size="18" />
                Nueva Inversión
            </x-ui.button>
        </div>
    </header>

    {{-- FILTRO POR FECHA DE CORTE --}}
    <x-ui.card class="bg-gray-50/50">
        <form method="GET" action="{{ route('inversiones.portafolio') }}" class="flex flex-col sm:flex-row items-center gap-4">
            <div class="flex items-center gap-2 text-sm text-gray-700">
                <x-icon name="nav-calendario" :size="20" class="text-indigo-600" />
                <span class="font-bold">Fecha de corte del portafolio:</span>
            </div>
            <div class="flex items-center gap-2">
                <input type="date" name="fecha_corte" value="{{ $fechaCorte }}" max="{{ date('Y-m-d') }}" class="rounded-md border-gray-300 shadow-sm text-sm" />
                <x-ui.button type="submit" variant="secondary" class="text-xs py-2">
                    Actualizar corte
                </x-ui.button>
            </div>
            @if($portafolio['trm'])
                <div class="text-xs text-slate-500 sm:ml-auto">
                    TRM aplicada para activos en USD: <strong>${{ number_format((float)$portafolio['trm']['valor'], 2, ',', '.') }} COP</strong>
                </div>
            @endif
        </form>
    </x-ui.card>

    {{-- TARJETAS KPI DEL PORTAFOLIO CONSOLIDADO (RF-041) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ui.card class="border-l-4 border-l-indigo-600">
            <span class="text-xs uppercase font-bold text-gray-500 tracking-wider">Valor Total del Portafolio</span>
            <div class="text-2xl font-black text-gray-900 mt-1">
                ${{ number_format((float)$portafolio['valor_total'], 0, ',', '.') }} <span class="text-sm font-semibold text-gray-500">COP</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">
                {{ $portafolio['cantidad_inversiones'] }} {{ $portafolio['cantidad_inversiones'] === 1 ? 'inversión activa' : 'inversiones activas' }} a la fecha
            </div>
        </x-ui.card>

        <x-ui.card class="border-l-4 border-l-blue-600">
            <span class="text-xs uppercase font-bold text-gray-500 tracking-wider">Capital Aportado Neto</span>
            <div class="text-2xl font-black text-gray-900 mt-1">
                ${{ number_format((float)$portafolio['capital_aportado_neto'], 0, ',', '.') }} <span class="text-sm font-semibold text-gray-500">COP</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">
                Total aportes menos retiros acumulados
            </div>
        </x-ui.card>

        <x-ui.card class="border-l-4 {{ (float)$portafolio['ganancia'] >= 0 ? 'border-l-emerald-600' : 'border-l-rose-600' }}">
            <span class="text-xs uppercase font-bold text-gray-500 tracking-wider">Ganancia Consolidada</span>
            <div class="text-2xl font-black mt-1 {{ (float)$portafolio['ganancia'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ (float)$portafolio['ganancia'] >= 0 ? '+' : '' }}${{ number_format((float)$portafolio['ganancia'], 0, ',', '.') }} <span class="text-sm font-semibold">COP</span>
            </div>
            <div class="text-xs text-gray-500 mt-1">
                ROI total: <span class="font-bold {{ (float)($portafolio['roi'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $portafolio['roi'] !== null ? number_format((float)$portafolio['roi'], 2, ',', '.') . '%' : 'N/A' }}</span>
            </div>
        </x-ui.card>

        <x-ui.card class="border-l-4 border-l-purple-600">
            <span class="text-xs uppercase font-bold text-gray-500 tracking-wider">TIR Consolidada (XIRR)</span>
            <div class="text-2xl font-black text-purple-900 mt-1">
                {{ $portafolio['xirr'] }}
            </div>
            <div class="text-xs text-gray-500 mt-1">
                Tasa interna de retorno ponderada del portafolio
            </div>
        </x-ui.card>
    </div>

    {{-- DISTRIBUCIÓN DEL PORTAFOLIO (RN-041.2 / CA-041.2) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- POR TIPO --}}
        <x-ui.card>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-3 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                Distribución por Tipo de Activo
            </h3>
            <div class="space-y-3">
                @forelse($portafolio['distribucion']['por_tipo'] as $item)
                    <div>
                        <div class="flex justify-between text-xs font-medium text-gray-700 mb-1">
                            <span class="truncate pr-2">{{ str_replace('_', ' ', $item['clave']) }}</span>
                            <span class="font-bold font-mono">{{ $item['porcentaje'] }}%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ $item['porcentaje'] }}%"></div>
                        </div>
                        <div class="text-[11px] text-gray-500 text-right mt-0.5">
                            ${{ number_format((float)$item['valor'], 0, ',', '.') }} COP
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-500 italic">Sin datos a la fecha.</p>
                @endforelse
            </div>
        </x-ui.card>

        {{-- POR RIESGO --}}
        <x-ui.card>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-3 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
                Distribución por Nivel de Riesgo
            </h3>
            <div class="space-y-3">
                @forelse($portafolio['distribucion']['por_riesgo'] as $item)
                    @php
                        $colorClass = match(strtoupper($item['clave'])) {
                            'BAJO' => 'bg-emerald-500',
                            'MEDIO' => 'bg-amber-500',
                            'ALTO' => 'bg-rose-500',
                            default => 'bg-gray-400'
                        };
                    @endphp
                    <div>
                        <div class="flex justify-between text-xs font-medium text-gray-700 mb-1">
                            <span>Riesgo {{ $item['clave'] === 'SIN_DECLARAR' ? 'Sin declarar' : ucfirst(strtolower($item['clave'])) }}</span>
                            <span class="font-bold font-mono">{{ $item['porcentaje'] }}%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="{{ $colorClass }} h-2 rounded-full" style="width: {{ $item['porcentaje'] }}%"></div>
                        </div>
                        <div class="text-[11px] text-gray-500 text-right mt-0.5">
                            ${{ number_format((float)$item['valor'], 0, ',', '.') }} COP
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-500 italic">Sin datos a la fecha.</p>
                @endforelse
            </div>
        </x-ui.card>

        {{-- POR MONEDA --}}
        <x-ui.card>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-3 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                Distribución por Moneda
            </h3>
            <div class="space-y-3">
                @forelse($portafolio['distribucion']['por_moneda'] as $item)
                    @php
                        $colorClass = $item['clave'] === 'COP' ? 'bg-blue-600' : 'bg-emerald-600';
                    @endphp
                    <div>
                        <div class="flex justify-between text-xs font-medium text-gray-700 mb-1">
                            <span>Moneda {{ $item['clave'] }}</span>
                            <span class="font-bold font-mono">{{ $item['porcentaje'] }}%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="{{ $colorClass }} h-2 rounded-full" style="width: {{ $item['porcentaje'] }}%"></div>
                        </div>
                        <div class="text-[11px] text-gray-500 text-right mt-0.5">
                            ${{ number_format((float)$item['valor'], 0, ',', '.') }} COP
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-500 italic">Sin datos a la fecha.</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>

    {{-- TABLA DE DETALLE DE INVERSIONES (RF-041 / CA-041.1) --}}
    <x-ui.card class="p-0 overflow-hidden">
        <div class="p-4 border-b bg-gray-50 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-gray-900">Detalle de Inversiones en Portafolio</h3>
                <p class="text-xs text-gray-500">Participación porcentual y rendimiento individual consolidado.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-100/70 border-b text-xs uppercase font-semibold text-gray-600">
                    <tr>
                        <th class="px-4 py-3">Inversión</th>
                        <th class="px-4 py-3">Tipo / Riesgo</th>
                        <th class="px-4 py-3 text-right">Valor Original</th>
                        <th class="px-4 py-3 text-right">Valor Actual (COP)</th>
                        <th class="px-4 py-3 text-right">% Portafolio</th>
                        <th class="px-4 py-3 text-right">ROI</th>
                        <th class="px-4 py-3 text-right">XIRR</th>
                        <th class="px-4 py-3 text-center">Última Valoración</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($portafolio['detalle'] as $inv)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('inversiones.show', $inv['id']) }}" class="font-bold text-indigo-600 hover:text-indigo-900 hover:underline">
                                    {{ $inv['nombre'] }}
                                </a>
                                @if($inv['valoracion_desactualizada'])
                                    <span class="block text-[10px] text-amber-700 font-bold">⚠️ Desactualizada (>35d)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                                <span class="font-medium text-gray-800">{{ str_replace('_', ' ', $inv['tipo']) }}</span>
                                @if($inv['nivel_riesgo'])
                                    <span class="text-gray-400">·</span> Riesgo {{ ucfirst(strtolower($inv['nivel_riesgo'])) }}
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-mono text-xs text-gray-700">
                                {{ $inv['moneda'] === 'COP' ? '$' : 'US$' }} {{ number_format((float)$inv['valor_actual'], 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-mono text-sm font-bold text-gray-900">
                                ${{ number_format((float)$inv['valor_actual_cop'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-mono text-xs font-bold text-indigo-700">
                                {{ $inv['porcentaje_portafolio'] }}%
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-mono text-xs font-semibold {{ (float)($inv['roi'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $inv['roi'] !== null ? number_format((float)$inv['roi'], 2, ',', '.') . '%' : 'N/A' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-mono text-xs text-purple-900 font-medium">
                                {{ $inv['xirr'] }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center text-xs text-gray-600">
                                {{ $inv['fecha_ultima_valoracion'] ? \Carbon\CarbonImmutable::parse($inv['fecha_ultima_valoracion'])->format('d/m/Y') : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500 text-sm">
                                No se encontraron inversiones abiertas a la fecha de corte seleccionada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($portafolio['detalle']) > 0)
                    <tfoot class="bg-gray-50 border-t font-bold text-xs uppercase text-gray-700">
                        <tr>
                            <td colspan="3" class="px-4 py-3">Total Consolidado</td>
                            <td class="px-4 py-3 text-right font-mono text-sm text-gray-900">
                                ${{ number_format((float)$portafolio['valor_total'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-indigo-700">
                                100,00%
                            </td>
                            <td class="px-4 py-3 text-right font-mono {{ (float)($portafolio['roi'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $portafolio['roi'] !== null ? number_format((float)$portafolio['roi'], 2, ',', '.') . '%' : 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-purple-900">
                                {{ $portafolio['xirr'] }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-ui.card>

    {{-- AVISO LEGAL PEDAGÓGICO (RNG-08) --}}
    <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-600">
        <p class="font-bold text-slate-700 mb-1">Aviso legal y normativo (RNG-08):</p>
        <p>{{ $portafolio['aviso_legal'] }}</p>
    </div>
</div>
@endsection

