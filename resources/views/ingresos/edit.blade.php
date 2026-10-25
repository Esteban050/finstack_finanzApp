@extends('layouts.app')
@section('title', 'Editar ingreso')
@section('content')
<header class="income-page-header"><div><h1>Editar ingreso</h1><p>El servidor actualizará los indicadores del periodo afectado.</p></div></header>
@include('ingresos.partials.authoritative-errors')
@php
    $record = ['fuente_id' => $ingreso->fuente_id, 'fecha_recepcion' => \Carbon\Carbon::parse($ingreso->fecha_recepcion)->toDateString(), 'monto_original' => (string) $ingreso->monto_bruto_cop, 'retencion_fuente' => (string) $ingreso->retencion_fuente_cop, 'aportes_descontados' => (string) $ingreso->aportes_descontados_cop, 'clasificacion_tributaria' => $ingreso->clasificacion_tributaria, 'descripcion' => $ingreso->descripcion];
@endphp
<form method="POST" action="{{ route('ingresos.update', $ingreso->id) }}" data-income-form data-return-url="{{ route('ingresos.index') }}" novalidate>
    @csrf @method('PUT')
    <x-income.form :sources="$fuentes" :classifications="$clasificaciones" :record="$record" />
    <x-income.form-status />
    <div class="income-actions"><x-ui.button :href="route('ingresos.index')" variant="secondary">Cancelar</x-ui.button><x-ui.button type="submit">Guardar cambios</x-ui.button></div>
</form>
@endsection
