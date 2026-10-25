@props(['email', 'document'])
<form method="POST" action="{{ route('password.custom.change') }}" data-auth-form="change" data-password-email="{{ $email }}" data-password-document="{{ $document }}" novalidate>
    @csrf @method('PUT')
    <x-auth.password name="current_password" id="change-current-password" label="Contraseña actual" autocomplete="current-password" />
    <x-auth.password id="change-password" label="Nueva contraseña" :rules="true" />
    <x-auth.password name="password_confirmation" id="change-password-confirmation" label="Confirmar nueva contraseña" />
    <x-auth.submit label="Actualizar contraseña" loading="Guardando…" />
</form>
