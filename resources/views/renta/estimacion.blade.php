@extends('layouts.app')

@section('title', 'Estimación del impuesto · Año ' . $anio)

@section('content')
<div class="finance-page">
    <header class="income-page-header">
        <div>
            <h1>Estimación del impuesto · {{ $anio }}</h1>
            <p>Depuración de la Cédula General unificando tus rentas de trabajo con los rendimientos, exenciones y retenciones de tu módulo de Inversiones.</p>
        </div>
        <div class="ui-inline">
            <a class="ui-button ui-button--secondary" href="{{ route('renta.evaluacion', ['anio' => $anio]) }}">Ver evaluación</a>
            <a class="ui-button ui-button--primary" href="{{ route('renta.pdf', ['anio' => $anio]) }}" target="_blank">Descargar constancia PDF</a>
        </div>
    </header>

    <div class="mk-split">
        {{-- COLUMNA IZQUIERDA: PASO A PASO DE DEPURACIÓN --}}
        <div class="ui-card">
            <h2 class="mk-h2">Paso a paso de depuración</h2>
            <ol class="mk-steps">
                {{-- PASO 1 --}}
                <li>
                    <b>1</b>
                    <div>
                        <strong>Ingresos brutos de la cédula general</strong>
                        <p class="mk-meta">
                            Rentas de trabajo y cesantías ($ {{ number_format($estimacion['paso1']['ingresos_trabajo_cop'] + $estimacion['paso1']['cesantias_cop'], 0, ',', '.') }})
                            + Rendimientos de CDTs, Cuentas Alto Rend. y FICs ($ {{ number_format($estimacion['paso1']['capital_inversiones_cop'], 0, ',', '.') }})
                            + Venta de ETFs/Acciones &lt; 2 años ($ {{ number_format($estimacion['paso1']['utilidad_acciones_cop'], 0, ',', '.') }})
                            @if($estimacion['paso1']['ajuste_manual_cop'] != 0)
                                + Ajuste manual ($ {{ number_format($estimacion['paso1']['ajuste_manual_cop'], 0, ',', '.') }})
                            @endif
                        </p>
                    </div>
                    <span class="v">$ {{ number_format($estimacion['paso1']['ingresos_brutos_cop'], 0, ',', '.') }}</span>
                </li>

                {{-- PASO 2 --}}
                <li>
                    <b>2</b>
                    <div>
                        <strong>(−) Ingresos no constitutivos de renta (INCRNGO)</strong>
                        <p class="mk-meta">
                            Salud y pensión obligatoria ($ {{ number_format($estimacion['paso2']['salud_pension_cop'], 0, ',', '.') }})
                            · Componente inflacionario sobre rendimientos de CDTs, cuentas y FICs ($ {{ number_format($estimacion['paso2']['componente_inflacionario_cop'], 0, ',', '.') }})
                            · Utilidad exenta en venta de ETFs/Acciones en BVC/MGC Art. 36-1 ET ($ {{ number_format($estimacion['paso2']['utilidad_exenta_bvc_cop'], 0, ',', '.') }})
                        </p>
                    </div>
                    <span class="v">− $ {{ number_format($estimacion['paso2']['incrngo_total_cop'], 0, ',', '.') }}</span>
                </li>

                {{-- SUBTOTAL: RENTA LÍQUIDA --}}
                <li class="key">
                    <b>=</b>
                    <div>
                        <strong>Renta líquida ordinaria</strong>
                    </div>
                    <span class="v mk-strong">$ {{ number_format($estimacion['subtotal_renta_liquida']['renta_liquida_cop'], 0, ',', '.') }}</span>
                </li>

                {{-- PASO 3 --}}
                <li>
                    <b>3</b>
                    <div>
                        <strong>Rentas exentas por inversiones (FPV y Cesantías)</strong>
                        <p class="mk-meta">
                            Aportes voluntarios a FPV Art. 126-1 ET ($ {{ number_format($estimacion['paso3']['fpv_aceptado_cop'], 0, ',', '.') }})
                            · Cesantías e intereses exentos Art. 206 num. 4 ET ($ {{ number_format($estimacion['paso3']['cesantias_exentas_cop'], 0, ',', '.') }})
                        </p>
                    </div>
                    <span class="v">$ {{ number_format($estimacion['paso3']['exentas_inversiones_cop'], 0, ',', '.') }}</span>
                </li>

                {{-- PASO 4 --}}
                <li>
                    <b>4</b>
                    <div>
                        <strong>Renta exenta laboral del 25 % (Art. 206 num. 10 ET)</strong>
                        <p class="mk-meta">
                            25 % sobre rentas de trabajo depuradas. Límite legal de 790 UVT = $ {{ number_format($estimacion['paso4']['tope_790_cop'], 0, ',', '.') }}
                        </p>
                    </div>
                    <span class="v">$ {{ number_format($estimacion['paso4']['exenta_25_laboral_cop'], 0, ',', '.') }}</span>
                </li>

                {{-- PASO 5 --}}
                <li>
                    <b>5</b>
                    <div>
                        <strong>Total rentas exentas (Pasos 3 + 4), con límite legal</strong>
                        <p class="mk-meta">
                            Suma $ {{ number_format($estimacion['paso5']['suma_exentas_bruta_cop'], 0, ',', '.') }}, limitada al menor valor entre el 40 % de la renta líquida ($ {{ number_format($estimacion['paso5']['limite_40_pct_cop'], 0, ',', '.') }}) y 1.340 UVT ($ {{ number_format($estimacion['paso5']['limite_1340_uvt_cop'], 0, ',', '.') }})
                        </p>
                        <p style="margin-top:.375rem">
                            @if(!$estimacion['paso5']['limite_aplicado'])
                                <span class="ui-badge ui-badge--success">100 % aprovechado · Margen disponible bajo el tope: $ {{ number_format($estimacion['paso5']['cupo_disponible_cop'], 0, ',', '.') }}</span>
                            @else
                                <span class="ui-badge ui-badge--warning">Tope legal alcanzado · Exceso sin beneficio: $ {{ number_format($estimacion['paso5']['exceso_no_aprovechado_cop'], 0, ',', '.') }}</span>
                            @endif
                        </p>
                    </div>
                    <span class="v">− $ {{ number_format($estimacion['paso5']['total_exentas_aceptadas_cop'], 0, ',', '.') }}</span>
                </li>

                {{-- SUBTOTAL: RENTA LÍQUIDA GRAVABLE --}}
                <li class="key">
                    <b>6</b>
                    <div>
                        <strong>Renta líquida gravable</strong>
                        <p class="mk-meta">{{ number_format($estimacion['paso6']['rlg_uvt'], 2, ',', '.') }} UVT</p>
                    </div>
                    <span class="v mk-strong">$ {{ number_format($estimacion['paso6']['rlg_cop'], 0, ',', '.') }}</span>
                </li>

                {{-- PASO 7 --}}
                <li>
                    <b>7</b>
                    <div>
                        <strong>Impuesto según la tabla de renta (Art. 241 ET)</strong>
                        <p class="mk-meta">
                            Rango: {{ $estimacion['paso7']['rango_texto'] }} (tarifa marginal {{ $estimacion['paso7']['tarifa_marginal'] }}). Redondeado al múltiplo de $ 1.000 más cercano.
                        </p>
                    </div>
                    <span class="v">$ {{ number_format($estimacion['paso7']['impuesto_redondeado_cop'], 0, ',', '.') }}</span>
                </li>

                {{-- PASO 8 --}}
                <li>
                    <b>8</b>
                    <div>
                        <strong>(−) Retenciones en la fuente del año</strong>
                        <p class="mk-meta">
                            Retención en ingresos laborales ($ {{ number_format($estimacion['paso8']['retenciones_laborales_cop'], 0, ',', '.') }})
                            + Retenciones practicadas en CDTs, cuentas y FICs ($ {{ number_format($estimacion['paso8']['retenciones_inversiones_cop'], 0, ',', '.') }})
                        </p>
                    </div>
                    <span class="v">− $ {{ number_format($estimacion['paso8']['retenciones_totales_cop'], 0, ',', '.') }}</span>
                </li>
            </ol>
        </div>

        {{-- COLUMNA DERECHA: RESULTADO NETO E IMPACTO DE INVERSIONES --}}
        <div class="mk-stack">
            {{-- TARJETA DE SALDO NETO --}}
            @if($estimacion['paso8']['tipo_resultado'] === 'SALDO_A_PAGAR')
                <div class="mk-result danger">
                    <span class="ui-badge ui-badge--danger">Resultado estimado</span>
                    <p class="mk-meta" style="margin-top:.75rem">Saldo neto a pagar</p>
                    <p class="mk-amount" data-result="negative">$ {{ number_format($estimacion['paso8']['saldo_valor_mostrado_cop'], 0, ',', '.') }}</p>
                    <p class="ui-help">Impuesto estimado $ {{ number_format($estimacion['paso7']['impuesto_redondeado_cop'], 0, ',', '.') }} − retenciones totales $ {{ number_format($estimacion['paso8']['retenciones_totales_cop'], 0, ',', '.') }}. Valores redondeados al múltiplo de $ 1.000 más cercano.</p>
                </div>
            @elseif($estimacion['paso8']['tipo_resultado'] === 'SALDO_A_FAVOR')
                <div class="mk-result success">
                    <span class="ui-badge ui-badge--success">Resultado estimado</span>
                    <p class="mk-meta" style="margin-top:.75rem">Saldo a favor estimado</p>
                    <p class="mk-amount" data-result="positive">$ {{ number_format($estimacion['paso8']['saldo_valor_mostrado_cop'], 0, ',', '.') }}</p>
                    <p class="ui-help">Tus retenciones practicadas ($ {{ number_format($estimacion['paso8']['retenciones_totales_cop'], 0, ',', '.') }}) superan el impuesto calculado ($ {{ number_format($estimacion['paso7']['impuesto_redondeado_cop'], 0, ',', '.') }}). Podrás solicitar compensación o devolución en tu declaración ante la DIAN.</p>
                </div>
            @else
                <div class="mk-result success">
                    <span class="ui-badge ui-badge--success">Resultado estimado</span>
                    <p class="mk-meta" style="margin-top:.75rem">Saldo en ceros</p>
                    <p class="mk-amount">$ 0</p>
                    <p class="ui-help">El impuesto calculado equivale exactamente a las retenciones practicadas o tu renta líquida gravable no genera impuesto.</p>
                </div>
            @endif

            {{-- IMPACTO TRIBUTARIO DE TUS INVERSIONES --}}
            <div class="ui-card">
                <h2>Impacto tributario de tus inversiones</h2>
                <p class="mk-small mk-muted" style="margin-top:-.75rem;margin-bottom:1rem">
                    Cuánto redujeron tu base gravable y tu impuesto los productos de tu módulo de Inversiones:
                </p>
                <dl class="mk-kv">
                    <dt>Base exenta por Componente Inflacionario (CDTs/Cuentas/FICs)</dt>
                    <dd data-result="positive">− $ {{ number_format($estimacion['paso2']['componente_inflacionario_cop'], 0, ',', '.') }}</dd>

                    <dt>Utilidad exenta en BVC / MGC (Art. 36-1 ET)</dt>
                    <dd data-result="positive">− $ {{ number_format($estimacion['paso2']['utilidad_exenta_bvc_cop'], 0, ',', '.') }}</dd>

                    <dt>Rentas exentas por aportes a FPV y Cesantías</dt>
                    <dd data-result="positive">− $ {{ number_format($estimacion['paso3']['exentas_inversiones_cop'], 0, ',', '.') }}</dd>

                    <dt>Retenciones a favor por inversiones</dt>
                    <dd data-result="positive">− $ {{ number_format($estimacion['impacto_inversiones']['retenciones_inversiones_cop'], 0, ',', '.') }}</dd>

                    <dt class="total">Ahorro total estimado en impuesto gracias a inversiones</dt>
                    <dd class="total" data-result="positive">$ {{ number_format($estimacion['impacto_inversiones']['ahorro_total_cop'], 0, ',', '.') }}</dd>
                </dl>
            </div>

            {{-- LO QUE ESTA ESTIMACIÓN NO CONSIDERA --}}
            <div class="ui-card">
                <h2>Lo que esta estimación no considera</h2>
                <ul class="mk-small" style="margin:0;padding-left:1.25rem;display:grid;gap:.375rem">
                    <li>Cédula de dividendos y participaciones (ETFs/Acciones con dividendos decretados)</li>
                    <li>Ganancias ocasionales (venta de activos con tenencia ≥ 2 años)</li>
                    <li>Rentas de fuente extranjera (brókers internacionales fuera de BVC/MGC)</li>
                    <li>Descuentos tributarios por impuestos pagados en el exterior</li>
                    <li>Anticipo de renta para el año siguiente</li>
                </ul>
            </div>
        </div>
    </div>

    <p class="ui-help finance-legal">Valores estimados con fines informativos. No constituyen asesoría tributaria, contable ni financiera.</p>
</div>
@endsection

