@extends('layouts.app')
@section('title', 'No fue posible completar la operación')
@section('content')<h1 class="sr-only">No fue posible completar la operación</h1><x-ui.empty-state illustration="error_generico" title="No fue posible completar la operación." ><x-ui.button :href="url('/')" variant="primary">Volver al inicio</x-ui.button></x-ui.empty-state>@isset($errorId)<p class="ui-help">Referencia para soporte: {{ $errorId }}</p>@endisset @endsection
