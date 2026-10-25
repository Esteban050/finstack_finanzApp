<!doctype html>
<html lang="es-CO">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · FinanzApp' : 'FinanzApp' }}</title><link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <x-ui.theme-init />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="scfp-theme auth-page" data-login-url="{{ route('login') }}">
    <a class="ui-skip" href="#main-content">Ir al contenido</a>
    <div class="auth-layout {{ isset($minimal) ? 'auth-layout--entry' : '' }}">
        <div class="auth-column">
            <header class="auth-header"><a class="ui-brand" href="{{ url('/') }}"><x-application-logo /><span class="ui-brand-name">FinanzApp</span></a><x-ui.theme-switch /></header>
            <main id="main-content" class="auth-card" tabindex="-1">
                @if(request('sesion') === 'expirada')<p role="alert" class="ui-error">Tu sesión expiró por inactividad</p>@endif
                {{ $slot }}
            </main>
            <x-ui.policy-link />
        </div>
        @if(!isset($minimal))<aside class="auth-brand-panel auth-panel-bg" aria-label="FinanzApp">
            <div><img src="{{ asset('favicon.svg') }}" alt="" width="160" height="160"><p class="auth-brand-name">FinanzApp</p><p>Controla tus ingresos y gastos en pesos colombianos.</p></div>
        </aside>@endif
    </div>
</body>
</html>
