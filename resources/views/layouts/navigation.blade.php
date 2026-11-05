<a href="{{ route('dashboard') }}" class="ui-brand"><x-application-logo /><span class="ui-brand-name">FinanzApp<span>Control financiero personal</span></span></a>
<nav class="ui-navigation" aria-label="Módulos">
@foreach([
    ['dashboard', 'Resumen', 'ui-resumen', request()->routeIs('dashboard')],
    ['ingresos.index', 'Ingresos', 'act-ingreso', request()->routeIs('ingresos.*')],
    ['gastos.index', 'Gastos', 'act-gasto', request()->routeIs('gastos.*') && !request()->routeIs('gastos.categorias.*')],
    ['gastos.categorias.index', 'Categorías', 'cls-etiqueta', request()->routeIs('gastos.categorias.*')],
    ['seguridad-social.index', 'Seguridad social', 'cat-seguridad-social', request()->routeIs('seguridad-social.*')],
    ['inversiones.index', 'Inversiones', 'src-rendimientos-financieros', request()->routeIs('inversiones.*')],
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
            @case('profile.show')<x-icon name="ui-usuario" :size="20" />@break
        @endswitch
        {{ $label }}
    </a>
@endforeach
</nav>
<div class="ui-sidebar-footer"><form method="POST" action="{{ route('logout') }}">@csrf<x-ui.button type="submit" variant="ghost"><x-icon name="ui-cerrar-sesion" :size="20" />Cerrar sesión</x-ui.button></form></div>
