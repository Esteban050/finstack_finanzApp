{{-- RF-027 — Gestión de categorías y subcategorías personalizadas --}}
@extends('layouts.app')

@section('title', 'Categorías de gasto')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6 space-y-6">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Categorías de gasto</h1>
        <button onclick="document.getElementById('modal-cat').classList.remove('hidden')"
                class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700">
            + Nueva categoría
        </button>
    </div>

    @if(session('status'))
        <div class="rounded-md bg-green-50 border border-green-300 px-4 py-3 text-green-800 text-sm">{{ session('status') }}</div>
    @endif

    {{-- Límites — RN-027.1 / CA-027.2 --}}
    <div class="flex gap-4 text-sm text-gray-500">
        <span>Categorías propias: <strong>{{ $limites['propias'] }}</strong> / {{ $limites['categorias'] }}</span>
        @if($limites['propias'] >= $limites['categorias'])
            <span class="text-red-600 font-semibold">⚠ Alcanzaste el máximo de {{ $limites['categorias'] }} categorías.</span>
        @endif
    </div>

    {{-- Árbol de categorías --}}
    @foreach($categorias->whereNull('categoria_padre_id') as $cat)
        <div class="rounded-lg border {{ $cat->activa ? 'border-gray-200 bg-white' : 'border-gray-100 bg-gray-50 opacity-60' }} shadow-sm">
            <div class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center gap-3">
                    @if($cat->icono)
                        <span class="text-xl">{{ $cat->icono }}</span>
                    @endif
                    @if($cat->color_hex)
                        <span class="inline-block w-3 h-3 rounded-full" style="background:{{ $cat->color_hex }}"></span>
                    @endif
                    <div>
                        <p class="font-semibold text-gray-900">{{ $cat->nombre }}</p>
                        @if(!$cat->es_predeterminada)
                            <p class="text-xs text-gray-400">Propia</p>
                        @else
                            <p class="text-xs text-gray-400">Predeterminada</p>
                        @endif
                    </div>
                </div>
                @if(!$cat->es_predeterminada)
                    <div class="flex gap-2">
                        {{-- Activar/Inactivar — RN-027.2 --}}
                        <form method="POST" action="{{ route('gastos.categorias.toggle', $cat->id) }}">
                            @csrf @method('PATCH')
                            <button type="submit"
                                    class="rounded border px-2 py-1 text-xs {{ $cat->activa ? 'border-yellow-300 text-yellow-700 hover:bg-yellow-50' : 'border-green-300 text-green-700 hover:bg-green-50' }}">
                                {{ $cat->activa ? 'Inactivar' : 'Activar' }}
                            </button>
                        </form>
                        {{-- Fusionar — CA-027.1 --}}
                        <button type="button"
                                onclick="abrirFusionar('{{ $cat->id }}', '{{ addslashes($cat->nombre) }}')"
                                class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                            Fusionar
                        </button>
                        {{-- Eliminar — RN-027.2 --}}
                        <form method="POST" action="{{ route('gastos.categorias.destroy', $cat->id) }}"
                              data-confirm-delete>
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50">
                                Eliminar
                            </button>
                        </form>
                        {{-- Agregar subcategoría --}}
                        <button type="button"
                                onclick="abrirCrearSub('{{ $cat->id }}', '{{ addslashes($cat->nombre) }}')"
                                class="rounded bg-indigo-50 border border-indigo-200 px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-100">
                            + Subcategoría
                        </button>
                    </div>
                @endif
            </div>

            {{-- Subcategorías --}}
            @php $subs = $categorias->where('categoria_padre_id', $cat->id); @endphp
            @if($subs->isNotEmpty())
                <div class="border-t border-gray-100 divide-y divide-gray-50">
                    @foreach($subs as $sub)
                        <div class="flex items-center justify-between px-6 py-2 {{ $sub->activa ? '' : 'opacity-50' }}">
                            <div class="text-sm">
                                <span class="text-gray-800">{{ $sub->nombre }}</span>
                                @if($sub->naturaleza_sugerida || $sub->necesidad_sugerida)
                                    <span class="ml-2 text-xs text-gray-400">
                                        {{ $sub->naturaleza_sugerida }} / {{ $sub->necesidad_sugerida }}
                                    </span>
                                @endif
                            </div>
                            @if(!$sub->es_predeterminada)
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('gastos.categorias.toggle', $sub->id) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="rounded border px-2 py-0.5 text-xs {{ $sub->activa ? 'border-yellow-300 text-yellow-600' : 'border-green-300 text-green-600' }}">
                                            {{ $sub->activa ? 'Inactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                    <button type="button"
                                            onclick="abrirFusionar('{{ $sub->id }}', '{{ addslashes($sub->nombre) }}')"
                                            class="rounded border border-gray-200 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50">
                                        Fusionar
                                    </button>
                                    <form method="POST" action="{{ route('gastos.categorias.destroy', $sub->id) }}"
                                          data-confirm-delete>
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rounded border border-red-200 px-2 py-0.5 text-xs text-red-600">Eliminar</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>

{{-- Modal: crear categoría o subcategoría --}}
<div id="modal-cat" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-bold text-gray-900" id="modal-cat-titulo">Nueva categoría</h2>
            <button onclick="cerrarModalCat()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <form method="POST" action="{{ route('gastos.categorias.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="categoria_padre_id" id="modal-padre-id" value="">

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    Nombre ({{ \App\Services\CategoriaGastoService::MIN_LONGITUD_NOMBRE }}–{{ \App\Services\CategoriaGastoService::MAX_LONGITUD_NOMBRE }} chars) *
                </label>
                <input type="text" name="nombre" required
                       minlength="{{ \App\Services\CategoriaGastoService::MIN_LONGITUD_NOMBRE }}"
                       maxlength="{{ \App\Services\CategoriaGastoService::MAX_LONGITUD_NOMBRE }}"
                       class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
            </div>

            <div id="bloque-sugerencias-modal" class="hidden grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Naturaleza sugerida *</label>
                    <select name="naturaleza_sugerida" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                        <option value="">Seleccione…</option>
                        <option value="FIJO">Fijo</option>
                        <option value="VARIABLE">Variable</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Necesidad sugerida *</label>
                    <select name="necesidad_sugerida" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                        <option value="">Seleccione…</option>
                        <option value="ESENCIAL">Esencial</option>
                        <option value="DISCRECIONAL">Discrecional</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Ícono</label>
                    <select name="icono" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                        <option value="">Ninguno</option>
                        @foreach($iconos as $ic)
                            <option value="{{ $ic }}">{{ $ic }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Color</label>
                    <select name="color_hex" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                        <option value="">Ninguno</option>
                        @foreach($paleta as $color)
                            <option value="{{ $color }}" style="background:{{ $color }}">{{ $color }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Crear
                </button>
                <button type="button" onclick="cerrarModalCat()"
                        class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal: fusionar categoría — CA-027.1 --}}
<div id="modal-fusionar" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-bold text-gray-900">Fusionar categoría</h2>
            <button onclick="document.getElementById('modal-fusionar').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <p class="text-sm text-gray-600" id="modal-fusionar-desc"></p>
        <form method="POST" id="form-fusionar" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Categoría destino *</label>
                <select name="destino_id" required class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                    <option value="">Seleccione destino…</option>
                    @foreach($categorias as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}{{ $c->categoria_padre_id ? ' (sub)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                    Fusionar
                </button>
                <button type="button" onclick="document.getElementById('modal-fusionar').classList.add('hidden')"
                        class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function cerrarModalCat() {
    document.getElementById('modal-cat').classList.add('hidden');
    document.getElementById('modal-padre-id').value = '';
    document.getElementById('modal-cat-titulo').textContent = 'Nueva categoría';
    document.getElementById('bloque-sugerencias-modal').classList.add('hidden');
}

function abrirCrearSub(padreId, padreNombre) {
    document.getElementById('modal-padre-id').value = padreId;
    document.getElementById('modal-cat-titulo').textContent = `Nueva subcategoría de "${padreNombre}"`;
    document.getElementById('bloque-sugerencias-modal').classList.remove('hidden');
    document.getElementById('modal-cat').classList.remove('hidden');
}

function abrirFusionar(origenId, origenNombre) {
    const form = document.getElementById('form-fusionar');
    form.action = `/gastos/categorias/${origenId}/fusionar`;
    document.getElementById('modal-fusionar-desc').textContent =
        `Todos los gastos de "${origenNombre}" pasarán a la categoría destino que elija. Esta acción no se puede deshacer.`;
    document.getElementById('modal-fusionar').classList.remove('hidden');
}
</script>
@endsection
