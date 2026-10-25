@extends('layouts.app')
@section('title', 'Ingresos')
@section('content')
<header class="income-page-header"><div><h1>Ingresos</h1><p>Movimientos y totales del conjunto filtrado, en pesos colombianos.</p></div><x-ui.button :href="route('ingresos.create')"><x-icon name="ui-mas" :size="18" />Registrar ingreso</x-ui.button></header>
@include('ingresos.partials.authoritative-errors')
<x-ui.card>
<details open><summary>Filtrar ingresos</summary>
<form method="GET" action="{{ route('ingresos.index') }}" class="income-filters" data-income-filters novalidate>
    <x-ui.date-input name="fecha_desde" label="Desde" :required="false" :allow-future="true" :value="request('fecha_desde', '')" />
    <x-ui.date-input name="fecha_hasta" label="Hasta" :required="false" :allow-future="true" :value="request('fecha_hasta', '')" />
    <x-ui.field name="fuente_id" id="filter-source" label="Fuente de ingreso"><select id="filter-source" name="fuente_id" aria-describedby="filter-source-error"><option value="">Todas las fuentes</option>@foreach($fuentes as $source)<option value="{{ $source->id }}" @selected(request('fuente_id') === $source->id)>{{ $source->nombre }}</option>@endforeach</select></x-ui.field>
    <x-ui.field name="tipo_fuente_id" id="filter-type" label="Tipo de fuente"><select id="filter-type" name="tipo_fuente_id" aria-describedby="filter-type-error"><option value="">Todos los tipos</option>@foreach($tiposFuente as $type)<option value="{{ $type->id }}" @selected(request('tipo_fuente_id') === $type->id)>{{ $type->nombre }}</option>@endforeach</select></x-ui.field>
    <x-income.classification :options="$clasificaciones" :value="request('clasificacion_tributaria', '')" id="filter-classification" placeholder="Todas las clasificaciones" />
    <x-ui.field name="origen" id="filter-origin" label="Origen"><select id="filter-origin" name="origen" aria-describedby="filter-origin-error"><option value="">Todos los orígenes</option><option value="MANUAL" @selected(request('origen') === 'MANUAL')>Manual</option><option value="RECURRENTE" @selected(request('origen') === 'RECURRENTE')>Recurrente</option><option value="IMPORTADO" @selected(request('origen') === 'IMPORTADO')>Importado</option></select></x-ui.field>
    <x-ui.money-input name="monto_min" label="Monto mínimo" :required="false" :allow-zero="true" :value="request('monto_min', '')" />
    <x-ui.money-input name="monto_max" label="Monto máximo" :required="false" :allow-zero="true" :value="request('monto_max', '')" />
    <div class="ui-inline"><x-ui.button type="submit">Aplicar filtros</x-ui.button><x-ui.button :href="route('ingresos.index')" variant="secondary">Limpiar filtros</x-ui.button></div>
</form>
</details>
</x-ui.card>
@if($pendientes->cantidad > 0)<p class="ui-help" role="status">{{ $pendientes->cantidad }} ingresos pendientes de confirmar. No se incluyen en los totales. <a href="{{ route('ingresos.recurrentes.index') }}">Ver pendientes</a></p>@endif
<x-income.table :incomes="$ingresos" :totals="$totales" :paginator="$ingresos" />
@endsection
