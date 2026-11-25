@extends('layouts.app')
@section('title', 'Calculadora de declaración de renta · ' . $anio)
@section('content')
<div class="finance-page renta-page" x-data="rentaCalculator" data-calculate-url="{{ route('renta.calculadora.calcular', ['anio'=>$anio]) }}">
    <header class="income-page-header">
        <div><h1>Calculadora de declaración de renta · {{ $anio }}</h1><p>Simula los topes según tus montos acumulados del año y consulta la estimación del sistema.</p></div>
        <div class="ui-inline">
            <div class="mk-year"><label for="select-anio">Año gravable</label><select id="select-anio" onchange="window.location.href = '{{ url('/renta') }}/' + this.value + '/calculadora'">@foreach(array_unique([$anio, ...($vigencias ?? [2025, 2026])]) as $year)<option value="{{ $year }}" @selected((int)$anio === $year)>{{ $year }}</option>@endforeach</select></div>
            <a class="ui-button ui-button--secondary" href="{{ route('renta.consolidado',['anio'=>$anio]) }}">Ver evaluación de mis registros</a>
        </div>
    </header>
    @if($sinVigencia ?? false)
        <p class="ui-error" role="alert">No hay una vigencia normativa activa para {{ $anio }}. Elige un año con parámetros disponibles.</p>
    @else
    <form @submit.prevent="calculate" @input="invalidate" @change="invalidate" @money-change="invalidate" novalidate :aria-busy="calculating">
        <x-ui.card title="Montos acumulados del año">
            <p class="ui-help">Puedes indicar cero. Usa puntos para los miles y coma para los decimales. La evaluación de tus registros también contempla tu perfil tributario.</p>
            <div class="income-two-fields">
                <x-ui.money-input name="patrimonio_bruto_cop" id="c1_patrimonio" label="Patrimonio bruto al 31 de diciembre" value="0" :allow-zero="true" />
                <x-ui.money-input name="ingresos_brutos_cop" id="c2_ingresos" label="Ingresos brutos anuales" value="0" :allow-zero="true" />
                <x-ui.money-input name="consumos_tarjeta_cop" id="c3_tarjetas" label="Consumos con tarjeta de crédito" value="0" :allow-zero="true" />
                <x-ui.money-input name="compras_totales_cop" id="c4_compras" label="Compras y consumos totales" value="0" :allow-zero="true" />
                <x-ui.money-input name="consignaciones_cop" id="c5_consignaciones" label="Consignaciones, depósitos e inversiones" value="0" :allow-zero="true" />
            </div>
            <div class="income-actions"><button type="submit" class="ui-button ui-button--primary" :disabled="calculating" :aria-busy="calculating"><x-icon name="act-clasificacion-tributaria" :size="20" /><span x-text="calculating ? 'Calculando…' : 'Calcular simulación'">Calcular simulación</span></button></div>
            <p class="ui-error" x-show="message" x-text="message" role="alert" x-cloak></p>
        </x-ui.card>
    </form>
    <div x-show="calculating" x-cloak role="status" aria-label="Calculando simulación"><x-ui.skeleton /></div>
    <template x-if="result">
        <section class="mk-stack" data-renta-result tabindex="-1" aria-labelledby="renta-result-title">
            <div class="mk-result" :class="result.obligacion.esta_obligado ? 'warning' : 'success'" role="status">
                <h2 id="renta-result-title" x-text="result.obligacion.esta_obligado ? 'Alcanzas al menos uno de los topes simulados' : 'No alcanzas los topes simulados'"></h2>
                <p>Este resultado corresponde a los montos ingresados. Consulta la evaluación de tus registros para considerar también tu perfil tributario.</p>
                <a class="ui-button ui-button--secondary" href="{{ route('renta.consolidado',['anio'=>$anio]) }}">Ver evaluación completa</a>
            </div>
            <x-ui.card title="Resultado por concepto">
                <div class="ui-table-scroll" role="region" aria-label="Comparación con los topes" tabindex="0"><table class="ui-table"><thead><tr><th>Concepto</th><th>Monto ingresado</th><th>Tope</th><th>Resultado</th></tr></thead><tbody>
                    <template x-for="criterion in criteria" :key="criterion.id"><tr>
                        <td data-label="Concepto" x-text="criterion.nombre"></td>
                        <td data-label="Monto ingresado" class="ui-number" x-text="money(criterion.valor_cop)"></td>
                        <td data-label="Tope" class="ui-number"><span x-text="money(criterion.tope_cop)"></span> <span class="ui-help" x-text="quantity(criterion.tope_uvt) + ' UVT · ' + (criterion.operador === '>=' ? 'Mayor o igual' : 'Mayor')"></span></td>
                        <td data-label="Resultado"><span class="ui-badge" :class="criterion.cumple ? 'ui-badge--warning' : ''" x-text="criterion.cumple ? 'Tope alcanzado' : 'Bajo el tope'"></span></td>
                    </tr></template>
                </tbody></table></div>
            </x-ui.card>
            <x-ui.card title="Estimación del sistema">
                <p class="ui-help">Esta estimación usa tus registros del año en la aplicación: no cambia con los valores que simulas arriba. Los topes de arriba sí comparan los montos ingresados.</p>
                <dl class="finance-stats"><div class="finance-stat"><dt>Impuesto estimado</dt><dd x-text="money(result.estimacion_sistema?.impuesto_cop)"></dd></div><div class="finance-stat"><dt>Saldo estimado</dt><dd x-text="money(result.estimacion_sistema?.saldo_valor)"></dd><span class="ui-help" x-text="result.estimacion_sistema?.saldo_label"></span></div></dl>
                <x-ui.calculation-notice />
            </x-ui.card>
        </section>
    </template>
    @endif
</div>
@endsection
