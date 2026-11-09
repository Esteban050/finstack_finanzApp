@php
    $tabs = request()->routeIs('ingresos.*') ? [
        ['ingresos.index', 'Movimientos', request()->routeIs('ingresos.index', 'ingresos.create', 'ingresos.edit')],
        ['ingresos.fuentes.index', 'Fuentes', request()->routeIs('ingresos.fuentes.*')],
        ['ingresos.recurrentes.index', 'Recurrentes', request()->routeIs('ingresos.recurrentes.*')],
    ] : (request()->routeIs('gastos.*') && !request()->routeIs('gastos.categorias.*') ? [
        ['gastos.index', 'Movimientos', request()->routeIs('gastos.index', 'gastos.create', 'gastos.edit')],
        ['gastos.recurrentes.index', 'Recurrentes', request()->routeIs('gastos.recurrentes.*')],
    ] : (request()->routeIs('seguridad-social.*') ? [
        ['seguridad-social.index', 'Vencimientos y Alertas', request()->routeIs('seguridad-social.index')],
        ['seguridad-social.liquidaciones.index', 'Liquidación Independiente', request()->routeIs('seguridad-social.liquidaciones.*')],
        ['seguridad-social.asalariado.index', 'Aportes Asalariado', request()->routeIs('seguridad-social.asalariado.*')],
        ['seguridad-social.pagos.index', 'Pagos PILA (Egresos)', request()->routeIs('seguridad-social.pagos.*')],
    ] : (request()->routeIs('inversiones.*') ? [
        ['inversiones.index', 'Mis Inversiones', request()->routeIs('inversiones.index', 'inversiones.show')],
        ['inversiones.portafolio', 'Portafolio Consolidado', request()->routeIs('inversiones.portafolio')],
        ['inversiones.reportes.retenciones', 'Reporte Retenciones', request()->routeIs('inversiones.reportes.retenciones*')],
        ['inversiones.reportes.utilidades', 'Reporte Utilidades', request()->routeIs('inversiones.reportes.utilidades*')],
        ['inversiones.simulaciones.index', 'Simulador', request()->routeIs('inversiones.simulaciones.*')],
    ] : [])));
@endphp
@if($tabs)<nav class="ui-tabs" aria-label="Secciones">@foreach($tabs as [$route, $label, $active])<a href="{{ route($route) }}" @if($active) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>@endif
