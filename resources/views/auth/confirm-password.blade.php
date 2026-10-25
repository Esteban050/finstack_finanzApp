<x-guest-layout>
    <x-slot name="title">Confirmar contraseña</x-slot><h1>Confirmar contraseña</h1>
    @if(\Illuminate\Support\Facades\Route::has('password.confirm'))
        <form method="POST" action="{{ route('password.confirm') }}" data-auth-form="confirm" novalidate>@csrf<x-auth.password autocomplete="current-password" /><x-auth.submit label="Confirmar" /></form>
    @else<p>No hay una solicitud de confirmación disponible.</p><a href="{{ route('login') }}">Volver a iniciar sesión</a>@endif
</x-guest-layout>
