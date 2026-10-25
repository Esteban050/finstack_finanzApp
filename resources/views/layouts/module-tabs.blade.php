@php
    $tabs = request()->routeIs('ingresos.*') ? [
        ['ingresos.index', 'Movimientos', request()->routeIs('ingresos.index', 'ingresos.create', 'ingresos.edit')],
        ['ingresos.fuentes.index', 'Fuentes', request()->routeIs('ingresos.fuentes.*')],
        ['ingresos.recurrentes.index', 'Recurrentes', request()->routeIs('ingresos.recurrentes.*')],
    ] : (request()->routeIs('gastos.*') && !request()->routeIs('gastos.categorias.*') ? [
        ['gastos.index', 'Movimientos', request()->routeIs('gastos.index', 'gastos.create', 'gastos.edit')],
        ['gastos.recurrentes.index', 'Recurrentes', request()->routeIs('gastos.recurrentes.*')],
    ] : []);
@endphp
@if($tabs)<nav class="ui-tabs" aria-label="Secciones">@foreach($tabs as [$route, $label, $active])<a href="{{ route($route) }}" @if($active) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>@endif
