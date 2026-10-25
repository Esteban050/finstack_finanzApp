<x-guest-layout>
    <x-slot name="title">Código de verificación</x-slot>
    <h1>Código de verificación</h1><p>Ingresa el código de 6 dígitos enviado a tu correo.</p>
    <x-auth.status />
    <form method="POST" action="{{ route('mfa.verify') }}" data-auth-form="mfa" novalidate>
        @csrf
        <x-ui.field name="codigo" label="Código de verificación" :required="true"><input id="codigo" name="codigo" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="auth-code" aria-describedby="codigo-error codigo-client-error"><p id="codigo-client-error" data-auth-client-error="codigo" class="ui-error" aria-live="polite" hidden></p></x-ui.field>
        <label class="auth-checkbox" for="recordar_dispositivo"><input id="recordar_dispositivo" name="recordar_dispositivo" type="checkbox" value="1"><span>Recordar este dispositivo por 30 días</span></label>
        <x-auth.submit label="Verificar código" loading="Verificando…" />
    </form>
    <div class="auth-resend-details"><x-auth.resend :mfa="true" /></div>
    <p class="auth-alternative"><a href="{{ route('login') }}">Volver a iniciar sesión</a></p>
</x-guest-layout>
