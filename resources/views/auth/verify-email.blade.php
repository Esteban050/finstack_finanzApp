<x-guest-layout>
    <x-slot name="title">Verificar correo</x-slot>
    <h1>Verifica tu correo</h1><p>Revisa tu correo para activar la cuenta</p>
    <x-auth.status /><x-auth.resend />
    <p class="auth-alternative"><a href="{{ route('login') }}">Volver a iniciar sesión</a></p>
</x-guest-layout>
