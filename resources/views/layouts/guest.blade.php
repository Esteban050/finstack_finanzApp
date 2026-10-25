<!doctype html>
<html lang="es-CO">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>SCFP</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="scfp-theme ui-auth" data-login-url="{{ route('login') }}">
    <div class="auth-bg ui-auth-grid" aria-hidden="true"></div>
    <main id="main-content" class="ui-auth-panel"><a class="ui-brand" href="{{ url('/') }}">SCFP<span>Control financiero personal</span></a>
        @if(request('sesion') === 'expirada')<p role="alert" class="ui-error">Tu sesión expiró por inactividad</p>@endif
        {{ $slot }}
    </main>
    <x-ui.policy-link /><x-ui.toast />
</body>
</html>
