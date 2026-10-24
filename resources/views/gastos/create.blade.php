{{-- RF-025 / RF-026 — Registro de gasto con clasificación multidimensional --}}
@extends('layouts.app')

@section('title', 'Registrar gasto')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6 space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('gastos.index') }}" class="text-indigo-600 hover:underline text-sm">← Gastos</a>
        <h1 class="text-2xl font-bold text-gray-900">Registrar gasto</h1>
    </div>

    @if($errors->any())
        <div class="rounded-md bg-red-50 border border-red-300 px-4 py-3 text-sm text-red-800 space-y-1">
            @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('gastos.store') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
        @csrf

        {{-- Fecha ≤ hoy — RN-025.1 --}}
        <div>
            <label for="fecha" class="block text-sm font-medium text-gray-700 mb-1">
                Fecha <span class="text-red-500">*</span>
            </label>
            <input type="date" id="fecha" name="fecha"
                   value="{{ old('fecha', date('Y-m-d')) }}"
                   max="{{ date('Y-m-d') }}" required
                   class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            <p class="mt-1 text-xs text-gray-400">Para gastos futuros use <a href="{{ route('gastos.recurrentes.index') }}" class="text-indigo-600 underline">gastos recurrentes</a>.</p>
            @error('fecha') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Monto — CA-025.1 / RNG-09 --}}
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label for="moneda" class="block text-sm font-medium text-gray-700 mb-1">Moneda *</label>
                <input type="text" id="moneda" name="moneda" value="{{ old('moneda', 'COP') }}"
                       maxlength="3" required
                       class="w-full rounded border-gray-300 text-sm uppercase focus:ring-indigo-500 focus:border-indigo-500"
                       oninput="toggleTasa(this.value)">
                @error('moneda') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-2">
                <label for="monto_original" class="block text-sm font-medium text-gray-700 mb-1">
                    Monto <span class="text-red-500">*</span>
                </label>
                <input type="number" id="monto_original" name="monto_original"
                       value="{{ old('monto_original') }}" step="0.01" min="0.01" required
                       class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <p class="mt-1 text-xs text-gray-400">No puede ser 0 ni negativo (CA-025.1).</p>
                @error('monto_original') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Tasa de cambio --}}
        <div id="bloque-tasa" class="{{ old('moneda', 'COP') !== 'COP' ? '' : 'hidden' }}">
            <label for="tasa_cambio_usada" class="block text-sm font-medium text-gray-700 mb-1">
                Tasa de cambio (a COP) *
            </label>
            <input type="number" id="tasa_cambio_usada" name="tasa_cambio_usada"
                   value="{{ old('tasa_cambio_usada') }}" step="0.0001" min="0.0001"
                   class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            @error('tasa_cambio_usada') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <hr class="border-gray-100">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Clasificación multidimensional (RN-026.1)</p>

        {{-- Categoría / Subcategoría — Dimensión 1 --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="categoria_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Categoría <span class="text-red-500">*</span>
                </label>
                <select id="categoria_id" name="categoria_id" required
                        class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        onchange="cargarSubcategorias(this)">
                    <option value="">Seleccione…</option>
                    @foreach($categorias->whereNull('categoria_padre_id') as $cat)
                        <option value="{{ $cat->id }}" @selected(old('categoria_id') == $cat->id)>
                            {{ $cat->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('categoria_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="subcategoria_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Subcategoría <span class="text-xs text-gray-400 font-normal">(Opcional)</span>
                </label>
                <select id="subcategoria_id" name="subcategoria_id"
                        class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        onchange="aplicarSugerencias(this)">
                    <option value="">— Sin subcategoría (Opcional) —</option>
                    @foreach($categorias->whereNotNull('categoria_padre_id') as $sub)
                        <option value="{{ $sub->id }}"
                                data-padre="{{ $sub->categoria_padre_id }}"
                                data-naturaleza="{{ $sub->naturaleza_sugerida }}"
                                data-necesidad="{{ $sub->necesidad_sugerida }}"
                                @selected(old('subcategoria_id') == $sub->id)>
                            {{ $sub->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('subcategoria_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Naturaleza + Necesidad — Dimensiones 2 y 3 — CA-026.1 --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="naturaleza" class="block text-sm font-medium text-gray-700 mb-1">
                    Naturaleza <span class="text-red-500">*</span>
                    <span class="text-xs text-gray-400">(sugerida por subcategoría)</span>
                </label>
                <select id="naturaleza" name="naturaleza"
                        class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Seleccione…</option>
                    @foreach($dimensiones['naturalezas'] as $n)
                        <option value="{{ $n }}" @selected(old('naturaleza') === $n)>{{ ucfirst(strtolower($n)) }}</option>
                    @endforeach
                </select>
                @error('naturaleza') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="necesidad" class="block text-sm font-medium text-gray-700 mb-1">
                    Necesidad <span class="text-red-500">*</span>
                    <span class="text-xs text-gray-400">(sugerida por subcategoría)</span>
                </label>
                <select id="necesidad" name="necesidad"
                        class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Seleccione…</option>
                    @foreach($dimensiones['necesidades'] as $n)
                        <option value="{{ $n }}" @selected(old('necesidad') === $n)>{{ ucfirst(strtolower($n)) }}</option>
                    @endforeach
                </select>
                @error('necesidad') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Medio de pago — Dimensión 4 — RN-025.2 --}}
        <div>
            <label for="medio_pago" class="block text-sm font-medium text-gray-700 mb-1">
                Medio de pago <span class="text-red-500">*</span>
            </label>
            <select id="medio_pago" name="medio_pago" required
                    class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Seleccione…</option>
                @foreach($dimensiones['medios_pago'] as $m)
                    <option value="{{ $m }}" @selected(old('medio_pago') === $m)>
                        {{ ucwords(strtolower(str_replace('_', ' ', $m))) }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-400">Tarjeta de crédito acumula en el indicador anual (RN-025.2).</p>
            @error('medio_pago') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Relevancia tributaria — Dimensión 5 — RN-026.1 --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="relevancia_tributaria" class="block text-sm font-medium text-gray-700 mb-1">
                    Relevancia tributaria
                </label>
                <select id="relevancia_tributaria" name="relevancia_tributaria"
                        class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        onchange="toggleSubtipo(this.value)">
                    @foreach($dimensiones['relevancias'] as $r)
                        <option value="{{ $r }}" @selected(old('relevancia_tributaria', 'NO_DEDUCIBLE') === $r)>
                            {{ ucwords(strtolower(str_replace('_', ' ', $r))) }}
                        </option>
                    @endforeach
                </select>
                @error('relevancia_tributaria') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div id="bloque-subtipo" class="{{ old('relevancia_tributaria') === 'POTENCIALMENTE_DEDUCIBLE' ? '' : 'hidden' }}">
                <label for="subtipo_deduccion" class="block text-sm font-medium text-gray-700 mb-1">
                    Subtipo de deducción <span class="text-red-500">*</span>
                </label>
                <select id="subtipo_deduccion" name="subtipo_deduccion"
                        class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Seleccione…</option>
                    @foreach($dimensiones['subtipos_deduccion'] as $st)
                        <option value="{{ $st }}" @selected(old('subtipo_deduccion') === $st)>{{ ucwords(str_replace('_', ' ', $st)) }}</option>
                    @endforeach
                </select>
                @error('subtipo_deduccion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Etiquetas — Dimensión 6 — RN-026.1 / CA-026.3 --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Etiquetas <span class="text-gray-400 text-xs">(0 a {{ $dimensiones['max_etiquetas'] }}, 2–30 chars cada una)</span>
            </label>
            <div id="contenedor-etiquetas" class="flex flex-wrap gap-2 mb-2" aria-label="Etiquetas del gasto"></div>
            <div class="flex gap-2">
                <input type="text" id="etiqueta-input" placeholder="viaje-cartagena"
                       maxlength="30" list="etiquetas-sugeridas"
                       class="flex-1 rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <button type="button" onclick="agregarEtiqueta()"
                        class="rounded border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                    Agregar
                </button>
                <datalist id="etiquetas-sugeridas">
                    @foreach($etiquetasUsadas as $et)
                        <option value="{{ $et }}">
                    @endforeach
                </datalist>
            </div>
            <div id="inputs-etiquetas"></div>
            <p class="mt-1 text-xs text-gray-400">CA-026.3: una etiqueta de más de 30 chars o la etiqueta número {{ ($dimensiones['max_etiquetas'] ?? 10) + 1 }} se rechazará.</p>
        </div>

        <hr class="border-gray-100">

        {{-- Comercio / Descripción --}}
        <div>
            <label for="comercio_beneficiario" class="block text-sm font-medium text-gray-700 mb-1">
                Comercio o beneficiario <span class="text-gray-400 text-xs">(máx. 80 chars)</span>
            </label>
            <input type="text" id="comercio_beneficiario" name="comercio_beneficiario"
                   value="{{ old('comercio_beneficiario') }}" maxlength="80"
                   class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            @error('comercio_beneficiario') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1">
                Descripción <span class="text-gray-400 text-xs">(máx. 255 chars)</span>
            </label>
            <textarea id="descripcion" name="descripcion" rows="2" maxlength="255"
                      class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('descripcion') }}</textarea>
            @error('descripcion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Registrar gasto
            </button>
            <a href="{{ route('gastos.index') }}" class="rounded border border-gray-300 px-6 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Cancelar
            </a>
        </div>
    </form>
</div>

{{-- Catálogo de subcategorías codificado en JSON para JS --}}
<script>
const CATALOGO = @json($categorias->groupBy('categoria_padre_id'));
const MAX_ETIQUETAS = {{ $dimensiones['max_etiquetas'] ?? 10 }};
let etiquetasActuales = [];

/* RN-026.1 (1): filtrar subcategorías al seleccionar categoría */
function cargarSubcategorias(select) {
    const padreId = select.value;
    const subSelect = document.getElementById('subcategoria_id');
    subSelect.innerHTML = '<option value="">— Sin subcategoría (Opcional) —</option>';
    if (!padreId) return;
    const subs = CATALOGO[padreId] || [];
    subs.forEach(sub => {
        const opt = document.createElement('option');
        opt.value = sub.id;
        opt.textContent = sub.nombre;
        opt.dataset.naturaleza = sub.naturaleza_sugerida || '';
        opt.dataset.necesidad  = sub.necesidad_sugerida  || '';
        subSelect.appendChild(opt);
    });
}

/* RN-026.2 / CA-026.1: precargar naturaleza y necesidad sugeridas */
function aplicarSugerencias(select) {
    const opt = select.options[select.selectedIndex];
    const nat = opt.dataset?.naturaleza;
    const nec = opt.dataset?.necesidad;
    if (nat) document.getElementById('naturaleza').value = nat;
    if (nec) document.getElementById('necesidad').value  = nec;
}

/* RN-026.1 (5): mostrar subtipo solo para POTENCIALMENTE_DEDUCIBLE */
function toggleSubtipo(valor) {
    document.getElementById('bloque-subtipo').classList.toggle('hidden', valor !== 'POTENCIALMENTE_DEDUCIBLE');
}

/* RN-021.3: mostrar tasa solo si moneda ≠ COP */
function toggleTasa(v) {
    document.getElementById('bloque-tasa').classList.toggle('hidden', v.toUpperCase() === 'COP' || v === '');
}

/* CA-026.3: manejo de etiquetas en el cliente */
function agregarEtiqueta() {
    const input = document.getElementById('etiqueta-input');
    const val   = input.value.trim().toLowerCase();
    if (!val) return;
    if (val.length < 2 || val.length > 30) {
        alert('La etiqueta debe tener entre 2 y 30 caracteres.');
        return;
    }
    if (etiquetasActuales.includes(val)) { input.value = ''; return; }
    if (etiquetasActuales.length >= MAX_ETIQUETAS) {
        alert(`Un gasto admite como máximo ${MAX_ETIQUETAS} etiquetas.`);
        return;
    }
    etiquetasActuales.push(val);
    renderEtiquetas();
    input.value = '';
}

function renderEtiquetas() {
    const cont   = document.getElementById('contenedor-etiquetas');
    const inputs = document.getElementById('inputs-etiquetas');
    cont.innerHTML   = '';
    inputs.innerHTML = '';
    etiquetasActuales.forEach((et, i) => {
        // Chip visual
        const chip = document.createElement('span');
        chip.className = 'inline-flex items-center gap-1 rounded-full bg-indigo-100 text-indigo-700 text-xs px-3 py-1';
        chip.textContent = et;
        const btn = document.createElement('button');
        btn.type = 'button'; btn.textContent = '×';
        btn.className = 'ml-1 text-indigo-400 hover:text-red-500';
        btn.onclick = () => { etiquetasActuales.splice(i, 1); renderEtiquetas(); };
        chip.appendChild(btn);
        cont.appendChild(chip);

        // Input hidden para enviar al servidor
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'etiquetas[]'; inp.value = et;
        inputs.appendChild(inp);
    });
}

document.getElementById('etiqueta-input')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); agregarEtiqueta(); }
});
</script>
@endsection
