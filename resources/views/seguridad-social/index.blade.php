@extends('layouts.app')
@section('title', 'Seguridad social')
@section('content')
<div class="finance-page" x-data="seguridadSocialApp()">
    <header class="income-page-header">
        <div>
            <h1>Seguridad social</h1>
            <p>Calculadora unificada de aportes, control del tope de 25 SMMLV ($ 43.772.625) y liquidación conforme a la normativa 2026.</p>
        </div>
    </header>

    @if(session('status'))
        <div class="ui-notice ui-notice-action" role="alert" style="border-left: 4px solid var(--accent);">
            <div>
                <strong>{{ session('status') }}</strong>
                @if(session('gasto_creado_id'))
                    <p class="ui-help" style="margin-top: 0.25rem;">El gasto correspondiente fue generado automáticamente en tu contabilidad.</p>
                @endif
            </div>
            @if(session('gasto_creado_id'))
                <x-ui.button :href="route('gastos.index')" variant="secondary">
                    Ir a la sección de Gastos →
                </x-ui.button>
            @endif
        </div>
    @endif

    {{-- SELECTOR DE TIPO DE COTIZANTE / MODALIDAD --}}
    <x-ui.card>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
                <h2 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.25rem;">Modalidad de cotización</h2>
                <p class="ui-help">Selecciona tu modalidad laboral o económica. Si tienes salario e ingresos independientes simultáneos, elige <strong>Múltiple vinculación</strong> para aplicar la prelación de cupos.</p>
            </div>
            
            <div class="ui-inline" role="group" aria-label="Tipo de cotizante" style="gap: 0.75rem; flex-wrap: wrap;">
                <button type="button" 
                    class="ui-button" 
                    :class="tipoCotizante === 'ASALARIADO' ? 'ui-button--primary' : 'ui-button--secondary'"
                    @click="cambiarTipo('ASALARIADO')">
                    💼 Asalariado
                </button>
                <button type="button" 
                    class="ui-button" 
                    :class="tipoCotizante === 'INDEPENDIENTE' ? 'ui-button--primary' : 'ui-button--secondary'"
                    @click="cambiarTipo('INDEPENDIENTE')">
                    🔨 Independiente / Contratista
                </button>
                <button type="button" 
                    class="ui-button" 
                    :class="tipoCotizante === 'RENTISTA_CAPITAL' ? 'ui-button--primary' : 'ui-button--secondary'"
                    @click="cambiarTipo('RENTISTA_CAPITAL')">
                    📈 Rentista / Inmobiliario
                </button>
                <button type="button" 
                    class="ui-button" 
                    :class="tipoCotizante === 'MULTI_INGRESO' ? 'ui-button--primary' : 'ui-button--secondary'"
                    @click="cambiarTipo('MULTI_INGRESO')">
                    ⚡ Múltiple vinculación (Multi-ingreso)
                </button>
            </div>
        </div>
    </x-ui.card>

    <div class="finance-grid">
        {{-- FORMULARIO DE LIQUIDACIÓN SEGÚN TIPO --}}
        <x-ui.card>
            <form method="POST" action="{{ route('seguridad-social.calcular') }}" @submit="onFormSubmit($event)">
                @csrf
                <input type="hidden" name="tipo_cotizante" :value="tipoCotizante">

                <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;" x-text="tituloFormulario"></h3>

                {{-- Campo Periodo Común --}}
                <x-ui.field name="periodo" label="Periodo a cotizar" :required="true" help="Mes y año de cotización (Normativa 2026: SMMLV $ 1.750.905 · Tope 25 SMMLV $ 43.772.625).">
                    <input id="periodo" name="periodo" type="month" x-model="periodo" required max="{{ now()->format('Y-m') }}">
                </x-ui.field>

                {{-- ==================== CAMPOS ASALARIADO ==================== --}}
                <template x-if="tipoCotizante === 'ASALARIADO'">
                    <div style="display: grid; gap: 1rem;">
                        <x-ui.field name="salario" label="Salario mensual devengado" :required="true" help="Salario básico pactado en el contrato de trabajo.">
                            <div class="ui-money">
                                <input type="text" inputmode="decimal" x-model="salarioDisplay" @input="onSalarioInput" placeholder="Ej. 3.000.000" required>
                                <span class="ui-money-unit">COP</span>
                                <input type="hidden" name="salario" :value="salarioCanonico">
                            </div>
                        </x-ui.field>

                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--ui-text-small); cursor: pointer;">
                                <input type="checkbox" name="es_salario_integral" value="1" x-model="esSalarioIntegral" @change="calcularEnVivo">
                                <span>Salario integral (IBC al 70 % según Art. 18 Ley 50/1990)</span>
                            </label>

                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--ui-text-small); cursor: pointer;">
                                <input type="checkbox" name="exonerado_parafiscales" value="1" x-model="exoneradoParafiscales" @change="calcularEnVivo">
                                <span>Exonerado de SENA e ICBF (Art. 114-1 E.T. - Salarios &lt; 10 SMMLV)</span>
                            </label>
                        </div>

                        <x-ui.field name="clase_riesgo_arl_laboral" label="Clase de riesgo ARL empleador" :required="false" help="Tarifa de riesgos laborales a cargo del empleador.">
                            <select name="clase_riesgo_arl_laboral" x-model="claseRiesgoArlLaboral" @change="calcularEnVivo">
                                <option value="1">Clase I — Riesgo mínimo (0,522 %)</option>
                                <option value="2">Clase II — Riesgo bajo (1,044 %)</option>
                                <option value="3">Clase III — Riesgo medio (2,436 %)</option>
                                <option value="4">Clase IV — Riesgo alto (4,350 %)</option>
                                <option value="5">Clase V — Riesgo máximo (6,960 %)</option>
                            </select>
                        </x-ui.field>

                        <x-ui.field name="retencion_fuente" label="Retención en la fuente informada (opcional)" :required="false" help="Monto retenido por el empleador en la colilla de pago.">
                            <div class="ui-money">
                                <input type="text" inputmode="decimal" x-model="retencionDisplay" @input="onRetencionInput" placeholder="0">
                                <span class="ui-money-unit">COP</span>
                                <input type="hidden" name="retencion_fuente" :value="retencionCanonica">
                            </div>
                        </x-ui.field>
                    </div>
                </template>

                {{-- ==================== CAMPOS INDEPENDIENTE / SERVICIOS ==================== --}}
                <template x-if="tipoCotizante === 'INDEPENDIENTE'">
                    <div style="display: grid; gap: 1rem;">
                        <x-ui.field name="monto_contrato" label="Monto del contrato / Honorarios del mes" :required="true" help="Ingreso bruto pactado por prestación de servicios u honorarios.">
                            <div class="ui-money">
                                <input type="text" inputmode="decimal" x-model="montoContratoDisplay" @input="onMontoContratoInput" placeholder="Ej. 5.000.000" required>
                                <span class="ui-money-unit">COP</span>
                                <input type="hidden" name="monto_contrato" :value="montoContratoCanonico">
                            </div>
                        </x-ui.field>

                        <div style="background: var(--ui-raised); padding: 0.875rem 1rem; border-radius: var(--ui-radius); border: 1px solid var(--ui-border); display: grid; gap: 0.75rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--ui-text-small); cursor: pointer;">
                                <input type="checkbox" name="usar_costos_reales" value="1" x-model="usarCostosReales" @change="calcularEnVivo">
                                <span>Ingresar costos reales deducibles con factura electrónica (en lugar de costo presunto UGPP 0 %)</span>
                            </label>

                            <div x-show="usarCostosReales" style="margin-top: 0.5rem;">
                                <x-ui.field name="costos_reales" label="Costos deducibles soportados ($ COP)" :required="false" help="Monto total de costos y gastos deducibles certificados.">
                                    <div class="ui-money">
                                        <input type="text" inputmode="decimal" x-model="costosRealesDisplay" @input="onCostosRealesInput" placeholder="Ej. 1.000.000">
                                        <span class="ui-money-unit">COP</span>
                                        <input type="hidden" name="costos_reales" :value="costosRealesCanonico">
                                    </div>
                                </x-ui.field>
                            </div>
                        </div>

                        <x-ui.field name="clase_riesgo_arl" label="Nivel de riesgo ARL independiente" :required="false" help="Tarifa ARL obligatoria o voluntaria para contratos de servicios.">
                            <select name="clase_riesgo_arl" x-model="claseRiesgoArl" @change="calcularEnVivo">
                                <option value="0">Sin afiliación ARL o no obligatoria (0,00 %)</option>
                                <option value="1">Clase I — Riesgo mínimo (0,522 %)</option>
                                <option value="2">Clase II — Riesgo bajo (1,044 %)</option>
                                <option value="3">Clase III — Riesgo medio (2,436 %)</option>
                                <option value="4">Clase IV — Riesgo alto (4,350 %)</option>
                                <option value="5">Clase V — Riesgo máximo (6,960 %)</option>
                            </select>
                        </x-ui.field>
                    </div>
                </template>

                {{-- ==================== CAMPOS RENTISTA / INMOBILIARIO ==================== --}}
                <template x-if="tipoCotizante === 'RENTISTA_CAPITAL'">
                    <div style="display: grid; gap: 1rem;">
                        <x-ui.field name="codigo_actividad" label="Actividad económica (Esquema de costos UGPP)" :required="true">
                            <select name="codigo_actividad" x-model="actividadRentista" @change="calcularEnVivo">
                                <option value="RENTAS_CAPITAL">Rentas de capital (intereses, rendimientos, dividendos) — Presunción: 28,08 %</option>
                                <option value="ACTIVIDADES_INMOBILIARIAS">Actividades inmobiliarias (arrendamientos) — Presunción: 65,70 %</option>
                            </select>
                        </x-ui.field>

                        <x-ui.field name="monto_contrato" label="Monto de ingresos brutos del mes" :required="true" help="Ingresos brutos percibidos por capital o arrendamientos.">
                            <div class="ui-money">
                                <input type="text" inputmode="decimal" x-model="montoContratoDisplay" @input="onMontoContratoInput" placeholder="Ej. 10.000.000" required>
                                <span class="ui-money-unit">COP</span>
                                <input type="hidden" name="monto_contrato" :value="montoContratoCanonico">
                            </div>
                        </x-ui.field>

                        <div style="background: var(--ui-raised); padding: 0.875rem 1rem; border-radius: var(--ui-radius); border: 1px solid var(--ui-border); display: grid; gap: 0.75rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--ui-text-small); cursor: pointer;">
                                <input type="checkbox" name="usar_costos_reales" value="1" x-model="usarCostosReales" @change="calcularEnVivo">
                                <span>Ingresar costos reales deducibles (si son superiores al costo presunto legal)</span>
                            </label>

                            <div x-show="usarCostosReales" style="margin-top: 0.5rem;">
                                <x-ui.field name="costos_reales" label="Costos deducibles soportados ($ COP)" :required="false">
                                    <div class="ui-money">
                                        <input type="text" inputmode="decimal" x-model="costosRealesDisplay" @input="onCostosRealesInput" placeholder="Ej. 3.000.000">
                                        <span class="ui-money-unit">COP</span>
                                        <input type="hidden" name="costos_reales" :value="costosRealesCanonico">
                                    </div>
                                </x-ui.field>
                            </div>
                        </div>

                        <div style="background: rgba(14, 165, 233, 0.08); padding: 0.75rem 1rem; border-radius: var(--ui-radius); font-size: 0.8125rem; border: 1px solid var(--ui-border);">
                            ℹ️ <strong>Nota legal ARL (RF-10):</strong> Los rentistas de capital e ingresos inmobiliarios no están sujetos a cotización de ARL. Esta se excluye de su liquidación.
                        </div>
                    </div>
                </template>

                {{-- ==================== CAMPOS MULTI-INGRESO SIMULTÁNEO ==================== --}}
                <template x-if="tipoCotizante === 'MULTI_INGRESO'">
                    <div style="display: grid; gap: 1.25rem;">
                        <div style="border: 1px solid var(--ui-border); padding: 1rem; border-radius: var(--ui-radius); background: var(--ui-surface);">
                            <h4 style="font-size: 0.9375rem; font-weight: 600; margin-bottom: 0.75rem;">1. Contrato laboral (Nómina - Primer consumo de cupo)</h4>
                            
                            <x-ui.field name="salario" label="Salario devengado en nómina ($ COP)" :required="false" help="Ingresa tu salario mensual si eres asalariado.">
                                <div class="ui-money">
                                    <input type="text" inputmode="decimal" x-model="salarioDisplay" @input="onSalarioInput" placeholder="0">
                                    <span class="ui-money-unit">COP</span>
                                    <input type="hidden" name="salario" :value="salarioCanonico">
                                </div>
                            </x-ui.field>

                            <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 0.5rem;">
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--ui-text-small); cursor: pointer;">
                                    <input type="checkbox" name="es_salario_integral" value="1" x-model="esSalarioIntegral" @change="calcularEnVivo">
                                    <span>Salario integral (70 %)</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--ui-text-small); cursor: pointer;">
                                    <input type="checkbox" name="exonerado_parafiscales" value="1" x-model="exoneradoParafiscales" @change="calcularEnVivo">
                                    <span>Exonerado SENA/ICBF</span>
                                </label>
                            </div>
                        </div>

                        <div style="border: 1px solid var(--ui-border); padding: 1rem; border-radius: var(--ui-radius); background: var(--ui-surface);">
                            <h4 style="font-size: 0.9375rem; font-weight: 600; margin-bottom: 0.75rem;">2. Ingresos independientes / Rentas de capital</h4>
                            
                            <div style="display: grid; gap: 1rem;">
                                <div>
                                    <label style="font-size: var(--ui-text-small); font-weight: 600;">Prestación de servicios (Honorarios):</label>
                                    <div class="ui-money" style="margin-top: 0.25rem;">
                                        <input type="text" inputmode="decimal" x-model="multiServiciosDisplay" @input="onMultiServiciosInput" placeholder="0">
                                        <span class="ui-money-unit">COP</span>
                                    </div>
                                </div>

                                <div>
                                    <label style="font-size: var(--ui-text-small); font-weight: 600;">Rentas de capital (intereses, rendimientos - Costo presunto: 28,08 %):</label>
                                    <div class="ui-money" style="margin-top: 0.25rem;">
                                        <input type="text" inputmode="decimal" x-model="multiCapitalDisplay" @input="onMultiCapitalInput" placeholder="0">
                                        <span class="ui-money-unit">COP</span>
                                    </div>
                                </div>

                                <div>
                                    <label style="font-size: var(--ui-text-small); font-weight: 600;">Actividades inmobiliarias (arrendamientos - Costo presunto: 65,70 %):</label>
                                    <div class="ui-money" style="margin-top: 0.25rem;">
                                        <input type="text" inputmode="decimal" x-model="multiInmobDisplay" @input="onMultiInmobInput" placeholder="0">
                                        <span class="ui-money-unit">COP</span>
                                    </div>
                                </div>

                                <x-ui.field name="clase_riesgo_arl_servicios" label="Nivel de riesgo ARL (solo servicios)" :required="false">
                                    <select name="clase_riesgo_arl_servicios" x-model="claseRiesgoArl" @change="calcularEnVivo">
                                        <option value="0">Sin ARL (0,00 %)</option>
                                        <option value="1">Clase I (0,522 %)</option>
                                        <option value="2">Clase II (1,044 %)</option>
                                        <option value="3">Clase III (2,436 %)</option>
                                        <option value="4">Clase IV (4,350 %)</option>
                                        <option value="5">Clase V (6,960 %)</option>
                                    </select>
                                </x-ui.field>
                            </div>
                        </div>
                    </div>
                </template>
            </form>
        </x-ui.card>

        {{-- RESULTADOS Y DESGLOSE DETALLADO --}}
        <div style="display: grid; gap: var(--ui-space-6);">
            <x-ui.card title="Desglose de liquidación y control del tope de 25 SMMLV">
                <template x-if="!tieneCalculo">
                    <x-ui.empty-state title="Ingresa tus ingresos para calcular" description="Digita tus ingresos del periodo y el sistema validará el cupo de 25 SMMLV ($ 43.772.625) y los aportes de ley." />
                </template>

                <template x-if="tieneCalculo">
                    <div style="display: grid; gap: 1.25rem;">
                        
                        {{-- ALERTA DESTACADA DE TOPE DE 25 SMMLV Y EXCEDENTE EXENTO (RF-01, RF-03, RF-05, RF-07) --}}
                        <div x-show="resultados.superoTope25" class="ui-notice" style="border-left: 4px solid var(--ui-success); background: rgba(34, 197, 94, 0.1); padding: 1rem;">
                            <strong style="color: var(--ui-success); font-size: 0.9375rem;">🛡️ Tope Legal de 25 SMMLV Alcanzado ($ <span x-text="formatMoney(tope25)"></span>)</strong>
                            <p style="margin: 0.35rem 0 0 0; font-size: var(--ui-text-small);">
                                Conforme al Art. 18 de la Ley 100 de 1993 y la Sentencia C-1089 de 2003, tu cotización mensual se limita exactamente al techo de 25 SMMLV. 
                                El excedente devengado de <strong>$ <span x-text="formatMoney(resultados.excedenteTotalExento)"></span></strong> queda legalmente <strong>100 % EXENTO</strong> de aportes a salud, pensión, FSP y ARL.
                            </p>
                        </div>

                        {{-- AVISO SI EL SALARIO YA COPÓ EL CUPO --}}
                        <div x-show="resultados.cupoLaboralCopoTope" class="ui-notice" style="border-left: 4px solid var(--accent); background: rgba(14, 165, 233, 0.1); padding: 0.875rem;">
                            <p style="margin: 0; font-size: var(--ui-text-small);">
                                💼 <strong>Cupo laboral copado:</strong> Tu salario cubre los 25 SMMLV ($ <span x-text="formatMoney(tope25)"></span>). El cupo para ingresos independientes es $ 0, quedando completamente exonerados de cotizar en este mes.
                            </p>
                        </div>

                        {{-- AVISO DE FSP (20 SMMLV) --}}
                        <div x-show="resultados.supera20Smmlv" class="ui-notice" style="border-left: 3px solid var(--ui-warning); font-size: var(--ui-text-small);">
                            <p style="margin: 0;">
                                ⭐ <strong>Fondo de Solidaridad Pensional (2,00 %):</strong> El IBC consolidado total supera los 20 SMMLV ($ <span x-text="formatMoney(20 * smmlv)"></span>), aplicando la tarifa máxima de FSP.
                            </p>
                        </div>

                        {{-- TARJETAS ESTADÍSTICAS RESUMEN --}}
                        <div class="finance-stats" style="margin: 0; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                            <div class="finance-stat">
                                <dt>IBC Consolidado Cotizado</dt>
                                <dd style="color: var(--accent); font-weight: 700;" x-text="'$ ' + formatMoney(resultados.ibcConsolidadoTotal)"></dd>
                            </div>

                            <div class="finance-stat">
                                <dt>Excedente Exento de Cotización</dt>
                                <dd style="color: var(--ui-success); font-weight: 700;" x-text="'$ ' + formatMoney(resultados.excedenteTotalExento)"></dd>
                            </div>

                            <div class="finance-stat">
                                <dt>Total Aportes Cotizante</dt>
                                <dd style="color: var(--accent);" x-text="'$ ' + formatMoney(resultados.totalCotizante)"></dd>
                            </div>
                        </div>

                        {{-- DESGLOSE NÓMINA LABORAL (TIPO E) SI APLICA --}}
                        <template x-if="resultados.tieneLaboral">
                            <div style="border: 1px solid var(--ui-border); border-radius: var(--ui-radius); padding: 1rem; background: var(--ui-surface);">
                                <h4 style="font-size: 0.9375rem; font-weight: 700; margin-bottom: 0.5rem; display: flex; justify-content: space-between;">
                                    <span>💼 Liquidación Contrato Laboral</span>
                                    <span style="font-weight: 600; color: var(--accent);" x-text="'IBC: $ ' + formatMoney(resultados.ibcLaboral)"></span>
                                </h4>

                                <dl class="finance-breakdown" style="margin: 0;">
                                    <dt>Salario devengado:</dt>
                                    <dd x-text="'$ ' + formatMoney(resultados.salarioLaboral)"></dd>

                                    <dt>Salud empleado (4 %):</dt>
                                    <dd x-text="'- $ ' + formatMoney(resultados.saludEmpleado)"></dd>

                                    <dt>Pensión empleado (4 %):</dt>
                                    <dd x-text="'- $ ' + formatMoney(resultados.pensionEmpleado)"></dd>

                                    <dt>FSP empleado (<span x-text="resultados.tarifaFsp"></span> %):</dt>
                                    <dd x-text="resultados.fspLaboral > 0 ? ('- $ ' + formatMoney(resultados.fspLaboral)) : 'No aplica ($ 0)'"></dd>

                                    <dt style="font-weight: 600;">Total descuentos de nómina:</dt>
                                    <dd style="font-weight: 600;" x-text="'$ ' + formatMoney(resultados.totalDescuentosEmpleado)"></dd>

                                    <dt style="border-top: 1px dashed var(--ui-border); padding-top: 0.5rem; color: var(--ui-muted);">Aporte Salud Empleador (8,5 %):</dt>
                                    <dd style="border-top: 1px dashed var(--ui-border); padding-top: 0.5rem; color: var(--ui-muted);" x-text="'$ ' + formatMoney(resultados.saludPatronal)"></dd>

                                    <dt style="color: var(--ui-muted);">Aporte Pensión Empleador (12 %):</dt>
                                    <dd style="color: var(--ui-muted);" x-text="'$ ' + formatMoney(resultados.pensionPatronal)"></dd>

                                    <dt style="color: var(--ui-muted);">ARL Empleador (<span x-text="resultados.tarifaArlLaboral"></span> %):</dt>
                                    <dd style="color: var(--ui-muted);" x-text="'$ ' + formatMoney(resultados.arlPatronal)"></dd>

                                    <dt style="color: var(--ui-muted);">Parafiscales (CCF 4 %<span x-text="exoneradoParafiscales ? ' - Exonerado SENA/ICBF' : ' + SENA 2 % + ICBF 3 %'"></span> sin tope):</dt>
                                    <dd style="color: var(--ui-muted);" x-text="'$ ' + formatMoney(resultados.totalParafiscales)"></dd>
                                </dl>
                            </div>
                        </template>

                        {{-- DESGLOSE ACTIVIDAD INDEPENDIENTE (TIPO I) SI APLICA --}}
                        <template x-if="resultados.tieneIndependiente">
                            <div style="border: 1px solid var(--ui-border); border-radius: var(--ui-radius); padding: 1rem; background: var(--ui-surface);">
                                <h4 style="font-size: 0.9375rem; font-weight: 700; margin-bottom: 0.5rem; display: flex; justify-content: space-between;">
                                    <span>🔨 Liquidación Independiente / Rentas</span>
                                    <span style="font-weight: 600; color: var(--accent);" x-text="'IBC Cotizado: $ ' + formatMoney(resultados.ibcIndepCotizado)"></span>
                                </h4>

                                <dl class="finance-breakdown" style="margin: 0;">
                                    <dt>Ingresos brutos declarados:</dt>
                                    <dd x-text="'$ ' + formatMoney(resultados.totalIngresosBrutosIndep)"></dd>

                                    <dt>Costos deducidos aplicados:</dt>
                                    <dd style="color: var(--ui-danger);" x-text="'- $ ' + formatMoney(resultados.totalCostosDeducidosIndep)"></dd>

                                    <dt>Ingreso neto base:</dt>
                                    <dd style="font-weight: 600;" x-text="'$ ' + formatMoney(resultados.totalIngresoNetoIndep)"></dd>

                                    <dt>IBC preliminar (40 %):</dt>
                                    <dd x-text="'$ ' + formatMoney(resultados.sumaIbcIndepPreliminar)"></dd>

                                    <dt>Cupo disponible asignado:</dt>
                                    <dd style="font-weight: 600;" x-text="'$ ' + formatMoney(resultados.cupoDisponible)"></dd>

                                    <dt style="border-top: 1px solid var(--ui-border); padding-top: 0.5rem;">Aporte Salud independiente (12,5 %):</dt>
                                    <dd style="border-top: 1px solid var(--ui-border); padding-top: 0.5rem;" x-text="'$ ' + formatMoney(resultados.saludIndep)"></dd>

                                    <dt>Aporte Pensión independiente (16 %):</dt>
                                    <dd x-text="'$ ' + formatMoney(resultados.pensionIndep)"></dd>

                                    <dt>FSP independiente (<span x-text="resultados.tarifaFsp"></span> %):</dt>
                                    <dd x-text="resultados.fspIndep > 0 ? ('$ ' + formatMoney(resultados.fspIndep)) : 'No aplica ($ 0)'"></dd>

                                    <dt>ARL sobre servicios (<span x-text="resultados.tarifaArlServicios"></span> % - Excluye capital):</dt>
                                    <dd x-text="resultados.arlIndep > 0 ? ('$ ' + formatMoney(resultados.arlIndep)) : 'Sin ARL ($ 0)'"></dd>

                                    <dt style="font-weight: 700; border-top: 1px solid var(--ui-border); padding-top: 0.5rem;">Total aportes independiente a pagar:</dt>
                                    <dd style="font-weight: 700; border-top: 1px solid var(--ui-border); padding-top: 0.5rem; color: var(--accent);" x-text="'$ ' + formatMoney(resultados.totalIndep)"></dd>
                                </dl>
                            </div>
                        </template>

                        {{-- BOTÓN DE PAGO --}}
                        <template x-if="resultados.totalPagarPila > 0">
                            <div style="border-top: 1px solid var(--ui-border); padding-top: 1rem; display: grid; gap: 0.75rem;">
                                <button type="button" 
                                    class="ui-button ui-button--primary" 
                                    style="padding: 0.875rem 1.25rem; font-size: 1rem; font-weight: 700; justify-content: center;"
                                    @click="abrirModalPago">
                                    💳 Registrar pago PILA ($ <span x-text="formatMoney(resultados.totalPagarPila)"></span>)
                                </button>
                                <p class="ui-help" style="margin: 0; text-align: center;">
                                    Se registrará automáticamente en <strong>Gastos</strong> (categoría Seguridad Social).
                                </p>
                            </div>
                        </template>
                    </div>
                </template>
            </x-ui.card>
        </div>
    </div>

    {{-- MODAL PARA CONFIRMAR PAGO PILA --}}
    <template x-teleport="body">
        <div x-show="modalPagoAbierto" 
             x-cloak
             style="position: fixed; inset: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.75); z-index: 99999; display: flex; align-items: center; justify-content: center; margin: 0; padding: 1.5rem;"
             @keydown.escape.window="modalPagoAbierto = false">
            
            <div class="ui-card" 
                 style="width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto; margin: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6); position: relative;" 
                 @click.away="modalPagoAbierto = false">
                <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0;">Confirmar pago de seguridad social</h2>
                    <button type="button" @click="modalPagoAbierto = false" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--ui-muted); line-height: 1;">&times;</button>
                </header>

                <form method="POST" action="{{ route('seguridad-social.pagos.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="redirect_to" value="seguridad-social.index">
                    <input type="hidden" name="periodo" :value="periodo">
                    <input type="hidden" name="salud" :value="resultados.modalSalud">
                    <input type="hidden" name="pension" :value="resultados.modalPension">
                    <input type="hidden" name="fsp" :value="resultados.modalFsp">
                    <input type="hidden" name="arl" :value="resultados.modalArl">
                    <input type="hidden" name="intereses_mora" value="0">

                    <div style="background: rgba(var(--accent-rgb, 14, 165, 233), 0.08); padding: 1rem; border-radius: var(--ui-radius); margin-bottom: 1rem; border: 1px solid var(--ui-border);">
                        <div style="display: flex; justify-content: space-between; font-size: var(--ui-text-small); margin-bottom: 0.5rem;">
                            <span>Periodo cotizado:</span>
                            <strong x-text="periodo"></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 1.125rem; font-weight: 700; color: var(--accent); padding-top: 0.5rem; border-top: 1px solid var(--ui-border);">
                            <span>Total a debitar:</span>
                            <span>$ <span x-text="formatMoney(resultados.totalPagarPila)"></span></span>
                        </div>
                    </div>

                    <div style="display: grid; gap: 1rem; margin-bottom: 1.25rem;">
                        <x-ui.field name="fecha_pago" label="Fecha de pago" :required="true">
                            <input type="date" name="fecha_pago" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                        </x-ui.field>

                        <x-ui.field name="medio_pago" label="Medio de pago" :required="true">
                            <select name="medio_pago" required>
                                @foreach(App\Models\Gasto::MEDIOS_PAGO as $medio)
                                    <option value="{{ $medio }}" @selected($medio === 'TRANSFERENCIA')>{{ ucfirst(strtolower(str_replace('_', ' ', $medio))) }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <x-ui.field name="numero_planilla" label="Número de planilla PILA (opcional)" :required="false">
                            <input type="text" name="numero_planilla" placeholder="Ej. 8839201948">
                        </x-ui.field>

                        <x-ui.field name="soporte" label="Comprobante PILA (opcional)" :required="false">
                            <input type="file" name="soporte" accept=".pdf,.jpg,.jpeg,.png">
                        </x-ui.field>
                    </div>

                    <div class="ui-inline" style="justify-content: flex-end; gap: 0.75rem;">
                        <x-ui.button type="button" variant="secondary" @click="modalPagoAbierto = false">
                            Cancelar
                        </x-ui.button>
                        <x-ui.button type="submit" variant="primary">
                            Confirmar y registrar gasto
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    {{-- TABLA DE HISTÓRICO DE PAGOS Y GASTOS VINCULADOS --}}
    <x-ui.card title="Historial reciente de pagos PILA y gastos generados">
        @if($pagosRecientes->isEmpty())
            <x-ui.empty-state title="No tienes pagos PILA registrados" description="Cuando realices y guardes un pago de seguridad social, aparecerá aquí vinculado con su respectivo gasto." />
        @else
            <table class="ui-table">
                <caption>Últimos pagos registrados en el sistema</caption>
                <thead>
                    <tr>
                        <th>Periodo</th>
                        <th>Fecha pago</th>
                        <th>Planilla</th>
                        <th data-align="right">Total pagado</th>
                        <th>Gasto registrado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pagosRecientes as $pago)
                        <tr>
                            <td><strong>{{ $pago->periodo }}</strong></td>
                            <td><time data-date="{{ $pago->fecha_pago->format('Y-m-d') }}">{{ $pago->fecha_pago->format('d/m/Y') }}</time></td>
                            <td>{{ $pago->numero_planilla ?? '—' }}</td>
                            <td class="ui-number"><strong>$ {{ number_format($pago->total, 0, ',', '.') }}</strong></td>
                            <td>
                                @if($pago->gasto)
                                    <span class="ui-badge" style="background: rgba(34, 197, 94, 0.15); color: var(--ui-success); font-weight: 600;">
                                        Gasto vinculado
                                    </span>
                                @else
                                    <span style="color: var(--ui-muted);">Sin gasto</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('gastos.index') }}" class="ui-help" style="font-weight: 600; text-decoration: underline;">
                                    Ver en Gastos ›
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    <x-ui.calculation-notice />
</div>

{{-- SCRIPT REACTIVO DE LA CALCULADORA UNIFICADA --}}
<script>
function seguridadSocialApp() {
    return {
        tipoCotizante: '{{ session('tipo_cotizante_seleccionado', 'INDEPENDIENTE') }}',
        periodo: '{{ old('periodo', session('calculo_reciente.periodo', $periodoActual)) }}',
        smmlv: {{ (float) ($parametro->valor_smmlv ?? 1750905) }},
        tope25: {{ (float) (($parametro->valor_smmlv ?? 1750905) * 25) }},
        tarifaSaludIndep: {{ (float) ($parametro->tarifa_salud_indep ?? 12.5) }},
        tarifaPensionIndep: {{ (float) ($parametro->tarifa_pension_indep ?? 16.0) }},
        tarifaSaludEmp: {{ (float) ($parametro->tarifa_salud_emp ?? 4.0) }},
        tarifaPensionEmp: {{ (float) ($parametro->tarifa_pension_emp ?? 4.0) }},
        tarifaSaludPatronal: {{ (float) ($parametro->tarifa_salud_patronal ?? 8.5) }},
        tarifaPensionPatronal: {{ (float) ($parametro->tarifa_pension_patronal ?? 12.0) }},

        salarioDisplay: '{{ session('calculo_reciente.salario', '') }}',
        salarioCanonico: '{{ session('calculo_reciente.salario', '0') }}',
        esSalarioIntegral: false,
        exoneradoParafiscales: true,
        claseRiesgoArlLaboral: 1,
        retencionDisplay: '{{ session('calculo_reciente.retencion_fuente', '') }}',
        retencionCanonica: '{{ session('calculo_reciente.retencion_fuente', '0') }}',

        montoContratoDisplay: '{{ session('calculo_reciente.monto_contrato', session('calculo_reciente.ingresos_brutos', '')) }}',
        montoContratoCanonico: '{{ session('calculo_reciente.monto_contrato', session('calculo_reciente.ingresos_brutos', '0')) }}',
        actividadRentista: 'RENTAS_CAPITAL',
        usarCostosReales: false,
        costosRealesDisplay: '',
        costosRealesCanonico: '0',
        claseRiesgoArl: 0,

        // Inputs modo multi-ingreso
        multiServiciosDisplay: '',
        multiServiciosCanonico: '0',
        multiCapitalDisplay: '',
        multiCapitalCanonico: '0',
        multiInmobDisplay: '',
        multiInmobCanonico: '0',

        modalPagoAbierto: false,
        tieneCalculo: {{ session('calculo_reciente') ? 'true' : 'false' }},

        resultados: {
            superoTope25: false,
            cupoLaboralCopoTope: false,
            supera20Smmlv: false,
            ibcConsolidadoTotal: 0,
            excedenteTotalExento: 0,
            cupoDisponible: 0,
            tarifaFsp: '0,00',
            totalCotizante: 0,
            totalPagarPila: 0,
            tieneLaboral: false,
            salarioLaboral: 0,
            ibcLaboral: 0,
            saludEmpleado: 0,
            pensionEmpleado: 0,
            fspLaboral: 0,
            totalDescuentosEmpleado: 0,
            salarioNetoEstimado: 0,
            saludPatronal: 0,
            pensionPatronal: 0,
            tarifaArlLaboral: '0,522',
            arlPatronal: 0,
            totalParafiscales: 0,
            tieneIndependiente: false,
            totalIngresosBrutosIndep: 0,
            totalCostosDeducidosIndep: 0,
            totalIngresoNetoIndep: 0,
            sumaIbcIndepPreliminar: 0,
            ibcIndepCotizado: 0,
            saludIndep: 0,
            pensionIndep: 0,
            fspIndep: 0,
            tarifaArlServicios: '0,00',
            arlIndep: 0,
            totalIndep: 0,
            modalSalud: 0,
            modalPension: 0,
            modalFsp: 0,
            modalArl: 0,
        },

        init() {
            if (this.salarioCanonico && Number(this.salarioCanonico) > 0) {
                this.salarioDisplay = this.formatNumber(Number(this.salarioCanonico));
            }
            if (this.montoContratoCanonico && Number(this.montoContratoCanonico) > 0) {
                this.montoContratoDisplay = this.formatNumber(Number(this.montoContratoCanonico));
            }
            this.calcularEnVivo();
        },

        get tituloFormulario() {
            if (this.tipoCotizante === 'ASALARIADO') return 'Liquidación para Asalariado (Nómina)';
            if (this.tipoCotizante === 'RENTISTA_CAPITAL') return 'Liquidación para Rentista / Inmobiliario';
            if (this.tipoCotizante === 'MULTI_INGRESO') return 'Liquidación Multi-ingreso (Prelación 25 SMMLV)';
            return 'Liquidación para Independiente / Prestación de Servicios';
        },

        cambiarTipo(nuevoTipo) {
            this.tipoCotizante = nuevoTipo;
            this.calcularEnVivo();
        },

        limpiarCadena(valor) {
            if (!valor) return '0';
            return String(valor).replace(/[^\d]/g, '');
        },

        formatNumber(num) {
            if (!num && num !== 0) return '';
            return new Intl.NumberFormat('es-CO').format(num);
        },

        formatMoney(num) {
            if (!num && num !== 0) return '0';
            return new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(num);
        },

        onSalarioInput(e) {
            const raw = this.limpiarCadena(e.target.value);
            this.salarioCanonico = raw;
            this.salarioDisplay = raw ? this.formatNumber(Number(raw)) : '';
            this.calcularEnVivo();
        },

        onRetencionInput(e) {
            const raw = this.limpiarCadena(e.target.value);
            this.retencionCanonica = raw;
            this.retencionDisplay = raw ? this.formatNumber(Number(raw)) : '';
            this.calcularEnVivo();
        },

        onMontoContratoInput(e) {
            const raw = this.limpiarCadena(e.target.value);
            this.montoContratoCanonico = raw;
            this.montoContratoDisplay = raw ? this.formatNumber(Number(raw)) : '';
            this.calcularEnVivo();
        },

        onCostosRealesInput(e) {
            const raw = this.limpiarCadena(e.target.value);
            this.costosRealesCanonico = raw;
            this.costosRealesDisplay = raw ? this.formatNumber(Number(raw)) : '';
            this.calcularEnVivo();
        },

        onMultiServiciosInput(e) {
            const raw = this.limpiarCadena(e.target.value);
            this.multiServiciosCanonico = raw;
            this.multiServiciosDisplay = raw ? this.formatNumber(Number(raw)) : '';
            this.calcularEnVivo();
        },

        onMultiCapitalInput(e) {
            const raw = this.limpiarCadena(e.target.value);
            this.multiCapitalCanonico = raw;
            this.multiCapitalDisplay = raw ? this.formatNumber(Number(raw)) : '';
            this.calcularEnVivo();
        },

        onMultiInmobInput(e) {
            const raw = this.limpiarCadena(e.target.value);
            this.multiInmobCanonico = raw;
            this.multiInmobDisplay = raw ? this.formatNumber(Number(raw)) : '';
            this.calcularEnVivo();
        },

        calcularEnVivo() {
            const sm = this.smmlv;
            const tope = this.tope25;
            const arlMap = { 0: 0, 1: 0.00522, 2: 0.01044, 3: 0.02436, 4: 0.0435, 5: 0.0696 };
            const arlStr = { 0: '0,00', 1: '0,522', 2: '1,044', 3: '2,436', 4: '4,350', 5: '6,960' };

            let salario = 0;
            let esIntegral = this.esSalarioIntegral;
            let actServicios = 0;
            let actCapital = 0;
            let actInmob = 0;

            if (this.tipoCotizante === 'ASALARIADO') {
                salario = Number(this.salarioCanonico) || 0;
            } else if (this.tipoCotizante === 'INDEPENDIENTE') {
                actServicios = Number(this.montoContratoCanonico) || 0;
            } else if (this.tipoCotizante === 'RENTISTA_CAPITAL') {
                const monto = Number(this.montoContratoCanonico) || 0;
                if (this.actividadRentista === 'ACTIVIDADES_INMOBILIARIAS') {
                    actInmob = monto;
                } else {
                    actCapital = monto;
                }
            } else if (this.tipoCotizante === 'MULTI_INGRESO') {
                salario = Number(this.salarioCanonico) || 0;
                actServicios = Number(this.multiServiciosCanonico) || 0;
                actCapital = Number(this.multiCapitalCanonico) || 0;
                actInmob = Number(this.multiInmobCanonico) || 0;
            }

            if (salario <= 0 && actServicios <= 0 && actCapital <= 0 && actInmob <= 0) {
                this.tieneCalculo = false;
                return;
            }
            this.tieneCalculo = true;

            // 1. Laboral
            let baseLaboral = esIntegral ? Math.round(salario * 0.70) : salario;
            let ibcLaboral = 0;
            let excedenteLaboral = 0;
            let topeLaboralAlcanzado = false;

            if (salario > 0) {
                if (baseLaboral > tope) {
                    ibcLaboral = tope;
                    excedenteLaboral = baseLaboral - tope;
                    topeLaboralAlcanzado = true;
                } else {
                    ibcLaboral = Math.max(sm, baseLaboral);
                }
            }

            // 2. Cupo disponible
            let cupoDisponible = Math.max(0, tope - ibcLaboral);

            // 3. Actividades independientes
            // Costos deducidos
            let costoServicios = (this.tipoCotizante === 'INDEPENDIENTE' && this.usarCostosReales) 
                ? (Number(this.costosRealesCanonico) || 0) : 0;
            let netoServicios = Math.max(0, actServicios - costoServicios);
            let ibcServiciosPrelim = Math.round(netoServicios * 0.40);

            let presuncionCap = (this.tipoCotizante === 'RENTISTA_CAPITAL' && this.usarCostosReales)
                ? (Number(this.costosRealesCanonico) || 0) : Math.round(actCapital * 0.2808);
            let netoCapital = Math.max(0, actCapital - presuncionCap);
            let ibcCapitalPrelim = Math.round(netoCapital * 0.40);

            let presuncionInmob = (this.tipoCotizante === 'RENTISTA_CAPITAL' && this.usarCostosReales)
                ? (Number(this.costosRealesCanonico) || 0) : Math.round(actInmob * 0.6570);
            let netoInmob = Math.max(0, actInmob - presuncionInmob);
            let ibcInmobPrelim = Math.round(netoInmob * 0.40);

            let sumaIbcIndepPrelim = ibcServiciosPrelim + ibcCapitalPrelim + ibcInmobPrelim;
            let ibcIndepCotizado = 0;
            let excedenteIndep = 0;
            let ibcServiciosCotizado = 0;

            if (sumaIbcIndepPrelim > 0) {
                if (cupoDisponible === 0) {
                    ibcIndepCotizado = 0;
                    excedenteIndep = sumaIbcIndepPrelim;
                } else if (sumaIbcIndepPrelim > cupoDisponible) {
                    ibcIndepCotizado = cupoDisponible;
                    excedenteIndep = sumaIbcIndepPrelim - cupoDisponible;
                    ibcServiciosCotizado = Math.min(ibcServiciosPrelim, cupoDisponible);
                } else {
                    ibcIndepCotizado = (salario <= 0 && sumaIbcIndepPrelim < sm) ? sm : sumaIbcIndepPrelim;
                    ibcServiciosCotizado = ibcServiciosPrelim;
                }
            }

            // 4. Consolidación
            let ibcConsolidadoTotal = ibcLaboral + ibcIndepCotizado;
            let excedenteTotalExento = excedenteLaboral + excedenteIndep;
            let superoTope25 = excedenteTotalExento > 0 || ibcConsolidadoTotal >= tope;
            let cupoLaboralCopoTope = topeLaboralAlcanzado && sumaIbcIndepPrelim > 0;

            // 5. FSP
            let supera20Smmlv = ibcConsolidadoTotal >= (20 * sm);
            let fspPorc = 0;
            if (supera20Smmlv) fspPorc = 0.02;
            else if (ibcConsolidadoTotal >= 19 * sm) fspPorc = 0.016;
            else if (ibcConsolidadoTotal >= 18 * sm) fspPorc = 0.014;
            else if (ibcConsolidadoTotal >= 17 * sm) fspPorc = 0.012;
            else if (ibcConsolidadoTotal >= 16 * sm) fspPorc = 0.01;
            else if (ibcConsolidadoTotal >= 4 * sm) fspPorc = 0.01;

            // 6. Liquidación Laboral
            let saludEmpleado = Math.round(ibcLaboral * 0.04);
            let pensionEmpleado = Math.round(ibcLaboral * 0.04);
            let fspLaboral = Math.round(ibcLaboral * fspPorc);
            let totalDescuentosEmpleado = saludEmpleado + pensionEmpleado + fspLaboral;
            let retencion = Number(this.retencionCanonica) || 0;
            let salarioNetoEstimado = Math.max(0, salario - totalDescuentosEmpleado - retencion);

            let saludPatronal = Math.round(ibcLaboral * 0.085);
            let pensionPatronal = Math.round(ibcLaboral * 0.12);
            let arlFactorLab = arlMap[this.claseRiesgoArlLaboral] || 0.00522;
            let arlPatronal = Math.round(ibcLaboral * arlFactorLab);

            // Parafiscales sobre 100% u 70% sin tope
            let ccf = Math.round(baseLaboral * 0.04);
            let sena = this.exoneradoParafiscales ? 0 : Math.round(baseLaboral * 0.02);
            let icbf = this.exoneradoParafiscales ? 0 : Math.round(baseLaboral * 0.03);
            let totalParafiscales = ccf + sena + icbf;

            // 7. Liquidación Independiente
            let saludIndep = Math.round(ibcIndepCotizado * 0.125);
            let pensionIndep = Math.round(ibcIndepCotizado * 0.16);
            let fspIndep = Math.round(ibcIndepCotizado * fspPorc);

            // ARL solo sobre porción de servicios dentro del cupo
            let arlFactorServ = arlMap[this.claseRiesgoArl] || 0;
            let arlIndep = Math.round(ibcServiciosCotizado * arlFactorServ);
            let totalIndep = saludIndep + pensionIndep + fspIndep + arlIndep;

            this.resultados = {
                superoTope25: superoTope25,
                cupoLaboralCopoTope: cupoLaboralCopoTope,
                supera20Smmlv: supera20Smmlv,
                ibcConsolidadoTotal: ibcConsolidadoTotal,
                excedenteTotalExento: excedenteTotalExento,
                cupoDisponible: cupoDisponible,
                tarifaFsp: (fspPorc * 100).toFixed(2).replace('.', ','),
                totalCotizante: totalDescuentosEmpleado + totalIndep,
                totalPagarPila: totalIndep,
                tieneLaboral: salario > 0,
                salarioLaboral: salario,
                ibcLaboral: ibcLaboral,
                saludEmpleado: saludEmpleado,
                pensionEmpleado: pensionEmpleado,
                fspLaboral: fspLaboral,
                totalDescuentosEmpleado: totalDescuentosEmpleado,
                salarioNetoEstimado: salarioNetoEstimado,
                saludPatronal: saludPatronal,
                pensionPatronal: pensionPatronal,
                tarifaArlLaboral: arlStr[this.claseRiesgoArlLaboral] || '0,522',
                arlPatronal: arlPatronal,
                totalParafiscales: totalParafiscales,
                tieneIndependiente: sumaIbcIndepPrelim > 0,
                totalIngresosBrutosIndep: actServicios + actCapital + actInmob,
                totalCostosDeducidosIndep: costoServicios + presuncionCap + presuncionInmob,
                totalIngresoNetoIndep: netoServicios + netoCapital + netoInmob,
                sumaIbcIndepPreliminar: sumaIbcIndepPrelim,
                ibcIndepCotizado: ibcIndepCotizado,
                saludIndep: saludIndep,
                pensionIndep: pensionIndep,
                fspIndep: fspIndep,
                tarifaArlServicios: arlStr[this.claseRiesgoArl] || '0,00',
                arlIndep: arlIndep,
                totalIndep: totalIndep,
                modalSalud: saludIndep,
                modalPension: pensionIndep,
                modalFsp: fspIndep,
                modalArl: arlIndep,
            };
        },

        onFormSubmit(e) {
            // Envío natural del formulario
        },

        abrirModalPago() {
            if (this.resultados.totalPagarPila <= 0) {
                alert('No hay aportes independientes pendientes para pago por PILA.');
                return;
            }
            this.modalPagoAbierto = true;
        }
    };
}
</script>
@endsection
