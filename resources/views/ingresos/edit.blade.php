{{-- RF-023 — Edición de ingreso --}}
@extends('layouts.app')

@section('title', 'Editar ingreso')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6 space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('ingresos.index') }}" class="text-indigo-600 hover:underline text-sm">← Ingresos</a>
        <h1 class="text-2xl font-bold text-gray-900">Editar ingreso</h1>
    </div>

    @if($errors->any())
        <div class="rounded-md bg-red-50 border border-red-300 px-4 py-3 text-sm text-red-800 space-y-1">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('ingresos.update', $ingreso->id) }}"
          class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
        @csrf
        @method('PUT')

        {{-- Fuente --}}
        <div>
            <label for="fuente_id" class="block text-sm font-medium text-gray-700 mb-1">
                Fuente de ingreso <span class="text-red-500">*</span>
            </label>
            <select id="fuente_id" name="fuente_id" required
                    class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                @foreach($fuentes as $f)
                    <option value="{{ $f->id }}" @selected(old('fuente_id', $ingreso->fuente_id) == $f->id)>
                        {{ $f->nombre }}
                    </option>
                @endforeach
            </select>
            @error('fuente_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Fecha --}}
        <div>
            <label for="fecha_recepcion" class="block text-sm font-medium text-gray-700 mb-1">
                Fecha de recepción <span class="text-red-500">*</span>
            </label>
            <input type="date" id="fecha_recepcion" name="fecha_recepcion"
                   value="{{ old('fecha_recepcion', $ingreso->fecha_recepcion->format('Y-m-d')) }}"
                   max="{{ date('Y-m-d') }}" required
                   class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            @error('fecha_recepcion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Moneda + monto --}}
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label for="moneda" class="block text-sm font-medium text-gray-700 mb-1">Moneda *</label>
                <input type="text" id="moneda" name="moneda"
                       value="{{ old('moneda', $ingreso->moneda) }}"
                       maxlength="3" required
                       class="w-full rounded border-gray-300 text-sm uppercase focus:ring-indigo-500 focus:border-indigo-500"
                       oninput="toggleTasa(this.value)">
                @error('moneda') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-2">
                <label for="monto_original" class="block text-sm font-medium text-gray-700 mb-1">Monto bruto *</label>
                <input type="number" id="monto_original" name="monto_original"
                       value="{{ old('monto_original', $ingreso->monto_original) }}"
                       step="0.01" min="0.01" required
                       class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                       oninput="calcularPorcentajes()">
                @error('monto_original') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Tasa de cambio --}}
        <div id="bloque-tasa" class="{{ old('moneda', $ingreso->moneda) !== 'COP' ? '' : 'hidden' }}">
            <label for="tasa_cambio_usada" class="block text-sm font-medium text-gray-700 mb-1">
                Tasa de cambio (a COP) *
            </label>
            <input type="number" id="tasa_cambio_usada" name="tasa_cambio_usada"
                   value="{{ old('tasa_cambio_usada', $ingreso->tasa_cambio_usada) }}"
                   step="0.0001" min="0.0001"
                   class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            @error('tasa_cambio_usada') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

{{-- Retención — Porcentaje y Valor calculado (RF-021) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <div>
                <label for="porcentaje_retencion" class="block text-sm font-semibold text-gray-700 mb-1">
                    % Retención en la fuente
                </label>
                <div class="relative">
                    <input type="number" id="porcentaje_retencion" name="porcentaje_retencion"
                           value="{{ old('porcentaje_retencion', '') }}" data-percentage-amount="{{ old('retencion_fuente', $ingreso->retencion_fuente_cop) }}"
                           step="0.01" min="0" max="100" placeholder="0"
                           class="w-full rounded border-gray-300 pr-8 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                           oninput="calcularPorcentajes()">
                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 text-sm font-semibold pointer-events-none">%</span>
                </div>
                <div class="flex items-center gap-1.5 mt-2">
                    <span class="text-[11px] text-gray-400">Comunes:</span>
                    <button type="button" onclick="setPorcentajeRetencion(0)" class="px-2 py-0.5 text-xs bg-white border border-gray-200 rounded hover:bg-gray-100 text-gray-600">0%</button>
                    <button type="button" onclick="setPorcentajeRetencion(4)" class="px-2 py-0.5 text-xs bg-white border border-gray-200 rounded hover:bg-gray-100 text-gray-600">4%</button>
                    <button type="button" onclick="setPorcentajeRetencion(10)" class="px-2 py-0.5 text-xs bg-white border border-gray-200 rounded hover:bg-gray-100 text-gray-600">10%</button>
                    <button type="button" onclick="setPorcentajeRetencion(11)" class="px-2 py-0.5 text-xs bg-white border border-gray-200 rounded hover:bg-gray-100 text-gray-600">11%</button>
                </div>
            </div>

            <div>
                <label for="retencion_fuente" class="block text-sm font-semibold text-gray-700 mb-1">
                    Valor de retención calculado
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm font-bold pointer-events-none">$</span>
                    <input type="number" id="retencion_fuente" name="retencion_fuente"
                           value="{{ old('retencion_fuente', $ingreso->retencion_fuente_cop) }}"
                           step="0.01" min="0" readonly
                           class="w-full rounded border-gray-300 pl-7 text-sm bg-gray-100 text-gray-700 font-semibold focus:ring-0 cursor-not-allowed">
                </div>
                <p class="mt-1 text-xs text-gray-400">Calculado automáticamente sobre el monto bruto.</p>
                @error('retencion_fuente') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Aportes descontados — Porcentaje y Valor calculado (RF-021) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <div>
                <label for="porcentaje_aportes" class="block text-sm font-semibold text-gray-700 mb-1">
                    % Aportes obligatorios (Salud y Pensión)
                </label>
                <div class="relative">
                    <input type="number" id="porcentaje_aportes" name="porcentaje_aportes"
                           value="{{ old('porcentaje_aportes', '') }}" data-percentage-amount="{{ old('aportes_descontados', $ingreso->aportes_descontados_cop) }}"
                           step="0.01" min="0" max="100" placeholder="0"
                           class="w-full rounded border-gray-300 pr-8 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                           oninput="calcularPorcentajes()">
                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 text-sm font-semibold pointer-events-none">%</span>
                </div>
                <div class="flex items-center gap-1.5 mt-2">
                    <span class="text-[11px] text-gray-400">Comunes:</span>
                    <button type="button" onclick="setPorcentajeAportes(0)" class="px-2 py-0.5 text-xs bg-white border border-gray-200 rounded hover:bg-gray-100 text-gray-600">0%</button>
                    <button type="button" onclick="setPorcentajeAportes(4)" class="px-2 py-0.5 text-xs bg-white border border-gray-200 rounded hover:bg-gray-100 text-gray-600">4%</button>
                    <button type="button" onclick="setPorcentajeAportes(8)" class="px-2 py-0.5 text-xs bg-white border border-gray-200 rounded hover:bg-gray-100 text-gray-600">8% (4% salud + 4% pensión)</button>
                </div>
            </div>

            <div>
                <label for="aportes_descontados" class="block text-sm font-semibold text-gray-700 mb-1">
                    Valor de aportes calculado
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm font-bold pointer-events-none">$</span>
                    <input type="number" id="aportes_descontados" name="aportes_descontados"
                           value="{{ old('aportes_descontados', $ingreso->aportes_descontados_cop) }}"
                           step="0.01" min="0" readonly
                           class="w-full rounded border-gray-300 pl-7 text-sm bg-gray-100 text-gray-700 font-semibold focus:ring-0 cursor-not-allowed">
                </div>
                <p class="mt-1 text-xs text-gray-400">Calculado automáticamente sobre el monto bruto.</p>
                @error('aportes_descontados') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Neto calculado --}}
        <div class="rounded-lg bg-indigo-50 border border-indigo-200 p-4">
            <p class="text-xs text-indigo-600 font-bold uppercase tracking-wider">Monto neto estimado</p>
            <p class="text-2xl font-extrabold text-indigo-900 mt-1" id="neto-preview">
                $ {{ number_format($ingreso->monto_neto_cop, 2, ',', '.') }}
            </p>
            <p class="text-xs text-gray-500 mt-1">Fórmula: Monto bruto − Retención calculada − Aportes calculados</p>
        </div>

        {{-- Clasificación tributaria --}}
        <div>
            <label for="clasificacion_tributaria" class="block text-sm font-medium text-gray-700 mb-1">
                Clasificación tributaria
            </label>
            <select id="clasificacion_tributaria" name="clasificacion_tributaria"
                    class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">— Heredar de la fuente —</option>
                @foreach($clasificaciones as $c)
                    <option value="{{ $c }}" @selected(old('clasificacion_tributaria', $ingreso->clasificacion_tributaria) == $c)>{{ $c }}</option>
                @endforeach
            </select>
            @error('clasificacion_tributaria') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Descripción --}}
        <div>
            <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="2" maxlength="255"
                      class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('descripcion', $ingreso->descripcion) }}</textarea>
            @error('descripcion') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Guardar cambios
            </button>
            <a href="{{ route('ingresos.index') }}" class="rounded border border-gray-300 px-6 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Cancelar
            </a>
        </div>
    </form>
</div>

<script>
function calcularPorcentajes() {
    window.scfpPreviewIncome?.();
}

function setPorcentajeRetencion(valor) {
    document.getElementById('porcentaje_retencion').value = valor;
    calcularPorcentajes();
}

function setPorcentajeAportes(valor) {
    document.getElementById('porcentaje_aportes').value = valor;
    calcularPorcentajes();
}

function toggleTasa(v) {
    document.getElementById('bloque-tasa').classList.toggle('hidden', v.toUpperCase() === 'COP' || v === '');
}
</script>
@endsection
