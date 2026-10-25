<x-guest-layout>
    <x-slot name="title">Recuperar contraseña</x-slot>
    <h1>Recuperar contraseña</h1><p>Ingresa tu correo para solicitar un enlace de restablecimiento.</p>
    <x-auth.status />
    <form method="POST" action="{{ route('password.email') }}" data-auth-form="recovery" novalidate>
        @csrf
        <x-ui.field name="correo" label="Correo electrónico" :required="true"><input id="correo" name="correo" type="email" maxlength="254" value="{{ old('correo') }}" required autocomplete="email" aria-describedby="correo-error correo-client-error"><p id="correo-client-error" data-auth-client-error="correo" class="ui-error" aria-live="polite" hidden></p></x-ui.field>
        <x-auth.submit label="Enviar enlace" />
    </form>
    <p class="auth-alternative"><a href="{{ route('login') }}">Volver a iniciar sesión</a></p>
</x-guest-layout>
