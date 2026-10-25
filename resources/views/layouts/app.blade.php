<!doctype html>
<html lang="es-CO">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FinanzApp')</title>
    <x-ui.theme-init />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}"></head>
<body class="scfp-theme" data-login-url="{{ route('login') }}">
    <x-ui.app-shell :title="trim($__env->yieldContent('title', 'FinanzApp'))">
        @isset($header)<header class="ui-page-header">{{ $header }}</header>@endisset
        @hasSection('content')@yield('content')@else{{ $slot ?? '' }}@endif
    </x-ui.app-shell>
    @stack('scripts')
</body>
</html>
