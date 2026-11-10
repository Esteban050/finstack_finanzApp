@extends('layouts.app')

@section('title', 'Declaración de renta · Información complementaria ' . $anio)

@section('content')
<div class="finance-page" x-data="{
    patrimonioConsolidado: {{ (float) $consolidado['patrimonio_bruto_31_12']['total_cop'] }},
    ingresosConsolidados: {{ (float) $consolidado['ingresos_brutos']['total_cop'] }},
    pisoInversiones: {{ (float) $consolidado['piso_inversiones_c5']['total_cop'] }},
    ajustePatrimonio: {{ (float) $declaracion->c1_ajuste_manual_cop }},
    ajusteIngresos: {{ (float) $declaracion->c2_ajuste_manual_cop }},
    consumosTarjeta: {{ (float) $declaracion->c3_consumos_tarjeta_cop }},
    comprasTotales: {{ (float) $declaracion->c4_compras_totales_cop }},
    consignaciones: {{ $declaracion->c5_consignaciones_declaradas_cop !== null ? (float) $declaracion->c5_consignaciones_declaradas_cop : ((float) $consolidado['piso_inversiones_c5']['total_cop']) }},
    
    get patrimonioFinal() {
        return this.patrimonioConsolidado + (parseFloat(this.ajustePatrimonio) || 0);
    },
    get ingresosFinales() {
        return this.ingresosConsolidados + (parseFloat(this.ajusteIngresos) || 0);
    },
    get patrimonioInvalido() {
        return this.patrimonioFinal < 0;
    },
    get ingresosInvalidos() {
        return this.ingresosFinales < 0;
    },
    get c5Evaluado() {
        return Math.max(this.pisoInversiones, parseFloat(this.consignaciones) || 0);
    },
    formatCop(val) {
        return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(val);
    }
}">
    <header class="income-page-header">
        <div>
            <h1>Información complementaria · {{ $anio }}</h1>
            <p>Completa los topes externos que el sistema no conoce en su totalidad o ajusta tus saldos de Ingresos y Patrimonio antes de evaluar.</p>
        </div>
        <div class="ui-inline">
            <a class="ui-button ui-button--ghost" href="{{ route('renta.consolidado', ['anio' => $anio]) }}">← Volver a consolidado</a>
            <a class="ui-button ui-button--secondary" href="{{ route('renta.evaluacion', ['anio' => $anio]) }}">Ir a evaluación →</a>
        </div>
    </header>

    @if($errors->any())
        <div class="mk-alert danger" role="alert">
            <span class="mk-icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </span>
            <div>
                <strong>Corrige los siguientes errores antes de continuar:</strong>
                <ul style="margin:0.25rem 0 0;padding-left:1.25rem">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('renta.complementaria.guardar', ['anio' => $anio]) }}">
        @csrf
        <div class="income-editor">
            <div class="income-form-main">
                {{-- Sección 1: Topes Bancarios y de Consumo Externo --}}
                <div class="ui-card">
                    <h2>Topes bancarios y de consumo externo (Criterios C3, C4 y C5)</h2>
                    
                    <div class="pila-autofill-box">
                        <div class="pila-autofill-header">
                            <div>
                                <p class="pila-autofill-title">Pre-llenado desde tu módulo de Inversiones</p>
                                <p class="pila-autofill-msg">
                                    Detectamos <strong>$ {{ number_format($consolidado['piso_inversiones_c5']['total_cop'], 0, ',', '.') }} ({{ number_format($consolidado['piso_inversiones_c5']['total_uvt'], 2, ',', '.') }} UVT)</strong> en aperturas de CDTs, depósitos en cuentas de alto rendimiento, compras de ETFs y aportes a FIC/FPV registrados en {{ $anio }}. Suma a ese piso el resto de consignaciones bancarias recibidas en el año.
                                </p>
                            </div>
                            <span class="ui-badge ui-badge--success">Conectado a Inversiones</span>
                        </div>
                    </div>

                    <div class="mk-fields three">
                        <div class="ui-field">
                            <label for="c5_input">Total consignaciones, depósitos e inversiones (C5) <span aria-hidden="true">*</span></label>
                            <div class="ui-money">
                                <input type="number" step="1000" min="0" id="c5_input" name="c5_consignaciones_declaradas_cop" x-model.number="consignaciones" required />
                                <span class="ui-money-unit">COP</span>
                            </div>
                            <p class="ui-help">Incluye los $ {{ number_format($consolidado['piso_inversiones_c5']['total_cop'], 0, ',', '.') }} de tu módulo de Inversiones más otros depósitos bancarios.</p>
                        </div>

                        <div class="ui-field">
                            <label for="c3_input">Consumos con tarjeta de crédito (C3)</label>
                            <div class="ui-money">
                                <input type="number" step="1000" min="0" id="c3_input" name="c3_consumos_tarjeta_cop" x-model.number="consumosTarjeta" />
                                <span class="ui-money-unit">COP</span>
                            </div>
                            <p class="ui-help">Opcional (según extractos bancarios del año).</p>
                        </div>

                        <div class="ui-field">
                            <label for="c4_input">Compras y consumos totales (C4)</label>
                            <div class="ui-money">
                                <input type="number" step="1000" min="0" id="c4_input" name="c4_compras_totales_cop" x-model.number="comprasTotales" />
                                <span class="ui-money-unit">COP</span>
                            </div>
                            <p class="ui-help">Opcional (total facturado a tu nombre en el año).</p>
                        </div>
                    </div>
                </div>

                {{-- Sección 2: Ajustes a Valores Consolidados --}}
                <div class="ui-card">
                    <h2>Ajustes a los valores consolidados de Ingresos e Inversiones</h2>
                    <p class="ui-help" style="margin-top:-0.5rem;margin-bottom:1.5rem">
                        Permite sumar o restar ingresos o activos externos al portafolio registrado en FinanzApp. Valor final = valor consolidado + ajuste.
                    </p>

                    <div class="mk-table-wrap">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Criterio consolidado</th>
                                    <th class="num">Consolidado (Ingresos + Inversiones)</th>
                                    <th class="num">Ajuste manual (+ / −)</th>
                                    <th class="num">Valor final a evaluar</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Fila Ingresos (C2) --}}
                                <tr :class="ingresosInvalidos ? 'is-row-alert' : 'is-row-current'">
                                    <td class="mk-strong">Ingresos brutos del año (C2)</td>
                                    <td class="num" data-money>$ {{ number_format($consolidado['ingresos_brutos']['total_cop'], 0, ',', '.') }}</td>
                                    <td class="num">
                                        <div class="ui-money" style="justify-content:flex-end">
                                            <input type="number" step="1000" name="c2_ajuste_manual_cop" x-model.number="ajusteIngresos" style="max-width:180px;text-align:right" :class="ingresosInvalidos ? 'mk-ctrl invalid' : ''" />
                                            <span class="ui-money-unit">COP</span>
                                        </div>
                                    </td>
                                    <td class="num mk-strong" :class="ingresosInvalidos ? 'ui-error' : ''" x-text="formatCop(ingresosFinales)"></td>
                                </tr>
                                <template x-if="ingresosInvalidos">
                                    <tr class="is-row-alert">
                                        <td colspan="4">
                                            <p class="ui-error" role="alert">El valor final de los ingresos brutos no puede ser negativo. Revisa el ajuste: el máximo que puedes restar es $ {{ number_format($consolidado['ingresos_brutos']['total_cop'], 0, ',', '.') }}.</p>
                                        </td>
                                    </tr>
                                </template>

                                {{-- Fila Patrimonio (C1) --}}
                                <tr :class="patrimonioInvalido ? 'is-row-alert' : ''">
                                    <td>Patrimonio bruto al 31/12 (C1)</td>
                                    <td class="num" data-money>$ {{ number_format($consolidado['patrimonio_bruto_31_12']['total_cop'], 0, ',', '.') }}</td>
                                    <td class="num">
                                        <div class="ui-money" style="justify-content:flex-end">
                                            <input type="number" step="1000" name="c1_ajuste_manual_cop" x-model.number="ajustePatrimonio" style="max-width:180px;text-align:right" :class="patrimonioInvalido ? 'mk-ctrl invalid' : ''" />
                                            <span class="ui-money-unit">COP</span>
                                        </div>
                                    </td>
                                    <td class="num mk-strong" :class="patrimonioInvalido ? 'ui-error' : ''" x-text="formatCop(patrimonioFinal)"></td>
                                </tr>
                                <template x-if="patrimonioInvalido">
                                    <tr class="is-row-alert">
                                        <td colspan="4">
                                            <p class="ui-error" role="alert">El valor final del patrimonio bruto no puede ser negativo. Revisa el ajuste: el máximo que puedes restar es $ {{ number_format($consolidado['patrimonio_bruto_31_12']['total_cop'], 0, ',', '.') }}.</p>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Justificación obligatoria si hay ajuste --}}
                    <div class="ui-field" style="margin-top:1.5rem" x-show="ajustePatrimonio != 0 || ajusteIngresos != 0">
                        <label for="justificacion">Nota explicativa del ajuste <span aria-hidden="true">*</span></label>
                        <textarea id="justificacion" name="justificacion_ajuste" rows="3" placeholder="Ejemplo: Honorarios ocasionales recibidos en enero antes de usar FinanzApp, según certificado de retención..." minlength="10" maxlength="500"></textarea>
                        <p class="ui-help">Obligatorio (mínimo 10 y máximo 500 caracteres). Queda registrado en la bitácora de auditoría del año gravable (RN-044.3).</p>
                    </div>

                    <div class="income-actions" style="margin-top:1.5rem">
                        <button type="submit" name="accion" value="guardar" class="ui-button ui-button--secondary" :disabled="patrimonioInvalido || ingresosInvalidos">Guardar información</button>
                        <button type="submit" name="accion" value="guardar_y_evaluar" class="ui-button ui-button--primary" :disabled="patrimonioInvalido || ingresosInvalidos">Guardar y evaluar obligación →</button>
                    </div>
                </div>
            </div>

            {{-- Sidebar resumen y auditoría --}}
            <aside class="income-summary">
                <div class="ui-card">
                    <h2>Valores que se evaluarán</h2>
                    <dl class="mk-kv" style="margin-top:1rem">
                        <dt>Patrimonio bruto (C1)</dt>
                        <dd :class="patrimonioInvalido ? 'ui-error' : 'mk-strong'" x-text="patrimonioInvalido ? 'Revisa el ajuste' : formatCop(patrimonioFinal)"></dd>

                        <dt>Ingresos brutos (C2)</dt>
                        <dd :class="ingresosInvalidos ? 'ui-error' : 'mk-strong'" x-text="ingresosInvalidos ? 'Revisa el ajuste' : formatCop(ingresosFinales)"></dd>

                        <dt>Consignaciones e inversiones (C5)</dt>
                        <dd class="mk-strong" x-text="formatCop(c5Evaluado)"></dd>

                        <dt>Consumos con tarjeta (C3)</dt>
                        <dd x-text="formatCop(consumosTarjeta)"></dd>

                        <dt>Compras y consumos (C4)</dt>
                        <dd x-text="formatCop(comprasTotales)"></dd>
                    </dl>

                    <h2 style="margin-top:2rem">Historial de ajustes</h2>
                    <p class="mk-meta">Bitácora de cambios auditados (RF-044 RN-044.3)</p>
                    <ol class="mk-tl" style="margin-top:1rem">
                        @forelse($ajustes as $ajuste)
                            <li class="done">
                                <div>
                                    <p class="mk-small mk-strong">{{ $ajuste->concepto === 'PATRIMONIO_BRUTO' ? 'Patrimonio' : 'Ingresos' }}: {{ $ajuste->monto_ajuste_cop >= 0 ? '+' : '−' }} $ {{ number_format(abs($ajuste->monto_ajuste_cop), 0, ',', '.') }}</p>
                                    <p class="mk-meta">"{{ $ajuste->justificacion }}"</p>
                                </div>
                                <span class="mk-meta">{{ $ajuste->creado_en ? $ajuste->creado_en->format('d/m/Y') : now()->format('d/m/Y') }}</span>
                            </li>
                        @empty
                            <li>
                                <div class="mk-muted mk-small">No hay ajustes manuales previos registrados para {{ $anio }}.</div>
                            </li>
                        @endforelse
                    </ol>
                </div>
            </aside>
        </div>
    </form>
</div>
@endsection

