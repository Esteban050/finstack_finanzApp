@props(['mfa' => false])
@php($resendId = $mfa ? 'resend-mfa' : 'resend-verification')
<form method="POST" action="{{ $mfa ? route('mfa.resend') : route('verification.resend.token') }}" data-auth-form="{{ $mfa ? 'mfa-resend' : 'verification-resend' }}" data-resend-key="{{ $mfa ? 'mfa' : 'verification' }}" data-resend-initial="{{ $mfa || session('status') === 'Revisa tu correo para activar la cuenta' ? 'true' : 'false' }}" novalidate>
    @csrf
    @if(!$mfa)<x-ui.field name="correo" :id="$resendId.'-correo'" label="Correo electrónico" :required="true"><input id="{{ $resendId }}-correo" type="email" name="correo" maxlength="254" autocomplete="email" value="{{ old('correo') }}" required aria-describedby="{{ $resendId }}-correo-error {{ $resendId }}-correo-client-error"><p id="{{ $resendId }}-correo-client-error" data-auth-client-error="correo" class="ui-error" aria-live="polite" hidden></p></x-ui.field>@endif
    <x-auth.submit :label="$mfa ? 'Reenviar código' : 'Reenviar correo de verificación'" />
    <p class="ui-help" data-resend-countdown role="status"></p>
    <p class="ui-help">{{ $mfa ? 'Máximo 3 reenvíos.' : 'Máximo 5 reenvíos en 24 horas.' }}</p>
</form>
