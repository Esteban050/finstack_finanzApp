@extends('layouts.app')
@section('title', 'Declaración de renta · Información complementaria ' . $anio)
@section('content')
@php
    $rentaInitial = [
        'patrimonioConsolidado'=>(string)$consolidado['patrimonio_bruto_31_12']['total_cop'],
        'ingresosConsolidados'=>(string)$consolidado['ingresos_brutos']['total_cop'],
        'pisoInversiones'=>(string)$consolidado['piso_inversiones_c5']['total_cop'],
        'ajustePatrimonio'=>(string)old('c1_ajuste_manual_cop',$declaracion->c1_ajuste_manual_cop ?? '0'),
        'ajusteIngresos'=>(string)old('c2_ajuste_manual_cop',$declaracion->c2_ajuste_manual_cop ?? '0'),
        'consumosTarjeta'=>(string)old('c3_consumos_tarjeta_cop',$declaracion->c3_consumos_tarjeta_cop ?? '0'),
        'comprasTotales'=>(string)old('c4_compras_totales_cop',$declaracion->c4_compras_totales_cop ?? '0'),
        'consignaciones'=>(string)old('c5_consignaciones_declaradas_cop',$declaracion->c5_consignaciones_declaradas_cop ?? $consolidado['piso_inversiones_c5']['total_cop']),
    ];
@endphp
<div class="finance-page renta-page" x-data="rentaComplementary" data-renta-initial="{{ json_encode($rentaInitial) }}" @money-change="update($event.detail)">
    <header class="income-page-header"><div><h1>Información complementaria · {{ $anio }}</h1><p>Completa los valores externos a tus registros y ajusta los saldos antes de evaluar.</p></div><a class="ui-button ui-button--secondary" href="{{ route('renta.consolidado',['anio'=>$anio]) }}">Volver al consolidado</a></header>
    @if($errors->any())<div class="ui-notice" role="alert">Revisa los campos indicados. Tus valores se conservan para que puedas corregirlos.</div>@endif
    <form method="POST" action="{{ route('renta.complementaria.guardar',['anio'=>$anio]) }}" @submit="validate($event)" novalidate>
        @csrf
        <div class="income-editor renta-editor">
            <div class="income-form-main">
                <x-ui.card title="Consignaciones, tarjetas y compras">
                    <p class="ui-help">Tus inversiones registradas suman <span data-money="{{ $consolidado['piso_inversiones_c5']['total_cop'] }}"></span>. Inclúyelas en el total de consignaciones junto con los demás depósitos del año.</p>
                    <x-ui.money-input name="c5_consignaciones_declaradas_cop" id="c5_input" label="Total consignaciones, depósitos e inversiones" :value="$rentaInitial['consignaciones']" :allow-zero="true" />
                    <div class="income-two-fields">
                        <x-ui.money-input name="c3_consumos_tarjeta_cop" id="c3_input" label="Consumos con tarjeta de crédito" :value="$rentaInitial['consumosTarjeta']" :allow-zero="true" :required="false" />
                        <x-ui.money-input name="c4_compras_totales_cop" id="c4_input" label="Compras y consumos totales" :value="$rentaInitial['comprasTotales']" :allow-zero="true" :required="false" />
                    </div>
                </x-ui.card>
                <x-ui.card title="Ajustes a ingresos y patrimonio">
                    <p class="ui-help">Valor final = consolidado + ajuste. Puedes sumar o restar, sin dejar el total por debajo de cero.</p>
                    <div class="renta-adjustment">
                        <p>Ingresos brutos consolidados: <strong data-money="{{ $consolidado['ingresos_brutos']['total_cop'] }}"></strong></p>
                        <x-ui.money-input name="c2_ajuste_manual_cop" label="Ajuste a los ingresos brutos" :value="$rentaInitial['ajusteIngresos']" :allow-zero="true" :allow-negative="true" />
                        <p :class="ingresosInvalidos ? 'ui-error' : 'ui-help'" aria-live="polite">Ingresos finales: <strong x-text="money(ingresosFinales)"></strong></p>
                    </div>
                    <div class="renta-adjustment">
                        <p>Patrimonio bruto consolidado: <strong data-money="{{ $consolidado['patrimonio_bruto_31_12']['total_cop'] }}"></strong></p>
                        <x-ui.money-input name="c1_ajuste_manual_cop" label="Ajuste al patrimonio bruto" :value="$rentaInitial['ajustePatrimonio']" :allow-zero="true" :allow-negative="true" />
                        <p :class="patrimonioInvalido ? 'ui-error' : 'ui-help'" aria-live="polite">Patrimonio final: <strong x-text="money(patrimonioFinal)"></strong></p>
                    </div>
                    <div x-show="hasAdjustments">
                        <x-ui.field name="justificacion_ajuste" id="justificacion" label="Explicación del ajuste" help="Obligatoria cuando hay ajustes: entre 10 y 500 caracteres."><textarea id="justificacion" name="justificacion_ajuste" rows="3" minlength="10" maxlength="500" :required="hasAdjustments" aria-describedby="justificacion-help justificacion-error">{{ old('justificacion_ajuste','') }}</textarea></x-ui.field>
                    </div>
                    <div class="income-actions"><button type="submit" name="accion" value="guardar" class="ui-button ui-button--secondary">Guardar información</button><button type="submit" name="accion" value="guardar_y_evaluar" class="ui-button ui-button--primary">Guardar y evaluar obligación</button></div>
                </x-ui.card>
            </div>
            <aside class="income-summary" aria-label="Valores para la evaluación">
                <x-ui.card title="Valores que se evaluarán"><dl class="mk-kv"><dt>Patrimonio bruto</dt><dd x-text="money(patrimonioFinal)"></dd><dt>Ingresos brutos</dt><dd x-text="money(ingresosFinales)"></dd><dt>Consignaciones e inversiones</dt><dd x-text="money(c5Evaluado)"></dd><dt>Consumos con tarjeta</dt><dd x-text="money(consumosTarjeta || '0')"></dd><dt>Compras y consumos</dt><dd x-text="money(comprasTotales || '0')"></dd></dl></x-ui.card>
            </aside>
        </div>
    </form>
    <x-ui.card title="Historial de ajustes"><ol class="mk-tl">@forelse($ajustes as $ajuste)<li><div><p class="mk-small"><strong>{{ $ajuste->concepto === 'PATRIMONIO_BRUTO' ? 'Patrimonio' : 'Ingresos' }}:</strong> <span data-money="{{ $ajuste->monto_ajuste_cop }}"></span></p><p>{{ $ajuste->justificacion }}</p></div><span class="ui-help">{{ $ajuste->creado_en?->timezone('America/Bogota')->format('d/m/Y') ?? 'Fecha no disponible' }}</span></li>@empty<li>No hay ajustes manuales registrados para {{ $anio }}.</li>@endforelse</ol></x-ui.card>
</div>
@endsection
