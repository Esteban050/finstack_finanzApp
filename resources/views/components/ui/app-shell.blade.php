@props(['title' => 'FinanzApp'])
<div class="ui-shell-backdrop" aria-hidden="true"></div>
<a class="ui-skip" href="#main-content">Ir al contenido</a>
@auth
<aside class="ui-sidebar" aria-label="Navegación principal">@include('layouts.navigation')</aside>
<dialog id="navigation-drawer" class="ui-drawer" aria-label="Navegación principal">
    <form method="dialog"><x-ui.button type="submit" variant="ghost" aria-label="Cerrar menú"><x-icon name="ui-cerrar" /></x-ui.button></form>
    @include('layouts.navigation')
</dialog>
@endauth
<div class="{{ auth()->check() ? 'ui-main' : 'ui-public' }}">
    <header class="ui-topbar">
        @auth<x-ui.button variant="ghost" class="ui-menu-toggle" data-open-drawer aria-label="Abrir menú" aria-controls="navigation-drawer" aria-expanded="false"><x-icon name="ui-menu" /></x-ui.button>@endauth
        <span class="ui-topbar-title">{{ $title }}</span>
        <x-ui.theme-switch />
    </header>
    <main id="main-content" class="ui-content" tabindex="-1">
        @include('layouts.module-tabs')
        <div data-page-loading hidden><x-ui.skeleton /></div>
        <div data-page-content>{{ $slot }}</div>
    </main>
    <x-ui.policy-link />
</div>
<x-ui.confirm-dialog />
<x-ui.toast :message="session('status')" />
