@extends('layouts.app')
@section('title', 'Liquidación de Seguridad Social · Independiente')
@section('content')
<div class="space-y-6">
    <header class="income-page-header">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Liquidación de Aportes (Independiente o Contratista)</h1>
            <p class="text-sm text-gray-600">RF-033: Cálculo del IBC y aportes mensuales a salud, pensión, FSP y ARL.</p>
        </div>
        <div class="ui-inline">
            <x-ui.button :href="route('seguridad-social.index')" variant="secondary">
                <x-icon name="ui-volver" :size="18" />
                Volver a Seguridad Social
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
                <span>No fue posible procesar la liquidación:</span>
            </div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- DETALLE DE RESULTADO DE CÁLCULO O SIMULACIÓN (SI SE ACABA DE CALCULAR) --}}
    @php
        $res = session('calculo_reciente') ?? session('simulacion');
    @endphp

    @if($res)
        <x-ui.card title="Resultado de Liquidación · Periodo {{ $res['periodo'] ?? '' }}">
            <div class="space-y-4">
                @if(!empty($res['avisos']))
                    @foreach($res['avisos'] as $aviso)
                        <div class="p-3 bg-amber-50 border border-amber-200 text-amber-900 text-xs sm:text-sm rounded-lg flex items-center gap-2">
                            <x-icon name="ui-advertencia" :size="18" class="text-amber-600 shrink-0" />
                            <span>{{ $aviso }}</span>
                        </div>
                    @endforeach
                @endif

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Ingreso Base</span>
                        <p class="text-lg font-bold text-gray-900">$ {{ number_format((float) ($res['ingreso_base'] ?? 0), 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">IBC (40% acotado)</span>
                        <p class="text-lg font-bold text-indigo-700">$ {{ number_format((float) ($res['ibc'] ?? 0), 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Origen del cálculo</span>
                        <p class="text-sm font-semibold text-gray-700">{{ ($res['origen_ingreso'] ?? '') === 'MANUAL' ? 'Simulación manual' : 'Ingresos registrados' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Total a Pagar</span>
                        <p class="text-2xl font-black text-emerald-600">$ {{ number_format((float) ($res['total'] ?? 0), 0, ',', '.') }}</p>
                    </div>
                </div>

                {{-- DESGLOSE DE CONCEPTOS (CA-033.4 / RN-033.4 / RN-033.5) --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border border-gray-200 rounded-lg">
                        <thead class="bg-gray-100 text-gray-700 text-xs uppercase font-semibold">
                            <tr>
                                <th class="p-3">Concepto</th>
                                <th class="p-3">Tarifa aplicada</th>
                                <th class="p-3 text-right">Valor redondeado (múltiplo $100)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr>
                                <td class="p-3 font-semibold text-gray-900">Salud</td>
                                <td class="p-3 text-gray-600">{{ $res['parametros_usados']['tarifa_salud'] ?? '12.50' }} %</td>
                                <td class="p-3 text-right font-bold text-gray-900">$ {{ number_format((float) ($res['salud'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="p-3 font-semibold text-gray-900">Pensión</td>
                                <td class="p-3 text-gray-600">
                                    @if(!empty($res['parametros_usados']['pensionado']))
                                        Exento (Pensionado)
                                    @else
                                        {{ $res['parametros_usados']['tarifa_pension'] ?? '16.00' }} %
                                    @endif
                                </td>
                                <td class="p-3 text-right font-bold text-gray-900">$ {{ number_format((float) ($res['pension'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="p-3 font-semibold text-gray-900">Fondo de Solidaridad Pensional (FSP)</td>
                                <td class="p-3 text-gray-600">
                                    @if((float)($res['parametros_usados']['tarifa_fsp'] ?? 0) > 0)
                                        {{ $res['parametros_usados']['tarifa_fsp'] }} % (≥ 4 SMMLV)
                                    @else
                                        0.00 % (&lt; 4 SMMLV)
                                    @endif
                                </td>
                                <td class="p-3 text-right font-bold text-gray-900">$ {{ number_format((float) ($res['fsp'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="p-3 font-semibold text-gray-900">Riesgos Laborales (ARL)</td>
                                <td class="p-3 text-gray-600">
                                    @if(isset($res['parametros_usados']['clase_riesgo_arl']) && $res['parametros_usados']['clase_riesgo_arl'] !== null)
                                        Clase {{ $res['parametros_usados']['clase_riesgo_arl'] }} ({{ $res['parametros_usados']['tarifa_arl'] }} %)
                                    @else
                                        No obligatoria / 0.00 %
                                    @endif
                                </td>
                                <td class="p-3 text-right font-bold text-gray-900">$ {{ number_format((float) ($res['arl'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                            <tr class="bg-emerald-50 text-emerald-950 font-bold">
                                <td class="p-3" colspan="2">TOTAL APORTES MENSUALES (Decreto 1990 de 2016)</td>
                                <td class="p-3 text-right text-lg text-emerald-700">$ {{ number_format((float) ($res['total'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="text-xs text-gray-500 bg-gray-50 p-3 rounded border">
                    <p><strong>Parámetros de la vigencia:</strong> SMMLV: $ {{ number_format((float)($res['parametros_usados']['valor_smmlv'] ?? 1423500), 0, ',', '.') }} · Tope mínimo: 1 SMMLV · Tope máximo: 25 SMMLV ($ {{ number_format((float)($res['parametros_usados']['ibc_maximo'] ?? 35587500), 0, ',', '.') }}).</p>
                    <p class="mt-1"><em>{{ $res['aviso_legal'] ?? App\Services\SeguridadSocialService::AVISO_LEGAL }}</em></p>
                </div>
            </div>
        </x-ui.card>
    @endif

    {{-- FORMULARIOS DE CÁLCULO Y SIMULACIÓN --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- CALCULAR Y GUARDAR CON INGRESOS REGISTRADOS --}}
        <x-ui.card title="Calcular con Ingresos Registrados">
            <form method="POST" action="{{ route('seguridad-social.liquidaciones.store') }}" class="space-y-4">
                @csrf
                <p class="text-xs text-gray-600">
                    Suma automáticamente los ingresos confirmados con clasificación "Rentas de trabajo" (no asalariados) del periodo seleccionado.
                </p>
                <x-ui.field name="periodo" label="Periodo a liquidar (AAAA-MM)">
                    <input type="month" name="periodo" id="periodo" value="{{ date('Y-m') }}" required class="w-full rounded-md border-gray-300 shadow-sm" />
                </x-ui.field>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="es_voluntario" id="es_voluntario_store" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    <label for="es_voluntario_store" class="text-xs text-gray-700">Cotización voluntaria (permite liquidar si no tienes ocupación independiente en tu perfil)</label>
                </div>

                <x-ui.button type="submit" class="w-full justify-center">
                    <x-icon name="cat-seguridad-social" :size="18" />
                    Calcular y guardar como liquidación estimada
                </x-ui.button>
            </form>
        </x-ui.card>

        {{-- SIMULACIÓN CON VALOR MANUAL --}}
        <x-ui.card title="Simulación Manual (Sin Guardar)">
            <form method="POST" action="{{ route('seguridad-social.liquidaciones.simular') }}" class="space-y-4">
                @csrf
                <p class="text-xs text-gray-600">
                    Ingresa un valor bruto mensual hipotético para conocer tu IBC y aportes estimados. No reemplaza ni altera la liquidación guardada.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.field name="periodo" label="Periodo (AAAA-MM)">
                        <input type="month" name="periodo" id="sim_periodo" value="{{ date('Y-m') }}" required class="w-full rounded-md border-gray-300 shadow-sm" />
                    </x-ui.field>
                    <x-ui.field name="ingreso_base" label="Ingreso bruto a simular ($)">
                        <input type="number" step="1000" min="0" name="ingreso_base" id="ingreso_base" placeholder="Ej: 8000000" required class="w-full rounded-md border-gray-300 shadow-sm" />
                    </x-ui.field>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="es_voluntario" id="es_voluntario_sim" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    <label for="es_voluntario_sim" class="text-xs text-gray-700">Cotización voluntaria</label>
                </div>

                <x-ui.button type="submit" variant="secondary" class="w-full justify-center">
                    <x-icon name="ui-buscar" :size="18" />
                    Simular aportes
                </x-ui.button>
            </form>
        </x-ui.card>
    </div>

    {{-- HISTÓRICO DE LIQUIDACIONES GUARDADAS --}}
    <x-ui.card title="Histórico de Liquidaciones Estimadas Guardadas (RN-033.7)">
        @if($liquidaciones->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm border border-gray-200 rounded-lg">
                    <thead class="bg-gray-100 text-gray-700 uppercase text-xs font-semibold">
                        <tr>
                            <th class="p-3">Periodo</th>
                            <th class="p-3">Origen</th>
                            <th class="p-3">Ingreso Base</th>
                            <th class="p-3">IBC</th>
                            <th class="p-3">Salud</th>
                            <th class="p-3">Pensión</th>
                            <th class="p-3">FSP</th>
                            <th class="p-3">ARL</th>
                            <th class="p-3 text-right">Total Estimado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($liquidaciones as $liq)
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-bold text-gray-900">{{ $liq->periodo }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $liq->origen_ingreso === 'MANUAL' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                        {{ $liq->origen_ingreso }}
                                    </span>
                                </td>
                                <td class="p-3">$ {{ number_format((float) $liq->ingreso_base, 0, ',', '.') }}</td>
                                <td class="p-3 font-medium text-indigo-700">$ {{ number_format((float) $liq->ibc, 0, ',', '.') }}</td>
                                <td class="p-3">$ {{ number_format((float) $liq->salud, 0, ',', '.') }}</td>
                                <td class="p-3">$ {{ number_format((float) $liq->pension, 0, ',', '.') }}</td>
                                <td class="p-3">$ {{ number_format((float) $liq->fsp, 0, ',', '.') }}</td>
                                <td class="p-3">$ {{ number_format((float) $liq->arl, 0, ',', '.') }}</td>
                                <td class="p-3 text-right font-black text-emerald-600">$ {{ number_format((float) $liq->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pt-4">
                {{ $liquidaciones->links() }}
            </div>
        @else
            <x-ui.empty-state message="No tienes liquidaciones estimadas guardadas. Puedes calcular el periodo actual con el formulario superior." />
        @endif
    </x-ui.card>
</div>
@endsection

