@extends('layouts.app')
@section('title', 'SCFP')
@section('content')
<x-ui.card title="Sistema de Control Financiero Personal">
    <div class="ui-inline">
        @auth
            <x-ui.button :href="route('dashboard')">Resumen</x-ui.button>
        @else
            <x-ui.button :href="route('login')">Iniciar sesión</x-ui.button>
            <x-ui.button :href="route('register')" variant="secondary">Crear cuenta</x-ui.button>
        @endauth
    </div>
</x-ui.card>
@endsection
