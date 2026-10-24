{{-- RF-029 — Consulta y filtrado de gastos --}}
@extends('layouts.app')

@section('title', 'Mis gastos')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Mis gastos</h1>
        <div class="flex gap-2">
            <a href="{{ route('gastos.categorias.index') }}"
               class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                Categorías
            </a>
            <a href="{{ route('gastos.recurrentes.index') }}"
               class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                Recurrentes
            </a>
            <a href="{{ route('gastos.create') }}"
               class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700">
                + Registrar gasto
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="rounded-md bg-green-50 border border-green-300 px-4 py-3 text-green-800 text-sm">{{ session('status') }}</div>
    @endif

    {{-- Filtros — RN-029.1 --}}
    <details class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <summary class="cursor-pointer px-4 py-3 font-semibold text-gray-700 select-none">
            🔍 Filtros (RN-029.1)
        </summary>
        <form method="GET" action="{{ route('gastos.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 px-4 pb-4">

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Desde</label>
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                       class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Hasta</label>
                <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                       class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Categoría</label>
                <select name="categoria_id" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                    <option value="">Todas</option>
                    @foreach($categorias->whereNull('categoria_padre_id') as $cat)
                        <option value="{{ $cat->id }}" @selected(request('categoria_id') == $cat->id)>{{ $cat->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Naturaleza</label>
                <select name="naturaleza" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                    <option value="">Todas</option>
                    @foreach($dimensiones['naturalezas'] as $n)
                        <option value="{{ $n }}" @selected(request('naturaleza') === $n)>{{ ucfirst(strtolower($n)) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Necesidad</label>
                <select name="necesidad" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                    <option value="">Todas</option>
                    @foreach($dimensiones['necesidades'] as $n)
                        <option value="{{ $n }}" @selected(request('necesidad') === $n)>{{ ucfirst(strtolower($n)) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Medio de pago</label>
                <select name="medio_pago" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($dimensiones['medios_pago'] as $m)
                        <option value="{{ $m }}" @selected(request('medio_pago') === $m)>{{ ucwords(strtolower(str_replace('_', ' ', $m))) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Relevancia tributaria</label>
                <select name="relevancia_tributaria" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                    <option value="">Todas</option>
                    @foreach($dimensiones['relevancias'] as $r)
                        <option value="{{ $r }}" @selected(request('relevancia_tributaria') === $r)>{{ ucwords(strtolower(str_replace('_', ' ', $r))) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Filtro por etiqueta — RN-026.1 (6) --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Etiqueta</label>
                <input type="text" name="etiqueta" value="{{ request('etiqueta') }}" maxlength="30"
                       list="etiquetas-lista"
                       class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                <datalist id="etiquetas-lista">
                    @foreach($etiquetasUsadas as $et)
                        <option value="{{ $et }}">
                    @endforeach
                </datalist>
            </div>

            {{-- Texto libre — RN-029.1 --}}
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Texto en descripción o comercio</label>
                <input type="text" name="texto" value="{{ request('texto') }}" maxlength="255"
                       class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Monto mínimo</label>
                <input type="number" name="monto_min" value="{{ request('monto_min') }}" min="0" step="0.01"
                       class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Monto máximo</label>
                <input type="number" name="monto_max" value="{{ request('monto_max') }}" min="0" step="0.01"
                       class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
            </div>

            <div class="sm:col-span-2 lg:col-span-4 flex gap-2">
                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Aplicar filtros
                </button>
                <a href="{{ route('gastos.index') }}" class="rounded border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Limpiar
                </a>
            </div>
        </form>
    </details>

    {{-- Totales — CA-029.1 / RN-024.3 --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="rounded-lg bg-white border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Registros</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($totales->cantidad) }}</p>
        </div>
        <div class="rounded-lg bg-white border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total (COP)</p>
            <p class="text-xl font-bold text-red-700">$ {{ number_format($totales->monto, 2, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Tarjeta crédito</p>
            <p class="text-xl font-bold text-orange-700">$ {{ number_format($totales->tarjeta_credito, 2, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Pot. deducible</p>
            <p class="text-xl font-bold text-green-700">$ {{ number_format($totales->potencialmente_deducible, 2, ',', '.') }}</p>
        </div>
    </div>

    {{-- Pendientes --}}
    @if($pendientes->cantidad > 0)
        <div class="rounded-md bg-amber-50 border border-amber-300 px-4 py-3 text-amber-800 text-sm flex justify-between">
            <span>⏳ <strong>{{ $pendientes->cantidad }}</strong> gasto(s) pendiente(s) de confirmar monto estimado.</span>
            <a href="{{ route('gastos.recurrentes.index') }}" class="font-semibold underline">Ver</a>
        </div>
    @endif

    {{-- Tabla — RN-026.1 columnas --}}
    @if($gastos->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 bg-white py-16 text-center text-gray-500">
            No hay gastos para los filtros seleccionados.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm bg-white">
            <table class="min-w-full text-sm divide-y divide-gray-100">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-3 py-3 text-left"><input type="checkbox" id="sel-todos" class="rounded"></th>
                        <th class="px-3 py-3 text-left">Fecha</th>
                        <th class="px-3 py-3 text-left">Categoría</th>
                        <th class="px-3 py-3 text-left">Naturaleza</th>
                        <th class="px-3 py-3 text-left">Necesidad</th>
                        <th class="px-3 py-3 text-left">Medio de pago</th>
                        <th class="px-3 py-3 text-right">Monto (COP)</th>
                        <th class="px-3 py-3 text-left">Etiquetas</th>
                        <th class="px-3 py-3 text-left">Estado</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50" id="tabla-gastos">
                    @foreach($gastos as $gasto)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-3 py-3">
                                <input type="checkbox" name="ids[]" value="{{ $gasto->id }}"
                                       class="rounded gasto-check">
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($gasto->fecha)->format('d/m/Y') }}</td>
                            <td class="px-3 py-3 text-xs">
                                {{ $gasto->categoria?->nombre ?? '—' }}
                                @if($gasto->subcategoria)
                                    <span class="text-gray-400"> / {{ $gasto->subcategoria->nombre }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-xs">{{ ucfirst(strtolower($gasto->naturaleza)) }}</td>
                            <td class="px-3 py-3 text-xs">{{ ucfirst(strtolower($gasto->necesidad)) }}</td>
                            <td class="px-3 py-3 text-xs">{{ ucwords(strtolower(str_replace('_', ' ', $gasto->medio_pago))) }}</td>
                            <td class="px-3 py-3 text-right font-mono font-semibold text-red-700">
                                $ {{ number_format($gasto->monto_cop, 2, ',', '.') }}
                            </td>
                            <td class="px-3 py-3">
                                @foreach($gasto->etiquetas ?? [] as $et)
                                    <span class="inline-block rounded-full bg-gray-100 text-gray-600 text-xs px-2 py-0.5 mr-1">{{ $et }}</span>
                                @endforeach
                            </td>
                            <td class="px-3 py-3">
                                @if($gasto->estado === 'PENDIENTE')
                                    <span class="inline-block rounded-full bg-amber-100 text-amber-800 text-xs px-2 py-0.5">Pendiente</span>
                                @else
                                    <span class="inline-block rounded-full bg-green-100 text-green-800 text-xs px-2 py-0.5">Confirmado</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 flex gap-2 justify-end">
                                @if($gasto->estado === 'PENDIENTE')
                                    <form method="POST" action="{{ route('gastos.confirmar', $gasto->id) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-xs text-green-600 hover:underline">Confirmar</button>
                                    </form>
                                @endif
                                <a href="{{ route('gastos.edit', $gasto->id) }}" class="text-xs text-indigo-600 hover:underline">Editar</a>
                                <form method="POST" action="{{ route('gastos.destroy', $gasto->id) }}"
                                      onsubmit="return confirm('¿Eliminar gasto del {{ \Carbon\Carbon::parse($gasto->fecha)->format('d/m/Y') }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Edición masiva — RN-029.3 --}}
        <div id="barra-masiva" class="hidden rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 flex items-center gap-4 flex-wrap">
            <span class="text-sm font-medium text-indigo-800"><span id="cant-sel">0</span> gasto(s) seleccionado(s)</span>
            <button type="button" onclick="document.getElementById('modal-masivo').classList.remove('hidden')"
                    class="rounded bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                Editar seleccionados
            </button>
        </div>

        <div class="flex justify-between items-center text-sm text-gray-500">
            <span>Mostrando {{ $gastos->firstItem() }}–{{ $gastos->lastItem() }} de {{ $gastos->total() }}</span>
            {{ $gastos->links() }}
        </div>
    @endif
</div>

{{-- Modal edición masiva — RN-029.3 --}}
<div id="modal-masivo" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-bold text-gray-900">Edición masiva</h2>
            <button onclick="document.getElementById('modal-masivo').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <p class="text-xs text-gray-500">Modifica la categoría, naturaleza, necesidad o etiquetas de todos los gastos seleccionados. Máximo {{ $dimensiones['max_etiquetas'] ?? 10 }} etiquetas por gasto.</p>
        <form method="POST" action="{{ route('gastos.edicion-masiva') }}" id="form-masivo" class="space-y-3">
            @csrf @method('PATCH')
            <div id="inputs-masivos-ids"></div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Naturaleza</label>
                <select name="naturaleza" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                    <option value="">Sin cambio</option>
                    @foreach($dimensiones['naturalezas'] as $n)
                        <option value="{{ $n }}">{{ ucfirst(strtolower($n)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Necesidad</label>
                <select name="necesidad" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                    <option value="">Sin cambio</option>
                    @foreach($dimensiones['necesidades'] as $n)
                        <option value="{{ $n }}">{{ ucfirst(strtolower($n)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Aplicar
                </button>
                <button type="button" onclick="document.getElementById('modal-masivo').classList.add('hidden')"
                        class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
/* Selección masiva — RN-029.3 */
const selTodos = document.getElementById('sel-todos');
const checks   = document.querySelectorAll('.gasto-check');
const barra    = document.getElementById('barra-masiva');
const cantSpan = document.getElementById('cant-sel');

function actualizarBarra() {
    const seleccionados = Array.from(checks).filter(c => c.checked);
    cantSpan.textContent = seleccionados.length;
    barra.classList.toggle('hidden', seleccionados.length === 0);

    // Sincronizar IDs al formulario masivo
    const cont = document.getElementById('inputs-masivos-ids');
    cont.innerHTML = '';
    seleccionados.forEach(c => {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = 'ids[]';
        inp.value = c.value;
        cont.appendChild(inp);
    });
}

selTodos?.addEventListener('change', () => {
    checks.forEach(c => { c.checked = selTodos.checked; });
    actualizarBarra();
});
checks.forEach(c => c.addEventListener('change', actualizarBarra));
</script>
@endsection
