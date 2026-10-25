{{-- RF-022 — Listado de ingresos recurrentes y ocurrencias pendientes --}}
@extends('layouts.app')

@section('title', 'Ingresos recurrentes')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6 space-y-8">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Ingresos recurrentes</h1>
        <button onclick="document.getElementById('modal-crear').classList.remove('hidden')"
                class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700">
            + Nueva recurrencia
        </button>
    </div>

    @if(session('status'))
        <div class="rounded-md bg-green-50 border border-green-300 px-4 py-3 text-green-800 text-sm">{{ session('status') }}</div>
    @endif

    {{-- Ocurrencias PENDIENTES — CA-022.3 / RN-022.3 --}}
    @if($pendientes->isNotEmpty())
        <section class="space-y-3">
            <h2 class="text-base font-semibold text-amber-700">⏳ Pendientes de confirmar ({{ $pendientes->count() }})</h2>
            @foreach($pendientes as $p)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 flex items-center justify-between">
                    <div class="text-sm">
                        <p class="font-semibold">{{ $p->fuente?->nombre ?? '—' }}</p>
                        <p class="text-gray-600">{{ \Carbon\Carbon::parse($p->fecha_recepcion)->format('d/m/Y') }}
                           · $ {{ number_format($p->monto_bruto_cop, 2, ',', '.') }} COP</p>
                    </div>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('ingresos.confirmar', $p->id) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="rounded bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700">
                                Confirmar
                            </button>
                        </form>
                        <form method="POST" action="{{ route('ingresos.destroy', $p->id) }}"
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

    {{-- Plantillas de recurrencia --}}
    <section class="space-y-3">
        <h2 class="text-base font-semibold text-gray-800">Plantillas activas e inactivas</h2>

        @forelse($plantillas as $pl)
            <div class="rounded-lg border {{ $pl->activa ? 'border-gray-200 bg-white' : 'border-gray-100 bg-gray-50 opacity-70' }} shadow-sm p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="text-sm space-y-0.5">
                    <p class="font-semibold text-gray-900">
                        {{ $pl->datos_plantilla['descripcion'] ?? 'Sin descripción' }}
                        <span class="ml-2 text-xs font-normal text-gray-500">· {{ $pl->periodicidad }}</span>
                    </p>
                    <p class="text-gray-600">
                        Inicio: {{ $pl->fecha_inicio->format('d/m/Y') }}
                        @if($pl->fecha_fin) · Fin: {{ $pl->fecha_fin->format('d/m/Y') }} @endif
                        @if($pl->max_ocurrencias) · Máx: {{ $pl->max_ocurrencias }} occ. @endif
                    </p>
                    <p class="text-gray-500">
                        Ocurrencias generadas: {{ $pl->ocurrencias_generadas }}
                        · Modo: {{ $pl->modo_confirmacion === 'AUTOMATICO' ? 'Automático' : 'Requiere confirmación' }}
                        · Próxima: {{ $pl->proxima_ejecucion?->format('d/m/Y') ?? '—' }}
                    </p>
                </div>
                <div class="flex gap-2 shrink-0">
                    {{-- Pausar/Reanudar — CA-022.2 --}}
                    <form method="POST" action="{{ route('ingresos.recurrentes.toggle', $pl->id) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="rounded border px-3 py-1.5 text-xs font-semibold
                            {{ $pl->activa ? 'border-yellow-300 text-yellow-700 hover:bg-yellow-50' : 'border-green-300 text-green-700 hover:bg-green-50' }}">
                            {{ $pl->activa ? 'Pausar' : 'Reanudar' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('ingresos.recurrentes.destroy', $pl->id) }}"
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
                No tienes ingresos recurrentes configurados.
            </div>
        @endforelse
    </section>
</div>

{{-- Modal: crear recurrencia — RN-022.1 --}}
<div id="modal-crear" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs p-3 sm:p-4 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg my-auto max-h-[88vh] flex flex-col border border-gray-200">
        {{-- Header fijo --}}
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Nueva recurrencia de ingreso</h2>
                <p class="text-xs text-gray-500">Configura ingresos automáticos o periódicos</p>
            </div>
            <button type="button" onclick="cerrarModalRecurrencia()"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        {{-- Formulario con scroll en campos y footer fijo --}}
        <form method="POST" action="{{ route('ingresos.recurrentes.store') }}" class="flex flex-col flex-1 min-h-0">
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
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Modo de confirmación *</label>
                        <select name="modo_confirmacion" required class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach($modosConfirmacion as $m)
                                <option value="{{ $m }}" @selected(old('modo_confirmacion') == $m)>
                                    {{ $m === 'AUTOMATICO' ? 'Automático' : 'Requiere confirmación' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Fecha de inicio obligatoria --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Fecha de inicio *</label>
                    <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', date('Y-m-d')) }}" required
                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                {{-- Selector mutuamente excluyente de finalización / tope (RN-022.1) --}}
                <div class="p-3.5 bg-gray-50 rounded-lg border border-gray-200 space-y-3">
                    <div>
                        <span class="block text-xs font-bold text-gray-800 uppercase tracking-wider">Finalización de la recurrencia</span>
                        <p class="text-xs text-gray-500">Elige solo una forma de finalizar o déjalo indefinido.</p>
                    </div>

                    @php
                        $tipoTopeActual = 'indefinido';
                        if (old('fecha_fin')) {
                            $tipoTopeActual = 'fecha';
                        } elseif (old('max_ocurrencias')) {
                            $tipoTopeActual = 'ocurrencias';
                        }
                    @endphp

                    <div class="grid grid-cols-3 gap-2">
                        <label class="flex flex-col items-center justify-center p-2 rounded-lg border text-center cursor-pointer transition text-xs font-medium bg-white hover:bg-gray-100" id="label-tope-indefinido">
                            <input type="radio" name="tipo_tope" value="indefinido" class="sr-only" onchange="cambiarTipoTope('indefinido')" {{ $tipoTopeActual === 'indefinido' ? 'checked' : '' }}>
                            <span>Indefinido</span>
                            <span class="text-[10px] text-gray-400 mt-0.5">Sin límite</span>
                        </label>

                        <label class="flex flex-col items-center justify-center p-2 rounded-lg border text-center cursor-pointer transition text-xs font-medium bg-white hover:bg-gray-100" id="label-tope-fecha">
                            <input type="radio" name="tipo_tope" value="fecha" class="sr-only" onchange="cambiarTipoTope('fecha')" {{ $tipoTopeActual === 'fecha' ? 'checked' : '' }}>
                            <span>Por fecha</span>
                            <span class="text-[10px] text-gray-400 mt-0.5">Fecha de fin</span>
                        </label>

                        <label class="flex flex-col items-center justify-center p-2 rounded-lg border text-center cursor-pointer transition text-xs font-medium bg-white hover:bg-gray-100" id="label-tope-ocurrencias">
                            <input type="radio" name="tipo_tope" value="ocurrencias" class="sr-only" onchange="cambiarTipoTope('ocurrencias')" {{ $tipoTopeActual === 'ocurrencias' ? 'checked' : '' }}>
                            <span>Por ocurrencias</span>
                            <span class="text-[10px] text-gray-400 mt-0.5">N° de veces</span>
                        </label>
                    </div>

                    {{-- Campo Fecha de fin --}}
                    <div id="contenedor-fecha-fin" class="{{ $tipoTopeActual === 'fecha' ? '' : 'hidden' }} pt-1">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Fecha de fin</label>
                        <input type="date" id="input_fecha_fin" name="fecha_fin" value="{{ old('fecha_fin') }}"
                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="text-[11px] text-gray-500 mt-1">La recurrencia se detendrá después de esta fecha.</p>
                    </div>

                    {{-- Campo Máximo de ocurrencias --}}
                    <div id="contenedor-max-ocurrencias" class="{{ $tipoTopeActual === 'ocurrencias' ? '' : 'hidden' }} pt-1">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Máximo de ocurrencias (1–360)</label>
                        <input type="number" id="input_max_ocurrencias" name="max_ocurrencias" value="{{ old('max_ocurrencias') }}" min="1" max="360"
                               placeholder="Ej. 2, 6, 12 veces"
                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="text-[11px] text-gray-500 mt-1">Se generará exactamente este número de ingresos y luego se detendrá.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Fuente de ingreso *</label>
                    <select name="fuente_id" required class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Seleccione fuente…</option>
                        @foreach($fuentes as $f)
                            <option value="{{ $f->id }}" @selected(old('fuente_id') == $f->id)>{{ $f->nombre }} ({{ $f->tipoFuente?->nombre }})</option>
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
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Monto bruto *</label>
                        <input type="number" id="rec_monto_original" name="monto_original" value="{{ old('monto_original') }}" step="0.01" min="0.01" required
                               placeholder="Ej. 2800000"
                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                               oninput="calcularPorcentajesRecurrente()">
                    </div>
                </div>

                {{-- Retención — Porcentaje y Valor calculado (RF-021) --}}
                <div class="grid grid-cols-2 gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">% Retención</label>
                        <div class="relative">
                            <input type="number" id="rec_porcentaje_retencion" name="porcentaje_retencion"
                                   value="{{ old('porcentaje_retencion', 0) }}"
                                   step="0.01" min="0" max="100" placeholder="0"
                                   class="w-full rounded-lg border-gray-300 pr-7 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                   oninput="calcularPorcentajesRecurrente()">
                            <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 text-xs font-bold pointer-events-none">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Valor calculado</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-gray-400 text-xs font-bold pointer-events-none">$</span>
                            <input type="number" id="rec_retencion_fuente" name="retencion_fuente"
                                   value="{{ old('retencion_fuente', 0) }}"
                                   step="0.01" min="0" readonly
                                   class="w-full rounded-lg border-gray-300 pl-6 text-sm bg-gray-100 text-gray-700 font-semibold focus:ring-0 cursor-not-allowed">
                        </div>
                    </div>
                </div>

                {{-- Aportes — Porcentaje y Valor calculado (RF-021) --}}
                <div class="grid grid-cols-2 gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">% Aportes</label>
                        <div class="relative">
                            <input type="number" id="rec_porcentaje_aportes" name="porcentaje_aportes"
                                   value="{{ old('porcentaje_aportes', 0) }}"
                                   step="0.01" min="0" max="100" placeholder="0"
                                   class="w-full rounded-lg border-gray-300 pr-7 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                   oninput="calcularPorcentajesRecurrente()">
                            <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 text-xs font-bold pointer-events-none">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Valor calculado</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-gray-400 text-xs font-bold pointer-events-none">$</span>
                            <input type="number" id="rec_aportes_descontados" name="aportes_descontados"
                                   value="{{ old('aportes_descontados', 0) }}"
                                   step="0.01" min="0" readonly
                                   class="w-full rounded-lg border-gray-300 pl-6 text-sm bg-gray-100 text-gray-700 font-semibold focus:ring-0 cursor-not-allowed">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Descripción (Opcional)</label>
                    <input type="text" name="descripcion" value="{{ old('descripcion') }}" maxlength="255"
                           placeholder="Ej. Pago mensual nómina o arriendo"
                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            {{-- Footer fijo: Botones de acción siempre visibles --}}
            <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 rounded-b-xl flex items-center justify-end gap-3 shrink-0">
                <button type="button" onclick="cerrarModalRecurrencia()"
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
    function cerrarModalRecurrencia() {
        document.getElementById('modal-crear').classList.add('hidden');
    }

    /* Cálculo automático de retención y aportes en recurrencias */
    function calcularPorcentajesRecurrente() {
        window.scfpPreviewIncome?.('rec_');
    }

    function cambiarTipoTope(tipo) {
        const contFecha = document.getElementById('contenedor-fecha-fin');
        const contOcurr = document.getElementById('contenedor-max-ocurrencias');
        const inputFecha = document.getElementById('input_fecha_fin');
        const inputOcurr = document.getElementById('input_max_ocurrencias');

        const labelIndef = document.getElementById('label-tope-indefinido');
        const labelFecha = document.getElementById('label-tope-fecha');
        const labelOcurr = document.getElementById('label-tope-ocurrencias');

        // Reset estilos botones
        [labelIndef, labelFecha, labelOcurr].forEach(l => {
            l.classList.remove('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
            l.classList.add('border-gray-200', 'bg-white', 'text-gray-700');
        });

        if (tipo === 'fecha') {
            contFecha.classList.remove('hidden');
            contOcurr.classList.add('hidden');
            inputOcurr.value = ''; // Limpia ocurrencias para no enviar ambos
            labelFecha.classList.add('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
            labelFecha.classList.remove('border-gray-200', 'bg-white', 'text-gray-700');
        } else if (tipo === 'ocurrencias') {
            contOcurr.classList.remove('hidden');
            contFecha.classList.add('hidden');
            inputFecha.value = ''; // Limpia fecha fin para no enviar ambos
            labelOcurr.classList.add('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
            labelOcurr.classList.remove('border-gray-200', 'bg-white', 'text-gray-700');
        } else {
            // Indefinido
            contFecha.classList.add('hidden');
            contOcurr.classList.add('hidden');
            inputFecha.value = '';
            inputOcurr.value = '';
            labelIndef.classList.add('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
            labelIndef.classList.remove('border-gray-200', 'bg-white', 'text-gray-700');
        }
    }

    // Inicializar estado del tope al cargar
    document.addEventListener('DOMContentLoaded', function() {
        const checkedRadio = document.querySelector('input[name="tipo_tope"]:checked');
        if (checkedRadio) {
            cambiarTipoTope(checkedRadio.value);
        } else {
            cambiarTipoTope('indefinido');
        }
    });

    // Cerrar modal al presionar Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModalRecurrencia();
        }
    });
</script>

@if($errors->any())
<script>
    // Reabrir el modal si hubo errores de validación
    document.getElementById('modal-crear').classList.remove('hidden');
</script>
@endif
@endsection

