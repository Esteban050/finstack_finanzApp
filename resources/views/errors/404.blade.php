@extends('layouts.app')
@section('title', 'Recurso no encontrado')
@section('content')<h1 class="sr-only">Recurso no encontrado</h1><x-ui.empty-state illustration="recurso_no_encontrado" title="Recurso no encontrado" ><x-ui.button :href="url('/')" variant="primary">Volver al inicio</x-ui.button></x-ui.empty-state>@endsection
