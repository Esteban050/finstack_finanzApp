<a href="{{ route('dashboard') }}" class="ui-brand"><x-application-logo /><span class="ui-brand-name">FinanzApp<span>Control financiero personal</span></span></a>
<nav class="ui-navigation" aria-label="Módulos">
@foreach([
    ['dashboard', 'Resumen', 'ui-resumen', request()->routeIs('dashboard')],
    ['ingresos.index', 'Ingresos', 'act-ingreso', request()->routeIs('ingresos.*')],
    ['gastos.index', 'Gastos', 'act-gasto', request()->routeIs('gastos.*') && !request()->routeIs('gastos.categorias.*')],
    ['gastos.categorias.index', 'Categorías', 'cls-etiqueta', request()->routeIs('gastos.categorias.*')],
    ['seguridad-social.index', 'Seguridad social', 'cat-seguridad-social', request()->routeIs('seguridad-social.*')],
    ['inversiones.index', 'Inversiones', 'src-rendimientos-financieros', request()->routeIs('inversiones.*')],
    ['creditos.index', 'Créditos', 'cat-obligaciones-financieras', request()->routeIs('creditos.*')],
    ['renta.index', 'Renta', 'act-renta', request()->routeIs('renta.*')],
    ['profile.show', 'Mi perfil', 'ui-usuario', request()->routeIs('profile.*')],
] as [$route, $label, $icon, $active])
    <a href="{{ route($route) }}" @if($active) aria-current="page" @endif>
        @switch($route)
            @case('dashboard')<x-icon name="ui-resumen" :size="20" />@break
            @case('ingresos.index')<x-icon name="act-ingreso" :size="20" />@break
            @case('gastos.index')<x-icon name="act-gasto" :size="20" />@break
            @case('gastos.categorias.index')<x-icon name="cls-etiqueta" :size="20" />@break
            @case('seguridad-social.index')<x-icon name="cat-seguridad-social" :size="20" />@break
            @case('inversiones.index')<x-icon name="src-rendimientos-financieros" :size="20" />@break
            @case('creditos.index')<x-icon name="cat-obligaciones-financieras" :size="20" />@break
            @case('renta.index')<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 3h14v18l-3.5-2-3.5 2-3.5-2L5 21z"/><circle cx="9.5" cy="8.5" r="1"/><circle cx="14.5" cy="13.5" r="1"/><path d="M15 7.5l-6 7"/></svg>@break
            @case('profile.show')<x-icon name="ui-usuario" :size="20" />@break
        @endswitch
        {{ $label }}
        @if($route === 'renta.index')
            <span style="font-size:0.6875rem;font-weight:700;margin-left:auto;padding:1px 6px;border-radius:4px;background:var(--accent-secondary);color:var(--accent-secondary-contrast);letter-spacing:0.02em;">Nuevo</span>
        @endif
    </a>
@endforeach
</nav>
<div class="ui-sidebar-footer"><form method="POST" action="{{ route('logout') }}">@csrf<x-ui.button type="submit" variant="ghost"><x-icon name="ui-cerrar-sesion" :size="20" />Cerrar sesión</x-ui.button></form></div>
