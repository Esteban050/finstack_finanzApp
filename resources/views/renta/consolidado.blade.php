@extends('layouts.app')

@section('title', 'Declaración de renta · Consolidado ' . $anio)

@section('content')
<div class="finance-page">
    <header class="income-page-header">
        <div>
            <h1>Consolidado del año gravable</h1>
            <p>Información del 1 de enero al 31 de diciembre consolidada desde tus módulos de Ingresos e Inversiones.</p>
        </div>
        <div class="ui-inline">
            <a class="ui-button ui-button--secondary" href="{{ route('renta.complementaria', ['anio' => $anio]) }}">Completar información</a>
            <a class="ui-button ui-button--primary" href="{{ route('renta.evaluacion', ['anio' => $anio]) }}">Evaluar obligación</a>
        </div>
    </header>

    <div class="ui-card">
        <div class="mk-row">
            <div class="ui-field" style="margin:0;min-width:200px">
                <label for="select-anio">Año gravable <span aria-hidden="true">*</span></label>
                <select id="select-anio" onchange="window.location.href = '{{ url('/renta') }}/' + this.value + '/consolidado'">
                    <option value="2025" @selected($anio === 2025)>2025</option>
                    <option value="2026" @selected($anio === 2026)>2026</option>
                </select>
            </div>
            <dl class="mk-kv" style="margin-left:auto;min-width:340px">
                <dt>UVT del año gravable</dt>
                <dd class="mk-strong">$ {{ number_format($parametros['uvt'], 0, ',', '.') }}</dd>
                <dt>Componente inflacionario (rendimientos)</dt>
                <dd class="mk-strong">{{ number_format($parametros['pct_componente_inflacionario'] * 100, 2, ',', '.') }} %</dd>
                <dt>Última actualización</dt>
                <dd>{{ now()->format('d/m/Y, h:i a') }}</dd>
            </dl>
        </div>
    </div>

    <div class="ui-card">
        <h2 class="mk-h2">Conceptos consolidados</h2>
        <div class="mk-table-wrap">
            <table class="ui-table">
                <caption>Valores en pesos y en UVT del año gravable {{ $anio }}, con 2 decimales en UVT.</caption>
                <thead>
                    <tr>
                        <th>Concepto</th>
                        <th>Origen</th>
                        <th class="num">Valor (COP)</th>
                        <th class="num">Valor (UVT)</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- 1. Ingresos Brutos Totales --}}
                    <tr>
                        <td class="mk-strong">Ingresos brutos totales (Cédula General)</td>
                        <td class="mk-meta">Módulos de Ingresos e Inversiones</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['ingresos_brutos']['total_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['ingresos_brutos']['total_uvt'], 2, ',', '.') }}</td>
                        <td><span class="ui-badge ui-badge--sm">Total C2</span></td>
                    </tr>
                    <tr>
                        <td>&nbsp;&nbsp;Rentas de trabajo (Salarios, honorarios y cesantías)</td>
                        <td class="mk-meta">Ingresos + Inversiones (Cesantías)</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['ingresos_brutos']['trabajo_cop'] + $consolidado['ingresos_brutos']['cesantias_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['ingresos_brutos']['trabajo_uvt'] + $consolidado['ingresos_brutos']['cesantias_uvt'], 2, ',', '.') }}</td>
                        <td><span class="mk-meta">{{ count($consolidado['ingresos_brutos']['drill_down']['trabajo']) }} registros</span></td>
                    </tr>
                    <tr>
                        <td>&nbsp;&nbsp;Rentas de capital (Intereses de CDTs, Cuentas Alto Rend. y FICs)</td>
                        <td class="mk-meta">Inversiones (Rendimientos abonados)</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['ingresos_brutos']['capital_inversiones_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['ingresos_brutos']['capital_inversiones_uvt'], 2, ',', '.') }}</td>
                        <td><span class="mk-meta">{{ count($consolidado['ingresos_brutos']['drill_down']['capital']) }} registros</span></td>
                    </tr>
                    <tr>
                        <td>&nbsp;&nbsp;Rentas no laborales (Utilidad en venta de ETFs/Acciones &lt; 2 años)</td>
                        <td class="mk-meta">Inversiones / Retiros</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['ingresos_brutos']['no_laborales_cop'] + $consolidado['ingresos_brutos']['utilidad_acciones_menor_2a_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['ingresos_brutos']['no_laborales_uvt'] + $consolidado['ingresos_brutos']['utilidad_acciones_menor_2a_uvt'], 2, ',', '.') }}</td>
                        <td><span class="mk-meta">{{ count($consolidado['ingresos_brutos']['drill_down']['no_laborales']) }} registros</span></td>
                    </tr>

                    {{-- 2. INCRNGO --}}
                    <tr>
                        <td class="mk-strong">Ingresos no constitutivos de renta (INCRNGO)</td>
                        <td class="mk-meta">Ingresos + Beneficios de Inversiones</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['incrngo']['total_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['incrngo']['total_uvt'], 2, ',', '.') }}</td>
                        <td><span class="ui-badge ui-badge--sm">Paso 2</span></td>
                    </tr>
                    <tr>
                        <td>&nbsp;&nbsp;Aportes obligatorios a salud y pensión</td>
                        <td class="mk-meta">Ingresos (Deducciones de ley)</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['incrngo']['salud_pension_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['incrngo']['salud_pension_uvt'], 2, ',', '.') }}</td>
                        <td><span class="mk-meta">Seguridad social</span></td>
                    </tr>
                    <tr>
                        <td>&nbsp;&nbsp;Componente inflacionario ({{ number_format($parametros['pct_componente_inflacionario'] * 100, 2, ',', '.') }} % de rendimientos)</td>
                        <td class="mk-meta">Inversiones (CDTs, Cuentas, FICs)</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['incrngo']['componente_inflacionario_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['incrngo']['componente_inflacionario_uvt'], 2, ',', '.') }}</td>
                        <td><span class="mk-meta">Decreto reglamentario</span></td>
                    </tr>
                    <tr>
                        <td>&nbsp;&nbsp;Utilidad exenta en venta de ETFs/Acciones BVC o MGC (Art. 36-1 ET)</td>
                        <td class="mk-meta">Inversiones (Bolsa local / MGC)</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['incrngo']['utilidad_exenta_bvc_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['incrngo']['utilidad_exenta_bvc_uvt'], 2, ',', '.') }}</td>
                        <td><span class="mk-meta">Art. 36-1 E.T.</span></td>
                    </tr>

                    {{-- 3. Rentas Exentas Inversiones --}}
                    <tr>
                        <td class="mk-strong">Rentas exentas por inversiones</td>
                        <td class="mk-meta">Módulo de Inversiones (FPV y Cesantías)</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['rentas_exentas_inversiones']['total_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['rentas_exentas_inversiones']['total_uvt'], 2, ',', '.') }}</td>
                        <td><span class="ui-badge ui-badge--sm">Paso 3</span></td>
                    </tr>
                    <tr>
                        <td>&nbsp;&nbsp;Aportes voluntarios a Fondo de Pensión Voluntaria (FPV)</td>
                        <td class="mk-meta">Inversiones (Art. 126-1 ET)</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['rentas_exentas_inversiones']['fpv_aceptado_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['rentas_exentas_inversiones']['fpv_aceptado_uvt'], 2, ',', '.') }}</td>
                        <td><span class="mk-meta">Límite 30% IB</span></td>
                    </tr>
                    <tr>
                        <td>&nbsp;&nbsp;Cesantías e intereses a las cesantías exentas</td>
                        <td class="mk-meta">Inversiones (Art. 206 num. 4 ET)</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['rentas_exentas_inversiones']['cesantias_exentas_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['rentas_exentas_inversiones']['cesantias_exentas_uvt'], 2, ',', '.') }}</td>
                        <td><span class="mk-meta">Art. 206 num. 4</span></td>
                    </tr>

                    {{-- 4. Retenciones Practicadas --}}
                    <tr>
                        <td class="mk-strong">Retenciones en la fuente practicadas</td>
                        <td class="mk-meta">Ingresos ($ {{ number_format($consolidado['retenciones_practicadas']['laborales_cop'], 0, ',', '.') }}) + Inversiones ($ {{ number_format($consolidado['retenciones_practicadas']['inversiones_cop'], 0, ',', '.') }})</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['retenciones_practicadas']['total_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['retenciones_practicadas']['total_uvt'], 2, ',', '.') }}</td>
                        <td><span class="ui-badge ui-badge--sm">Paso 8</span></td>
                    </tr>

                    {{-- 5. Patrimonio Bruto de Inversiones al 31/12 --}}
                    <tr class="is-open">
                        <td class="mk-strong">Patrimonio bruto de inversiones al 31/12/{{ $anio }}</td>
                        <td class="mk-meta">Saldos y costo fiscal de los 6 productos de inversión</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['patrimonio_bruto_31_12']['total_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['patrimonio_bruto_31_12']['total_uvt'], 2, ',', '.') }}</td>
                        <td><span class="ui-badge ui-badge--sm">Total C1</span></td>
                    </tr>
                    <tr class="is-open">
                        <td colspan="5">
                            <div class="mk-drawer">
                                <div class="mk-row between">
                                    <strong>Portafolio de inversiones al cierre del 31/12/{{ $anio }}</strong>
                                    <span class="mk-meta">Detalle de los productos activos en el portafolio (RF-043 CA-043.2)</span>
                                </div>
                                <table class="ui-table" style="margin-top:1rem">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Entidad / Activo</th>
                                            <th>Tratamiento tributario</th>
                                            <th class="num">Rendimiento {{ $anio }}</th>
                                            <th class="num">Retención {{ $anio }}</th>
                                            <th class="num">Saldo / Costo al 31/12</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($consolidado['patrimonio_bruto_31_12']['productos_activos'] as $prod)
                                            <tr>
                                                <td><span class="ui-badge">{{ $prod['tipo'] }}</span></td>
                                                <td>{{ $prod['nombre'] }} ({{ $prod['entidad'] ?? 'Directo' }})</td>
                                                <td class="mk-meta">{{ $prod['tratamiento_tributario'] }}</td>
                                                <td class="num" data-money>$ {{ number_format($prod['rendimiento_anual_cop'], 0, ',', '.') }}</td>
                                                <td class="num" data-money>$ {{ number_format($prod['retencion_anual_cop'], 0, ',', '.') }}</td>
                                                <td class="num mk-strong" data-money>$ {{ number_format($prod['saldo_patrimonial_cop'], 0, ',', '.') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="mk-muted" style="text-align:center;padding:1.5rem">No se registraron posiciones activas de inversión al 31 de diciembre.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>

                    {{-- 6. Aperturas, depósitos y compras del año (Piso C5) --}}
                    <tr>
                        <td class="mk-strong">Aperturas, depósitos y compras de inversión del año</td>
                        <td class="mk-meta">Módulo de Inversiones (Piso base para tope C5)</td>
                        <td class="num" data-money>$ {{ number_format($consolidado['piso_inversiones_c5']['total_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($consolidado['piso_inversiones_c5']['total_uvt'], 2, ',', '.') }}</td>
                        <td><a href="{{ route('renta.complementaria', ['anio' => $anio]) }}">Ver en C5 →</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <p class="ui-help finance-legal">Valores estimados con fines informativos. No constituyen asesoría tributaria, contable ni financiera.</p>
</div>
@endsection

