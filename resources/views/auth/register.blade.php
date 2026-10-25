<x-guest-layout>
    <x-slot name="title">Crear cuenta</x-slot>
    <h1>Crear cuenta</h1><p>Los campos con * son obligatorios.</p>
    <x-auth.status />
    <form method="POST" action="{{ route('register') }}" data-auth-form="register" novalidate>
        @csrf
        <div class="auth-two-fields">
            <x-ui.field name="nombres" label="Nombres" :required="true"><input id="nombres" name="nombres" value="{{ old('nombres') }}" minlength="2" maxlength="60" autocomplete="given-name" required aria-describedby="nombres-error nombres-client-error"><p id="nombres-client-error" data-auth-client-error="nombres" class="ui-error" aria-live="polite" hidden></p></x-ui.field>
            <x-ui.field name="apellidos" label="Apellidos" :required="true"><input id="apellidos" name="apellidos" value="{{ old('apellidos') }}" minlength="2" maxlength="60" autocomplete="family-name" required aria-describedby="apellidos-error apellidos-client-error"><p id="apellidos-client-error" data-auth-client-error="apellidos" class="ui-error" aria-live="polite" hidden></p></x-ui.field>
        </div>
        <div class="auth-two-fields">
            <x-ui.field name="tipo_documento" label="Tipo de documento" :required="true"><select id="tipo_documento" name="tipo_documento" required aria-describedby="tipo_documento-error tipo_documento-client-error"><option value="">Selecciona el tipo</option>@foreach(['CC','CE','PA','PPT'] as $type)<option value="{{ $type }}" @selected(old('tipo_documento') === $type)>{{ $type }}</option>@endforeach</select><p id="tipo_documento-client-error" data-auth-client-error="tipo_documento" class="ui-error" aria-live="polite" hidden></p></x-ui.field>
            <x-ui.field name="numero_documento" label="Número de documento" :required="true" help="De 5 a 15 caracteres alfanuméricos, sin puntos."><input id="numero_documento" name="numero_documento" value="{{ old('numero_documento') }}" minlength="5" maxlength="15" required aria-describedby="numero_documento-help numero_documento-error numero_documento-client-error"><p id="numero_documento-client-error" data-auth-client-error="numero_documento" class="ui-error" aria-live="polite" hidden></p></x-ui.field>
        </div>
        <x-ui.field name="correo" label="Correo electrónico" :required="true"><input id="correo" type="email" name="correo" value="{{ old('correo') }}" maxlength="254" autocomplete="email" required aria-describedby="correo-error correo-client-error"><p id="correo-client-error" data-auth-client-error="correo" class="ui-error" aria-live="polite" hidden></p></x-ui.field>
        <x-auth.password :rules="true" />
        <x-auth.password name="password_confirmation" label="Confirmar contraseña" />
        <div data-field="acepta_politica">
            <label class="auth-checkbox" for="acepta_politica"><input id="acepta_politica" name="acepta_politica" type="checkbox" value="1" required aria-describedby="acepta_politica-error acepta_politica-client-error terms-unavailable"><span>Acepto la <a href="{{ route('politica.mostrar') }}" target="_blank" rel="noopener">política de tratamiento de datos personales</a> y los <span aria-disabled="true">términos de uso</span>. *</span></label>
            <p id="terms-unavailable" class="ui-help">Los términos de uso aún no tienen una página publicada.</p>
            <p id="acepta_politica-error" class="ui-error" data-field-error aria-live="polite" @if(!$errors->has('acepta_politica')) hidden @endif>{{ $errors->first('acepta_politica') }}</p>
            <p id="acepta_politica-client-error" class="ui-error" data-auth-client-error="acepta_politica" aria-live="polite" hidden></p>
        </div>
        <x-auth.submit label="Crear cuenta" loading="Creando cuenta…" :locked="true" />
    </form>
    <p class="auth-alternative">¿Ya tienes cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
</x-guest-layout>
