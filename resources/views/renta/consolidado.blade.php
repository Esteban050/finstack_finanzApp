@extends('layouts.app')

@section('title', 'Declaración de renta · Consolidado y evaluación ' . $anio)

@section('content')
<div class="finance-page">
    <header class="income-page-header">
        <div>
            <h1>¿Debo declarar renta? · Consolidado del año gravable {{ $anio }}</h1>
            <p>Lo que sumaron tus registros del 1 de enero al 31 de diciembre, comparado con los topes de la DIAN para personas naturales residentes (UVT $ {{ number_format($parametros['uvt'], 0, ',', '.') }}).</p>
        </div>
        <div class="ui-inline">
            <div class="mk-year">
                <span class="mk-meta">Año gravable</span>
                <select id="select-anio" onchange="window.location.href = '{{ url('/renta') }}/' + this.value + '/consolidado'">
                    <option value="2025" @selected($anio === 2025)>2025</option>
                    <option value="2026" @selected($anio === 2026)>2026</option>
                </select>
            </div>
            <a class="ui-button ui-button--secondary" href="{{ route('renta.complementaria', ['anio' => $anio]) }}">Completar información</a>
            <a class="ui-button ui-button--primary" href="{{ route('renta.pdf', ['anio' => $anio]) }}" target="_blank">Constancia PDF</a>
        </div>
    </header>

    {{-- BLOQUE 1: VEREDICTO DE LA DIAN + IMPUESTO ESTIMADO --}}
    <div class="mk-verdict">
        {{-- TARJETA IZQUIERDA: VEREDICTO --}}
        @if($evaluacion['es_preliminar'])
            <div class="mk-result warning" role="status">
                <div class="mk-row" style="gap:.5rem">
                    <span class="ui-badge ui-badge--warning">Preliminar · año en curso</span>
                </div>
                <p class="mk-result-title">{{ $evaluacion['veredicto_label'] }}</p>
                <p class="mk-small" style="margin:0">Año gravable {{ $anio }} (UVT $ {{ number_format($parametros['uvt'], 0, ',', '.') }}). Con los meses transcurridos a la fecha, el sistema proyecta tus valores al 31/12/{{ $anio }} usando el promedio mensual.</p>
                <dl class="mk-facts">
                    <div><dt>Evaluado el</dt><dd>{{ now()->format('d/m/Y') }}</dd></div>
                    <div><dt>Periodo</dt><dd>Año en curso</dd></div>
                    <div><dt>Estado</dt><dd><span class="ui-badge ui-badge--warning">Informativo</span></dd></div>
                </dl>
            </div>
        @elseif($evaluacion['veredicto'] === 'OBLIGADO')
            <div class="mk-result danger" role="status">
                <div class="mk-row" style="gap:.5rem">
                    <span class="ui-badge ui-badge--danger">Resultado definitivo</span>
                </div>
                <p class="mk-result-title">OBLIGADO A DECLARAR</p>
                @php
                    $cumplidosNombres = [];
                    foreach($evaluacion['criterios'] as $c) {
                        if ($c['cumple']) {
                            $cumplidosNombres[] = '<strong>' . e(strtolower($c['nombre'])) . '</strong>';
                        }
                    }
                @endphp
                <p class="mk-small" style="margin:0">
                    Cumples {{ $evaluacion['resumen']['criterios_cumplidos'] }} de los 6 criterios:
                    {!! implode(', ', $cumplidosNombres) !!}. Recuerda que basta con cumplir solo uno para estar legalmente obligado.
                </p>
                <dl class="mk-facts">
                    <div><dt>Evaluado el</dt><dd>{{ $declaracion->updated_at ? $declaracion->updated_at->format('d/m/Y') : now()->format('d/m/Y') }}</dd></div>
                    <div><dt>Fecha límite</dt><dd>{{ $declaracion->fecha_limite_presentacion ? $declaracion->fecha_limite_presentacion->format('d/m/Y') : '15/09/' . ($anio + 1) }}</dd></div>
                    <div>
                        <dt>Estado</dt>
                        <dd>
                            @if($declaracion->estado_declaracion === 'PRESENTADA')
                                <span class="ui-badge ui-badge--success">Presentada · {{ $declaracion->fecha_presentacion_declaracion ? $declaracion->fecha_presentacion_declaracion->format('d/m/Y') : '' }}</span>
                            @else
                                <span class="ui-badge ui-badge--warning">Pendiente</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        @elseif($evaluacion['veredicto'] === 'INCOMPLETO')
            <div class="mk-result warning" role="status">
                <div class="mk-row" style="gap:.5rem">
                    <span class="ui-badge ui-badge--warning">Incompleto</span>
                </div>
                <p class="mk-result-title">FALTA INFORMACIÓN</p>
                <p class="mk-small" style="margin:0">
                    No has confirmado el total de consignaciones bancarias de {{ $anio }}. Sin ese dato no es posible determinar si debes declarar.
                </p>
                <dl class="mk-facts">
                    <div><dt>Evaluado el</dt><dd>{{ now()->format('d/m/Y') }}</dd></div>
                    <div><dt>Piso inversiones</dt><dd>$ {{ number_format($declaracion->c5_piso_inversiones_cop, 0, ',', '.') }}</dd></div>
                    <div><dt>Acción</dt><dd><a href="{{ route('renta.complementaria', ['anio' => $anio]) }}">Confirmar</a></dd></div>
                </dl>
            </div>
        @else
            <div class="mk-result success" role="status">
                <div class="mk-row" style="gap:.5rem">
                    <span class="ui-badge ui-badge--success">Resultado definitivo</span>
                </div>
                <p class="mk-result-title">NO OBLIGADO A DECLARAR</p>
                <p class="mk-small" style="margin:0">No cumples ninguno de los 6 criterios de obligación tributaria del año gravable {{ $anio }}.</p>
                <dl class="mk-facts">
                    <div><dt>Evaluado el</dt><dd>{{ $declaracion->updated_at ? $declaracion->updated_at->format('d/m/Y') : now()->format('d/m/Y') }}</dd></div>
                    <div><dt>Criterios cumplidos</dt><dd>0 de 6</dd></div>
                    <div><dt>Declaración voluntaria</dt><dd><span class="ui-badge ui-badge--success">Disponible</span></dd></div>
                </dl>
            </div>
        @endif

        {{-- TARJETA DERECHA: IMPUESTO ESTIMADO --}}
        <div class="ui-card mk-pay">
            <div class="mk-row" style="gap:.5rem">
                <span class="mk-meta">Impuesto estimado · cédula general</span>
            </div>
            @if(($estimacion['paso8']['tipo_resultado'] ?? '') === 'SALDO_A_FAVOR')
                <p class="mk-small mk-strong" style="margin:.75rem 0 0">Saldo a favor estimado</p>
                <p class="mk-amount" data-result="positive" style="color:var(--ui-success)">$ {{ number_format($estimacion['paso8']['saldo_valor_mostrado_cop'] ?? 0, 0, ',', '.') }}</p>
            @else
                <p class="mk-small mk-strong" style="margin:.75rem 0 0">Saldo a pagar estimado</p>
                <p class="mk-amount" data-result="negative" style="color:var(--ui-danger)">$ {{ number_format($estimacion['paso8']['saldo_valor_mostrado_cop'] ?? 0, 0, ',', '.') }}</p>
            @endif
            <dl class="mk-kv" style="margin:.75rem 0 1.25rem">
                <dt>Impuesto</dt><dd>$ {{ number_format($estimacion['paso7']['impuesto_redondeado_cop'] ?? 0, 0, ',', '.') }}</dd>
                <dt>Retenciones</dt><dd>− $ {{ number_format($estimacion['paso8']['retenciones_totales_cop'] ?? 0, 0, ',', '.') }}</dd>
            </dl>
            <a class="ui-button ui-button--secondary" href="{{ route('renta.estimacion', ['anio' => $anio]) }}">Ver cálculo paso a paso</a>
            <p class="mk-meta" style="margin:.75rem 0 0">Podrías pagar menos: <a href="{{ route('renta.estimacion', ['anio' => $anio]) }}#s05-beneficios">revisa los beneficios</a></p>
        </div>
    </div>

    {{-- BLOQUE 2: TABLA DE CONCEPTOS DEL AÑO Y TOPES (VALOR EN COP Y EN UVT) --}}
    <div class="ui-card">
        <div class="mk-row between" style="margin-bottom:1rem">
            <h2 style="margin:0">Conceptos del año y topes</h2>
            <span class="mk-meta">UVT {{ $anio }} = $ {{ number_format($parametros['uvt'], 0, ',', '.') }} · valores con ajustes</span>
        </div>
        <div class="mk-table-wrap">
            <table class="ui-table mk-criteria">
                <colgroup>
                    <col style="width:28%">
                    <col style="width:18%">
                    <col style="width:16%">
                    <col style="width:14%">
                    <col style="width:14%">
                    <col style="width:10%">
                </colgroup>
                <caption class="sr-only">Valor sumado de cada concepto en pesos y UVT, tope legal, estado y margen hasta el tope.</caption>
                <thead>
                    <tr>
                        <th>Concepto y condición</th>
                        <th class="num">Valor sumado</th>
                        <th class="num">Tope</th>
                        <th>Estado</th>
                        <th class="num">Margen hasta el tope</th>
                        <th class="mk-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Ordenar: Cumplidos primero, luego cercanos, luego normales
                        $criteriosOrdenados = $evaluacion['criterios'];
                        uasort($criteriosOrdenados, function ($a, $b) {
                            if ($a['cumple'] === $b['cumple']) {
                                if ($a['cerca'] === $b['cerca']) return 0;
                                return $a['cerca'] ? -1 : 1;
                            }
                            return $a['cumple'] ? -1 : 1;
                        });
                    @endphp

                    @foreach($criteriosOrdenados as $cid => $crit)
                        <tr class="{{ $crit['cumple'] ? 'is-row-alert' : ($crit['cerca'] ? 'is-row-warning' : '') }}">
                            <td>
                                <span class="mk-strong">{{ $crit['nombre'] }}</span>
                                <br>
                                <span class="mk-meta">{{ strtoupper($crit['id']) }} · {{ $crit['operador'] }} {{ $crit['tope_uvt'] ? number_format($crit['tope_uvt'], 0, ',', '.') . ' UVT' : 'Dato de perfil' }}</span>
                            </td>
                            <td class="num">
                                @if($crit['valor_cop'] !== null)
                                    <span data-money class="mk-strong">$ {{ number_format($crit['valor_cop'], 0, ',', '.') }}</span>
                                    <br>
                                    <span class="mk-meta">{{ number_format($crit['valor_uvt'], 2, ',', '.') }} UVT</span>
                                @else
                                    <span class="mk-strong">{{ $usuario->es_responsable_iva ? 'Sí' : 'No' }}</span>
                                @endif
                            </td>
                            <td class="num">
                                @if($crit['tope_cop'] !== null)
                                    $ {{ number_format($crit['tope_cop'], 0, ',', '.') }}
                                    <br>
                                    <span class="mk-meta">{{ number_format($crit['tope_uvt'], 0, ',', '.') }} UVT</span>
                                @else
                                    Sí
                                @endif
                            </td>
                            <td>
                                @if($crit['cumple'])
                                    <span class="ui-badge ui-badge--danger">Cumple</span>
                                @elseif($crit['cerca'])
                                    <span class="ui-badge ui-badge--warning">No cumple · cerca</span>
                                @else
                                    <span class="ui-badge">No cumple</span>
                                @endif
                            </td>
                            <td class="num">
                                @if($crit['tope_cop'] === null)
                                    —
                                @elseif($crit['cumple'])
                                    Superado
                                    <br>
                                    <span class="mk-meta">por $ {{ number_format(abs($crit['valor_cop'] - $crit['tope_cop']), 0, ',', '.') }}</span>
                                @else
                                    $ {{ number_format($crit['margen_disponible_cop'], 0, ',', '.') }}
                                    <br>
                                    <span class="mk-meta">{{ number_format($crit['margen_disponible_uvt'], 2, ',', '.') }} UVT</span>
                                @endif
                            </td>
                            <td class="mk-right">
                                <button type="button" class="ui-button ui-button--sm ui-button--ghost" onclick="toggleDetail('detail-{{ $crit['id'] }}')">Ver detalle</button>
                            </td>
                        </tr>
                    @endforeach

                    {{-- SUBGRUPO: OTROS VALORES DEL AÑO (SIN TOPE) --}}
                    <tr class="mk-group">
                        <th colspan="6" scope="colgroup">Otros valores del año · no tienen tope, se usan en la estimación del impuesto</th>
                    </tr>
                    <tr>
                        <td>
                            <span class="mk-strong">Retenciones en la fuente practicadas</span>
                            <br><span class="mk-meta">Laborales ($ {{ number_format($consolidado['retenciones_practicadas']['laborales_cop'], 0, ',', '.') }}) + Inversiones ($ {{ number_format($consolidado['retenciones_practicadas']['inversiones_cop'], 0, ',', '.') }})</span>
                        </td>
                        <td class="num">
                            <span data-money class="mk-strong">$ {{ number_format($consolidado['retenciones_practicadas']['total_cop'], 0, ',', '.') }}</span>
                            <br><span class="mk-meta">{{ number_format($consolidado['retenciones_practicadas']['total_uvt'], 2, ',', '.') }} UVT</span>
                        </td>
                        <td colspan="3" class="mk-meta">Crédito fiscal directo contra el impuesto de renta</td>
                        <td class="mk-right">
                            <button type="button" class="ui-button ui-button--sm ui-button--ghost" onclick="toggleDetail('detail-retenciones')">Ver detalle</button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="mk-strong">Aportes obligatorios a salud y pensión</span>
                            <br><span class="mk-meta">Deducciones de ley sobre rentas laborales (INCRNGO Art. 55-56 ET)</span>
                        </td>
                        <td class="num">
                            <span data-money class="mk-strong">$ {{ number_format($consolidado['incrngo']['salud_pension_cop'], 0, ',', '.') }}</span>
                            <br><span class="mk-meta">{{ number_format($consolidado['incrngo']['salud_pension_uvt'], 2, ',', '.') }} UVT</span>
                        </td>
                        <td colspan="3" class="mk-meta">Ingreso no constitutivo de renta ni ganancia ocasional</td>
                        <td class="mk-right">
                            <button type="button" class="ui-button ui-button--sm ui-button--ghost" onclick="toggleDetail('detail-salud')">Ver detalle</button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="mk-strong">Rentas exentas y deducciones por inversiones</span>
                            <br><span class="mk-meta">Aportes voluntarios FPV + Cesantías e intereses exentos</span>
                        </td>
                        <td class="num">
                            <span data-money class="mk-strong">$ {{ number_format($consolidado['rentas_exentas_inversiones']['total_cop'], 0, ',', '.') }}</span>
                            <br><span class="mk-meta">{{ number_format($consolidado['rentas_exentas_inversiones']['total_uvt'], 2, ',', '.') }} UVT</span>
                        </td>
                        <td colspan="3" class="mk-meta">Sujeto a límite conjunto del 40 % / 1.340 UVT en Cédula General</td>
                        <td class="mk-right">
                            <a class="ui-button ui-button--sm ui-button--ghost" href="{{ route('renta.estimacion', ['anio' => $anio]) }}">Ver en estimación</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- BLOQUE 3: SECCIONES DE DETALLE DESPLEGABLES (DRILL-DOWNS) --}}
    <div id="drawer-container">
        {{-- Detalle C1: Patrimonio e Inversiones --}}
        <div id="detail-c1" class="mk-drawer" style="display:none">
            <div class="mk-row between">
                <h3 style="margin:0">Detalle del Criterio C1: Patrimonio bruto al 31/12/{{ $anio }}</h3>
                <button type="button" class="ui-button ui-button--sm ui-button--ghost" onclick="toggleDetail('detail-c1')">Cerrar ✕</button>
            </div>
            <p class="mk-meta">Composición consolidada de tus posiciones patrimoniales en los 6 productos de inversión más ajustes manuales.</p>
            <div class="mk-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Entidad / Activo</th>
                            <th>Tratamiento tributario</th>
                            <th class="num">Rendimiento anual</th>
                            <th class="num">Retención anual</th>
                            <th class="num">Saldo / Costo fiscal</th>
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
                                <td colspan="6" class="mk-muted" style="text-align:center;padding:1rem">No se registraron inversiones patrimoniales al 31 de diciembre.</td>
                            </tr>
                        @endforelse
                        @if($declaracion->c1_ajuste_manual_cop != 0)
                            <tr class="is-row-current">
                                <td colspan="3"><span class="mk-strong">Ajuste manual del usuario</span><br><span class="mk-meta">"{{ $declaracion->justificacion_ajustes['c1'] ?? 'Ajuste patrimonial' }}"</span></td>
                                <td class="num">—</td>
                                <td class="num">—</td>
                                <td class="num mk-strong" data-money>{{ $declaracion->c1_ajuste_manual_cop > 0 ? '+' : '' }} $ {{ number_format($declaracion->c1_ajuste_manual_cop, 0, ',', '.') }}</td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5">Total patrimonio evaluado (COP y UVT)</th>
                            <th class="num mk-strong">
                                $ {{ number_format($evaluacion['criterios']['c1']['valor_cop'], 0, ',', '.') }}
                                <br><span class="mk-meta">{{ number_format($evaluacion['criterios']['c1']['valor_uvt'], 2, ',', '.') }} UVT</span>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Detalle C2: Ingresos Brutos --}}
        <div id="detail-c2" class="mk-drawer" style="display:none">
            <div class="mk-row between">
                <h3 style="margin:0">Detalle del Criterio C2: Ingresos brutos del año</h3>
                <button type="button" class="ui-button ui-button--sm ui-button--ghost" onclick="toggleDetail('detail-c2')">Cerrar ✕</button>
            </div>
            <p class="mk-meta">Suma total de ingresos laborales, rentas de capital (rendimientos de inversiones) y rentas no laborales.</p>
            <div class="mk-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Cédula / Concepto</th>
                            <th>Origen de datos</th>
                            <th class="num">Valor (COP)</th>
                            <th class="num">Valor (UVT)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Rentas de trabajo (Salarios, honorarios y cesantías)</td>
                            <td class="mk-meta">Módulo Ingresos + Cesantías consignadas</td>
                            <td class="num" data-money>$ {{ number_format($consolidado['ingresos_brutos']['trabajo_cop'] + $consolidado['ingresos_brutos']['cesantias_cop'], 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($consolidado['ingresos_brutos']['trabajo_uvt'] + $consolidado['ingresos_brutos']['cesantias_uvt'], 2, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Rentas de capital (Intereses de CDTs, Cuentas de Rendimiento y FICs)</td>
                            <td class="mk-meta">Módulo Inversiones (Rendimientos generados)</td>
                            <td class="num" data-money>$ {{ number_format($consolidado['ingresos_brutos']['capital_inversiones_cop'], 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($consolidado['ingresos_brutos']['capital_inversiones_uvt'], 2, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Rentas no laborales (Utilidad venta de ETFs/Acciones &lt; 2 años)</td>
                            <td class="mk-meta">Módulo Inversiones (Retiros y ventas)</td>
                            <td class="num" data-money>$ {{ number_format($consolidado['ingresos_brutos']['no_laborales_cop'] + $consolidado['ingresos_brutos']['utilidad_acciones_menor_2a_cop'], 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($consolidado['ingresos_brutos']['no_laborales_uvt'] + $consolidado['ingresos_brutos']['utilidad_acciones_menor_2a_uvt'], 2, ',', '.') }}</td>
                        </tr>
                        @if($declaracion->c2_ajuste_manual_cop != 0)
                            <tr class="is-row-current">
                                <td><span class="mk-strong">Ajuste manual del usuario</span><br><span class="mk-meta">"{{ $declaracion->justificacion_ajustes['c2'] ?? 'Ajuste de ingresos' }}"</span></td>
                                <td class="mk-meta">Información complementaria</td>
                                <td class="num mk-strong" data-money>{{ $declaracion->c2_ajuste_manual_cop > 0 ? '+' : '' }} $ {{ number_format($declaracion->c2_ajuste_manual_cop, 0, ',', '.') }}</td>
                                <td class="num">{{ number_format(App\Services\Renta\ConsolidadoRentaService::aUvt($declaracion->c2_ajuste_manual_cop, $parametros['uvt']), 2, ',', '.') }}</td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2">Total evaluado</th>
                            <th class="num mk-strong">$ {{ number_format($evaluacion['criterios']['c2']['valor_cop'], 0, ',', '.') }}</th>
                            <th class="num mk-strong">{{ number_format($evaluacion['criterios']['c2']['valor_uvt'], 2, ',', '.') }} UVT</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Detalle C3: Tarjeta de Crédito --}}
        <div id="detail-c3" class="mk-drawer" style="display:none">
            <div class="mk-row between">
                <h3 style="margin:0">Detalle del Criterio C3: Consumos mediante tarjeta de crédito</h3>
                <button type="button" class="ui-button ui-button--sm ui-button--ghost" onclick="toggleDetail('detail-c3')">Cerrar ✕</button>
            </div>
            <div class="mk-compose">
                <span><b>$ {{ number_format($declaracion->c3_consumos_tarjeta_cop, 0, ',', '.') }}</b> evaluados</span>
                <span aria-hidden="true">=</span>
                <span>Tope legal DIAN: <b>$ {{ number_format($evaluacion['criterios']['c3']['tope_cop'], 0, ',', '.') }}</b> (1.400 UVT)</span>
                <a href="{{ route('renta.complementaria', ['anio' => $anio]) }}" class="mk-small" style="margin-left:auto">Ver o ajustar en Información Complementaria →</a>
            </div>
        </div>

        {{-- Detalle C4: Compras Totales --}}
        <div id="detail-c4" class="mk-drawer" style="display:none">
            <div class="mk-row between">
                <h3 style="margin:0">Detalle del Criterio C4: Compras y consumos totales</h3>
                <button type="button" class="ui-button ui-button--sm ui-button--ghost" onclick="toggleDetail('detail-c4')">Cerrar ✕</button>
            </div>
            <div class="mk-compose">
                <span><b>$ {{ number_format($declaracion->c4_compras_totales_cop, 0, ',', '.') }}</b> evaluados</span>
                <span aria-hidden="true">=</span>
                <span>Tope legal DIAN: <b>$ {{ number_format($evaluacion['criterios']['c4']['tope_cop'], 0, ',', '.') }}</b> (1.400 UVT)</span>
                <a href="{{ route('renta.complementaria', ['anio' => $anio]) }}" class="mk-small" style="margin-left:auto">Ver o ajustar en Información Complementaria →</a>
            </div>
        </div>

        {{-- Detalle C5: Consignaciones --}}
        <div id="detail-c5" class="mk-drawer" style="display:none">
            <div class="mk-row between">
                <h3 style="margin:0">Detalle del Criterio C5: Consignaciones, depósitos e inversiones</h3>
                <button type="button" class="ui-button ui-button--sm ui-button--ghost" onclick="toggleDetail('detail-c5')">Cerrar ✕</button>
            </div>
            <div class="mk-compose">
                <span><b>$ {{ number_format($consolidado['piso_inversiones_c5']['total_cop'], 0, ',', '.') }}</b> de aperturas de inversión</span>
                <span aria-hidden="true">+</span>
                <span><b>$ {{ number_format(max(0, $declaracion->c5_consignaciones_declaradas_cop - $consolidado['piso_inversiones_c5']['total_cop']), 0, ',', '.') }}</b> de consignaciones bancarias</span>
                <span aria-hidden="true">=</span>
                <span><b>$ {{ number_format($evaluacion['criterios']['c5']['valor_cop'], 0, ',', '.') }}</b> evaluados ({{ number_format($evaluacion['criterios']['c5']['valor_uvt'], 2, ',', '.') }} UVT)</span>
                <a href="{{ route('renta.complementaria', ['anio' => $anio]) }}" class="mk-small" style="margin-left:auto">Actualizar certificados bancarios →</a>
            </div>
        </div>

        {{-- Detalle Retenciones --}}
        <div id="detail-retenciones" class="mk-drawer" style="display:none">
            <div class="mk-row between">
                <h3 style="margin:0">Detalle de Retenciones en la fuente practicadas</h3>
                <button type="button" class="ui-button ui-button--sm ui-button--ghost" onclick="toggleDetail('detail-retenciones')">Cerrar ✕</button>
            </div>
            <div class="mk-compose">
                <span>Retenciones laborales (certificados de ingresos): <b>$ {{ number_format($consolidado['retenciones_practicadas']['laborales_cop'], 0, ',', '.') }}</b></span>
                <span aria-hidden="true">+</span>
                <span>Retenciones financieras (CDTs, cuentas, FICs): <b>$ {{ number_format($consolidado['retenciones_practicadas']['inversiones_cop'], 0, ',', '.') }}</b></span>
                <span aria-hidden="true">=</span>
                <span><b>$ {{ number_format($consolidado['retenciones_practicadas']['total_cop'], 0, ',', '.') }}</b> deducibles directamente del impuesto</span>
            </div>
        </div>

        {{-- Detalle Salud y Pensión --}}
        <div id="detail-salud" class="mk-drawer" style="display:none">
            <div class="mk-row between">
                <h3 style="margin:0">Detalle de Aportes obligatorios a salud y pensión</h3>
                <button type="button" class="ui-button ui-button--sm ui-button--ghost" onclick="toggleDetail('detail-salud')">Cerrar ✕</button>
            </div>
            <div class="mk-compose">
                <span>Total aportes de ley certificados en tus pagos: <b>$ {{ number_format($consolidado['incrngo']['salud_pension_cop'], 0, ',', '.') }}</b> ({{ number_format($consolidado['incrngo']['salud_pension_uvt'], 2, ',', '.') }} UVT)</span>
                <span class="mk-meta" style="margin-left:auto">Constituyen ingreso no constitutivo de renta ni ganancia ocasional (INCRNGO).</span>
            </div>
        </div>
    </div>

    {{-- BLOQUE 4: DISTRIBUCIÓN DE CERCANÍA A TOPES + PARÁMETROS USADOS --}}
    <div class="mk-grid-2" style="margin-top:1.5rem">
        {{-- Card: Qué tan cerca estás de cada tope --}}
        <div class="ui-card">
            <h2>Qué tan cerca estás de cada tope</h2>
            <ul class="finance-distribution" style="padding:0;margin:0">
                @foreach(['c5', 'c2', 'c1', 'c4', 'c3'] as $cid)
                    @php
                        $crit = $evaluacion['criterios'][$cid];
                        $pct = ($crit['tope_cop'] && $crit['tope_cop'] > 0) ? ($crit['valor_cop'] / $crit['tope_cop']) * 100 : 0;
                        $barWidth = min(100, $pct);
                        $isOver = $crit['cumple'];
                    @endphp
                    <li>
                        <div class="mk-row between mk-small">
                            <span>{{ $crit['nombre'] }} ({{ strtoupper($cid) }})</span>
                            @if($isOver)
                                <span class="mk-strong" style="color:var(--ui-danger)">Superado · {{ number_format($pct, 2, ',', '.') }} %</span>
                            @else
                                <span>{{ number_format($pct, 2, ',', '.') }} % del tope</span>
                            @endif
                        </div>
                        <div class="mk-bar {{ $isOver ? 'over' : '' }}">
                            <i style="width: {{ $barWidth }}%"></i>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Card: Parámetros usados --}}
        <div class="ui-card">
            <h2>Parámetros usados</h2>
            <dl class="mk-kv">
                <dt>UVT del año gravable {{ $anio }}</dt>
                <dd class="mk-strong">$ {{ number_format($parametros['uvt'], 0, ',', '.') }}</dd>

                <dt>Tope de patrimonio (4.500 UVT)</dt>
                <dd>$ {{ number_format(4500 * $parametros['uvt'], 0, ',', '.') }}</dd>

                <dt>Tope de ingresos y consumos (1.400 UVT)</dt>
                <dd>$ {{ number_format(1400 * $parametros['uvt'], 0, ',', '.') }}</dd>

                <dt>Responsable de IVA (perfil)</dt>
                <dd>{{ $usuario->es_responsable_iva ? 'Sí' : 'No' }}</dd>

                <dt>Fuente de los valores</dt>
                <dd>Registros + ajustes</dd>

                <dt>Última actualización</dt>
                <dd>{{ now()->format('d/m/Y, h:i a') }}</dd>
            </dl>
            <p class="ui-help" style="margin-top:1.25rem">
                <a href="{{ route('renta.complementaria', ['anio' => $anio]) }}">Revisar información complementaria →</a>
            </p>
        </div>
    </div>

    <p class="ui-help finance-legal" style="margin-top:1.5rem">
        Valores estimados con fines informativos. No constituyen asesoría tributaria, contable ni financiera.
    </p>
</div>

<script>
function toggleDetail(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const isHidden = (el.style.display === 'none' || el.style.display === '');
    // Ocultar todos los otros drawers primero
    document.querySelectorAll('.mk-drawer').forEach(d => d.style.display = 'none');
    if (isHidden) {
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}
</script>
@endsection
