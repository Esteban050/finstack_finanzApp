<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Parámetros Normativos por Vigencia
                </h2>
                <p class="text-xs text-gray-500 mt-1">Una versión activa por cada año gravable para cálculos de retención y liquidación fiscal.</p>
            </div>
            <a href="{{ route('admin.parametros.create') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm shadow">
                + Nueva Vigencia
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left font-bold">Año Gravable</th>
                            <th class="px-6 py-3 text-left font-bold">Versión</th>
                            <th class="px-6 py-3 text-left font-bold">Estado</th>
                            <th class="px-6 py-3 text-left font-bold">Acto Administrativo</th>
                            <th class="px-6 py-3 text-right font-bold">UVT</th>
                            <th class="px-6 py-3 text-right font-bold">SMMLV</th>
                            <th class="px-6 py-3 text-center font-bold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($parametros as $p)
                            <tr class="{{ $p->es_activa ? 'bg-indigo-50/30' : '' }}">
                                <td class="px-6 py-4 font-bold text-gray-900">{{ $p->anio_vigencia }}</td>
                                <td class="px-6 py-4 font-mono">v{{ $p->version }}</td>
                                <td class="px-6 py-4">
                                    @if ($p->es_activa)
                                        <span class="px-2.5 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">Activa (Año {{ $p->anio_vigencia }})</span>
                                    @else
                                        <span class="px-2.5 py-1 text-xs font-semibold bg-gray-100 text-gray-600 rounded-full">Histórica</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-600">{{ $p->acto_administrativo }} ({{ $p->fecha_acto->format('Y-m-d') }})</td>
                                <td class="px-6 py-4 text-right font-mono font-semibold">${{ number_format($p->valor_uvt, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 text-right font-mono font-semibold">${{ number_format($p->valor_smmlv, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 text-center space-x-2">
                                    <a href="{{ route('admin.parametros.edit', $p->id) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">Editar</a>
                                    <span class="text-gray-300">|</span>
                                    <a href="{{ route('admin.tarifas.index', $p->id) }}" class="text-emerald-600 hover:text-emerald-900 font-medium">Tarifas Escalonadas</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    No hay vigencias normativas registradas aún. Haz clic en "+ Nueva Vigencia" para crear la primera.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $parametros->links() }}
        </div>
    </div>
</x-app-layout>
