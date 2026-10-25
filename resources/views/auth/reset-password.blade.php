<x-guest-layout>
    <x-slot name="title">Restablecer contraseña</x-slot>
    <h1>Restablecer contraseña</h1><p>Elige una nueva contraseña para tu cuenta.</p>
    <x-auth.status />
    <form method="POST" action="{{ route('password.update.custom') }}" data-auth-form="reset" novalidate>
        @csrf<input type="hidden" name="token" value="{{ $token }}">
        <x-auth.password :rules="true" />
        <x-auth.password name="password_confirmation" label="Confirmar contraseña" />
        <x-auth.submit label="Restablecer contraseña" loading="Guardando…" />
    </form>
    <p class="auth-alternative"><a href="{{ route('login') }}">Volver a iniciar sesión</a></p>
</x-guest-layout>
