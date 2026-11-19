@extends('layouts.app')

@section('title', 'Calculadora de Declaración de Renta · Año ' . $anio)

@section('content')
<div class="finance-page" x-data="calculadoraRentaApp({
    anio: {{ (int) $anio }},
    uvt: {{ (float) $parametros['uvt'] }},
    topePatrimonioUvt: {{ (int) $parametros['tope_patrimonio_uvt'] }},
    topeIngresosUvt: {{ (int) $parametros['tope_ingresos_uvt'] }},
    topeTarjetasUvt: {{ (int) $parametros['tope_tarjetas_uvt'] }},
    topeComprasUvt: {{ (int) $parametros['tope_compras_uvt'] }},
    topeConsignacionesUvt: {{ (int) $parametros['tope_consignaciones_uvt'] }},
    limiteGlobalExentasPct: {{ (float) $parametros['limite_global_exentas_pct'] }},
    limiteGlobalExentasUvt: {{ (int) $parametros['limite_global_exentas_uvt'] }},
    limiteExenta25Uvt: {{ (int) $parametros['limite_exenta_laboral_25_uvt'] }}
})">
    <header class="income-page-header">
        <div>
            <h1>Calculadora de Declaración de Renta · {{ $anio }}</h1>
            <p>Ingresa tus montos para simular si estás obligado a declarar renta ante la DIAN, identificar por cuáles conceptos debes declarar y estimar el monto a pagar.</p>
        </div>
        <div class="ui-inline">
            <div class="mk-year">
                <span class="mk-meta">Año gravable</span>
                <select id="select-anio" onchange="window.location.href = '{{ url('/renta') }}/' + this.value + '/calculadora'">
                    <option value="2025" @selected($anio === 2025)>2025</option>
                    <option value="2026" @selected($anio === 2026)>2026</option>
                </select>
            </div>
            <div class="mk-ctrl" style="min-height:44px;padding:0 .875rem;display:flex;align-items:center;background:var(--ui-surface)">
                <span class="mk-meta" style="margin-right:.375rem">UVT {{ $anio }}:</span>
                <strong style="font-variant-numeric:tabular-nums">$ {{ number_format($parametros['uvt'], 0, ',', '.') }} COP</strong>
            </div>
        </div>
    </header>

    <form @submit.prevent="calcular()">
        <div class="mk-stack">
            {{-- BLOQUE 1: CONCEPTOS QUE DETERMINAN LA OBLIGACIÓN DE DECLARAR --}}
            <div class="ui-card">
                <div class="mk-row between" style="margin-bottom:1rem">
                    <div>
                        <h2 class="mk-h2" style="margin-bottom:.25rem">1. Conceptos de Obligación Tributaria (Topes DIAN)</h2>
                        <p class="mk-meta" style="margin:0">Ingresa los montos acumulados durante el año {{ $anio }}. Si superas al menos uno de estos 5 topes, estás obligado por ley a declarar renta.</p>
                    </div>
                    <span class="ui-badge ui-badge--info">5 Topes legales</span>
                </div>

                <div class="mk-fields" style="row-gap:1.25rem">
                    {{-- C1: Patrimonio bruto al 31 de diciembre --}}
                    <div class="ui-field">
                        <label for="c1_patrimonio">
                            <strong>Patrimonio bruto al 31 de diciembre</strong>
                            <span class="mk-meta" style="font-weight:normal;display:block">Tope: 4.500 UVT ($ <span x-text="formatNumber(topePatrimonioCop)"></span> COP)</span>
                        </label>
                        <div class="ui-money">
                            <input type="number" step="1000" min="0" id="c1_patrimonio" x-model.number="patrimonio" placeholder="0" class="mk-ctrl money" />
                            <span class="ui-money-unit">COP</span>
                        </div>
                        <p class="ui-help">Suma de bienes raíces, vehículos, cuentas bancarias, inversiones y activos al 31/12.</p>
                    </div>

                    {{-- C2: Ingresos brutos del año --}}
                    <div class="ui-field">
                        <label for="c2_ingresos">
                            <strong>Ingresos brutos anuales</strong>
                            <span class="mk-meta" style="font-weight:normal;display:block">Tope: 1.400 UVT ($ <span x-text="formatNumber(topeIngresosCop)"></span> COP) — Operador &ge;</span>
                        </label>
                        <div class="ui-money">
                            <input type="number" step="1000" min="0" id="c2_ingresos" x-model.number="ingresos" placeholder="0" class="mk-ctrl money" />
                            <span class="ui-money-unit">COP</span>
                        </div>
                        <p class="ui-help">Total salarios, honorarios, comisiones, rendimientos de inversión y otros ingresos del año.</p>
                    </div>

                    {{-- C3: Consumos con tarjeta de crédito --}}
                    <div class="ui-field">
                        <label for="c3_tarjetas">
                            <strong>Consumos con tarjeta de crédito</strong>
                            <span class="mk-meta" style="font-weight:normal;display:block">Tope: 1.400 UVT ($ <span x-text="formatNumber(topeTarjetasCop)"></span> COP)</span>
                        </label>
                        <div class="ui-money">
                            <input type="number" step="1000" min="0" id="c3_tarjetas" x-model.number="tarjetas" placeholder="0" class="mk-ctrl money" />
                            <span class="ui-money-unit">COP</span>
                        </div>
                        <p class="ui-help">Total compras y consumos facturados en tarjetas de crédito durante el año.</p>
                    </div>

                    {{-- C4: Compras y consumos totales --}}
                    <div class="ui-field">
                        <label for="c4_compras">
                            <strong>Compras y consumos totales del año</strong>
                            <span class="mk-meta" style="font-weight:normal;display:block">Tope: 1.400 UVT ($ <span x-text="formatNumber(topeComprasCop)"></span> COP)</span>
                        </label>
                        <div class="ui-money">
                            <input type="number" step="1000" min="0" id="c4_compras" x-model.number="compras" placeholder="0" class="mk-ctrl money" />
                            <span class="ui-money-unit">COP</span>
                        </div>
                        <p class="ui-help">Total adquisiciones de bienes o servicios por cualquier medio (efectivo, débito, transferencia).</p>
                    </div>

                    {{-- C5: Consignaciones bancarias e inversiones --}}
                    <div class="ui-field" style="grid-column: 1 / -1">
                        <label for="c5_consignaciones">
                            <strong>Consignaciones bancarias, depósitos o inversiones financieras</strong>
                            <span class="mk-meta" style="font-weight:normal;display:block">Tope: 1.400 UVT ($ <span x-text="formatNumber(topeConsignacionesCop)"></span> COP)</span>
                        </label>
                        <div class="ui-money">
                            <input type="number" step="1000" min="0" id="c5_consignaciones" x-model.number="consignaciones" placeholder="0" class="mk-ctrl money" />
                            <span class="ui-money-unit">COP</span>
                        </div>
                        <p class="ui-help">Total acumulado de dinero consignado o recibido en cuentas bancarias, depósitos o apertura de inversiones.</p>
                    </div>
                </div>
            </div>

            {{-- BLOQUE 2: CAMPOS AVANZADOS DE DEPURACIÓN Y DEDUCCIONES (OPCIONALES) --}}
            <div class="ui-card">
                <div class="mk-row between" style="cursor:pointer" @click="mostrarAvanzados = !mostrarAvanzados">
                    <div style="display:flex;align-items:center;gap:.75rem">
                        <button type="button" class="ui-button ui-button--sm ui-button--ghost" style="padding:.25rem .5rem" aria-label="Desplegar opciones avanzadas">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :style="mostrarAvanzados ? 'transform:rotate(180deg);transition:transform .2s' : 'transition:transform .2s'"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div>
                            <h2 class="mk-h2" style="margin:0;font-size:1.1rem;display:inline-flex;align-items:center;gap:.5rem">
                                2. Opciones avanzadas de depuración y deducciones
                                <span class="ui-badge ui-badge--secondary" style="font-size:0.75rem;font-weight:normal">Opcional</span>
                            </h2>
                            <p class="mk-meta" style="margin:.25rem 0 0">
                                <span x-show="!mostrarAvanzados">Haz clic para desplegar deducciones de salud, pensión, FPV, cesantías y retenciones en la fuente.</span>
                                <span x-show="mostrarAvanzados">Ajusta deducciones y retenciones para mayor exactitud en el monto a pagar. Si no las diligencias, se liquidará automáticamente con los ingresos brutos.</span>
                            </p>
                        </div>
                    </div>
                    <span class="mk-meta" x-text="mostrarAvanzados ? 'Ocultar ▲' : 'Desplegar ▼'"></span>
                </div>

                {{-- Contenido desplegable --}}
                <div x-show="mostrarAvanzados" x-transition style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--ui-border)">
                    <div class="mk-fields three" style="row-gap:1.25rem">
                        {{-- Aportes obligatorios a salud y pensión --}}
                        <div class="ui-field">
                            <label for="salud_pension">
                                <strong>Aportes salud y pensión (INCRNGO)</strong>
                            </label>
                            <div class="ui-money">
                                <input type="number" step="1000" min="0" id="salud_pension" x-model.number="saludPension" placeholder="0" class="mk-ctrl money" />
                                <span class="ui-money-unit">COP</span>
                            </div>
                            <p class="ui-help">Aportes obligatorios a seguridad social como empleado o independiente.</p>
                        </div>

                        {{-- Aportes voluntarios a FPV o AFC --}}
                        <div class="ui-field">
                            <label for="fpv_afc">
                                <strong>Aportes a pensión voluntaria (FPV) o AFC</strong>
                            </label>
                            <div class="ui-money">
                                <input type="number" step="1000" min="0" id="fpv_afc" x-model.number="fpvAfc" placeholder="0" class="mk-ctrl money" />
                                <span class="ui-money-unit">COP</span>
                            </div>
                            <p class="ui-help">Renta exenta voluntaria (sujeta al 30% del ingreso y límite global).</p>
                        </div>

                        {{-- Cesantías e intereses de cesantías exentas --}}
                        <div class="ui-field">
                            <label for="cesantias_exentas">
                                <strong>Cesantías exentas (Art. 206 #4 E.T.)</strong>
                            </label>
                            <div class="ui-money">
                                <input type="number" step="1000" min="0" id="cesantias_exentas" x-model.number="cesantiasExentas" placeholder="0" class="mk-ctrl money" />
                                <span class="ui-money-unit">COP</span>
                            </div>
                            <p class="ui-help">Cesantías consignadas o pagadas exentas por ley.</p>
                        </div>

                        {{-- Retenciones en la fuente practicadas --}}
                        <div class="ui-field">
                            <label for="retenciones_fuente">
                                <strong>Retenciones en la fuente practicadas</strong>
                            </label>
                            <div class="ui-money">
                                <input type="number" step="1000" min="0" id="retenciones_fuente" x-model.number="retencionesFuente" placeholder="0" class="mk-ctrl money" />
                                <span class="ui-money-unit">COP</span>
                            </div>
                            <p class="ui-help">Total retenciones practicadas por empresas, bancos o clientes (se descuentan del impuesto a pagar).</p>
                        </div>

                        {{-- Componente inflacionario de rendimientos --}}
                        <div class="ui-field">
                            <label for="componente_inflacionario">
                                <strong>Componente inflacionario rendimientos</strong>
                            </label>
                            <div class="ui-money">
                                <input type="number" step="1000" min="0" id="componente_inflacionario" x-model.number="componenteInflacionario" placeholder="0" class="mk-ctrl money" />
                                <span class="ui-money-unit">COP</span>
                            </div>
                            <p class="ui-help">Porción no gravada de intereses financieros recibidos en el año.</p>
                        </div>

                        {{-- Desglose opcional: Rentas de capital / no laborales --}}
                        <div class="ui-field">
                            <label for="ingresos_capital">
                                <strong>De los ingresos: ¿Cuánto es capital / otros?</strong>
                            </label>
                            <div class="ui-money">
                                <input type="number" step="1000" min="0" id="ingresos_capital" x-model.number="ingresosCapital" placeholder="0" class="mk-ctrl money" />
                                <span class="ui-money-unit">COP</span>
                            </div>
                            <p class="ui-help">Rendimientos o rentas no laborales (el saldo restante se toma como laboral para el 25% exento).</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- BOTÓN PRINCIPAL DE CÁLCULO --}}
            <div style="display:flex;justify-content:center;margin:0.5rem 0 1rem">
                <button type="submit" class="ui-button ui-button--primary" style="font-size:1.1rem;padding:0.875rem 2.25rem;border-radius:var(--ui-radius)">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:.5rem"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="16" y1="14" x2="16" y2="18"/><path d="M16 10h.01"/><path d="M12 10h.01"/><path d="M8 10h.01"/><path d="M12 14h.01"/><path d="M8 14h.01"/><path d="M12 18h.01"/><path d="M8 18h.01"/></svg>
                    Calcular Declaración y Monto a Pagar
                </button>
            </div>
        </div>
    </form>

    {{-- SECCIÓN DE RESULTADOS --}}
    <div id="seccion-resultados" x-show="calculado" x-transition style="margin-top:2rem">
        <h2 class="mk-h2" style="font-size:1.5rem;display:flex;align-items:center;gap:.5rem;margin-bottom:1.5rem">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Resultados de la Simulación · {{ $anio }}
        </h2>

        {{-- GRID PRINCIPAL DE RESULTADOS: VEREDICTO DE OBLIGACIÓN + MONTO A PAGAR --}}
        <div class="mk-verdict">
            {{-- TARJETA 1: VEREDICTO DE OBLIGACIÓN --}}
            <template x-if="obligacion.esta_obligado">
                <div class="mk-result danger" role="status">
                    <div class="mk-row" style="gap:.5rem">
                        <span class="ui-badge ui-badge--danger">Obligado por ley</span>
                        <span class="ui-badge ui-badge--secondary" x-text="obligacion.conceptos_que_obligan.length + ' de 5 conceptos superados'"></span>
                    </div>
                    <p class="mk-result-title">OBLIGADO A DECLARAR</p>
                    <div style="background:rgba(239, 68, 68, 0.08);padding:1rem;border-radius:var(--ui-radius);margin:1rem 0;border-left:4px solid var(--ui-danger)">
                        <strong style="color:var(--ui-danger);display:block;margin-bottom:.5rem">Debes presentar declaración de renta por superar los topes en los siguientes conceptos:</strong>
                        <ul style="margin:0;padding-left:1.25rem;display:grid;gap:.375rem">
                            <template x-for="c in obligacion.conceptos_que_obligan" :key="c.id">
                                <li>
                                    <strong x-text="c.nombre"></strong>: Ingresaste <span class="mk-strong" x-text="formatCop(c.valor_cop)"></span> (Tope: <span x-text="formatCop(c.tope_cop)"></span>) &rarr; <span style="color:var(--ui-danger)">Superas por <span x-text="formatCop(c.exceso_cop)"></span> (<span x-text="c.exceso_uvt"></span> UVT)</span>.
                                </li>
                            </template>
                        </ul>
                    </div>
                    <p class="mk-small" style="margin:0">
                        Recuerda que con solo superar <strong>uno</strong> de los 5 conceptos, adquieres la obligación legal de presentar la declaración de renta ante la DIAN para el año gravable {{ $anio }}.
                    </p>
                </div>
            </template>

            <template x-if="!obligacion.esta_obligado">
                <div class="mk-result success" role="status">
                    <div class="mk-row" style="gap:.5rem">
                        <span class="ui-badge ui-badge--success">No obligado</span>
                        <span class="ui-badge ui-badge--secondary">0 de 5 conceptos superados</span>
                    </div>
                    <p class="mk-result-title">NO OBLIGADO A DECLARAR</p>
                    <div style="background:rgba(34, 197, 94, 0.08);padding:1rem;border-radius:var(--ui-radius);margin:1rem 0;border-left:4px solid var(--ui-success)">
                        <strong style="color:var(--ui-success)">¡Buenas noticias! Ninguno de tus montos supera los topes legales de la DIAN.</strong>
                        <p style="margin:.375rem 0 0;font-size:var(--ui-text-small)">
                            Tus ingresos, patrimonio, compras, consumos con tarjeta y consignaciones están dentro de los márgenes exentos para personas naturales en el año gravable {{ $anio }}.
                        </p>
                    </div>
                    <p class="mk-small" style="margin:0">
                        <strong>Nota importante:</strong> Si te practicaron retenciones en la fuente durante el año, tienes la opción de presentar la declaración de forma voluntaria para solicitar la devolución del saldo a favor.
                    </p>
                </div>
            </template>

            {{-- TARJETA 2: MONTO A PAGAR / SALDO A FAVOR --}}
            <div class="mk-result" :class="liquidacion.tipo_resultado === 'SALDO_A_PAGAR' ? 'danger' : 'success'" style="display:flex;flex-direction:column;justify-content:space-between">
                <div>
                    <div class="mk-row between" style="gap:.5rem">
                        <span class="ui-badge" :class="liquidacion.tipo_resultado === 'SALDO_A_PAGAR' ? 'ui-badge--danger' : 'ui-badge--success'" x-text="liquidacion.saldo_label"></span>
                        <span class="ui-badge ui-badge--secondary" x-text="'Tarifa marginal ' + liquidacion.tarifa_marginal"></span>
                    </div>
                    
                    <p class="mk-meta" style="margin:.75rem 0 0">Monto estimado a pagar a la DIAN</p>
                    <p class="mk-amount" :style="liquidacion.tipo_resultado === 'SALDO_A_PAGAR' ? 'color:var(--ui-danger)' : 'color:var(--ui-success)'" x-text="formatCop(liquidacion.saldo_valor)"></p>

                    <dl class="mk-facts" style="margin-top:1rem;padding-top:.75rem">
                        <div>
                            <dt>Impuesto calculado</dt>
                            <dd x-text="formatCop(liquidacion.impuesto_cop)"></dd>
                        </div>
                        <div>
                            <dt>(−) Retenciones</dt>
                            <dd x-text="'− ' + formatCop(liquidacion.retenciones_cop)"></dd>
                        </div>
                    </dl>
                </div>

                <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--ui-border)">
                    <p class="mk-meta" style="margin:0">
                        Liquidado sobre una Renta Líquida Gravable de <strong><span x-text="formatCop(liquidacion.renta_liquida_gravable_cop)"></span></strong> (<span x-text="liquidacion.renta_liquida_gravable_uvt"></span> UVT) según el Art. 241 del Estatuto Tributario.
                    </p>
                </div>
            </div>
        </div>

        {{-- COMPARATIVA DETALLADA DE LOS 5 CONCEPTOS FRENTE A LOS TOPES --}}
        <div class="ui-card" style="margin-bottom:1.5rem">
            <h3 class="mk-h2" style="font-size:1.15rem;margin-bottom:1rem">Evaluación detallada de los 5 conceptos frente a los topes de ley</h3>
            
            <div class="mk-table-wrap">
                <table class="ui-table mk-criteria">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Monto ingresado</th>
                            <th>Tope de ley ({{ $anio }})</th>
                            <th>Porcentaje alcanzado</th>
                            <th>Estado y Margen / Exceso</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- C1: Patrimonio --}}
                        <template x-for="crit in Object.values(obligacion.criterios)" :key="crit.id">
                            <tr>
                                <td>
                                    <strong x-text="crit.nombre"></strong>
                                    <div class="mk-meta" x-text="crit.norma"></div>
                                </td>
                                <td>
                                    <strong x-text="formatCop(crit.valor_cop)"></strong>
                                    <div class="mk-meta" x-text="crit.valor_uvt + ' UVT'"></div>
                                </td>
                                <td>
                                    <span x-text="formatCop(crit.tope_cop)"></span>
                                    <div class="mk-meta" x-text="crit.tope_uvt + ' UVT (' + crit.operador + ')'"></div>
                                </td>
                                <td style="min-width:140px">
                                    <div style="display:flex;align-items:center;gap:.5rem">
                                        <div style="flex:1;background:var(--ui-border);height:8px;border-radius:4px;overflow:hidden">
                                            <div :style="'width:' + Math.min(100, crit.porcentaje) + '%;height:100%;background:' + (crit.cumple ? 'var(--ui-danger)' : (crit.cerca ? 'var(--ui-warning)' : 'var(--ui-success)'))"></div>
                                        </div>
                                        <span class="mk-small" style="font-variant-numeric:tabular-nums" x-text="crit.porcentaje + '%'"></span>
                                    </div>
                                </td>
                                <td>
                                    <template x-if="crit.cumple">
                                        <span class="ui-badge ui-badge--danger" x-text="'Supera tope por +' + formatCop(crit.exceso_cop)"></span>
                                    </template>
                                    <template x-if="!crit.cumple && crit.cerca">
                                        <span class="ui-badge ui-badge--warning" x-text="'Cerca del tope · Margen: ' + formatCop(crit.margen_disponible_cop)"></span>
                                    </template>
                                    <template x-if="!crit.cumple && !crit.cerca">
                                        <span class="ui-badge ui-badge--success" x-text="'Bajo el tope · Margen: ' + formatCop(crit.margen_disponible_cop)"></span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- DESGLOSE PASO A PASO DE LA DEPURACIÓN (CÉDULA GENERAL) --}}
        <div class="ui-card">
            <h3 class="mk-h2" style="font-size:1.15rem;margin-bottom:1rem">Desglose paso a paso de la liquidación del impuesto (Cédula General)</h3>
            <ol class="mk-steps">
                {{-- 1. Ingresos brutos --}}
                <li>
                    <b>1</b>
                    <div>
                        <strong>Ingresos brutos de la cédula general</strong>
                        <p class="mk-meta">Total ingresos declarados para la cédula general.</p>
                    </div>
                    <span class="v" x-text="formatCop(liquidacion.ingresos_brutos_cop)"></span>
                </li>

                {{-- 2. INCRNGO --}}
                <li>
                    <b>2</b>
                    <div>
                        <strong>(−) Ingresos no constitutivos de renta (INCRNGO)</strong>
                        <p class="mk-meta">Aportes obligatorios a salud y pensión más componente inflacionario.</p>
                    </div>
                    <span class="v" style="color:var(--ui-muted)" x-text="'− ' + formatCop(liquidacion.incrngo_total_cop)"></span>
                </li>

                {{-- Subtotal: Renta Líquida --}}
                <li class="key">
                    <b>=</b>
                    <div>
                        <strong>Renta líquida ordinaria</strong>
                    </div>
                    <span class="v mk-strong" x-text="formatCop(liquidacion.renta_liquida_cop)"></span>
                </li>

                {{-- 3. Rentas exentas voluntarias --}}
                <li>
                    <b>3</b>
                    <div>
                        <strong>(−) Rentas exentas voluntarias (FPV, AFC y Cesantías)</strong>
                        <p class="mk-meta">Aportes voluntarios y cesantías exentas reportadas.</p>
                    </div>
                    <span class="v" x-text="formatCop(liquidacion.exentas_voluntarias_cop)"></span>
                </li>

                {{-- 4. Exención laboral del 25% --}}
                <li>
                    <b>4</b>
                    <div>
                        <strong>(−) Renta exenta laboral del 25% (Art. 206 #10 E.T.)</strong>
                        <p class="mk-meta">25% sobre rentas de trabajo depuradas (tope 790 UVT = $<span x-text="formatNumber(limiteExenta25Cop)"></span>).</p>
                    </div>
                    <span class="v" x-text="formatCop(liquidacion.exenta_25_laboral_cop)"></span>
                </li>

                {{-- 5. Límite global del 40% --}}
                <li>
                    <b>5</b>
                    <div>
                        <strong>Total rentas exentas aceptadas (con límite legal)</strong>
                        <p class="mk-meta">
                            Limitado al menor entre el 40% de la renta líquida y 1.340 UVT ($<span x-text="formatNumber(limite1340Cop)"></span>).
                            <span x-show="liquidacion.limite_exentas_aplicado" style="color:var(--ui-warning);display:block;margin-top:.25rem">
                                Tope legal alcanzado. Exceso no aprovechado: <span x-text="formatCop(liquidacion.exceso_no_aprovechado_cop)"></span>.
                            </span>
                        </p>
                    </div>
                    <span class="v" style="color:var(--ui-muted)" x-text="'− ' + formatCop(liquidacion.exentas_aceptadas_cop)"></span>
                </li>

                {{-- 6. Renta Líquida Gravable --}}
                <li class="key">
                    <b>=</b>
                    <div>
                        <strong>Renta líquida gravable (Base imponible)</strong>
                        <p class="mk-meta" x-text="liquidacion.renta_liquida_gravable_uvt + ' UVT'"></p>
                    </div>
                    <span class="v mk-strong" x-text="formatCop(liquidacion.renta_liquida_gravable_cop)"></span>
                </li>

                {{-- 7. Impuesto calculado Art. 241 --}}
                <li>
                    <b>7</b>
                    <div>
                        <strong>Impuesto sobre la renta (Tabla Art. 241 E.T.)</strong>
                        <p class="mk-meta" x-text="'Rango: ' + liquidacion.rango_texto + ' · Tarifa marginal: ' + liquidacion.tarifa_marginal"></p>
                    </div>
                    <span class="v mk-strong" x-text="formatCop(liquidacion.impuesto_cop)"></span>
                </li>

                {{-- 8. Retenciones en la fuente --}}
                <li>
                    <b>8</b>
                    <div>
                        <strong>(−) Retenciones en la fuente practicadas</strong>
                        <p class="mk-meta">Anticipos retenidos durante el año fiscal.</p>
                    </div>
                    <span class="v" style="color:var(--ui-muted)" x-text="'− ' + formatCop(liquidacion.retenciones_cop)"></span>
                </li>

                {{-- Saldo final --}}
                <li class="key" :style="liquidacion.tipo_resultado === 'SALDO_A_PAGAR' ? 'background:rgba(239, 68, 68, 0.1)' : 'background:rgba(34, 197, 94, 0.1)'">
                    <b>=</b>
                    <div>
                        <strong style="font-size:1.15rem" x-text="liquidacion.saldo_label"></strong>
                    </div>
                    <span class="v mk-strong" style="font-size:1.25rem" :style="liquidacion.tipo_resultado === 'SALDO_A_PAGAR' ? 'color:var(--ui-danger)' : 'color:var(--ui-success)'" x-text="formatCop(liquidacion.saldo_valor)"></span>
                </li>
            </ol>
        </div>
    </div>
</div>

<script>
function calculadoraRentaApp(config) {
    return {
        anio: config.anio,
        uvt: config.uvt,
        topePatrimonioUvt: config.topePatrimonioUvt,
        topeIngresosUvt: config.topeIngresosUvt,
        topeTarjetasUvt: config.topeTarjetasUvt,
        topeComprasUvt: config.topeComprasUvt,
        topeConsignacionesUvt: config.topeConsignacionesUvt,
        limiteGlobalExentasPct: config.limiteGlobalExentasPct,
        limiteGlobalExentasUvt: config.limiteGlobalExentasUvt,
        limiteExenta25Uvt: config.limiteExenta25Uvt,
        datosRegistrados: config.datosRegistrados,

        // Campos principales de obligación
        patrimonio: 0,
        ingresos: 0,
        tarjetas: 0,
        compras: 0,
        consignaciones: 0,

        // Campos avanzados opcionales
        mostrarAvanzados: false,
        saludPension: 0,
        fpvAfc: 0,
        cesantiasExentas: 0,
        retencionesFuente: 0,
        componenteInflacionario: 0,
        ingresosCapital: 0,

        // Estado de resultados
        calculado: false,
        obligacion: {},
        liquidacion: {},

        get topePatrimonioCop() { return this.topePatrimonioUvt * this.uvt; },
        get topeIngresosCop() { return this.topeIngresosUvt * this.uvt; },
        get topeTarjetasCop() { return this.topeTarjetasUvt * this.uvt; },
        get topeComprasCop() { return this.topeComprasUvt * this.uvt; },
        get topeConsignacionesCop() { return this.topeConsignacionesUvt * this.uvt; },
        get limite1340Cop() { return this.limiteGlobalExentasUvt * this.uvt; },
        get limiteExenta25Cop() { return this.limiteExenta25Uvt * this.uvt; },

        calcular() {
            const uvt = this.uvt;
            const valC1 = Math.max(0, parseFloat(this.patrimonio) || 0);
            const valC2 = Math.max(0, parseFloat(this.ingresos) || 0);
            const valC3 = Math.max(0, parseFloat(this.tarjetas) || 0);
            const valC4 = Math.max(0, parseFloat(this.compras) || 0);
            const valC5 = Math.max(0, parseFloat(this.consignaciones) || 0);

            const topeC1 = this.topePatrimonioCop;
            const topeC2 = this.topeIngresosCop;
            const topeC3 = this.topeTarjetasCop;
            const topeC4 = this.topeComprasCop;
            const topeC5 = this.topeConsignacionesCop;

            // 1. C1: Patrimonio > 4.500 UVT
            const cumpleC1 = valC1 > topeC1;
            const excesoC1 = Math.max(0, valC1 - topeC1);
            const margenC1 = Math.max(0, topeC1 - valC1);
            const cercaC1 = (!cumpleC1 && topeC1 > 0 && (valC1 / topeC1) >= 0.90);
            const pctC1 = topeC1 > 0 ? Math.min(100, Math.round((valC1 / topeC1) * 1000) / 10) : 0;

            // 2. C2: Ingresos >= 1.400 UVT
            const cumpleC2 = valC2 >= topeC2;
            const excesoC2 = Math.max(0, valC2 - topeC2);
            const margenC2 = Math.max(0, topeC2 - valC2);
            const cercaC2 = (!cumpleC2 && topeC2 > 0 && (valC2 / topeC2) >= 0.90);
            const pctC2 = topeC2 > 0 ? Math.min(100, Math.round((valC2 / topeC2) * 1000) / 10) : 0;

            // 3. C3: Tarjetas > 1.400 UVT
            const cumpleC3 = valC3 > topeC3;
            const excesoC3 = Math.max(0, valC3 - topeC3);
            const margenC3 = Math.max(0, topeC3 - valC3);
            const cercaC3 = (!cumpleC3 && topeC3 > 0 && (valC3 / topeC3) >= 0.90);
            const pctC3 = topeC3 > 0 ? Math.min(100, Math.round((valC3 / topeC3) * 1000) / 10) : 0;

            // 4. C4: Compras > 1.400 UVT
            const cumpleC4 = valC4 > topeC4;
            const excesoC4 = Math.max(0, valC4 - topeC4);
            const margenC4 = Math.max(0, topeC4 - valC4);
            const cercaC4 = (!cumpleC4 && topeC4 > 0 && (valC4 / topeC4) >= 0.90);
            const pctC4 = topeC4 > 0 ? Math.min(100, Math.round((valC4 / topeC4) * 1000) / 10) : 0;

            // 5. C5: Consignaciones > 1.400 UVT
            const cumpleC5 = valC5 > topeC5;
            const excesoC5 = Math.max(0, valC5 - topeC5);
            const margenC5 = Math.max(0, topeC5 - valC5);
            const cercaC5 = (!cumpleC5 && topeC5 > 0 && (valC5 / topeC5) >= 0.90);
            const pctC5 = topeC5 > 0 ? Math.min(100, Math.round((valC5 / topeC5) * 1000) / 10) : 0;

            const criterios = {
                c1: {
                    id: 'c1',
                    nombre: 'Patrimonio bruto al 31 de diciembre',
                    norma: 'Art. 592 num. 1 y Art. 594-3 E.T.',
                    valor_cop: valC1,
                    valor_uvt: (valC1 / uvt).toFixed(2),
                    tope_cop: topeC1,
                    tope_uvt: this.topePatrimonioUvt,
                    operador: '>',
                    cumple: cumpleC1,
                    cerca: cercaC1,
                    porcentaje: pctC1,
                    margen_disponible_cop: margenC1,
                    exceso_cop: excesoC1,
                    exceso_uvt: (excesoC1 / uvt).toFixed(2)
                },
                c2: {
                    id: 'c2',
                    nombre: 'Ingresos brutos anuales',
                    norma: 'Art. 592 num. 1 y Art. 593 E.T.',
                    valor_cop: valC2,
                    valor_uvt: (valC2 / uvt).toFixed(2),
                    tope_cop: topeC2,
                    tope_uvt: this.topeIngresosUvt,
                    operador: '>=',
                    cumple: cumpleC2,
                    cerca: cercaC2,
                    porcentaje: pctC2,
                    margen_disponible_cop: margenC2,
                    exceso_cop: excesoC2,
                    exceso_uvt: (excesoC2 / uvt).toFixed(2)
                },
                c3: {
                    id: 'c3',
                    nombre: 'Consumos mediante tarjeta de crédito',
                    norma: 'Art. 594-3 num. 1 E.T.',
                    valor_cop: valC3,
                    valor_uvt: (valC3 / uvt).toFixed(2),
                    tope_cop: topeC3,
                    tope_uvt: this.topeTarjetasUvt,
                    operador: '>',
                    cumple: cumpleC3,
                    cerca: cercaC3,
                    porcentaje: pctC3,
                    margen_disponible_cop: margenC3,
                    exceso_cop: excesoC3,
                    exceso_uvt: (excesoC3 / uvt).toFixed(2)
                },
                c4: {
                    id: 'c4',
                    nombre: 'Compras y consumos totales del año',
                    norma: 'Art. 594-3 num. 2 E.T.',
                    valor_cop: valC4,
                    valor_uvt: (valC4 / uvt).toFixed(2),
                    tope_cop: topeC4,
                    tope_uvt: this.topeComprasUvt,
                    operador: '>',
                    cumple: cumpleC4,
                    cerca: cercaC4,
                    porcentaje: pctC4,
                    margen_disponible_cop: margenC4,
                    exceso_cop: excesoC4,
                    exceso_uvt: (excesoC4 / uvt).toFixed(2)
                },
                c5: {
                    id: 'c5',
                    nombre: 'Consignaciones bancarias, depósitos o inversiones',
                    norma: 'Art. 594-3 num. 3 E.T.',
                    valor_cop: valC5,
                    valor_uvt: (valC5 / uvt).toFixed(2),
                    tope_cop: topeC5,
                    tope_uvt: this.topeConsignacionesUvt,
                    operador: '>',
                    cumple: cumpleC5,
                    cerca: cercaC5,
                    porcentaje: pctC5,
                    margen_disponible_cop: margenC5,
                    exceso_cop: excesoC5,
                    exceso_uvt: (excesoC5 / uvt).toFixed(2)
                }
            };

            const conceptosQueObligan = Object.values(criterios).filter(c => c.cumple);
            const estaObligado = conceptosQueObligan.length > 0;

            this.obligacion = {
                esta_obligado: estaObligado,
                veredicto: estaObligado ? 'OBLIGADO' : 'NO_OBLIGADO',
                conceptos_que_obligan: conceptosQueObligan,
                criterios: criterios
            };

            // 2. Liquidación del Impuesto (Cédula General)
            const ib = valC2;
            const ingCap = Math.max(0, parseFloat(this.ingresosCapital) || 0);
            const ingTrabajo = Math.max(0, ib - ingCap);

            const sp = Math.max(0, parseFloat(this.saludPension) || 0);
            const compInf = Math.max(0, parseFloat(this.componenteInflacionario) || 0);
            const incrngo = sp + compInf;

            const rl = Math.max(0, ib - incrngo);
            const rlTrabajo = Math.max(0, ingTrabajo - sp);

            const fpv = Math.max(0, parseFloat(this.fpvAfc) || 0);
            const cesEx = Math.max(0, parseFloat(this.cesantiasExentas) || 0);
            const topeFpv = Math.max(0, Math.round(ib * 0.30) - sp);
            const fpvAceptado = Math.min(fpv, topeFpv);
            const exentasVoluntarias = fpvAceptado + cesEx;

            const base25 = Math.max(0, rlTrabajo - exentasVoluntarias);
            const tope790 = this.limiteExenta25Cop;
            const exenta25 = Math.min(Math.round(base25 * 0.25), tope790);

            const sumaExentas = exentasVoluntarias + exenta25;
            const limite40 = Math.round(rl * this.limiteGlobalExentasPct);
            const limite1340 = this.limite1340Cop;
            const topeGlobal = Math.min(limite40, limite1340);
            const exentasAceptadas = Math.min(sumaExentas, topeGlobal);

            const limiteAplicado = sumaExentas > topeGlobal;
            const excesoNoAprovechado = limiteAplicado ? (sumaExentas - topeGlobal) : 0;
            const cupoDisponible = (!limiteAplicado) ? (topeGlobal - sumaExentas) : 0;

            const rlgCop = Math.max(0, rl - exentasAceptadas);
            const rlgUvt = Math.round((rlgCop / uvt) * 100) / 100;

            // Tabla marginal Art. 241 E.T.
            let impUvt = 0;
            let tarifaMarginal = '0%';
            let rangoTexto = '0 a 1.090 UVT';

            if (rlgUvt <= 1090) {
                impUvt = 0;
                tarifaMarginal = '0%';
                rangoTexto = '0 a 1.090 UVT';
            } else if (rlgUvt <= 1700) {
                impUvt = (rlgUvt - 1090) * 0.19;
                tarifaMarginal = '19%';
                rangoTexto = '> 1.090 a 1.700 UVT';
            } else if (rlgUvt <= 4100) {
                impUvt = (rlgUvt - 1700) * 0.28 + 116;
                tarifaMarginal = '28%';
                rangoTexto = '> 1.700 a 4.100 UVT';
            } else if (rlgUvt <= 8670) {
                impUvt = (rlgUvt - 4100) * 0.33 + 788;
                tarifaMarginal = '33%';
                rangoTexto = '> 4.100 a 8.670 UVT';
            } else if (rlgUvt <= 18970) {
                impUvt = (rlgUvt - 8670) * 0.35 + 2296;
                tarifaMarginal = '35%';
                rangoTexto = '> 8.670 a 18.970 UVT';
            } else if (rlgUvt <= 31000) {
                impUvt = (rlgUvt - 18970) * 0.37 + 5901;
                tarifaMarginal = '37%';
                rangoTexto = '> 18.970 a 31.000 UVT';
            } else {
                impUvt = (rlgUvt - 31000) * 0.39 + 10352;
                tarifaMarginal = '39%';
                rangoTexto = '> 31.000 UVT';
            }

            const impCop = Math.round((impUvt * uvt) / 1000) * 1000;
            const ret = Math.max(0, parseFloat(this.retencionesFuente) || 0);
            const retRedondeadas = Math.round(ret / 1000) * 1000;

            const saldoNeto = impCop - retRedondeadas;
            let tipoResultado = 'CERO';
            let saldoLabel = 'Saldo en ceros';
            let saldoValor = 0;

            if (saldoNeto > 0) {
                tipoResultado = 'SALDO_A_PAGAR';
                saldoLabel = 'Saldo neto a pagar';
                saldoValor = saldoNeto;
            } else if (saldoNeto < 0) {
                tipoResultado = 'SALDO_A_FAVOR';
                saldoLabel = 'Saldo a favor';
                saldoValor = Math.abs(saldoNeto);
            }

            this.liquidacion = {
                ingresos_brutos_cop: ib,
                incrngo_total_cop: incrngo,
                renta_liquida_cop: rl,
                exentas_voluntarias_cop: exentasVoluntarias,
                exenta_25_laboral_cop: exenta25,
                exentas_aceptadas_cop: exentasAceptadas,
                limite_exentas_aplicado: limiteAplicado,
                exceso_no_aprovechado_cop: excesoNoAprovechado,
                cupo_disponible_cop: cupoDisponible,
                renta_liquida_gravable_cop: rlgCop,
                renta_liquida_gravable_uvt: rlgUvt.toFixed(2),
                impuesto_cop: impCop,
                impuesto_uvt: impUvt.toFixed(2),
                tarifa_marginal: tarifaMarginal,
                rango_texto: rangoTexto,
                retenciones_cop: retRedondeadas,
                saldo_neto_cop: saldoNeto,
                tipo_resultado: tipoResultado,
                saldo_label: saldoLabel,
                saldo_valor: saldoValor
            };

            this.calculado = true;

            this.$nextTick(() => {
                const el = document.getElementById('seccion-resultados');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth' });
                }
            });
        },

        formatCop(val) {
            return new Intl.NumberFormat('es-CO', {
                style: 'currency',
                currency: 'COP',
                maximumFractionDigits: 0
            }).format(val || 0);
        },

        formatNumber(val) {
            return new Intl.NumberFormat('es-CO', {
                maximumFractionDigits: 0
            }).format(val || 0);
        }
    };
}
</script>
@endsection
