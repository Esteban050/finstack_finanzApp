<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Catálogo de Categorías Predeterminadas
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />

            <!-- Pestañas de Tipo (INGRESO / GASTO) -->
            <div class="flex space-x-2 border-b border-gray-200">
                <a href="{{ route('admin.categorias.index', ['tipo' => 'GASTO']) }}" class="px-4 py-2 font-bold text-sm border-b-2 {{ $tipo === 'GASTO' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Categorías de Gastos
                </a>
                <a href="{{ route('admin.categorias.index', ['tipo' => 'INGRESO']) }}" class="px-4 py-2 font-bold text-sm border-b-2 {{ $tipo === 'INGRESO' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Fuentes de Ingreso
                </a>
            </div>

            <!-- Formulario para agregar nueva categoría -->
            <div class="bg-white p-6 shadow sm:rounded-xl">
                <h3 class="font-bold text-md text-gray-900 mb-3">Agregar Nueva Categoría Predeterminada</h3>
                <form method="POST" action="{{ route('admin.categorias.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    @csrf
                    <input type="hidden" name="tipo" value="{{ $tipo }}">

                    <div>
                        <x-input-label for="nombre" value="Nombre de la Categoría *" />
                        <x-text-input id="nombre" type="text" name="nombre" :value="old('nombre')" required class="w-full mt-1" placeholder="Ej: Mascotas" />
                    </div>

                    <div>
                        <x-input-label for="categoria_padre_id" value="Categoría Padre (Opcional si es subcategoría)" />
                        <select id="categoria_padre_id" name="categoria_padre_id" class="w-full mt-1 border-gray-300 rounded-md text-sm">
                            <option value="">-- Ninguna (Categoría Principal) --</option>
                            @foreach ($categorias as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($tipo === 'GASTO')
                        <div>
                            <x-input-label for="naturaleza_sugerida" value="Naturaleza Sugerida" />
                            <select id="naturaleza_sugerida" name="naturaleza_sugerida" class="w-full mt-1 border-gray-300 rounded-md text-sm">
                                <option value="FIJO">FIJO</option>
                                <option value="VARIABLE">VARIABLE</option>
                            </select>
                        </div>
                    @endif

                    <div>
                        <x-primary-button class="w-full justify-center">
                            Guardar Categoría
                        </x-primary-button>
                    </div>
                </form>
            </div>

            <!-- Listado en Acordeón/Tarjetas -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($categorias as $categoria)
                    <div class="bg-white p-5 rounded-xl shadow border border-gray-100 {{ !$categoria->activa ? 'opacity-60 bg-gray-50' : '' }}">
                        <div class="flex items-center justify-between border-b pb-3 mb-3">
                            <div class="flex items-center space-x-2">
                                <span class="font-bold text-gray-900 text-base">{{ $categoria->nombre }}</span>
                                @if (!$categoria->activa)
                                    <span class="text-xs bg-red-100 text-red-800 font-semibold px-2 py-0.5 rounded">Inactiva</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.categorias.toggle', $categoria->id) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-xs font-semibold {{ $categoria->activa ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }}">
                                    {{ $categoria->activa ? 'Inactivar' : 'Activar' }}
                                </button>
                            </form>
                        </div>

                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Subcategorías asociadas:</p>
                            @forelse ($categoria->subcategorias as $sub)
                                <div class="flex items-center justify-between text-xs py-1 px-2 rounded hover:bg-gray-50">
                                    <span class="text-gray-700">• {{ $sub->nombre }}</span>
                                    <span class="text-gray-400 font-mono">{{ $sub->activa ? 'Activa' : 'Inactiva' }}</span>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400 italic">Sin subcategorías registradas.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
