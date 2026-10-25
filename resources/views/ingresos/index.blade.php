{{-- RF-024 — Consulta y filtrado de ingresos --}}
@extends('layouts.app')

@section('title', 'Mis ingresos')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Mis ingresos</h1>
        <a href="{{ route('ingresos.create') }}"
           class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700">
            + Registrar ingreso
        </a>
    </div>

    {{-- Mensajes flash --}}
    @if(session('status'))
        <div class="rounded-md bg-green-50 border border-green-300 px-4 py-3 text-green-800 text-sm">
            {{ session('status') }}
        </div>
    @endif

    {{-- Filtros — RN-024.1 --}}
    <details open class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <summary class="cursor-pointer px-4 py-3 font-semibold text-gray-700 select-none">
            🔍 Filtros
        </summary>
        <form method="GET" action="{{ route('ingresos.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 px-4 pb-4">
            {{-- Rango de fechas --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Desde</label>
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                       class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Hasta</label>
                <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                       class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            {{-- Fuente --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Fuente</label>
                <select name="fuente_id" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                    <option value="">Todas</option>
                    @foreach($fuentes as $f)
                        <option value="{{ $f->id }}" @selected(request('fuente_id') == $f->id)>{{ $f->nombre }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Clasificación tributaria --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Clasificación tributaria</label>
                <select name="clasificacion_tributaria" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                    <option value="">Todas</option>
                    @foreach($clasificaciones as $c)
                        <option value="{{ $c }}" @selected(request('clasificacion_tributaria') == $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Origen --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Origen</label>
                <select name="origen" class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="MANUAL" @selected(request('origen') === 'MANUAL')>Manual</option>
                    <option value="RECURRENTE" @selected(request('origen') === 'RECURRENTE')>Recurrente</option>
                    <option value="IMPORTADO" @selected(request('origen') === 'IMPORTADO')>Importado</option>
                </select>
            </div>

            {{-- Moneda --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Moneda</label>
                <input type="text" name="moneda" value="{{ request('moneda') }}" placeholder="COP"
                       maxlength="3"
                       class="w-full rounded border-gray-300 text-sm focus:ring-indigo-500 uppercase">
            </div>

            {{-- Rango de montos --}}
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
                <button type="submit"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Aplicar filtros
                </button>
                <a href="{{ route('ingresos.index') }}"
                   class="rounded border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Limpiar
                </a>
            </div>
        </form>
    </details>

    {{-- Totales — RN-024.3 --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="rounded-lg bg-white border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Registros</p>
            <p class="text-2xl font-bold text-gray-900">{{ number_format($totales->cantidad) }}</p>
        </div>
        <div class="rounded-lg bg-white border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Bruto (COP)</p>
            <p class="text-xl font-bold text-green-700">$ {{ number_format($totales->bruto, 2, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Retención</p>
            <p class="text-xl font-bold text-yellow-600">$ {{ number_format($totales->retencion, 2, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white border border-gray-200 shadow-sm p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Neto</p>
            <p class="text-xl font-bold text-indigo-700">$ {{ number_format($totales->neto, 2, ',', '.') }}</p>
        </div>
    </div>

    {{-- Pendientes por confirmar — RN-022.3 --}}
    @if($pendientes->cantidad > 0)
        <div class="rounded-md bg-amber-50 border border-amber-300 px-4 py-3 text-amber-800 text-sm flex justify-between items-center">
            <span>
                ⏳ Tienes <strong>{{ $pendientes->cantidad }}</strong> ingreso(s) recurrente(s) pendiente(s) de confirmar
                ($ {{ number_format($pendientes->bruto, 2, ',', '.') }} COP en bruto).
            </span>
            <a href="{{ route('ingresos.recurrentes.index') }}" class="font-semibold underline ml-4">Ver pendientes</a>
        </div>
    @endif

    {{-- Tabla de ingresos — RN-024.2 --}}
    @if($ingresos->isEmpty())
        {{-- CA-024.2 --}}
        <x-ui.empty-state illustration="empty_ingresos" title="No hay ingresos para los filtros seleccionados." />
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm bg-white">
            <table class="min-w-full text-sm divide-y divide-gray-100">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Fecha</th>
                        <th class="px-4 py-3 text-left">Fuente</th>
                        <th class="px-4 py-3 text-left">Clasificación</th>
                        <th class="px-4 py-3 text-right">Bruto</th>
                        <th class="px-4 py-3 text-right">Retención</th>
                        <th class="px-4 py-3 text-right">Aportes</th>
                        <th class="px-4 py-3 text-right">Neto</th>
                        <th class="px-4 py-3 text-left">Moneda</th>
                        <th class="px-4 py-3 text-left">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($ingresos as $ingreso)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($ingreso->fecha_recepcion)->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $ingreso->fuente?->nombre ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs">{{ $ingreso->clasificacion_tributaria }}</td>
                            <td class="px-4 py-3 text-right font-mono">$ {{ number_format($ingreso->monto_bruto_cop, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-yellow-700">$ {{ number_format($ingreso->retencion_fuente_cop, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-orange-700">$ {{ number_format($ingreso->aportes_descontados_cop, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-indigo-700">$ {{ number_format($ingreso->monto_neto_cop, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-xs">{{ $ingreso->moneda }}</td>
                            <td class="px-4 py-3">
                                @if($ingreso->estado === 'PENDIENTE')
                                    <span class="inline-block rounded-full bg-amber-100 text-amber-800 text-xs px-2 py-0.5">Pendiente</span>
                                @else
                                    <span class="inline-block rounded-full bg-green-100 text-green-800 text-xs px-2 py-0.5">Confirmado</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 flex gap-2 justify-end">
                                @if($ingreso->estado === 'PENDIENTE')
                                    <form method="POST" action="{{ route('ingresos.confirmar', $ingreso->id) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-xs text-green-600 hover:underline">Confirmar</button>
                                    </form>
                                @endif
                                <a href="{{ route('ingresos.edit', $ingreso->id) }}" class="text-xs text-indigo-600 hover:underline">Editar</a>
                                {{-- CA-023.2: diálogo de confirmación antes de eliminar --}}
                                <form method="POST" action="{{ route('ingresos.destroy', $ingreso->id) }}"
                                      data-confirm-delete>
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div class="flex justify-between items-center text-sm text-gray-500">
            <span>Mostrando {{ $ingresos->firstItem() }}–{{ $ingresos->lastItem() }} de {{ $ingresos->total() }} registros</span>
            <x-ui.pagination :paginator="$ingresos" />
        </div>
    @endif

</div>
@endsection
