@php
 $workspaceRoutes = ['expenses'=>route('gastos.index'),'incomeCreate'=>route('ingresos.create'),'expenseCreate'=>route('gastos.create'),'budgets'=>route('presupuestos.show'),'categories'=>route('gastos.categorias.index'),'alerts'=>route('perfil.alertas.index'),'reset'=>route('perfil.alertas.restablecer'),'notifications'=>route('notificaciones.index'),'counter'=>route('notificaciones.contador'),'allRead'=>route('notificaciones.marcar-todas-leidas'),'dashboard'=>route('resumen.index'),'indicators'=>route('reportes.indicadores'),'cash'=>route('reportes.flujo-de-caja'),'matrix'=>route('reportes.gastos'),'compare'=>route('reportes.comparativo'),'indicatorPdf'=>route('reportes.indicadores.pdf'),'cashPdf'=>route('reportes.flujo-de-caja.exportar.pdf'),'cashXlsx'=>route('reportes.flujo-de-caja.exportar.xlsx'),'matrixXlsx'=>route('reportes.gastos.exportar.xlsx'),'compareXlsx'=>route('reportes.comparativo.exportar.xlsx')];
@endphp
<div class="finance-page planning-page" data-financial-workspace data-workspace-page="{{ $workspacePage }}" data-routes="{{ json_encode($workspaceRoutes) }}" data-month="{{ $budgetMonth ?? '' }}" data-line-id="{{ $budgetLineId ?? '' }}" data-initial="{{ isset($datos) ? json_encode($datos) : '' }}" data-month-limit="{{ config('scfp.presupuestos.meses_futuros_max') }}" data-line-limit="{{ config('scfp.presupuestos.lineas_max') }}">
@php
    $reportPages = ['indicators'=>'Indicadores','cash'=>'Flujo de caja','matrix'=>'Gastos por dimensión','compare'=>'Comparativo'];
    $requestedMonth = $budgetMonth ?? request()->query('mes');
    $selectedMonth = is_string($requestedMonth) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requestedMonth) ? $requestedMonth : now('America/Bogota')->format('Y-m');
    $configuringBudget = request()->query('vista') === 'configurar';
@endphp
@if(array_key_exists($workspacePage, $reportPages))
<nav class="ui-tabs" aria-label="Secciones de reportes">
    @foreach($reportPages as $key=>$label)<a href="{{ $workspaceRoutes[$key] }}" @if($workspacePage===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach
</nav>
@elseif($workspacePage === 'budgets')
<nav class="ui-tabs" aria-label="Secciones de presupuestos">
    <a href="{{ route('presupuestos.show', ['mes'=>$selectedMonth]) }}" @if(!$configuringBudget) aria-current="page" @endif>Presupuesto</a>
    @if($selectedMonth >= now('America/Bogota')->format('Y-m'))<a href="{{ route('presupuestos.show', ['mes'=>$selectedMonth,'vista'=>'configurar']) }}" @if($configuringBudget) aria-current="page" @endif>Configurar</a>@endif
</nav>
@elseif($workspacePage === 'alerts')
<nav class="planning-breadcrumb" aria-label="Ubicación"><a href="{{ route('profile.show') }}">Mi perfil</a><span aria-hidden="true">/</span><span>Alertas y notificaciones</span></nav>
@endif
<header class="income-page-header planning-page-header"><div class="planning-heading"><h1 data-workspace-title></h1><p data-workspace-description hidden></p></div><div class="ui-inline planning-heading-actions" data-workspace-actions></div></header>
<div data-workspace-status role="alert" hidden></div><div data-workspace-loading><x-ui.skeleton :lines="4" /></div><div data-workspace-content></div>
<noscript><p class="ui-notice">Activa JavaScript para consultar los datos y utilizar estos reportes.</p></noscript>
</div>