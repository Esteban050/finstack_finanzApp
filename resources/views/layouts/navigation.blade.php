<a href="{{ route('dashboard') }}" class="ui-brand"><x-application-logo /><span class="ui-brand-name">FinanzApp<span>Control financiero personal</span></span></a>
<nav class="ui-navigation" aria-label="Módulos">
@if(auth()->user()?->esAdmin())
    <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif><x-icon name="ui-resumen" :size="20" />Administración</a>
    <a href="{{ route('admin.parametros.index') }}" @if(request()->routeIs('admin.parametros.*','admin.tarifas.*')) aria-current="page" @endif><x-icon name="ui-categorias" :size="20" />Vigencias y tarifas</a>
    <a href="{{ route('admin.categorias.index') }}" @if(request()->routeIs('admin.categorias.*')) aria-current="page" @endif><x-icon name="cls-etiqueta" :size="20" />Catálogo general</a>
    <a href="{{ route('admin.calendario.index') }}" @if(request()->routeIs('admin.calendario.*')) aria-current="page" @endif><x-icon name="ui-resumen" :size="20" />Calendario tributario</a>
    <a href="{{ route('admin.usuarios.index') }}" @if(request()->routeIs('admin.usuarios.*')) aria-current="page" @endif><x-icon name="ui-usuario" :size="20" />Cuentas de usuario</a>
@endif
@foreach([
    ['dashboard', 'Resumen', 'ui-resumen', request()->routeIs('dashboard', 'resumen.index')],
    ['ingresos.index', 'Ingresos', 'act-ingreso', request()->routeIs('ingresos.*')],
    ['gastos.index', 'Gastos', 'act-gasto', request()->routeIs('gastos.*') && !request()->routeIs('gastos.categorias.*')],
    ['gastos.categorias.index', 'Categorías', 'cls-etiqueta', request()->routeIs('gastos.categorias.*')],
    ['seguridad-social.index', 'Seguridad social', 'cat-seguridad-social', request()->routeIs('seguridad-social.*')],
    ['inversiones.index', 'Inversiones', 'src-rendimientos-financieros', request()->routeIs('inversiones.*')],
    ['creditos.index', 'Créditos', 'cat-obligaciones-financieras', request()->routeIs('creditos.*')],
    ['presupuestos.show', 'Presupuestos', 'ui-categorias', request()->routeIs('presupuestos.*')],
    ['reportes.indicadores', 'Reportes', 'ui-resumen', request()->routeIs('reportes.*')],
    ['renta.index', 'Renta', 'act-clasificacion-tributaria', request()->routeIs('renta.*')],
    ['profile.show', 'Mi perfil', 'ui-usuario', request()->routeIs('profile.*', 'perfil.alertas.*')],
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
            @case('presupuestos.show')<x-icon name="ui-categorias" :size="20" />@break
            @case('reportes.indicadores')<x-icon name="ui-resumen" :size="20" />@break
            @case('renta.index')<x-icon name="act-clasificacion-tributaria" :size="20" />@break
            @case('profile.show')<x-icon name="ui-usuario" :size="20" />@break
        @endswitch
        {{ $label }}
    </a>
@endforeach
</nav>
<div class="ui-sidebar-footer"><form method="POST" action="{{ route('logout') }}">@csrf<x-ui.button type="submit" variant="ghost"><x-icon name="ui-cerrar-sesion" :size="20" />Cerrar sesión</x-ui.button></form></div>
