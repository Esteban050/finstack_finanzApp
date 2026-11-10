@extends('layouts.app')

@section('title', '¿Debo declarar renta? · Año gravable ' . $anio)

@section('content')
<div class="finance-page">
    <header class="income-page-header">
        <div>
            <h1>¿Debo declarar renta? · Año gravable {{ $anio }}</h1>
            <p>Persona natural residente. Topes de los artículos 592, 593, 594-1 y 594-3 del Estatuto Tributario, con la UVT de {{ $anio }} ($ {{ number_format($evaluacion['parametros']['uvt'], 0, ',', '.') }}).</p>
        </div>
        <div class="ui-inline">
            <a class="ui-button ui-button--secondary" href="{{ route('renta.evaluacion', ['anio' => $anio]) }}">Volver a evaluar</a>
            <a class="ui-button ui-button--primary" href="{{ route('renta.pdf', ['anio' => $anio]) }}" target="_blank">Guardar constancia en PDF</a>
        </div>
    </header>

    @if($evaluacion['es_preliminar'])
        {{-- VARIANTE PRELIMINAR (Año en curso) --}}
        <div class="mk-result warning" role="status">
            <div class="mk-row between">
                <div>
                    <span class="ui-badge ui-badge--warning">Preliminar · año en curso</span>
                    <p class="mk-result-title">{{ $evaluacion['veredicto_label'] }}</p>
                    <p class="mk-small" style="margin:0">Año gravable {{ $anio }} (UVT $ {{ number_format($evaluacion['parametros']['uvt'], 0, ',', '.') }}). Con los meses transcurridos a la fecha, el sistema proyecta tus ingresos y rendimientos de inversiones al 31/12/{{ $anio }} usando el promedio mensual.</p>
                    <dl class="mk-kv" style="margin-top:1rem">
                        <dt>Ingresos + rendimientos acumulados</dt>
                        <dd>$ {{ number_format($evaluacion['criterios']['c2']['valor_cop'], 0, ',', '.') }}</dd>
                        <dt>Proyección al 31/12/{{ $anio }}</dt>
                        <dd class="mk-strong">$ {{ number_format($evaluacion['criterios']['c2']['proyectado_cop'] ?? $evaluacion['criterios']['c2']['valor_cop'], 0, ',', '.') }} · {{ number_format(ConsolidadoRentaService::aUvt($evaluacion['criterios']['c2']['proyectado_cop'] ?? $evaluacion['criterios']['c2']['valor_cop'], $evaluacion['parametros']['uvt']), 2, ',', '.') }} UVT</dd>
                    </dl>
                    <p class="ui-help" style="margin-top:.75rem">El resultado es informativo y puede cambiar hasta el cierre formal del año gravable.</p>
                </div>
                <dl class="mk-kv" style="min-width:260px">
                    <dt>Evaluado el</dt>
                    <dd>{{ now()->format('d/m/Y') }}</dd>
                    <dt>Estado periodo</dt>
                    <dd><span class="ui-badge ui-badge--warning">Año en curso</span></dd>
                </dl>
            </div>
        </div>
    @elseif($evaluacion['veredicto'] === 'OBLIGADO')
        {{-- VARIANTE OBLIGADO --}}
        <div class="mk-result danger" role="status">
            <div class="mk-row between">
                <div>
                    <span class="ui-badge ui-badge--danger">Resultado definitivo</span>
                    <p class="mk-result-title">OBLIGADO A DECLARAR</p>
                    @if($evaluacion['criterios']['c6']['cumple'] && $evaluacion['resumen']['criterios_cumplidos'] === 1)
                        <p class="mk-muted" style="margin:0">Tu perfil indica que eres <strong>responsable de IVA</strong> (criterio C6). Esto te obliga a declarar sin importar los demás criterios.</p>
                    @else
                        <p class="mk-muted" style="margin:0">Cumples <strong>{{ $evaluacion['resumen']['criterios_cumplidos'] }} de los 6 criterios</strong> evaluados según el Estatuto Tributario. Recuerda que basta con cumplir solo uno para estar legalmente obligado.</p>
                    @endif
                </div>
                <dl class="mk-kv" style="min-width:260px">
                    <dt>Evaluado el</dt>
                    <dd>{{ $declaracion->updated_at ? $declaracion->updated_at->format('d/m/Y') : now()->format('d/m/Y') }}</dd>
                    <dt>Fecha límite</dt>
                    <dd class="mk-strong">{{ $declaracion->fecha_limite_presentacion ? $declaracion->fecha_limite_presentacion->format('d/m/Y') : '15/09/' . ($anio + 1) }}</dd>
                    <dt>Estado</dt>
                    <dd>
                        @if($declaracion->estado_declaracion === 'PRESENTADA')
                            <span class="ui-badge ui-badge--success">Presentada</span>
                        @else
                            <span class="ui-badge ui-badge--warning">Pendiente</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
    @elseif($evaluacion['veredicto'] === 'INCOMPLETO')
        {{-- VARIANTE INCOMPLETO --}}
        <div class="mk-result warning" role="status">
            <span class="ui-badge ui-badge--warning">Incompleto</span>
            <p class="mk-result-title">Falta confirmar consignaciones bancarias</p>
            <div class="mk-alert" style="margin-top:.75rem">
                <span class="mk-icon">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10.3 4.2L2.8 17.5A2 2 0 0 0 4.5 20.5h15a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0z"/><path d="M12 9.5v4M12 17h.01"/></svg>
                </span>
                <div>
                    <strong>Tus inversiones nuevas del año suman $ {{ number_format($declaracion->c5_piso_inversiones_cop, 0, ',', '.') }} ({{ number_format(ConsolidadoRentaService::aUvt($declaracion->c5_piso_inversiones_cop, $evaluacion['parametros']['uvt']), 2, ',', '.') }} UVT), pero no has confirmado el total de consignaciones bancarias de {{ $anio }}.</strong>
                    <p>Confirma ese dato para descartar o activar el criterio C5. Por ahora ningún otro criterio se cumple.</p>
                </div>
            </div>
            <div class="ui-inline" style="margin-top:1.25rem">
                <a class="ui-button ui-button--primary" href="{{ route('renta.complementaria', ['anio' => $anio]) }}">Completar información</a>
            </div>
        </div>
    @else
        {{-- VARIANTE NO OBLIGADO --}}
        <div class="mk-result success" role="status">
            <span class="ui-badge ui-badge--success">Resultado definitivo</span>
            <p class="mk-result-title">NO OBLIGADO A DECLARAR</p>
            <p class="mk-small" style="margin:0">No cumples ninguno de los 6 criterios de obligación tributaria del año gravable {{ $anio }}.</p>
            <div class="mk-alert info" style="margin-top:1rem">
                <span class="mk-icon">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.5h.01"/></svg>
                </span>
                <div>
                    <strong>Puedes declarar de forma voluntaria.</strong>
                    <p>Si te practicaron retenciones en la fuente por salarios o por rendimientos de CDTs y cuentas de alto rendimiento, puedes presentar tu declaración voluntariamente para solicitar ese saldo a favor.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- TABLA DETALLADA DE LOS 6 CRITERIOS --}}
    <div class="ui-card">
        <h2 class="mk-h2">Resultado por criterio</h2>
        <div class="mk-table-wrap">
            <table class="ui-table">
                <caption>Un criterio se cumple cuando el valor supera (o iguala, en el caso de los ingresos) el tope. El margen es lo que falta para llegar al tope.</caption>
                <thead>
                    <tr>
                        <th>Criterio y condición</th>
                        <th>Fuente principal</th>
                        <th class="num">Valor (COP)</th>
                        <th class="num">Valor (UVT)</th>
                        <th class="num">Tope</th>
                        <th>Estado</th>
                        <th class="num">Margen hasta el tope</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- C1 --}}
                    @php $c1 = $evaluacion['criterios']['c1']; @endphp
                    <tr @class(['is-row-alert' => $c1['cumple']])>
                        <td>
                            <strong>C1 · Patrimonio bruto al 31/12</strong><br>
                            <span class="mk-meta">Mayor que 4.500 UVT</span>
                        </td>
                        <td class="mk-meta">Módulo Inversiones (Saldos 31/12)</td>
                        <td class="num" data-money>$ {{ number_format($c1['valor_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($c1['valor_uvt'], 2, ',', '.') }}</td>
                        <td class="num">$ {{ number_format($c1['tope_cop'], 0, ',', '.') }}<br><span class="mk-meta">4.500 UVT</span></td>
                        <td>
                            @if($c1['cumple'])
                                <span class="ui-badge ui-badge--danger">Cumple</span>
                            @elseif($c1['cerca'])
                                <span class="ui-badge ui-badge--warning">No cumple · cerca</span>
                            @else
                                <span class="ui-badge">No cumple</span>
                            @endif
                        </td>
                        <td class="num">
                            @if($c1['cumple'])
                                Superado<br><span class="mk-meta">por $ {{ number_format($c1['valor_cop'] - $c1['tope_cop'], 0, ',', '.') }}</span>
                            @else
                                $ {{ number_format($c1['margen_disponible_cop'], 0, ',', '.') }}
                            @endif
                        </td>
                    </tr>

                    {{-- C2 --}}
                    @php $c2 = $evaluacion['criterios']['c2']; @endphp
                    <tr @class(['is-row-alert' => $c2['cumple']])>
                        <td>
                            <strong class="mk-strong">C2 · Ingresos brutos del año</strong><br>
                            <span class="mk-meta">Mayor o igual a 1.400 UVT</span>
                        </td>
                        <td class="mk-meta">Ingresos + Rendimientos Inv.</td>
                        <td class="num" data-money>$ {{ number_format($c2['valor_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($c2['valor_uvt'], 2, ',', '.') }}</td>
                        <td class="num">$ {{ number_format($c2['tope_cop'], 0, ',', '.') }}<br><span class="mk-meta">1.400 UVT</span></td>
                        <td>
                            @if($c2['cumple'])
                                <span class="ui-badge ui-badge--danger">Cumple</span>
                            @elseif($c2['cerca'])
                                <span class="ui-badge ui-badge--warning">No cumple · cerca</span>
                            @else
                                <span class="ui-badge">No cumple</span>
                            @endif
                        </td>
                        <td class="num">
                            @if($c2['cumple'])
                                Superado<br><span class="mk-meta">por $ {{ number_format($c2['valor_cop'] - $c2['tope_cop'], 0, ',', '.') }}</span>
                            @else
                                $ {{ number_format($c2['margen_disponible_cop'], 0, ',', '.') }}
                            @endif
                        </td>
                    </tr>

                    {{-- C3 --}}
                    @php $c3 = $evaluacion['criterios']['c3']; @endphp
                    <tr @class(['is-row-alert' => $c3['cumple']])>
                        <td>
                            <strong>C3 · Consumos con tarjeta de crédito</strong><br>
                            <span class="mk-meta">Mayor que 1.400 UVT</span>
                        </td>
                        <td class="mk-meta">Info. complementaria</td>
                        <td class="num" data-money>$ {{ number_format($c3['valor_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($c3['valor_uvt'], 2, ',', '.') }}</td>
                        <td class="num">$ {{ number_format($c3['tope_cop'], 0, ',', '.') }}<br><span class="mk-meta">1.400 UVT</span></td>
                        <td>
                            @if($c3['cumple'])
                                <span class="ui-badge ui-badge--danger">Cumple</span>
                            @elseif($c3['cerca'])
                                <span class="ui-badge ui-badge--warning">No cumple · cerca</span>
                            @else
                                <span class="ui-badge">No cumple</span>
                            @endif
                        </td>
                        <td class="num">
                            @if($c3['cumple'])
                                Superado<br><span class="mk-meta">por $ {{ number_format($c3['valor_cop'] - $c3['tope_cop'], 0, ',', '.') }}</span>
                            @else
                                $ {{ number_format($c3['margen_disponible_cop'], 0, ',', '.') }}
                            @endif
                        </td>
                    </tr>

                    {{-- C4 --}}
                    @php $c4 = $evaluacion['criterios']['c4']; @endphp
                    <tr @class(['is-row-alert' => $c4['cumple']])>
                        <td>
                            <strong>C4 · Compras y consumos totales</strong><br>
                            <span class="mk-meta">Mayor que 1.400 UVT</span>
                        </td>
                        <td class="mk-meta">Info. complementaria</td>
                        <td class="num" data-money>$ {{ number_format($c4['valor_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($c4['valor_uvt'], 2, ',', '.') }}</td>
                        <td class="num">$ {{ number_format($c4['tope_cop'], 0, ',', '.') }}<br><span class="mk-meta">1.400 UVT</span></td>
                        <td>
                            @if($c4['cumple'])
                                <span class="ui-badge ui-badge--danger">Cumple</span>
                            @elseif($c4['cerca'])
                                <span class="ui-badge ui-badge--warning">No cumple · cerca</span>
                            @else
                                <span class="ui-badge">No cumple</span>
                            @endif
                        </td>
                        <td class="num">
                            @if($c4['cumple'])
                                Superado<br><span class="mk-meta">por $ {{ number_format($c4['valor_cop'] - $c4['tope_cop'], 0, ',', '.') }}</span>
                            @else
                                $ {{ number_format($c4['margen_disponible_cop'], 0, ',', '.') }}
                            @endif
                        </td>
                    </tr>

                    {{-- C5 --}}
                    @php $c5 = $evaluacion['criterios']['c5']; @endphp
                    <tr @class(['is-row-alert' => $c5['cumple']])>
                        <td>
                            <strong class="mk-strong">C5 · Consignaciones, depósitos o inversiones</strong><br>
                            <span class="mk-meta">Mayor que 1.400 UVT</span>
                        </td>
                        <td class="mk-meta">Inversiones ($ {{ number_format($c5['piso_inversiones_cop'] / 1000000, 1, ',', '.') }}M) + Bancos</td>
                        <td class="num" data-money>$ {{ number_format($c5['valor_cop'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($c5['valor_uvt'], 2, ',', '.') }}</td>
                        <td class="num">$ {{ number_format($c5['tope_cop'], 0, ',', '.') }}<br><span class="mk-meta">1.400 UVT</span></td>
                        <td>
                            @if($c5['cumple'])
                                <span class="ui-badge ui-badge--danger">Cumple</span>
                            @elseif($c5['cerca'])
                                <span class="ui-badge ui-badge--warning">No cumple · cerca</span>
                            @else
                                <span class="ui-badge">No cumple</span>
                            @endif
                        </td>
                        <td class="num">
                            @if($c5['cumple'])
                                Superado<br><span class="mk-meta">por $ {{ number_format($c5['valor_cop'] - $c5['tope_cop'], 0, ',', '.') }}</span>
                            @else
                                $ {{ number_format($c5['margen_disponible_cop'], 0, ',', '.') }}
                            @endif
                        </td>
                    </tr>

                    {{-- C6 --}}
                    @php $c6 = $evaluacion['criterios']['c6']; @endphp
                    <tr @class(['is-row-alert' => $c6['cumple']])>
                        <td>
                            <strong>C6 · Responsable de IVA</strong><br>
                            <span class="mk-meta">Igual a Sí (perfil)</span>
                        </td>
                        <td class="mk-meta">Perfil de usuario</td>
                        <td class="num">{{ $c6['cumple'] ? 'Sí' : 'No' }}</td>
                        <td class="num">—</td>
                        <td class="num">—</td>
                        <td>
                            @if($c6['cumple'])
                                <span class="ui-badge ui-badge--danger">Cumple</span>
                            @else
                                <span class="ui-badge">No cumple</span>
                            @endif
                        </td>
                        <td class="num">—</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- PROGRESO DE TOPES Y PARÁMETROS --}}
    <div class="mk-grid-2">
        <div class="ui-card">
            <h2>Qué tan cerca estás de cada tope</h2>
            <ul class="finance-distribution" style="padding:0;margin:0;list-style:none">
                @php
                    $pctC1 = $c1['tope_cop'] > 0 ? round(($c1['valor_cop'] / $c1['tope_cop']) * 100, 2) : 0;
                    $pctC3 = $c3['tope_cop'] > 0 ? round(($c3['valor_cop'] / $c3['tope_cop']) * 100, 2) : 0;
                    $pctC4 = $c4['tope_cop'] > 0 ? round(($c4['valor_cop'] / $c4['tope_cop']) * 100, 2) : 0;
                    $pctC2 = $c2['tope_cop'] > 0 ? round(($c2['valor_cop'] / $c2['tope_cop']) * 100, 2) : 0;
                    $pctC5 = $c5['tope_cop'] > 0 ? round(($c5['valor_cop'] / $c5['tope_cop']) * 100, 2) : 0;
                @endphp
                <li style="margin-bottom:.75rem">
                    <div class="mk-row between mk-small">
                        <span>C1 · Patrimonio en inversiones</span>
                        <span>{{ number_format($pctC1, 2, ',', '.') }} % del tope</span>
                    </div>
                    <div @class(['mk-bar', 'over' => $pctC1 > 100])>
                        <i style="width: {{ min(100, $pctC1) }}%"></i>
                    </div>
                </li>
                <li style="margin-bottom:.75rem">
                    <div class="mk-row between mk-small">
                        <span>C3 · Tarjeta de crédito</span>
                        <span>{{ number_format($pctC3, 2, ',', '.') }} %</span>
                    </div>
                    <div @class(['mk-bar', 'over' => $pctC3 > 100])>
                        <i style="width: {{ min(100, $pctC3) }}%"></i>
                    </div>
                </li>
                <li style="margin-bottom:.75rem">
                    <div class="mk-row between mk-small">
                        <span>C4 · Compras y consumos</span>
                        <span>{{ number_format($pctC4, 2, ',', '.') }} %</span>
                    </div>
                    <div @class(['mk-bar', 'over' => $pctC4 > 100])>
                        <i style="width: {{ min(100, $pctC4) }}%"></i>
                    </div>
                </li>
                <li style="margin-bottom:.75rem">
                    <div class="mk-row between mk-small">
                        <span>C2 · Ingresos + Rendimientos</span>
                        <span>{{ $pctC2 >= 100 ? 'Superado (' . number_format($pctC2, 2, ',', '.') . ' %)' : number_format($pctC2, 2, ',', '.') . ' %' }}</span>
                    </div>
                    <div @class(['mk-bar', 'over' => $pctC2 >= 100])>
                        <i style="width: {{ min(100, $pctC2) }}%"></i>
                    </div>
                </li>
                <li style="margin-bottom:.75rem">
                    <div class="mk-row between mk-small">
                        <span>C5 · Inversiones y consignaciones</span>
                        <span>{{ $pctC5 > 100 ? 'Superado (' . number_format($pctC5, 2, ',', '.') . ' %)' : number_format($pctC5, 2, ',', '.') . ' %' }}</span>
                    </div>
                    <div @class(['mk-bar', 'over' => $pctC5 > 100])>
                        <i style="width: {{ min(100, $pctC5) }}%"></i>
                    </div>
                </li>
            </ul>
            <p class="ui-help" style="margin-top:1rem">La gráfica representa visualmente la cercanía a los límites tributarios fijados en UVT.</p>
        </div>

        <div class="ui-card">
            <h2>Parámetros usados</h2>
            <dl class="mk-kv">
                <dt>UVT año gravable {{ $anio }}</dt>
                <dd>$ {{ number_format($evaluacion['parametros']['uvt'], 0, ',', '.') }}</dd>
                <dt>Componente inflacionario {{ $anio }}</dt>
                <dd>{{ number_format($evaluacion['parametros']['pct_componente_inflacionario'] * 100, 2, ',', '.') }} %</dd>
                <dt>Tope de patrimonio (4.500 UVT)</dt>
                <dd>$ {{ number_format($evaluacion['parametros']['tope_patrimonio_uvt'] * $evaluacion['parametros']['uvt'], 0, ',', '.') }}</dd>
                <dt>Tope de ingresos e inversiones (1.400 UVT)</dt>
                <dd>$ {{ number_format($evaluacion['parametros']['tope_ingresos_uvt'] * $evaluacion['parametros']['uvt'], 0, ',', '.') }}</dd>
                <dt>Responsable de IVA (perfil)</dt>
                <dd>{{ $declaracion->c6_responsable_iva ? 'Sí' : 'No' }}</dd>
                <dt>Fuente de los valores</dt>
                <dd>Ingresos + Inversiones + Ajustes</dd>
            </dl>
            <p class="ui-help" style="margin-top:1.25rem">
                <a href="{{ route('renta.complementaria', ['anio' => $anio]) }}">Revisar o actualizar información complementaria →</a>
            </p>
        </div>
    </div>

    <p class="ui-help finance-legal">Valores estimados con fines informativos. No constituyen asesoría tributaria, contable ni financiera.</p>
</div>
@endsection

