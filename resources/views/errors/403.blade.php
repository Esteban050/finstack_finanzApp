@extends('layouts.app')
@section('title', 'Acceso denegado')
@section('content')<h1 class="sr-only">Acceso denegado</h1><x-ui.empty-state illustration="acceso_denegado" title="Acceso denegado" ><x-ui.button :href="url('/')" variant="primary">Volver al inicio</x-ui.button></x-ui.empty-state>@endsection
