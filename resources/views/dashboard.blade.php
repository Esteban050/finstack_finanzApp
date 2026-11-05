@extends('layouts.app')
@section('title', 'Resumen')
@section('content')
<div class="finance-page">
    <header class="income-page-header">
        <div>
            <h1>Resumen</h1>
            <p>Balance y métricas clave de tus movimientos.</p>
        </div>
        <div class="ui-inline">
            <x-ui.button :href="route('ingresos.create')">+ Registrar ingreso</x-ui.button>
            <x-ui.button :href="route('gastos.create')" variant="secondary">− Registrar gasto</x-ui.button>
        </div>
    </header>

    @if (!$perfil || empty($perfil->ocupaciones))
        <div class="ui-notice" role="status">
            <div class="ui-notice-action">
                <div>
                    <strong>Completa tu perfil financiero para usar todas las funciones</strong>
                    <p class="ui-help">Para habilitar el cálculo de seguridad social y retenciones, debes registrar tu ocupación y datos tributarios.</p>
                </div>
                <x-ui.button :href="route('profile.show')" variant="secondary">Completar perfil</x-ui.button>
            </div>
        </div>
    @endif

    <section data-dashboard data-income-url="{{ route('ingresos.index') }}" data-expense-url="{{ route('gastos.index') }}" aria-label="Resumen financiero del periodo">
        <form data-dashboard-period class="income-two-fields">
            <x-ui.field name="periodo" label="Periodo"><select name="periodo" id="periodo"><option value="mes-actual">Mes actual</option><option value="mes-anterior">Mes anterior</option><option value="trimestre">Trimestre</option><option value="ano">Año en curso</option><option value="personalizado">Rango personalizado</option></select></x-ui.field>
            <div class="income-two-fields"><x-ui.field name="fecha_desde" label="Desde"><input id="fecha_desde" name="fecha_desde" type="date" required></x-ui.field><x-ui.field name="fecha_hasta" label="Hasta"><input id="fecha_hasta" name="fecha_hasta" type="date" required></x-ui.field></div>
            <x-ui.button type="submit">Consultar periodo</x-ui.button>
        </form>
        <p class="ui-help">Solo movimientos confirmados del periodo. Los pendientes no se incluyen.</p>
        <div data-dashboard-loading><x-ui.skeleton /></div>
        <p data-dashboard-status class="ui-help" role="status" aria-live="polite" hidden></p>
        <div data-dashboard-results class="income-source-list" hidden>
            <x-ui.card title="Balance del periodo"><div class="income-net"><output data-dashboard-money="balance">—</output><p data-dashboard-balance-text class="ui-help"></p></div></x-ui.card>
            <div class="income-two-fields">
                <x-ui.card title="Ingresos brutos"><output data-dashboard-money="bruto">—</output></x-ui.card>
                <x-ui.card title="Retenciones"><output data-dashboard-money="retencion">—</output></x-ui.card>
                <x-ui.card title="Aportes"><output data-dashboard-money="aportes">—</output></x-ui.card>
                <x-ui.card title="Ingresos netos"><output data-dashboard-money="neto">—</output></x-ui.card>
                <x-ui.card title="Gastos"><output data-dashboard-money="gastos">—</output></x-ui.card>
            </div>
        </div>
    </section>
</div>
@endsection
