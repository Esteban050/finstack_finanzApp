{{-- RF-020 — Gestión de fuentes de ingreso (MOD-03) --}}
@extends('layouts.app')

@section('title', 'Fuentes de ingreso — SCFP')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6 space-y-6">

    {{-- Encabezado --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900">Fuentes de ingreso</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                    {{ $fuentes->count() }} {{ $fuentes->count() === 1 ? 'fuente' : 'fuentes' }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-1">
                Registra y clasifica tus fuentes de ingreso (salarios, honorarios, arriendos, inversiones) según las cédulas tributarias del art. 330 ET.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('ingresos.create') }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 shadow-sm">
                + Registrar ingreso
            </a>
            <button type="button" onclick="abrirModalCrear()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-md bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Nueva fuente
            </button>
        </div>
    </div>

    {{-- Mensajes de estado y errores --}}
    @if(session('status'))
        <div class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-800">
            <div class="font-semibold mb-1">Por favor corrige los siguientes errores:</div>
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Tabla de Fuentes --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        @if($fuentes->isEmpty())
            <div class="py-14 text-center px-4">
                <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 mx-auto flex items-center justify-center mb-3">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900">Aún no tienes fuentes de ingreso creadas</h3>
                <p class="text-sm text-gray-500 max-w-md mx-auto mt-1 mb-4">
                    Crea fuentes como "Empresa XYZ – Salario", "Apartamento Calle 10 – Arriendo" o "CDT Banco ABC" para asociar tus ingresos.
                </p>
                <button type="button" onclick="abrirModalCrear()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-md bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm">
                    + Crear primera fuente
                </button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs uppercase font-semibold text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-5 py-3">Nombre de la fuente</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Cédula Tributaria (Art. 330 ET)</th>
                            <th class="px-5 py-3">Pagador / Entidad</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($fuentes as $f)
                            @php
                                $nombresCedulas = [
                                    'RENTAS_TRABAJO' => 'Rentas de trabajo',
                                    'RENTAS_CAPITAL' => 'Rentas de capital',
                                    'RENTAS_NO_LABORALES' => 'Rentas no laborales',
                                    'PENSIONES' => 'Pensiones',
                                    'DIVIDENDOS' => 'Dividendos y participaciones',
                                ];
                                $colorCedulas = [
                                    'RENTAS_TRABAJO' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'RENTAS_CAPITAL' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'RENTAS_NO_LABORALES' => 'bg-purple-50 text-purple-700 border-purple-200',
                                    'PENSIONES' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'DIVIDENDOS' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/70 transition {{ !$f->activa ? 'opacity-60 bg-gray-50/40' : '' }}">
                                <td class="px-5 py-3 font-semibold text-gray-900">
                                    {{ $f->nombre }}
                                </td>
                                <td class="px-5 py-3 text-gray-700">
                                    {{ $f->tipoFuente?->nombre ?? '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border {{ $colorCedulas[$f->clasificacion_tributaria] ?? 'bg-gray-100 text-gray-800 border-gray-200' }}">
                                        {{ $nombresCedulas[$f->clasificacion_tributaria] ?? $f->clasificacion_tributaria }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-gray-500">
                                    {{ $f->pagador_entidad ?: '—' }}
                                </td>
                                <td class="px-5 py-3 text-center">
                                    @if($f->activa)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                            Activa
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                            Inactiva
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="inline-flex items-center gap-2 justify-end">
                                        {{-- Botón Editar --}}
                                        <button type="button"
                                                onclick="abrirModalEditar('{{ $f->id }}', '{{ addslashes($f->nombre) }}', '{{ $f->tipo_fuente_id }}', '{{ $f->clasificacion_tributaria }}', '{{ addslashes($f->pagador_entidad ?? '') }}')"
                                                class="text-xs font-medium text-indigo-600 hover:text-indigo-900">
                                            Editar
                                        </button>

                                        {{-- Toggle Activa/Inactiva (RN-020.3) --}}
                                        <form method="POST" action="{{ route('ingresos.fuentes.toggle', $f->id) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-xs font-medium {{ $f->activa ? 'text-amber-600 hover:text-amber-800' : 'text-green-600 hover:text-green-800' }}">
                                                {{ $f->activa ? 'Inactivar' : 'Activar' }}
                                            </button>
                                        </form>

                                        {{-- Eliminar (RN-020.3 / CA-020.2) --}}
                                        <form method="POST" action="{{ route('ingresos.fuentes.destroy', $f->id) }}"
                                              data-confirm-delete
                                              class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-900">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

{{-- ═════ MODAL: CREAR FUENTE DE INGRESO (RN-020.1, RN-020.2) ═════ --}}
<div id="modal-crear" class="fixed inset-0 z-50 hidden overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full p-6 border border-gray-100">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-4">
            <h3 class="text-lg font-bold text-gray-900">Nueva fuente de ingreso</h3>
            <button type="button" onclick="cerrarModalCrear()" class="text-gray-400 hover:text-gray-600 text-lg font-bold">✕</button>
        </div>

        <form method="POST" action="{{ route('ingresos.fuentes.store') }}" class="space-y-4">
            @csrf

            {{-- Nombre (3-60 chars) --}}
            <div>
                <label for="crear-nombre" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Nombre de la fuente <span class="text-red-500">*</span>
                </label>
                <input type="text" id="crear-nombre" name="nombre" required minlength="3" maxlength="60"
                       placeholder="Ej. Empresa XYZ – Salario, Apartamento Calle 10"
                       value="{{ old('nombre') }}"
                       class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                <p class="text-xs text-gray-400 mt-1">Entre 3 y 60 caracteres. Debe ser único para tu cuenta.</p>
            </div>

            {{-- Tipo de fuente (catálogo RN-015.2) --}}
            <div>
                <label for="crear-tipo" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Tipo de fuente <span class="text-red-500">*</span>
                </label>
                <select id="crear-tipo" name="tipo_fuente_id" required onchange="actualizarSugerenciaCrear()"
                        class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- Selecciona el tipo de fuente --</option>
                    @foreach($tiposFuente as $tipo)
                        <option value="{{ $tipo->id }}" data-nombre="{{ $tipo->nombre }}" {{ old('tipo_fuente_id') == $tipo->id ? 'selected' : '' }}>
                            {{ $tipo->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Clasificación tributaria (cédula art. 330 ET) con sugerencia automática (RN-020.2) --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label for="crear-clasificacion" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">
                        Clasificación tributaria (Cédula) <span class="text-red-500">*</span>
                    </label>
                    <span id="crear-sugerencia-badge" class="hidden text-xs text-indigo-600 font-medium">
                        ✓ Sugerida por tipo
                    </span>
                </div>
                <select id="crear-clasificacion" name="clasificacion_tributaria" required
                        class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- Selecciona cédula tributaria --</option>
                    <option value="RENTAS_TRABAJO" {{ old('clasificacion_tributaria') == 'RENTAS_TRABAJO' ? 'selected' : '' }}>Rentas de trabajo</option>
                    <option value="RENTAS_CAPITAL" {{ old('clasificacion_tributaria') == 'RENTAS_CAPITAL' ? 'selected' : '' }}>Rentas de capital</option>
                    <option value="RENTAS_NO_LABORALES" {{ old('clasificacion_tributaria') == 'RENTAS_NO_LABORALES' ? 'selected' : '' }}>Rentas no laborales</option>
                    <option value="PENSIONES" {{ old('clasificacion_tributaria') == 'PENSIONES' ? 'selected' : '' }}>Pensiones</option>
                    <option value="DIVIDENDOS" {{ old('clasificacion_tributaria') == 'DIVIDENDOS' ? 'selected' : '' }}>Dividendos y participaciones</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">
                    Según el art. 330 del Estatuto Tributario para tu declaración de renta sugerida.
                </p>
            </div>

            {{-- Pagador o Entidad (opcional, máx 80) --}}
            <div>
                <label for="crear-pagador" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Pagador o entidad <span class="text-gray-400 font-normal">(Opcional)</span>
                </label>
                <input type="text" id="crear-pagador" name="pagador_entidad" maxlength="80"
                       placeholder="Ej. Bancolombia, Inmobiliaria S.A.S."
                       value="{{ old('pagador_entidad') }}"
                       class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                <p class="text-xs text-gray-400 mt-1">Máximo 80 caracteres.</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                <button type="button" onclick="cerrarModalCrear()"
                        class="px-4 py-2 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="submit"
                        class="px-5 py-2 text-xs font-semibold rounded-md bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm">
                    Guardar fuente
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═════ MODAL: EDITAR FUENTE DE INGRESO ═════ --}}
<div id="modal-editar" class="fixed inset-0 z-50 hidden overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full p-6 border border-gray-100">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-4">
            <h3 class="text-lg font-bold text-gray-900">Editar fuente de ingreso</h3>
            <button type="button" onclick="cerrarModalEditar()" class="text-gray-400 hover:text-gray-600 text-lg font-bold">✕</button>
        </div>

        <form id="form-editar" method="POST" action="" class="space-y-4">
            @csrf
            @method('PUT')

            {{-- Nombre (3-60 chars) --}}
            <div>
                <label for="editar-nombre" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Nombre de la fuente <span class="text-red-500">*</span>
                </label>
                <input type="text" id="editar-nombre" name="nombre" required minlength="3" maxlength="60"
                       class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            {{-- Tipo de fuente --}}
            <div>
                <label for="editar-tipo" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Tipo de fuente <span class="text-red-500">*</span>
                </label>
                <select id="editar-tipo" name="tipo_fuente_id" required
                        class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach($tiposFuente as $tipo)
                        <option value="{{ $tipo->id }}" data-nombre="{{ $tipo->nombre }}">
                            {{ $tipo->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Clasificación tributaria --}}
            <div>
                <label for="editar-clasificacion" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Clasificación tributaria (Cédula) <span class="text-red-500">*</span>
                </label>
                <select id="editar-clasificacion" name="clasificacion_tributaria" required
                        class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="RENTAS_TRABAJO">Rentas de trabajo</option>
                    <option value="RENTAS_CAPITAL">Rentas de capital</option>
                    <option value="RENTAS_NO_LABORALES">Rentas no laborales</option>
                    <option value="PENSIONES">Pensiones</option>
                    <option value="DIVIDENDOS">Dividendos y participaciones</option>
                </select>
            </div>

            {{-- Pagador o Entidad --}}
            <div>
                <label for="editar-pagador" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Pagador o entidad <span class="text-gray-400 font-normal">(Opcional)</span>
                </label>
                <input type="text" id="editar-pagador" name="pagador_entidad" maxlength="80"
                       class="w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                <button type="button" onclick="cerrarModalEditar()"
                        class="px-4 py-2 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="submit"
                        class="px-5 py-2 text-xs font-semibold rounded-md bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // RN-020.2: Diccionario de sugerencias según el tipo de fuente
    const sugerencias = @json($sugerencias);

    function actualizarSugerenciaCrear() {
        const selectTipo = document.getElementById('crear-tipo');
        const selectClasif = document.getElementById('crear-clasificacion');
        const badge = document.getElementById('crear-sugerencia-badge');

        const selectedOption = selectTipo.options[selectTipo.selectedIndex];
        const nombreTipo = selectedOption ? selectedOption.getAttribute('data-nombre') : null;

        if (nombreTipo && sugerencias[nombreTipo]) {
            selectClasif.value = sugerencias[nombreTipo];
            badge.classList.remove('hidden');
        } else if (nombreTipo) {
            selectClasif.value = 'RENTAS_NO_LABORALES';
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    function abrirModalCrear() {
        document.getElementById('modal-crear').classList.remove('hidden');
        document.getElementById('crear-nombre').focus();
    }

    function cerrarModalCrear() {
        document.getElementById('modal-crear').classList.add('hidden');
    }

    function abrirModalEditar(id, nombre, tipoId, clasificacion, pagador) {
        const form = document.getElementById('form-editar');
        form.action = "{{ url('ingresos/fuentes') }}/" + id;

        document.getElementById('editar-nombre').value = nombre;
        document.getElementById('editar-tipo').value = tipoId;
        document.getElementById('editar-clasificacion').value = clasificacion;
        document.getElementById('editar-pagador').value = pagador;

        document.getElementById('modal-editar').classList.remove('hidden');
    }

    function cerrarModalEditar() {
        document.getElementById('modal-editar').classList.add('hidden');
    }

    // Cerrar modales con tecla ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            cerrarModalCrear();
            cerrarModalEditar();
        }
    });
</script>
@endsection
