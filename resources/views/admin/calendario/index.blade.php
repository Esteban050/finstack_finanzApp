<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Calendario Tributario y de Aportes
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />

            @if ($errors->any())
                <div class="p-4 bg-red-100 border border-red-300 text-red-700 text-sm rounded-lg">
                    <p class="font-bold mb-2">Por favor corrige los siguientes errores:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Formulario de Carga Masiva CSV -->
                <div class="bg-white p-6 shadow sm:rounded-xl md:col-span-1">
                    <h3 class="font-bold text-md text-gray-900 border-b pb-2 mb-4">Cargar Calendario (CSV)</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        El archivo CSV debe contener las columnas: <code>digito_desde, digito_hasta, fecha</code> y cubrir del 00 al 99 sin huecos ni traslapes.
                    </p>

                    <form method="POST" action="{{ route('admin.calendario.importar') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="anio_vigencia" value="Año de Vigencia" />
                            <x-text-input id="anio_vigencia" type="number" name="anio_vigencia" :value="old('anio_vigencia', $anio)" required class="w-full mt-1" />
                        </div>

                        <div>
                            <x-input-label for="tipo_obligacion" value="Tipo de Obligación" />
                            <select id="tipo_obligacion" name="tipo_obligacion" class="w-full mt-1 border-gray-300 rounded-md text-sm">
                                <option value="RENTA_PN" {{ $tipo === 'RENTA_PN' ? 'selected' : '' }}>Renta Personas Naturales</option>
                                <option value="SEGURIDAD_SOCIAL_INDEP" {{ $tipo === 'SEGURIDAD_SOCIAL_INDEP' ? 'selected' : '' }}>Seguridad Social Independientes</option>
                            </select>
                        </div>

                        <div>
                            <x-input-label for="archivo_csv" value="Archivo CSV *" />
                            <input type="file" id="archivo_csv" name="archivo_csv" accept=".csv,text/csv" required class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 mt-1" />
                        </div>

                        <x-primary-button class="w-full justify-center">
                            Validar e Importar CSV
                        </x-primary-button>
                    </form>

                    <!-- Consultador Rápido por Dígito -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <h4 class="font-bold text-sm text-gray-900 mb-2">Consultar Fecha por Dígitos</h4>
                        <div class="flex space-x-2">
                            <input type="text" id="consulta_digitos" maxlength="2" placeholder="Ej: 37" class="w-20 text-center font-mono text-sm border-gray-300 rounded-md">
                            <button type="button" id="btnConsultar" class="px-3 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-md hover:bg-gray-700">
                                Consultar
                            </button>
                        </div>
                        <div id="resultadoConsulta" class="mt-2 text-xs font-semibold text-gray-800 hidden"></div>
                    </div>
                </div>

                <!-- Tabla de Vencimientos Cargados -->
                <div class="bg-white p-6 shadow sm:rounded-xl md:col-span-2">
                    <div class="flex items-center justify-between border-b pb-3 mb-4">
                        <h3 class="font-bold text-md text-gray-900">
                            Vencimientos Registrados ({{ $tipo === 'RENTA_PN' ? 'Renta PN' : 'Seguridad Social' }} - {{ $anio }})
                        </h3>
                        <div class="flex space-x-2">
                            <a href="{{ route('admin.calendario.index', ['anio' => $anio, 'tipo' => 'RENTA_PN']) }}" class="text-xs px-2.5 py-1 rounded {{ $tipo === 'RENTA_PN' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}">Renta</a>
                            <a href="{{ route('admin.calendario.index', ['anio' => $anio, 'tipo' => 'SEGURIDAD_SOCIAL_INDEP']) }}" class="text-xs px-2.5 py-1 rounded {{ $tipo === 'SEGURIDAD_SOCIAL_INDEP' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}">Seguridad Social</a>
                        </div>
                    </div>

                    <div class="overflow-x-auto max-h-[500px]">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-gray-700 sticky top-0">
                                <tr>
                                    <th class="px-4 py-2 text-left">Dígito Desde</th>
                                    <th class="px-4 py-2 text-left">Dígito Hasta</th>
                                    <th class="px-4 py-2 text-left">Fecha de Vencimiento</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 font-mono">
                                @forelse ($registros as $reg)
                                    <tr>
                                        <td class="px-4 py-2 text-indigo-700 font-bold">{{ $reg->digito_desde }}</td>
                                        <td class="px-4 py-2 text-indigo-700 font-bold">{{ $reg->digito_hasta }}</td>
                                        <td class="px-4 py-2 font-sans">{{ $reg->fecha_vencimiento->format('Y-m-d') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-8 text-center text-gray-500 font-sans">
                                            No hay calendario cargado para esta vigencia y obligación. Carga un archivo CSV para configurarlo.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('btnConsultar')?.addEventListener('click', async () => {
            const digitos = document.getElementById('consulta_digitos').value;
            const resDiv = document.getElementById('resultadoConsulta');

            if (!digitos || digitos.length < 2) {
                alert('Ingresa 2 dígitos (ej: 05, 37, 99)');
                return;
            }

            try {
                const url = `{{ route('admin.calendario.consultar') }}?digitos=${digitos}&anio={{ $anio }}&tipo={{ $tipo }}`;
                const response = await fetch(url);
                const data = await response.json();

                resDiv.classList.remove('hidden');
                if (data.encontrado) {
                    resDiv.className = 'mt-2 text-xs p-2 bg-green-50 border border-green-200 text-green-800 rounded font-semibold';
                    resDiv.innerText = `Dígitos ${data.digitos}: Vence el ${data.fecha_vencimiento}`;
                } else {
                    resDiv.className = 'mt-2 text-xs p-2 bg-red-50 border border-red-200 text-red-700 rounded font-semibold';
                    resDiv.innerText = data.mensaje;
                }
            } catch (e) {
                alert('Error al consultar: ' + e.message);
            }
        });
    </script>
</x-app-layout>
