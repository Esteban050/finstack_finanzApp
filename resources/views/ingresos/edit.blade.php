@extends('layouts.app')
@section('title', 'Editar ingreso')
@section('content')
<header class="income-page-header"><div><h1>Editar ingreso</h1><p>El servidor actualizará los indicadores del periodo afectado.</p></div></header>
@include('ingresos.partials.authoritative-errors')
@php
    // RN-019.3: un ingreso en moneda extranjera se edita en su moneda, con su
    // monto original y la tasa que se usó; la retención y los aportes se
    // devuelven a esa moneda para que el formulario no los reconvierta.
    $moneda = strtoupper($ingreso->moneda ?? 'COP');
    $tasa = $moneda !== 'COP' && (float) ($ingreso->tasa_cambio_usada ?? 0) > 0 ? (string) $ingreso->tasa_cambio_usada : null;
    $enMoneda = fn ($cop) => $tasa ? bcdiv((string) $cop, $tasa, 2) : (string) $cop;
    $record = ['fuente_id' => $ingreso->fuente_id, 'cuenta_id' => $ingreso->cuenta_id, 'fecha_recepcion' => \Carbon\Carbon::parse($ingreso->fecha_recepcion)->toDateString(), 'moneda' => $tasa ? $moneda : 'COP', 'tasa_cambio_usada' => $tasa ?? '', 'monto_original' => $tasa ? (string) ($ingreso->monto_original ?? $ingreso->monto_bruto_cop) : (string) $ingreso->monto_bruto_cop, 'retencion_fuente' => $enMoneda($ingreso->retencion_fuente_cop), 'aportes_descontados' => $enMoneda($ingreso->aportes_descontados_cop), 'clasificacion_tributaria' => $ingreso->clasificacion_tributaria, 'descripcion' => $ingreso->descripcion];
@endphp
<form method="POST" action="{{ route('ingresos.update', $ingreso->id) }}" data-income-form data-return-url="{{ route('ingresos.index') }}" novalidate>
    @csrf @method('PUT')
    <x-income.form :sources="$fuentes" :classifications="$clasificaciones" :record="$record" :cuentas="$cuentas" />
    <x-income.form-status />
    <div class="income-actions"><x-ui.button :href="route('ingresos.index')" variant="secondary">Cancelar</x-ui.button><x-ui.button type="submit">Guardar cambios</x-ui.button></div>
</form>
@endsection
