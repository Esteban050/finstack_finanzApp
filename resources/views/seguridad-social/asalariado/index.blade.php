@extends('layouts.app')
@section('title', 'Cálculo de Aportes como Asalariado · RF-034')
@section('content')
<div class="space-y-6">
    <header class="income-page-header">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Aportes a Seguridad Social como Asalariado</h1>
            <p class="text-sm text-gray-600">RF-034: Cálculo de deducciones que realiza el empleador (salud 4%, pensión 4%, FSP) y salario neto estimado.</p>
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
                <span>Error en el cálculo:</span>
            </div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- RESULTADO DEL CÁLCULO (SI ESTÁ EN SESIÓN) --}}
    @php
        $calc = session('calculo_asalariado');
    @endphp

    @if($calc)
        <x-ui.card title="Resultado del Cálculo de Nómina · Salario de $ {{ number_format((float) ($calc['salario'] ?? 0), 0, ',', '.') }}">
            <div class="space-y-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Salario Bruto</span>
                        <p class="text-lg font-bold text-gray-900">$ {{ number_format((float) ($calc['salario'] ?? 0), 0, ',', '.') }}</p>
                        @if(!empty($calc['es_salario_integral']))
                            <span class="text-xs text-indigo-600 font-medium">Salario Integral</span>
                        @endif
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">IBC Nómina</span>
                        <p class="text-lg font-bold text-indigo-700">$ {{ number_format((float) ($calc['ibc'] ?? 0), 0, ',', '.') }}</p>
                        <span class="text-xs text-gray-500">{{ !empty($calc['es_salario_integral']) ? '70% del salario' : '100% del salario' }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Total Deducciones Aportes</span>
                        <p class="text-lg font-bold text-red-600">-$ {{ number_format((float) ($calc['total_aportes'] ?? 0), 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Salario Neto Estimado</span>
                        <p class="text-2xl font-black text-emerald-600">$ {{ number_format((float) ($calc['salario_neto_estimado'] ?? 0), 0, ',', '.') }}</p>
                    </div>
                </div>

                {{-- DESGLOSE DE APORTES --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border border-gray-200 rounded-lg">
                        <thead class="bg-gray-100 text-gray-700 text-xs uppercase font-semibold">
                            <tr>
                                <th class="p-3">Concepto Descontado</th>
                                <th class="p-3">Porcentaje empleado</th>
                                <th class="p-3 text-right">Valor deducido ($)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr>
                                <td class="p-3 font-semibold text-gray-900">Aporte Salud empleado (RN-034.3)</td>
                                <td class="p-3 text-gray-600">4,00 %</td>
                                <td class="p-3 text-right font-bold text-gray-900">$ {{ number_format((float) ($calc['salud'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="p-3 font-semibold text-gray-900">Aporte Pensión empleado (RN-034.3)</td>
                                <td class="p-3 text-gray-600">4,00 %</td>
                                <td class="p-3 text-right font-bold text-gray-900">$ {{ number_format((float) ($calc['pension'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="p-3 font-semibold text-gray-900">Fondo Solidaridad Pensional (FSP)</td>
                                <td class="p-3 text-gray-600">{{ $calc['parametros_usados']['tarifa_fsp'] ?? '0' }} % (desde 4 SMMLV)</td>
                                <td class="p-3 text-right font-bold text-gray-900">$ {{ number_format((float) ($calc['fsp'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                            <tr class="bg-gray-50 font-bold">
                                <td class="p-3" colspan="2">TOTAL APORTES DESCONTADOS (Ingreso no constitutivo de renta)</td>
                                <td class="p-3 text-right text-indigo-700">$ {{ number_format((float) ($calc['total_aportes'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="p-3 font-semibold text-gray-900">Retención en la fuente informada (opcional)</td>
                                <td class="p-3 text-gray-600">—</td>
                                <td class="p-3 text-right font-bold text-gray-900">$ {{ number_format((float) ($calc['retencion_fuente'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                            <tr class="bg-emerald-50 text-emerald-950 font-bold">
                                <td class="p-3" colspan="2">SALARIO NETO ESTIMADO EN BOLSILLO</td>
                                <td class="p-3 text-right text-lg text-emerald-700">$ {{ number_format((float) ($calc['salario_neto_estimado'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="ui-help text-xs text-gray-500">
                    * Nota: ARL corre 100% por cuenta del empleador y no se le descuenta al empleado (RN-034.3).
                </p>
            </div>
        </x-ui.card>
    @endif

    {{-- FORMULARIO DE CÁLCULO DE ASALARIADO --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <x-ui.card title="Calculadora de Deducciones de Nómina">
            <form method="POST" action="{{ route('seguridad-social.asalariado.calcular') }}" class="space-y-4">
                @csrf
                <x-ui.field name="salario" label="Salario mensual ($)">
                    <input type="number" step="1000" min="1" name="salario" id="salario" placeholder="Ej: 6000000" required class="w-full rounded-md border-gray-300 shadow-sm" />
                </x-ui.field>

                <x-ui.field name="retencion_fuente" label="Retención en la fuente informada ($) (Opcional)">
                    <input type="number" step="1000" min="0" name="retencion_fuente" id="retencion_fuente" placeholder="0" class="w-full rounded-md border-gray-300 shadow-sm" />
                </x-ui.field>

                <x-ui.field name="periodo" label="Periodo (AAAA-MM) (Opcional)">
                    <input type="month" name="periodo" id="periodo" value="{{ date('Y-m') }}" class="w-full rounded-md border-gray-300 shadow-sm" />
                </x-ui.field>

                <p class="text-xs text-gray-500">
                    * El cálculo toma automáticamente la condición de Salario Integral de tu perfil financiero.
                </p>

                <x-ui.button type="submit" class="w-full justify-center">
                    <x-icon name="act-neto" :size="18" />
                    Calcular deducciones y salario neto
                </x-ui.button>
            </form>
        </x-ui.card>

        {{-- APLICAR APORTES AL INGRESO REGISTRADO (RN-034.5) --}}
        <x-ui.card title="Aplicar Aportes a un Ingreso de Salario (RN-034.5)">
            <form method="POST" action="{{ route('seguridad-social.asalariado.aplicar') }}" class="space-y-4">
                @csrf
                <p class="text-xs text-gray-600">
                    Selecciona un ingreso de nómina registrado previamente para calcular y llenar automáticamente su campo "Aportes descontados" en el módulo de Ingresos.
                </p>

                @if($ingresosSalario->count() > 0)
                    <x-ui.field name="ingreso_id" label="Ingreso de salario confirmado">
                        <select name="ingreso_id" id="ingreso_id" required class="w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">-- Selecciona un ingreso --</option>
                            @foreach($ingresosSalario as $ing)
                                <option value="{{ $ing->id }}">
                                    {{ $ing->fecha_recepcion->format('Y-m-d') }} · {{ $ing->fuente?->nombre }} · $ {{ number_format((float) $ing->monto_bruto_cop, 0, ',', '.') }} (Aportes actuales: ${{ number_format((float)$ing->aportes_descontados_cop, 0, ',', '.') }})
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.button type="submit" variant="secondary" class="w-full justify-center">
                        <x-icon name="act-confirmar" :size="18" />
                        Aplicar aportes descontados al ingreso
                    </x-ui.button>
                @else
                    <div class="p-4 bg-gray-50 rounded-lg text-xs text-gray-600 border">
                        No tienes ingresos de nómina (tipo Salario) registrados. Registra tu salario en el <a href="{{ route('ingresos.create') }}" class="text-indigo-600 font-bold">módulo de Ingresos</a> para aplicar los aportes calculados.
                    </div>
                @endif
            </form>
        </x-ui.card>
    </div>
</div>
@endsection

