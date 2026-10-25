{{-- RF-028 — Gastos recurrentes --}}
@extends('layouts.app')

@section('title', 'Gastos recurrentes')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6 space-y-8">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Gastos recurrentes</h1>
        <button onclick="document.getElementById('modal-crear-rec').classList.remove('hidden')"
                class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700">
            + Nueva recurrencia
        </button>
    </div>

    @if(session('status'))
        <div class="rounded-md bg-green-50 border border-green-300 px-4 py-3 text-green-800 text-sm">{{ session('status') }}</div>
    @endif

    {{-- Ocurrencias pendientes (monto estimado) — RN-028.2 / CA-028.2 --}}
    @if($pendientes->isNotEmpty())
        <section class="space-y-3">
            <h2 class="text-base font-semibold text-amber-700">⏳ Gastos pendientes de confirmar monto ({{ $pendientes->count() }})</h2>
            @foreach($pendientes as $p)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 flex items-center justify-between">
                    <div class="text-sm">
                        <p class="font-semibold">{{ $p->categoria?->nombre ?? '—' }} / {{ $p->subcategoria?->nombre ?? '—' }}</p>
                        <p class="text-gray-600">{{ \Carbon\Carbon::parse($p->fecha)->format('d/m/Y') }}
                           · $ {{ number_format($p->monto_cop, 2, ',', '.') }} COP (estimado)</p>
                    </div>
                    <div class="flex gap-2">
                        {{-- Confirmar con monto real — RN-028.2 --}}
                        <form method="POST" action="{{ route('gastos.confirmar', $p->id) }}" class="flex gap-2 items-center">
                            @csrf @method('PATCH')
                            <input type="number" name="monto_original" placeholder="Monto real"
                                   step="0.01" min="0.01"
                                   class="rounded border-gray-300 text-sm w-32 focus:ring-indigo-500">
                            <button type="submit" class="rounded bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700">
                                Confirmar
                            </button>
                        </form>
                        <form method="POST" action="{{ route('gastos.destroy', $p->id) }}"
                              data-confirm-delete>
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">
                                Descartar
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    {{-- Plantillas --}}
    <section class="space-y-3">
        <h2 class="text-base font-semibold text-gray-800">Plantillas configuradas</h2>

        @forelse($plantillas as $pl)
            @php $datos = $pl->datos_plantilla ?? []; @endphp
            <div class="rounded-lg border {{ $pl->activa ? 'border-gray-200 bg-white' : 'border-gray-100 bg-gray-50 opacity-70' }} shadow-sm p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="text-sm space-y-0.5">
                    <p class="font-semibold text-gray-900">
                        {{ $datos['descripcion'] ?? 'Sin descripción' }}
                        <span class="ml-2 text-xs font-normal text-gray-500">· {{ $pl->periodicidad }}</span>
                        @if($pl->es_monto_estimado)
                            <span class="ml-1 rounded-full bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5">Monto estimado</span>
                        @endif
                    </p>
                    <p class="text-gray-600">
                        $ {{ number_format($datos['monto_original'] ?? 0, 2, ',', '.') }} {{ $datos['moneda'] ?? 'COP' }}
                        · Inicio: {{ $pl->fecha_inicio->format('d/m/Y') }}
                        @if($pl->fecha_fin) · Fin: {{ $pl->fecha_fin->format('d/m/Y') }} @endif
                    </p>
                    <p class="text-gray-400 text-xs">
                        Generadas: {{ $pl->ocurrencias_generadas }}
                        · Próxima: {{ $pl->proxima_ejecucion?->format('d/m/Y') ?? '—' }}
                        · Modo: {{ $pl->modo_confirmacion === 'AUTOMATICO' ? 'Automático' : 'Requiere confirmación' }}
                    </p>
                </div>
                <div class="flex gap-2 shrink-0">
                    {{-- Pausar/Reanudar — CA-022.2 aplicado a gastos --}}
                    <form method="POST" action="{{ route('gastos.recurrentes.toggle', $pl->id) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="rounded border px-3 py-1.5 text-xs font-semibold
                            {{ $pl->activa ? 'border-yellow-300 text-yellow-700 hover:bg-yellow-50' : 'border-green-300 text-green-700 hover:bg-green-50' }}">
                            {{ $pl->activa ? 'Pausar' : 'Reanudar' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('gastos.recurrentes.destroy', $pl->id) }}"
                          data-confirm-delete>
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">
                            Eliminar
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-gray-300 py-12 text-center text-gray-500 text-sm">
                No tienes gastos recurrentes configurados.
            </div>
        @endforelse
    </section>
</div>

{{-- Modal crear gasto recurrente — RN-028.1 / RN-028.2 --}}
<div id="modal-crear-rec" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs p-3 sm:p-4 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg my-auto max-h-[88vh] flex flex-col border border-gray-200">
        {{-- Header fijo --}}
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Nueva recurrencia de gasto</h2>
                <p class="text-xs text-gray-500">Configura gastos fijos, suscripciones o servicios periódicos</p>
            </div>
            <button type="button" onclick="cerrarModalGastoRecurrente()"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        {{-- Formulario con scroll en campos y footer fijo --}}
        <form method="POST" action="{{ route('gastos.recurrentes.store') }}" class="flex flex-col flex-1 min-h-0">
            @csrf

            {{-- Cuerpo con scroll interno --}}
            <div class="px-6 py-4 overflow-y-auto flex-1 space-y-4 text-sm" style="max-height: calc(88vh - 130px);">

                @if($errors->any())
                    <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-xs text-red-700 space-y-1">
                        <p class="font-bold">Por favor corrige los siguientes puntos:</p>
                        @foreach($errors->all() as $e) <p>• {{ $e }}</p> @endforeach
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Periodicidad *</label>
                        <select name="periodicidad" required class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach($periodicidades as $p)
                                <option value="{{ $p }}" @selected(old('periodicidad') == $p)>{{ ucfirst(strtolower($p)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Modo confirmación *</label>
                        <select name="modo_confirmacion" required class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach($modosConfirmacion as $m)
                                <option value="{{ $m }}" @selected(old('modo_confirmacion') == $m)>
                                    {{ $m === 'AUTOMATICO' ? 'Automático' : 'Requiere confirmación' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Fecha inicio *</label>
                    <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', date('Y-m-d')) }}" required
                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                {{-- Selector mutuamente excluyente de finalización / tope --}}
                <div class="p-3.5 bg-gray-50 rounded-lg border border-gray-200 space-y-3">
                    <div>
                        <span class="block text-xs font-bold text-gray-800 uppercase tracking-wider">Finalización de la recurrencia</span>
                        <p class="text-xs text-gray-500">Elige solo una forma de finalizar o déjalo indefinido.</p>
                    </div>

                    @php
                        $tipoTopeActualGasto = 'indefinido';
                        if (old('fecha_fin')) {
                            $tipoTopeActualGasto = 'fecha';
                        } elseif (old('max_ocurrencias')) {
                            $tipoTopeActualGasto = 'ocurrencias';
                        }
                    @endphp

                    <div class="grid grid-cols-3 gap-2">
                        <label class="flex flex-col items-center justify-center p-2 rounded-lg border text-center cursor-pointer transition text-xs font-medium bg-white hover:bg-gray-100" id="label-tope-gasto-indefinido">
                            <input type="radio" name="tipo_tope_gasto" value="indefinido" class="sr-only" onchange="cambiarTipoTopeGasto('indefinido')" {{ $tipoTopeActualGasto === 'indefinido' ? 'checked' : '' }}>
                            <span>Indefinido</span>
                            <span class="text-[10px] text-gray-400 mt-0.5">Sin límite</span>
                        </label>

                        <label class="flex flex-col items-center justify-center p-2 rounded-lg border text-center cursor-pointer transition text-xs font-medium bg-white hover:bg-gray-100" id="label-tope-gasto-fecha">
                            <input type="radio" name="tipo_tope_gasto" value="fecha" class="sr-only" onchange="cambiarTipoTopeGasto('fecha')" {{ $tipoTopeActualGasto === 'fecha' ? 'checked' : '' }}>
                            <span>Por fecha</span>
                            <span class="text-[10px] text-gray-400 mt-0.5">Fecha fin</span>
                        </label>

                        <label class="flex flex-col items-center justify-center p-2 rounded-lg border text-center cursor-pointer transition text-xs font-medium bg-white hover:bg-gray-100" id="label-tope-gasto-ocurrencias">
                            <input type="radio" name="tipo_tope_gasto" value="ocurrencias" class="sr-only" onchange="cambiarTipoTopeGasto('ocurrencias')" {{ $tipoTopeActualGasto === 'ocurrencias' ? 'checked' : '' }}>
                            <span>Por ocurrencias</span>
                            <span class="text-[10px] text-gray-400 mt-0.5">N° de veces</span>
                        </label>
                    </div>

                    {{-- Campo Fecha fin --}}
                    <div id="contenedor-gasto-fecha-fin" class="{{ $tipoTopeActualGasto === 'fecha' ? '' : 'hidden' }} pt-1">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Fecha fin</label>
                        <input type="date" id="input_gasto_fecha_fin" name="fecha_fin" value="{{ old('fecha_fin') }}"
                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="text-[11px] text-gray-500 mt-1">La recurrencia se detendrá después de esta fecha.</p>
                    </div>

                    {{-- Campo Máximo ocurrencias --}}
                    <div id="contenedor-gasto-max-ocurrencias" class="{{ $tipoTopeActualGasto === 'ocurrencias' ? '' : 'hidden' }} pt-1">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Máximo de ocurrencias (1–360)</label>
                        <input type="number" id="input_gasto_max_ocurrencias" name="max_ocurrencias" value="{{ old('max_ocurrencias') }}" min="1" max="360"
                               placeholder="Ej. 12 meses"
                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="text-[11px] text-gray-500 mt-1">Se generará exactamente este número de gastos y luego se detendrá.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Categoría *</label>
                        <select name="categoria_id" required class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Seleccione…</option>
                            @foreach($categorias->whereNull('categoria_padre_id') as $cat)
                                <option value="{{ $cat->id }}" @selected(old('categoria_id') == $cat->id)>{{ $cat->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Subcategoría <span class="text-gray-400 font-normal">(Opcional)</span></label>
                        <select name="subcategoria_id" class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">— Sin subcategoría (Opcional) —</option>
                            @foreach($categorias->whereNotNull('categoria_padre_id') as $sub)
                                <option value="{{ $sub->id }}" @selected(old('subcategoria_id') == $sub->id)>{{ $sub->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Medio de pago *</label>
                    <select name="medio_pago" required class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Seleccione…</option>
                        @foreach(($mediosPago ?? $dimensiones['medios_pago'] ?? \App\Models\Gasto::MEDIOS_PAGO) as $m)
                            <option value="{{ $m }}" @selected(old('medio_pago') === $m)>{{ ucwords(strtolower(str_replace('_', ' ', $m))) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Moneda *</label>
                        <input type="text" name="moneda" value="{{ old('moneda', 'COP') }}" maxlength="3" required
                               class="w-full rounded-lg border-gray-300 text-sm uppercase focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Monto *</label>
                        <input type="number" name="monto_original" value="{{ old('monto_original') }}" step="0.01" min="0.01" required
                               placeholder="Ej. 150000"
                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                {{-- RN-028.2: monto estimado --}}
                <div class="flex items-center gap-2 p-3 bg-amber-50 rounded-lg border border-amber-200">
                    <input type="checkbox" id="es_monto_estimado" name="es_monto_estimado" value="1"
                           @checked(old('es_monto_estimado'))
                           class="rounded text-indigo-600 focus:ring-indigo-500">
                    <label for="es_monto_estimado" class="text-xs text-amber-900">
                        Monto variable (estimado) — la ocurrencia quedará en estado <strong>Pendiente</strong> para confirmar el valor real de la factura.
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Descripción</label>
                    <input type="text" name="descripcion" value="{{ old('descripcion') }}" maxlength="255"
                           placeholder="Ej. Suscripción internet o gimnasio"
                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            {{-- Footer fijo con botones siempre visibles --}}
            <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 rounded-b-xl flex items-center justify-end gap-3 shrink-0">
                <button type="button" onclick="cerrarModalGastoRecurrente()"
                        class="px-4 py-2 text-xs font-semibold rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="submit"
                        class="px-5 py-2 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm">
                    Crear recurrencia
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function cerrarModalGastoRecurrente() {
        document.getElementById('modal-crear-rec').classList.add('hidden');
    }

    function cambiarTipoTopeGasto(tipo) {
        const contFecha = document.getElementById('contenedor-gasto-fecha-fin');
        const contOcurr = document.getElementById('contenedor-gasto-max-ocurrencias');
        const inputFecha = document.getElementById('input_gasto_fecha_fin');
        const inputOcurr = document.getElementById('input_gasto_max_ocurrencias');

        const labelIndef = document.getElementById('label-tope-gasto-indefinido');
        const labelFecha = document.getElementById('label-tope-gasto-fecha');
        const labelOcurr = document.getElementById('label-tope-gasto-ocurrencias');

        [labelIndef, labelFecha, labelOcurr].forEach(l => {
            l.classList.remove('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
            l.classList.add('border-gray-200', 'bg-white', 'text-gray-700');
        });

        if (tipo === 'fecha') {
            contFecha.classList.remove('hidden');
            contOcurr.classList.add('hidden');
            inputOcurr.value = '';
            labelFecha.classList.add('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
            labelFecha.classList.remove('border-gray-200', 'bg-white', 'text-gray-700');
        } else if (tipo === 'ocurrencias') {
            contOcurr.classList.remove('hidden');
            contFecha.classList.add('hidden');
            inputFecha.value = '';
            labelOcurr.classList.add('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
            labelOcurr.classList.remove('border-gray-200', 'bg-white', 'text-gray-700');
        } else {
            contFecha.classList.add('hidden');
            contOcurr.classList.add('hidden');
            inputFecha.value = '';
            inputOcurr.value = '';
            labelIndef.classList.add('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
            labelIndef.classList.remove('border-gray-200', 'bg-white', 'text-gray-700');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const checkedRadio = document.querySelector('input[name="tipo_tope_gasto"]:checked');
        if (checkedRadio) {
            cambiarTipoTopeGasto(checkedRadio.value);
        } else {
            cambiarTipoTopeGasto('indefinido');
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModalGastoRecurrente();
        }
    });
</script>

@if($errors->any())
<script>
    document.getElementById('modal-crear-rec').classList.remove('hidden');
</script>
@endif
@endsection
