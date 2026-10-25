<x-guest-layout>
    <x-slot name="title">Iniciar sesión</x-slot>
    <h1>Iniciar sesión</h1>
    <x-auth.status />
    <form method="POST" action="{{ route('login') }}" data-auth-form="login" novalidate>
        @csrf
        <x-ui.field name="correo" label="Correo electrónico" :required="true"><input id="correo" name="correo" type="email" maxlength="254" value="{{ old('correo') }}" required autocomplete="email" aria-describedby="correo-error correo-client-error"><p id="correo-client-error" data-auth-client-error="correo" class="ui-error" aria-live="polite" hidden></p></x-ui.field>
        <x-auth.password autocomplete="current-password" />
        <p class="auth-forgot"><a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a></p>
        <x-auth.submit label="Iniciar sesión" loading="Ingresando…" />
    </form>
    <p class="auth-alternative">¿No tienes cuenta? <a href="{{ route('register') }}">Crear cuenta</a></p>
    <details class="auth-resend-details" @if($errors->has('verificacion_error') || in_array(session('status'), ['Revisa tu correo para activar la cuenta', 'Si tu cuenta está pendiente de verificación, recibirás un correo.', 'Correo de verificación reenviado exitosamente.']) || str_contains($errors->first('correo'), 'Debes esperar') || str_contains($errors->first('correo'), 'límite')) open @endif><summary>Reenviar correo de verificación</summary><x-auth.resend /></details>
</x-guest-layout>
