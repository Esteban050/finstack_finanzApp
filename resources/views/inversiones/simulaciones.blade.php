@extends('layouts.app')
@section('title', 'Simulador de Inversiones y Comparador de Alternativas')
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
            <h1 class="text-2xl font-bold text-gray-900">Simulador y Comparador de Inversiones</h1>
            <p class="text-sm text-gray-600">RF-042: Proyecta y compara hasta 4 alternativas de inversión con capitalización mensual, comisiones, retención en la fuente y ajuste por inflación. No afecta registros reales (RN-042.4).</p>
        </div>
        <div class="ui-inline">
            <x-ui.button :href="route('inversiones.portafolio')" variant="secondary">
                <x-icon name="act-balance" :size="18" />
                Ver portafolio consolidado
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
                <span>Ocurrió un error en la simulación:</span>
            </div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $comparacion = session('comparacion');
        $alternativasEnviadas = session('alternativas_enviadas') ?? [
            ['nombre' => 'CDT Tradicional', 'monto_inicial' => '10000000', 'tasa_ea' => '10.5', 'plazo_meses' => '12', 'aporte_periodico' => '0', 'periodicidad_aporte' => 'MENSUAL', 'retencion_porc' => '4.00', 'comision_anual_porc' => '0.00'],
            ['nombre' => 'Fondo Inversión (FIC)', 'monto_inicial' => '10000000', 'tasa_ea' => '12.0', 'plazo_meses' => '12', 'aporte_periodico' => '200000', 'periodicidad_aporte' => 'MENSUAL', 'retencion_porc' => '7.00', 'comision_anual_porc' => '1.50'],
        ];
    @endphp

    {{-- RESULTADOS DE LA COMPARACIÓN (SI SE EJECUTÓ) --}}
    @if($comparacion)
        <div class="space-y-4" id="seccion-resultados">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <span>Resultados de la Comparación (CA-042.1 / CA-042.2)</span>
                        <span class="text-xs bg-indigo-100 text-indigo-800 font-semibold px-2 py-0.5 rounded-full">Simulado</span>
                    </h2>
                    <p class="text-xs text-gray-600">
                        Capitalización con interés compuesto mensual. 
                        @if($comparacion['inflacion_vigencia'])
                            Ajuste de poder adquisitivo a pesos de hoy con inflación anual del {{ $comparacion['inflacion_vigencia'] }}%.
                        @endif
                    </p>
                </div>
            </div>

            {{-- TARJETAS RESUMEN DE CADA ALTERNATIVA --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-{{ min(4, count($comparacion['alternativas'])) }} gap-4">
                @foreach($comparacion['alternativas'] as $idx => $alt)
                    <x-ui.card class="relative flex flex-col justify-between {{ $alt['es_mejor'] ? 'ring-2 ring-emerald-500 bg-emerald-50/20' : 'bg-white' }}">
                        @if($alt['es_mejor'])
                            <div class="absolute -top-3 right-3 bg-emerald-600 text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full shadow flex items-center gap-1">
                                <span>★ MEJOR ALTERNATIVA</span>
                            </div>
                        @endif
                        <div>
                            <div class="text-sm font-bold text-gray-900 mb-1 flex items-center justify-between">
                                <span>{{ $alt['nombre'] }}</span>
                                <span class="text-xs font-semibold px-2 py-0.5 bg-gray-100 text-gray-700 rounded">{{ $alt['datos']['tasa_ea'] }}% EA</span>
                            </div>
                            <div class="text-xs text-gray-500 mb-3">
                                Plazo: <strong>{{ $alt['datos']['plazo_meses'] }} meses</strong> · Aporte inicial: ${{ number_format((float)$alt['datos']['monto_inicial'], 0, ',', '.') }}
                                @if((float)$alt['datos']['aporte_periodico'] > 0)
                                    · Aportes: ${{ number_format((float)$alt['datos']['aporte_periodico'], 0, ',', '.') }} {{ strtolower($alt['datos']['periodicidad_aporte']) }}
                                @endif
                            </div>

                            <div class="space-y-1.5 border-t pt-2 text-xs">
                                <div class="flex justify-between text-gray-600">
                                    <span>Total aportado:</span>
                                    <span class="font-mono font-medium">${{ number_format((float)$alt['total_aportado'], 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600">
                                    <span>Valor final bruto:</span>
                                    <span class="font-mono font-medium">${{ number_format((float)$alt['valor_final_bruto'], 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600">
                                    <span>Comisiones totales:</span>
                                    <span class="font-mono text-rose-600">-${{ number_format((float)$alt['total_comisiones'], 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600">
                                    <span>Retención estimada:</span>
                                    <span class="font-mono text-purple-700">-${{ number_format((float)$alt['impuestos'], 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="border-t pt-3 mt-3">
                            <div class="flex justify-between items-baseline">
                                <span class="text-xs uppercase font-bold text-gray-700">Valor Final Neto:</span>
                                <span class="text-lg font-black font-mono text-gray-900">${{ number_format((float)$alt['valor_final_neto'], 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-xs text-emerald-700 font-semibold mt-1">
                                <span>Ganancia neta:</span>
                                <span class="font-mono">+${{ number_format((float)$alt['ganancia_neta'], 0, ',', '.') }}</span>
                            </div>
                            @if($alt['valor_final_pesos_hoy'])
                                <div class="flex justify-between text-[11px] text-slate-500 mt-1 border-t border-dashed pt-1">
                                    <span>En pesos de hoy (IPC):</span>
                                    <span class="font-mono font-bold text-slate-800">${{ number_format((float)$alt['valor_final_pesos_hoy'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            {{-- GUARDAR ESTA SIMULACIÓN (RN-042.4) --}}
            <x-ui.card class="bg-indigo-50/50 border border-indigo-200">
                <form method="POST" action="{{ route('inversiones.simulaciones.store') }}" class="flex flex-col sm:flex-row items-center gap-3">
                    @csrf
                    <div class="flex items-center gap-2 text-sm text-indigo-950 font-bold shrink-0">
                        <x-icon name="ui-exito" :size="18" class="text-indigo-600" />
                        <span>¿Deseas guardar esta comparación para consultarla luego?</span>
                    </div>
                    <div class="w-full sm:w-80">
                        <input type="text" name="nombre" required maxlength="60" placeholder="Nombre para guardar la simulación..." class="w-full text-sm rounded-md border-gray-300 shadow-sm" />
                    </div>
                    @foreach($comparacion['alternativas'] as $idx => $alt)
                        <input type="hidden" name="alternativas[{{ $idx }}][nombre]" value="{{ $alt['nombre'] }}" />
                        <input type="hidden" name="alternativas[{{ $idx }}][monto_inicial]" value="{{ $alt['datos']['monto_inicial'] }}" />
                        <input type="hidden" name="alternativas[{{ $idx }}][aporte_periodico]" value="{{ $alt['datos']['aporte_periodico'] }}" />
                        <input type="hidden" name="alternativas[{{ $idx }}][periodicidad_aporte]" value="{{ $alt['datos']['periodicidad_aporte'] ?? 'MENSUAL' }}" />
                        <input type="hidden" name="alternativas[{{ $idx }}][tasa_ea]" value="{{ $alt['datos']['tasa_ea'] }}" />
                        <input type="hidden" name="alternativas[{{ $idx }}][plazo_meses]" value="{{ $alt['datos']['plazo_meses'] }}" />
                        <input type="hidden" name="alternativas[{{ $idx }}][retencion_porc]" value="{{ $alt['datos']['retencion_porc'] }}" />
                        <input type="hidden" name="alternativas[{{ $idx }}][comision_anual_porc]" value="{{ $alt['datos']['comision_anual_porc'] }}" />
                    @endforeach
                    <x-ui.button type="submit" variant="primary" class="text-xs py-2 shrink-0">
                        Guardar Simulación
                    </x-ui.button>
                </form>
            </x-ui.card>
        </div>
    @endif

    {{-- FORMULARIO DE SIMULADOR (HASTA 4 ALTERNATIVAS) --}}
    <x-ui.card>
        <div class="mb-4">
            <h2 class="text-base font-bold text-gray-900">Configurar Alternativas a Comparar</h2>
            <p class="text-xs text-gray-500">Ingresa los parámetros de 1 a 4 alternativas para calcular sus rendimientos mes a mes.</p>
        </div>

        <form method="POST" action="{{ route('inversiones.simulaciones.calcular') }}" id="form-comparar" class="space-y-6">
            @csrf
            <div class="space-y-4" id="contenedor-alternativas">
                @foreach($alternativasEnviadas as $i => $alt)
                    <div class="p-4 rounded-lg border border-gray-200 bg-gray-50/40 relative panel-alternativa" id="panel-alt-{{ $i }}">
                        <div class="flex items-center justify-between mb-3 border-b pb-2">
                            <span class="text-xs font-bold text-indigo-700 uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[11px] font-bold">{{ $i + 1 }}</span>
                                Alternativa {{ $i + 1 }}
                            </span>
                            @if($i > 0)
                                <button type="button" onclick="eliminarAlternativa({{ $i }})" class="text-xs text-rose-600 hover:text-rose-800 font-medium">
                                    Eliminar
                                </button>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <x-ui.field name="alternativas[{{ $i }}][nombre]" label="Nombre de la alternativa*">
                                <input type="text" name="alternativas[{{ $i }}][nombre]" required maxlength="40" value="{{ $alt['nombre'] ?? 'Alternativa ' . ($i+1) }}" class="w-full text-sm rounded-md border-gray-300 shadow-sm" />
                            </x-ui.field>

                            <x-ui.field name="alternativas[{{ $i }}][monto_inicial]" label="Monto inicial (COP)*">
                                <input type="number" name="alternativas[{{ $i }}][monto_inicial]" step="0.01" min="0" required value="{{ $alt['monto_inicial'] ?? '10000000' }}" class="w-full text-sm rounded-md border-gray-300 shadow-sm font-mono" />
                            </x-ui.field>

                            <x-ui.field name="alternativas[{{ $i }}][tasa_ea]" label="Tasa esperada (% EA)*">
                                <input type="number" name="alternativas[{{ $i }}][tasa_ea]" step="0.01" min="0" max="100" required value="{{ $alt['tasa_ea'] ?? '10.0' }}" class="w-full text-sm rounded-md border-gray-300 shadow-sm font-mono" />
                            </x-ui.field>

                            <x-ui.field name="alternativas[{{ $i }}][plazo_meses]" label="Plazo en meses (1-360)*">
                                <input type="number" name="alternativas[{{ $i }}][plazo_meses]" min="1" max="360" required value="{{ $alt['plazo_meses'] ?? '12' }}" class="w-full text-sm rounded-md border-gray-300 shadow-sm font-mono" />
                            </x-ui.field>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-3">
                            <x-ui.field name="alternativas[{{ $i }}][aporte_periodico]" label="Aporte periódico (Opcional)">
                                <input type="number" name="alternativas[{{ $i }}][aporte_periodico]" step="0.01" min="0" value="{{ $alt['aporte_periodico'] ?? '0' }}" class="w-full text-sm rounded-md border-gray-300 shadow-sm font-mono" />
                            </x-ui.field>

                            <x-ui.field name="alternativas[{{ $i }}][periodicidad_aporte]" label="Frecuencia del aporte">
                                <select name="alternativas[{{ $i }}][periodicidad_aporte]" class="w-full text-sm rounded-md border-gray-300 shadow-sm">
                                    <option value="MENSUAL" @selected(($alt['periodicidad_aporte'] ?? 'MENSUAL') === 'MENSUAL')>Mensual</option>
                                    <option value="TRIMESTRAL" @selected(($alt['periodicidad_aporte'] ?? '') === 'TRIMESTRAL')>Trimestral</option>
                                    <option value="SEMESTRAL" @selected(($alt['periodicidad_aporte'] ?? '') === 'SEMESTRAL')>Semestral</option>
                                    <option value="ANUAL" @selected(($alt['periodicidad_aporte'] ?? '') === 'ANUAL')>Anual</option>
                                </select>
                            </x-ui.field>

                            <x-ui.field name="alternativas[{{ $i }}][retencion_porc]" label="Retención en la fuente (%)">
                                <input type="number" name="alternativas[{{ $i }}][retencion_porc]" step="0.01" min="0" max="100" value="{{ $alt['retencion_porc'] ?? '4.00' }}" placeholder="4.00" class="w-full text-sm rounded-md border-gray-300 shadow-sm font-mono" />
                            </x-ui.field>

                            <x-ui.field name="alternativas[{{ $i }}][comision_anual_porc]" label="Comisión anual (% EA)">
                                <input type="number" name="alternativas[{{ $i }}][comision_anual_porc]" step="0.01" min="0" max="100" value="{{ $alt['comision_anual_porc'] ?? '0.00' }}" placeholder="0.00" class="w-full text-sm rounded-md border-gray-300 shadow-sm font-mono" />
                            </x-ui.field>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2 border-t">
                <button type="button" id="btn-agregar-alt" onclick="agregarAlternativa()" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                    <x-icon name="ui-mas" :size="16" />
                    Agregar otra alternativa (Máx. 4)
                </button>

                <x-ui.button type="submit" variant="primary">
                    <x-icon name="ui-buscar" :size="18" />
                    Comparar Alternativas
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>

    {{-- SIMULACIONES GUARDADAS (RN-042.4) --}}
    <x-ui.card class="p-0 overflow-hidden">
        <div class="p-4 border-b bg-gray-50 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-gray-900">Simulaciones Guardadas (RN-042.4)</h3>
                <p class="text-xs text-gray-500">Historial de comparaciones guardadas (máximo {{ $maximo }} por usuario).</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded bg-slate-200 text-slate-700">
                {{ $simulaciones->total() }} / {{ $maximo }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-100/70 border-b text-xs uppercase font-semibold text-gray-600">
                    <tr>
                        <th class="px-4 py-3">Nombre de la Simulación</th>
                        <th class="px-4 py-3">Alternativas</th>
                        <th class="px-4 py-3">Fecha de Guardado</th>
                        <th class="px-4 py-3 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($simulaciones as $sim)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 font-semibold text-gray-900">
                                {{ $sim->nombre }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                @if(is_array($sim->alternativas))
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($sim->alternativas as $a)
                                            <span class="px-2 py-0.5 rounded bg-gray-100 border text-gray-700 text-[11px]">
                                                {{ $a['nombre'] ?? 'Alternativa' }} ({{ $a['tasa_ea'] ?? 0 }}% EA)
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                {{ \Carbon\CarbonImmutable::parse($sim->creado_en)->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap text-xs">
                                <form method="POST" action="{{ route('inversiones.simulaciones.destroy', $sim->id) }}" onsubmit="return confirm('¿Eliminar esta simulación guardada?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-800 font-medium">
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500 text-sm">
                                No tienes simulaciones guardadas actualmente.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($simulaciones->hasPages())
            <div class="p-4 border-t">
                {{ $simulaciones->links() }}
            </div>
        @endif
    </x-ui.card>

    {{-- AVISO LEGAL PEDAGÓGICO (RNG-08) --}}
    <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-600">
        <p class="font-bold text-slate-700 mb-1">Aviso legal y normativo (RNG-08):</p>
        <p>Los valores calculados son estimaciones pedagógicas basadas en la normativa colombiana vigente y en tasas hipotéticas. Los rendimientos pasados o simulados no garantizan resultados futuros. No constituyen asesoría financiera vinculante.</p>
    </div>
</div>

<script>
    let contadorAlternativas = {{ count($alternativasEnviadas) }};

    function agregarAlternativa() {
        if (contadorAlternativas >= 4) {
            alert('Puede simular un máximo de 4 alternativas simultáneas (RN-042.1).');
            return;
        }

        const contenedor = document.getElementById('contenedor-alternativas');
        const i = contadorAlternativas;
        const div = document.createElement('div');
        div.className = 'p-4 rounded-lg border border-gray-200 bg-gray-50/40 relative panel-alternativa';
        div.id = 'panel-alt-' + i;
        div.innerHTML = `
            <div class="flex items-center justify-between mb-3 border-b pb-2">
                <span class="text-xs font-bold text-indigo-700 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[11px] font-bold">${i + 1}</span>
                    Alternativa ${i + 1}
                </span>
                <button type="button" onclick="eliminarAlternativa(${i})" class="text-xs text-rose-600 hover:text-rose-800 font-medium">
                    Eliminar
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Nombre de la alternativa*</label>
                    <input type="text" name="alternativas[${i}][nombre]" required maxlength="40" value="Alternativa ${i + 1}" class="w-full text-sm rounded-md border-gray-300 shadow-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Monto inicial (COP)*</label>
                    <input type="number" name="alternativas[${i}][monto_inicial]" step="0.01" min="0" required value="10000000" class="w-full text-sm rounded-md border-gray-300 shadow-sm font-mono" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Tasa esperada (% EA)*</label>
                    <input type="number" name="alternativas[${i}][tasa_ea]" step="0.01" min="0" max="100" required value="11.0" class="w-full text-sm rounded-md border-gray-300 shadow-sm font-mono" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Plazo en meses (1-360)*</label>
                    <input type="number" name="alternativas[${i}][plazo_meses]" min="1" max="360" required value="12" class="w-full text-sm rounded-md border-gray-300 shadow-sm font-mono" />
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Aporte periódico (Opcional)</label>
                    <input type="number" name="alternativas[${i}][aporte_periodico]" step="0.01" min="0" value="0" class="w-full text-sm rounded-md border-gray-300 shadow-sm font-mono" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Frecuencia del aporte</label>
                    <select name="alternativas[${i}][periodicidad_aporte]" class="w-full text-sm rounded-md border-gray-300 shadow-sm">
                        <option value="MENSUAL">Mensual</option>
                        <option value="TRIMESTRAL">Trimestral</option>
                        <option value="SEMESTRAL">Semestral</option>
                        <option value="ANUAL">Anual</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Retención en la fuente (%)</label>
                    <input type="number" name="alternativas[${i}][retencion_porc]" step="0.01" min="0" max="100" value="4.00" class="w-full text-sm rounded-md border-gray-300 shadow-sm font-mono" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Comisión anual (% EA)</label>
                    <input type="number" name="alternativas[${i}][comision_anual_porc]" step="0.01" min="0" max="100" value="0.00" class="w-full text-sm rounded-md border-gray-300 shadow-sm font-mono" />
                </div>
            </div>
        `;
        contenedor.appendChild(div);
        contadorAlternativas++;

        if (contadorAlternativas >= 4) {
            document.getElementById('btn-agregar-alt').style.display = 'none';
        }
    }

    function eliminarAlternativa(i) {
        const el = document.getElementById('panel-alt-' + i);
        if (el) {
            el.remove();
            contadorAlternativas--;
            document.getElementById('btn-agregar-alt').style.display = 'flex';
        }
    }
</script>
@endsection

