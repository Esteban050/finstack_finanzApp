@php
    $tabs = [];
    if (request()->routeIs('ingresos.*')) {
        $tabs = [
            ['ingresos.index', 'Movimientos', request()->routeIs('ingresos.index', 'ingresos.create', 'ingresos.edit')],
            ['ingresos.fuentes.index', 'Fuentes', request()->routeIs('ingresos.fuentes.*')],
            ['ingresos.recurrentes.index', 'Recurrentes', request()->routeIs('ingresos.recurrentes.*')],
        ];
    } elseif (request()->routeIs('gastos.*') && !request()->routeIs('gastos.categorias.*')) {
        $tabs = [
            ['gastos.index', 'Movimientos', request()->routeIs('gastos.index', 'gastos.create', 'gastos.edit')],
            ['gastos.recurrentes.index', 'Recurrentes', request()->routeIs('gastos.recurrentes.*')],
        ];
    } elseif (request()->routeIs('inversiones.*')) {
        $tabs = [
            ['inversiones.index', 'Mis Inversiones', request()->routeIs('inversiones.index', 'inversiones.show')],
            ['inversiones.portafolio', 'Portafolio Consolidado', request()->routeIs('inversiones.portafolio')],
            ['inversiones.reportes.retenciones', 'Reporte Retenciones', request()->routeIs('inversiones.reportes.retenciones*')],
            ['inversiones.reportes.utilidades', 'Reporte Utilidades', request()->routeIs('inversiones.reportes.utilidades*')],
            ['inversiones.simulaciones.index', 'Simulador', request()->routeIs('inversiones.simulaciones.*')],
        ];
    } elseif (request()->routeIs('renta.*')) {
        $anioRenta = request()->route('anio', 2025);
        $tabs = [
            ['renta.consolidado', 'Consolidado y evaluación', request()->routeIs('renta.consolidado', 'renta.evaluacion'), ['anio' => $anioRenta]],
            ['renta.complementaria', 'Información complementaria', request()->routeIs('renta.complementaria'), ['anio' => $anioRenta]],
            ['renta.estimacion', 'Estimación del impuesto', request()->routeIs('renta.estimacion'), ['anio' => $anioRenta]],
            ['renta.vencimiento', 'Vencimiento', request()->routeIs('renta.vencimiento'), ['anio' => $anioRenta]],
        ];
    }
@endphp
@if(!empty($tabs))
<nav class="ui-tabs" aria-label="Secciones">
    @foreach($tabs as $tab)
        @php
            $route = $tab[0];
            $label = $tab[1];
            $active = $tab[2];
            $params = $tab[3] ?? [];
        @endphp
        <a href="{{ route($route, $params) }}" @if($active) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
@endif
