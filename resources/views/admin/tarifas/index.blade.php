<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Tablas de Tarifas Escalonadas — Vigencia {{ $parametro->anio_vigencia }}
            </h2>
            <a href="{{ route('admin.parametros.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-900">
                &larr; Volver a Vigencias
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <x-auth-session-status :status="session('status')" />

            @if ($errors->has('tabla_error'))
                <div class="p-4 bg-red-100 border border-red-300 text-red-700 text-sm rounded-lg">
                    {{ $errors->first('tabla_error') }}
                </div>
            @endif

            <!-- 1. TABLA RENTA ART. 241 ET -->
            <div class="bg-white p-6 shadow sm:rounded-xl">
                <div class="flex items-center justify-between border-b pb-4 mb-4">
                    <div>
                        <h3 class="font-bold text-lg text-gray-900">1. Tabla de Retención e Impuesto de Renta (Art. 241 ET)</h3>
                        <p class="text-xs text-gray-500">Valores expresados en UVT. Valida tramos continuos sin huecos ni traslapes.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm mb-6">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left">Desde (> UVT)</th>
                                <th class="px-4 py-2 text-left">Hasta (≤ UVT)</th>
                                <th class="px-4 py-2 text-right">Tarifa Marginal</th>
                                <th class="px-4 py-2 text-right">Impuesto Base (UVT)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($tarifasRenta as $t)
                                <tr>
                                    <td class="px-4 py-2 font-mono">{{ number_format($t->rango_desde, 0) }}</td>
                                    <td class="px-4 py-2 font-mono">{{ $t->rango_hasta ? number_format($t->rango_hasta, 0) : 'En adelante' }}</td>
                                    <td class="px-4 py-2 text-right font-bold text-indigo-600">{{ $t->tarifa_porc }}%</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ number_format($t->impuesto_base_uvt, 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-center text-gray-500">
                                        No hay tramos configurados para esta vigencia. Usa el botón de carga por defecto abajo.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Probador Interactivo -->
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <h4 class="font-bold text-sm text-gray-800 mb-2">Simulador de Impuesto de Renta:</h4>
                    <div class="flex items-center space-x-3">
                        <input type="number" id="simulador_base" placeholder="Base gravable en UVT (ej: 2000)" class="border-gray-300 rounded-md text-sm w-64" value="2000">
                        <button type="button" id="btnCalcular" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700">
                            Calcular Impuesto
                        </button>
                    </div>
                    <div id="resultadoSimulador" class="mt-3 text-sm font-semibold text-gray-800 hidden"></div>
                </div>
            </div>

            <!-- 2. TABLA FSP -->
            <div class="bg-white p-6 shadow sm:rounded-xl">
                <h3 class="font-bold text-lg text-gray-900 border-b pb-2 mb-4">2. Fondo de Solidaridad Pensional (FSP)</h3>
                <p class="text-xs text-gray-500 mb-4">Sobre el Ingreso Base de Cotización (IBC) medido en SMMLV.</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left">Desde (≥ SMMLV)</th>
                                <th class="px-4 py-2 text-left">Hasta (≤ SMMLV)</th>
                                <th class="px-4 py-2 text-right">Tarifa FSP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 font-mono">
                            <tr><td class="px-4 py-2">4</td><td class="px-4 py-2">16</td><td class="px-4 py-2 text-right font-bold text-indigo-600">1.0%</td></tr>
                            <tr><td class="px-4 py-2">16</td><td class="px-4 py-2">17</td><td class="px-4 py-2 text-right font-bold text-indigo-600">1.2%</td></tr>
                            <tr><td class="px-4 py-2">17</td><td class="px-4 py-2">18</td><td class="px-4 py-2 text-right font-bold text-indigo-600">1.4%</td></tr>
                            <tr><td class="px-4 py-2">18</td><td class="px-4 py-2">19</td><td class="px-4 py-2 text-right font-bold text-indigo-600">1.6%</td></tr>
                            <tr><td class="px-4 py-2">19</td><td class="px-4 py-2">20</td><td class="px-4 py-2 text-right font-bold text-indigo-600">1.8%</td></tr>
                            <tr><td class="px-4 py-2">> 20</td><td class="px-4 py-2">En adelante</td><td class="px-4 py-2 text-right font-bold text-indigo-600">2.0%</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. TABLA ARL -->
            <div class="bg-white p-6 shadow sm:rounded-xl">
                <h3 class="font-bold text-lg text-gray-900 border-b pb-2 mb-4">3. Tabla ARL por Clase de Riesgo (Decreto 1072 de 2015)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left">Clase de Riesgo</th>
                                <th class="px-4 py-2 text-left">Nivel</th>
                                <th class="px-4 py-2 text-right">Tarifa ARL (%)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr><td class="px-4 py-2 font-bold">Clase I</td><td class="px-4 py-2 text-gray-600">Mínimo (Oficinas, actividades administrativas)</td><td class="px-4 py-2 text-right font-mono font-bold text-indigo-600">0.522%</td></tr>
                            <tr><td class="px-4 py-2 font-bold">Clase II</td><td class="px-4 py-2 text-gray-600">Bajo (Manufactura liviana, comercio)</td><td class="px-4 py-2 text-right font-mono font-bold text-indigo-600">1.044%</td></tr>
                            <tr><td class="px-4 py-2 font-bold">Clase III</td><td class="px-4 py-2 text-gray-600">Medio (Procesos industriales, transportes)</td><td class="px-4 py-2 text-right font-mono font-bold text-indigo-600">2.436%</td></tr>
                            <tr><td class="px-4 py-2 font-bold">Clase IV</td><td class="px-4 py-2 text-gray-600">Alto (Construcción, transporte de carga pesada)</td><td class="px-4 py-2 text-right font-mono font-bold text-indigo-600">4.350%</td></tr>
                            <tr><td class="px-4 py-2 font-bold">Clase V</td><td class="px-4 py-2 text-gray-600">Máximo (Minería, explosivos, trabajo en alturas)</td><td class="px-4 py-2 text-right font-mono font-bold text-indigo-600">6.960%</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('btnCalcular')?.addEventListener('click', async () => {
            const base = document.getElementById('simulador_base').value;
            const resDiv = document.getElementById('resultadoSimulador');

            try {
                const response = await fetch("{{ route('admin.tarifas.probar', $parametro->id) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ base_uvt: base })
                });

                const data = await response.json();
                resDiv.classList.remove('hidden');
                resDiv.innerHTML = `
                    <div class="p-3 bg-indigo-50 border border-indigo-200 text-indigo-900 rounded-md">
                        Base: <strong>${data.base_uvt} UVT</strong> |
                        Impuesto calculado: <strong class="text-indigo-600 font-mono">${data.impuesto_uvt} UVT</strong>
                        (aprox. <strong>$${new Intl.NumberFormat('es-CO').format(data.impuesto_cop)} COP</strong>)
                    </div>
                `;
            } catch (err) {
                alert('Error al calcular el impuesto: ' + err.message);
            }
        });
    </script>
</x-app-layout>
