@extends('layouts.app')
@section('title', 'No fue posible completar la operación')
@section('content')<x-ui.empty-state illustration="error_generico" title="No fue posible completar la operación." />@isset($errorId)<p class="ui-help">Referencia para soporte: {{ $errorId }}</p>@endisset @endsection
