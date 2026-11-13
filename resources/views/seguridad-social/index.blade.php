@extends('layouts.app')
@section('title', 'Seguridad social')
@section('content')
<div class="finance-page" x-data="seguridadSocialApp()">
    <header class="income-page-header">
        <div>
            <h1>Seguridad social</h1>
            <p>Calculadora unificada de aportes, liquidación y pago por PILA con registro automático en Gastos.</p>
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

    {{-- SELECTOR DE TIPO DE COTIZANTE --}}
    <x-ui.card>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
                <h2 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.25rem;">Tipo de cotizante</h2>
                <p class="ui-help">Selecciona tu modalidad laboral o económica para adaptar los campos de cálculo y liquidación.</p>
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
                    📈 Rentista de capital
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
                <x-ui.field name="periodo" label="Periodo a cotizar" :required="true" help="Mes y año de la cotización o liquidación.">
                    <input id="periodo" name="periodo" type="month" x-model="periodo" required max="{{ now()->format('Y-m') }}">
                </x-ui.field>

                {{-- ==================== CAMPOS ASALARIADO ==================== --}}
                <template x-if="tipoCotizante === 'ASALARIADO'">
                    <div style="display: grid; gap: 1rem;">
                        <x-ui.field name="salario" label="Salario mensual bruto" :required="true" help="Salario básico pactado en tu contrato de trabajo.">
                            <div class="ui-money">
                                <input type="text" inputmode="decimal" x-model="salarioDisplay" @input="onSalarioInput" placeholder="Ej. 3.000.000" required>
                                <span class="ui-money-unit">COP</span>
                                <input type="hidden" name="salario" :value="salarioCanonico">
                            </div>
                        </x-ui.field>

                        {{-- Desglose visual de porcentajes de nómina --}}
                        <div style="background: var(--ui-raised); padding: 0.875rem 1rem; border-radius: var(--ui-radius); border: 1px solid var(--ui-border); display: grid; gap: 0.5rem; font-size: var(--ui-text-small);">
                            <div style="display: flex; justify-content: space-between;">
                                <span>Aporte Salud (a cargo del empleado):</span>
                                <strong x-text="tarifaSaludEmp + ' %'"></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Aporte Pensión (a cargo del empleado):</span>
                                <strong x-text="tarifaPensionEmp + ' %'"></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Fondo de Solidaridad Pensional (FSP):</span>
                                <span x-show="aplicaFspAsalariado" class="ui-badge" style="background: rgba(34, 197, 94, 0.15); color: var(--ui-success); font-weight: 600;">
                                    Aplica (<span x-text="tarifaFspDisplay"></span>)
                                </span>
                                <span x-show="!aplicaFspAsalariado" style="color: var(--ui-muted);">
                                    No aplica (&lt; 4 SMMLV)
                                </span>
                            </div>
                            <p class="ui-help" style="margin-top: 0.25rem;">El empleador asume el pago de ARL y los aportes patronales; estos no se descuentan de tu salario.</p>
                        </div>

                        <x-ui.field name="retencion_fuente" label="Retención en la fuente informada (opcional)" :required="false" help="Monto retenido por el empleador si aplica retención en la fuente en tu colilla de pago.">
                            <div class="ui-money">
                                <input type="text" inputmode="decimal" x-model="retencionDisplay" @input="onRetencionInput" placeholder="0">
                                <span class="ui-money-unit">COP</span>
                                <input type="hidden" name="retencion_fuente" :value="retencionCanonica">
                            </div>
                        </x-ui.field>
                    </div>
                </template>

                {{-- ==================== CAMPOS INDEPENDIENTE ==================== --}}
                <template x-if="tipoCotizante === 'INDEPENDIENTE'">
                    <div style="display: grid; gap: 1rem;">
                        <x-ui.field name="monto_contrato" label="Monto del contrato / Honorarios del mes" :required="true" help="Ingreso bruto pactado por prestación de servicios u honorarios.">
                            <div class="ui-money">
                                <input type="text" inputmode="decimal" x-model="montoContratoDisplay" @input="onMontoContratoInput" placeholder="Ej. 5.000.000" required>
                                <span class="ui-money-unit">COP</span>
                                <input type="hidden" name="monto_contrato" :value="montoContratoCanonico">
                            </div>
                        </x-ui.field>

                        <div style="background: var(--ui-raised); padding: 0.875rem 1rem; border-radius: var(--ui-radius); border: 1px solid var(--ui-border); display: grid; gap: 0.5rem; font-size: var(--ui-text-small);">
                            <div style="display: flex; justify-content: space-between;">
                                <span>Base de cotización (IBC legal):</span>
                                <strong>40 % del monto del contrato</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Tarifa Salud independiente:</span>
                                <strong x-text="tarifaSaludIndep + ' %'"></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Tarifa Pensión independiente:</span>
                                <strong x-text="tarifaPensionIndep + ' %'"></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Fondo de Solidaridad (si IBC ≥ 4 SMMLV):</span>
                                <strong x-text="aplicaFspIndep ? (tarifaFspIndepDisplay + ' (Aplica)') : 'No aplica'"></strong>
                            </div>
                        </div>

                        <x-ui.field name="clase_riesgo_arl" label="Nivel de riesgo ARL" :required="false" help="Tarifa de riesgos laborales según la actividad contratada.">
                            <select name="clase_riesgo_arl" x-model="claseRiesgoArl" @change="calcularEnVivo">
                                <option value="0">Sin afiliación ARL o no obligatoria (0,00%)</option>
                                <option value="1">Clase I — Riesgo mínimo (0,522 %)</option>
                                <option value="2">Clase II — Riesgo bajo (1,044 %)</option>
                                <option value="3">Clase III — Riesgo medio (2,436 %)</option>
                                <option value="4">Clase IV — Riesgo alto (4,350 %)</option>
                                <option value="5">Clase V — Riesgo máximo (6,960 %)</option>
                            </select>
                        </x-ui.field>
                    </div>
                </template>

                {{-- ==================== CAMPOS RENTISTA DE CAPITAL ==================== --}}
                <template x-if="tipoCotizante === 'RENTISTA_CAPITAL'">
                    <div style="display: grid; gap: 1rem;">
                        <x-ui.field name="monto_contrato" label="Monto de ingresos brutos / Contratos de capital" :required="true" help="Ingresos por arrendamientos, rendimientos o inversiones recibidos en el mes.">
                            <div class="ui-money">
                                <input type="text" inputmode="decimal" x-model="montoContratoDisplay" @input="onMontoContratoInput" placeholder="Ej. 10.000.000" required>
                                <span class="ui-money-unit">COP</span>
                                <input type="hidden" name="monto_contrato" :value="montoContratoCanonico">
                            </div>
                        </x-ui.field>

                        <x-ui.field name="clase_riesgo_arl" label="Nivel de riesgo ARL (opcional)" :required="false" help="Tarifa de riesgos laborales si cotizas voluntariamente a ARL.">
                            <select name="clase_riesgo_arl" x-model="claseRiesgoArl" @change="calcularEnVivo">
                                <option value="0">Sin afiliación ARL (0,00%)</option>
                                <option value="1">Clase I — Riesgo mínimo (0,522 %)</option>
                                <option value="2">Clase II — Riesgo bajo (1,044 %)</option>
                                <option value="3">Clase III — Riesgo medio (2,436 %)</option>
                                <option value="4">Clase IV — Riesgo alto (4,350 %)</option>
                                <option value="5">Clase V — Riesgo máximo (6,960 %)</option>
                            </select>
                        </x-ui.field>
                    </div>
                </template>
            </form>
        </x-ui.card>

        {{-- RESULTADOS Y ACCIÓN DE PAGO --}}
        <div style="display: grid; gap: var(--ui-space-6);">
            <x-ui.card title="Desglose de liquidación y aportes">
                <template x-if="!tieneCalculo">
                    <x-ui.empty-state title="Ingresa un monto para calcular" description="Digita el salario o monto de contrato y verás el cálculo detallado con los porcentajes y valores de ley." />
                </template>

                <template x-if="tieneCalculo">
                    <div style="display: grid; gap: 1.25rem;">
                        {{-- AVISOS LEGALES / SMMLV --}}
                        <div x-show="avisoBajoSmmlv" class="ui-notice" style="border-left: 3px solid var(--ui-warning); font-size: var(--ui-text-small);">
                            <p style="margin: 0;">Con ingresos inferiores a 1 SMMLV no existe obligación legal de cotizar; el cálculo se realiza sobre la base mínima legal de 1 SMMLV ($ <span x-text="formatMoney(smmlv)"></span>).</p>
                        </div>

                        {{-- RESUMEN DESTACADO --}}
                        <div class="finance-stats" style="margin: 0; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                            <template x-if="tipoCotizante === 'ASALARIADO'">
                                <div class="finance-stat">
                                    <dt>Salario neto estimado</dt>
                                    <dd style="color: var(--ui-success);" x-text="formatMoney(resultados.salarioNeto)"></dd>
                                </div>
                            </template>

                            <template x-if="tipoCotizante !== 'ASALARIADO'">
                                <div class="finance-stat">
                                    <dt>Total seguridad social a pagar</dt>
                                    <dd style="color: var(--accent);" x-text="formatMoney(resultados.totalPagar)"></dd>
                                </div>
                            </template>

                            <div class="finance-stat">
                                <dt>IBC (Base de cotización)</dt>
                                <dd x-text="formatMoney(resultados.ibc)"></dd>
                            </div>
                        </div>

                        {{-- TABLA DESGLOSE --}}
                        <dl class="finance-breakdown" style="margin: 0;">
                            <template x-if="tipoCotizante === 'ASALARIADO'">
                                <div style="display: contents;">
                                    <dt>Salario mensual bruto:</dt>
                                    <dd x-text="formatMoney(resultados.salarioBruto)"></dd>

                                    <dt>Salud (<span x-text="tarifaSaludEmp"></span> % a cargo empleado):</dt>
                                    <dd x-text="'- ' + formatMoney(resultados.salud)"></dd>

                                    <dt>Pensión (<span x-text="tarifaPensionEmp"></span> % a cargo empleado):</dt>
                                    <dd x-text="'- ' + formatMoney(resultados.pension)"></dd>

                                    <dt>Fondo Solidaridad Pensional (FSP):</dt>
                                    <dd x-text="resultados.fsp > 0 ? ('- ' + formatMoney(resultados.fsp)) : 'No aplica ($ 0)'"></dd>

                                    <dt>Retención en la fuente informada:</dt>
                                    <dd x-text="resultados.retencion > 0 ? ('- ' + formatMoney(resultados.retencion)) : 'No informada ($ 0)'"></dd>

                                    <dt style="font-weight: 700; border-top: 1px solid var(--ui-border); padding-top: 0.5rem;">Total descuentos de nómina:</dt>
                                    <dd style="font-weight: 700; border-top: 1px solid var(--ui-border); padding-top: 0.5rem;" x-text="formatMoney(resultados.totalDescuentos)"></dd>
                                </div>
                            </template>

                            <template x-if="tipoCotizante === 'INDEPENDIENTE'">
                                <div style="display: contents;">
                                    <dt>Monto del contrato:</dt>
                                    <dd x-text="formatMoney(resultados.montoContrato)"></dd>

                                    <dt>IBC (40 % acotado 1 a 25 SMMLV):</dt>
                                    <dd style="font-weight: 600;" x-text="formatMoney(resultados.ibc)"></dd>

                                    <dt>Aporte Salud (<span x-text="tarifaSaludIndep"></span> %):</dt>
                                    <dd x-text="formatMoney(resultados.salud)"></dd>

                                    <dt>Aporte Pensión (<span x-text="tarifaPensionIndep"></span> %):</dt>
                                    <dd x-text="formatMoney(resultados.pension)"></dd>

                                    <dt>Fondo Solidaridad Pensional (FSP):</dt>
                                    <dd x-text="resultados.fsp > 0 ? formatMoney(resultados.fsp) : 'No aplica ($ 0)'"></dd>

                                    <dt>Riesgos Laborales ARL (<span x-text="tarifaArlCalculada"></span> %):</dt>
                                    <dd x-text="resultados.arl > 0 ? formatMoney(resultados.arl) : 'Sin ARL ($ 0)'"></dd>

                                    <dt style="font-weight: 700; border-top: 1px solid var(--ui-border); padding-top: 0.5rem;">Total aportes a pagar:</dt>
                                    <dd style="font-weight: 700; border-top: 1px solid var(--ui-border); padding-top: 0.5rem; color: var(--accent);" x-text="formatMoney(resultados.totalPagar)"></dd>
                                </div>
                            </template>

                            <template x-if="tipoCotizante === 'RENTISTA_CAPITAL'">
                                <div style="display: contents;">
                                    <dt>Monto ingresos brutos de capital:</dt>
                                    <dd x-text="formatMoney(resultados.montoContrato)"></dd>

                                    <dt>Presunción de costos (28,08 %):</dt>
                                    <dd style="color: var(--ui-danger);" x-text="'- ' + formatMoney(resultados.presuncionCostos)"></dd>

                                    <dt>Base neta después de costos:</dt>
                                    <dd style="font-weight: 600;" x-text="formatMoney(resultados.baseNeta)"></dd>

                                    <dt>IBC calculado (40 % sobre base neta):</dt>
                                    <dd style="font-weight: 600;" x-text="formatMoney(resultados.ibc)"></dd>

                                    <dt>Aporte Salud (<span x-text="tarifaSaludIndep"></span> %):</dt>
                                    <dd x-text="formatMoney(resultados.salud)"></dd>

                                    <dt>Aporte Pensión (<span x-text="tarifaPensionIndep"></span> %):</dt>
                                    <dd x-text="formatMoney(resultados.pension)"></dd>

                                    <dt>Fondo Solidaridad Pensional (FSP):</dt>
                                    <dd x-text="resultados.fsp > 0 ? formatMoney(resultados.fsp) : 'No aplica ($ 0)'"></dd>

                                    <dt>Riesgos Laborales ARL (<span x-text="tarifaArlCalculada"></span> %):</dt>
                                    <dd x-text="resultados.arl > 0 ? formatMoney(resultados.arl) : 'Sin ARL ($ 0)'"></dd>

                                    <dt style="font-weight: 700; border-top: 1px solid var(--ui-border); padding-top: 0.5rem;">Total aportes a pagar:</dt>
                                    <dd style="font-weight: 700; border-top: 1px solid var(--ui-border); padding-top: 0.5rem; color: var(--accent);" x-text="formatMoney(resultados.totalPagar)"></dd>
                                </div>
                            </template>
                        </dl>

                        {{-- BOTÓN DESTACADO PAGAR SEGURIDAD SOCIAL (INDEPENDIENTE Y RENTISTA) --}}
                        <template x-if="tipoCotizante !== 'ASALARIADO'">
                            <div style="border-top: 1px solid var(--ui-border); padding-top: 1rem; display: grid; gap: 0.75rem;">
                                <button type="button" 
                                    class="ui-button ui-button--primary" 
                                    style="padding: 0.875rem 1.25rem; font-size: 1rem; font-weight: 700; justify-content: center; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.25);"
                                    @click="abrirModalPago">
                                    💳 Pagar seguridad social ($ <span x-text="formatMoney(resultados.totalPagar)"></span>)
                                </button>
                                <p class="ui-help" style="margin: 0; text-align: center;">
                                    Al pagar, se registrará automáticamente como un <strong>gasto</strong> en la sección de Gastos (categoría Seguridad Social).
                                </p>
                            </div>
                        </template>

                        {{-- CASO ASALARIADO: APLICAR AL INGRESO REGISTRADO --}}
                        <template x-if="tipoCotizante === 'ASALARIADO' && {{ $ingresosSalario->count() }} > 0">
                            <div style="border-top: 1px solid var(--ui-border); padding-top: 1rem;">
                                <form method="POST" action="{{ route('seguridad-social.asalariado.aplicar') }}">
                                    @csrf
                                    <p style="font-size: var(--ui-text-small); font-weight: 600; margin-bottom: 0.5rem;">Aplicar aportes calculados a un ingreso registrado:</p>
                                    <div style="display: grid; gap: 0.5rem;">
                                        <select name="ingreso_id" required>
                                            <option value="">Selecciona un ingreso de salario...</option>
                                            @foreach($ingresosSalario as $ing)
                                                <option value="{{ $ing->id }}">{{ $ing->fecha_recepcion->format('d/m/Y') }} · {{ $ing->fuente?->nombre }} ($ {{ number_format($ing->monto_bruto_cop, 0, ',', '.') }})</option>
                                            @endforeach
                                        </select>
                                        <x-ui.button type="submit" variant="secondary">
                                            Guardar descuentos en el ingreso
                                        </x-ui.button>
                                    </div>
                                </form>
                            </div>
                        </template>
                    </div>
                </template>
            </x-ui.card>
        </div>
    </div>

    {{-- MODAL PARA CONFIRMAR PAGO PILA Y GENERAR GASTO --}}
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
                    <input type="hidden" name="salud" :value="resultados.salud">
                    <input type="hidden" name="pension" :value="resultados.pension">
                    <input type="hidden" name="fsp" :value="resultados.fsp">
                    <input type="hidden" name="arl" :value="resultados.arl">
                    <input type="hidden" name="intereses_mora" value="0">

                    <div style="background: rgba(var(--accent-rgb, 14, 165, 233), 0.08); padding: 1rem; border-radius: var(--ui-radius); margin-bottom: 1rem; border: 1px solid var(--ui-border);">
                        <div style="display: flex; justify-content: space-between; font-size: var(--ui-text-small); margin-bottom: 0.5rem;">
                            <span>Periodo cotizado:</span>
                            <strong x-text="periodo"></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: var(--ui-text-small); margin-bottom: 0.5rem;">
                            <span>Modalidad:</span>
                            <strong x-text="tipoCotizante === 'RENTISTA_CAPITAL' ? 'Rentista de capital' : 'Independiente'"></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 1.125rem; font-weight: 700; color: var(--accent); padding-top: 0.5rem; border-top: 1px solid var(--ui-border);">
                            <span>Total a debitar y registrar:</span>
                            <span>$ <span x-text="formatMoney(resultados.totalPagar)"></span></span>
                        </div>
                    </div>

                    <div style="display: grid; gap: 1rem; margin-bottom: 1.25rem;">
                        <x-ui.field name="fecha_pago" label="Fecha de pago" :required="true" help="Fecha en la que realizaste o realizarás el pago.">
                            <input type="date" name="fecha_pago" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                        </x-ui.field>

                        <x-ui.field name="medio_pago" label="Medio de pago" :required="true" help="Forma de pago utilizada para debitar el gasto.">
                            <select name="medio_pago" required>
                                @foreach(App\Models\Gasto::MEDIOS_PAGO as $medio)
                                    <option value="{{ $medio }}" @selected($medio === 'TRANSFERENCIA')>{{ ucfirst(strtolower(str_replace('_', ' ', $medio))) }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <x-ui.field name="numero_planilla" label="Número de planilla PILA (opcional)" :required="false" help="Identificador del comprobante o planilla emitida por el operador.">
                            <input type="text" name="numero_planilla" placeholder="Ej. 8839201948">
                        </x-ui.field>

                        <x-ui.field name="soporte" label="Comprobante o soporte PILA (opcional)" :required="false" help="PDF, JPG o PNG de hasta 5 MB.">
                            <input type="file" name="soporte" accept=".pdf,.jpg,.jpeg,.png">
                        </x-ui.field>
                    </div>

                    <div style="background: var(--ui-raised); padding: 0.75rem 1rem; border-radius: var(--ui-radius); margin-bottom: 1.25rem; font-size: 0.8125rem; color: var(--ui-muted);">
                        ℹ️ Al confirmar el pago, se creará automáticamente un registro de egreso en <strong>Gastos</strong> (categoría <em>Seguridad social › Aporte PILA independiente</em>) por el valor total.
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

    {{-- FECHAS LÍMITE Y ALERTAS --}}
    <x-ui.card title="Fechas límite de pago PILA">
        @if(!$hayCalendario)
            <p role="status">No hay calendario disponible para esta vigencia.</p>
        @elseif($digitos === null)
            <p role="status">Completa tu perfil para consultar las fechas de pago personalizadas según tu cédula/NIT.</p>
            <a href="{{ route('profile.show') }}">Ir a mi perfil</a>
        @elseif(empty($periodos))
            <x-ui.empty-state title="No hay fechas de pago disponibles" />
        @else
            <table class="ui-table">
                <caption>Periodos cotizados y fechas límite oficiales</caption>
                <thead>
                    <tr>
                        <th>Periodo</th>
                        <th>Fecha límite</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($periodos, 0, 6) as $p)
                        <tr>
                            <td>{{ $p['periodo'] }}</td>
                            <td><time data-date="{{ $p['fecha_limite'] }}">{{ $p['fecha_limite'] }}</time></td>
                            <td>
                                <x-ui.badge :tone="($p['estado'] === 'VENCIDO') ? 'danger' : (($p['estado'] === 'POR_VENCER') ? 'warning' : 'neutral')">
                                    {{ ucfirst(strtolower(str_replace('_', ' ', $p['estado']))) }}
                                </x-ui.badge>
                            </td>
                            <td>
                                <a href="{{ route('seguridad-social.pagos.index') }}">Gestionar</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{-- RESUMEN ANUAL DE APORTES --}}
    <x-ui.card title="Resumen anual de aportes obligatorios (PILA)">
        <dl class="finance-stats">
            <x-ui.finance-stat label="Salud acumulada año" :value="$resumenAnual['salud']" />
            <x-ui.finance-stat label="Pensión acumulada año" :value="$resumenAnual['pension']" />
            <x-ui.finance-stat label="Ingreso no constitutivo acumulado" :value="$resumenAnual['ingreso_no_constitutivo_pila']" />
        </dl>
        <p class="ui-help">Aportes obligatorios pagados por PILA en el año gravable {{ now()->year }}.</p>
    </x-ui.card>

    <x-ui.calculation-notice />
</div>

{{-- SCRIPT REACTIVO DE LA CALCULADORA UNIFICADA --}}
<script>
function seguridadSocialApp() {
    return {
        tipoCotizante: '{{ session('tipo_cotizante_seleccionado', 'INDEPENDIENTE') }}',
        periodo: '{{ old('periodo', session('calculo_reciente.periodo', $periodoActual)) }}',
        smmlv: {{ (float) ($parametro->valor_smmlv ?? 1550000) }},
        tarifaSaludIndep: {{ (float) ($parametro->tarifa_salud_indep ?? 12.5) }},
        tarifaPensionIndep: {{ (float) ($parametro->tarifa_pension_indep ?? 16.0) }},
        tarifaSaludEmp: {{ (float) ($parametro->tarifa_salud_emp ?? 4.0) }},
        tarifaPensionEmp: {{ (float) ($parametro->tarifa_pension_emp ?? 4.0) }},
        porcPresuncionRentista: {{ (float) \App\Services\SeguridadSocialService::PORCENTAJE_PRESUNCION_COSTOS_RENTISTA }},
        
        salarioDisplay: '{{ session('calculo_reciente.salario', '') }}',
        salarioCanonico: '{{ session('calculo_reciente.salario', '0') }}',
        retencionDisplay: '{{ session('calculo_reciente.retencion_fuente', '') }}',
        retencionCanonica: '{{ session('calculo_reciente.retencion_fuente', '0') }}',

        montoContratoDisplay: '{{ session('calculo_reciente.monto_contrato', session('calculo_reciente.ingresos_brutos', '')) }}',
        montoContratoCanonico: '{{ session('calculo_reciente.monto_contrato', session('calculo_reciente.ingresos_brutos', '0')) }}',
        claseRiesgoArl: 0,

        modalPagoAbierto: false,
        tieneCalculo: {{ session('calculo_reciente') ? 'true' : 'false' }},
        avisoBajoSmmlv: false,

        resultados: {
            salarioBruto: {{ (float) (session('calculo_reciente.salario', 0)) }},
            salarioNeto: {{ (float) (session('calculo_reciente.salario_neto_estimado', 0)) }},
            totalDescuentos: {{ (float) (session('calculo_reciente.total_aportes', 0)) }},
            montoContrato: {{ (float) (session('calculo_reciente.monto_contrato', session('calculo_reciente.ingresos_brutos', 0))) }},
            presuncionCostos: {{ (float) (session('calculo_reciente.presuncion_costos', 0)) }},
            baseNeta: {{ (float) (session('calculo_reciente.ingreso_neto', 0)) }},
            ibc: {{ (float) (session('calculo_reciente.ibc', 0)) }},
            salud: {{ (float) (session('calculo_reciente.salud', 0)) }},
            pension: {{ (float) (session('calculo_reciente.pension', 0)) }},
            fsp: {{ (float) (session('calculo_reciente.fsp', 0)) }},
            arl: {{ (float) (session('calculo_reciente.arl', 0)) }},
            retencion: {{ (float) (session('calculo_reciente.retencion_fuente', 0)) }},
            totalPagar: {{ (float) (session('calculo_reciente.total', 0)) }},
        },

        init() {
            if (this.salarioCanonico && Number(this.salarioCanonico) > 0) {
                this.salarioDisplay = this.formatNumber(Number(this.salarioCanonico));
            }
            if (this.montoContratoCanonico && Number(this.montoContratoCanonico) > 0) {
                this.montoContratoDisplay = this.formatNumber(Number(this.montoContratoCanonico));
            }
            if (this.tieneCalculo) {
                this.calcularEnVivo();
            }
        },

        get tituloFormulario() {
            if (this.tipoCotizante === 'ASALARIADO') return 'Liquidación para Asalariado (Nómina)';
            if (this.tipoCotizante === 'RENTISTA_CAPITAL') return 'Liquidación para Rentista de capital';
            return 'Liquidación para Independiente / Prestación de Servicios';
        },

        get aplicaFspAsalariado() {
            const salario = Number(this.salarioCanonico) || 0;
            return salario >= (4 * this.smmlv);
        },

        get tarifaFspDisplay() {
            const salario = Number(this.salarioCanonico) || 0;
            const sm = this.smmlv;
            if (salario < 4 * sm) return '0,00 %';
            if (salario < 16 * sm) return '1,00 %';
            if (salario < 17 * sm) return '1,20 %';
            if (salario < 18 * sm) return '1,40 %';
            if (salario < 19 * sm) return '1,60 %';
            if (salario < 20 * sm) return '1,80 %';
            return '2,00 %';
        },

        get aplicaFspIndep() {
            return this.resultados.ibc >= (4 * this.smmlv);
        },

        get tarifaFspIndepDisplay() {
            const ibc = this.resultados.ibc || 0;
            const sm = this.smmlv;
            if (ibc < 4 * sm) return '0,00 %';
            if (ibc < 16 * sm) return '1,00 %';
            if (ibc < 17 * sm) return '1,20 %';
            if (ibc < 18 * sm) return '1,40 %';
            if (ibc < 19 * sm) return '1,60 %';
            if (ibc < 20 * sm) return '1,80 %';
            return '2,00 %';
        },

        get tarifaArlCalculada() {
            const map = { 0: '0,00', 1: '0,522', 2: '1,044', 3: '2,436', 4: '4,350', 5: '6,960' };
            return map[this.claseRiesgoArl] || '0,00';
        },

        get presuncionCostosCalculada() {
            const monto = Number(this.montoContratoCanonico) || 0;
            return Math.ceil(monto * (this.porcPresuncionRentista / 100));
        },

        get baseNetaRentistaCalculada() {
            const monto = Number(this.montoContratoCanonico) || 0;
            return Math.max(0, monto - this.presuncionCostosCalculada);
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

        calcularEnVivo() {
            const sm = this.smmlv;
            const arlTarifas = { 0: 0, 1: 0.00522, 2: 0.01044, 3: 0.02436, 4: 0.0435, 5: 0.0696 };

            if (this.tipoCotizante === 'ASALARIADO') {
                const salario = Number(this.salarioCanonico) || 0;
                if (salario <= 0) {
                    this.tieneCalculo = false;
                    return;
                }
                this.tieneCalculo = true;
                this.avisoBajoSmmlv = false;

                const ibc = Math.min(Math.max(salario, sm), 25 * sm);
                const salud = Math.round(ibc * (this.tarifaSaludEmp / 100));
                const pension = Math.round(ibc * (this.tarifaPensionEmp / 100));
                
                let fspPorc = 0;
                if (salario >= 20 * sm) fspPorc = 0.02;
                else if (salario >= 19 * sm) fspPorc = 0.016;
                else if (salario >= 18 * sm) fspPorc = 0.014;
                else if (salario >= 17 * sm) fspPorc = 0.012;
                else if (salario >= 16 * sm) fspPorc = 0.01;
                else if (salario >= 4 * sm) fspPorc = 0.01;

                const fsp = Math.round(ibc * fspPorc);
                const retencion = Number(this.retencionCanonica) || 0;
                const totalDescuentos = salud + pension + fsp;
                const salarioNeto = Math.max(0, salario - totalDescuentos - retencion);

                this.resultados = {
                    salarioBruto: salario,
                    salarioNeto: salarioNeto,
                    totalDescuentos: totalDescuentos,
                    retencion: retencion,
                    ibc: ibc,
                    salud: salud,
                    pension: pension,
                    fsp: fsp,
                    arl: 0,
                    totalPagar: totalDescuentos
                };
            } else if (this.tipoCotizante === 'INDEPENDIENTE') {
                const monto = Number(this.montoContratoCanonico) || 0;
                if (monto <= 0) {
                    this.tieneCalculo = false;
                    return;
                }
                this.tieneCalculo = true;
                this.avisoBajoSmmlv = monto < sm;

                const ibcBase = monto * 0.40;
                const ibc = Math.ceil(Math.min(Math.max(ibcBase, sm), 25 * sm));
                const salud = Math.round(ibc * 0.125);
                const pension = Math.round(ibc * 0.16);

                let fspPorc = 0;
                if (ibc >= 20 * sm) fspPorc = 0.02;
                else if (ibc >= 19 * sm) fspPorc = 0.016;
                else if (ibc >= 18 * sm) fspPorc = 0.014;
                else if (ibc >= 17 * sm) fspPorc = 0.012;
                else if (ibc >= 16 * sm) fspPorc = 0.01;
                else if (ibc >= 4 * sm) fspPorc = 0.01;

                const fsp = Math.round(ibc * fspPorc);
                const arlFactor = arlTarifas[this.claseRiesgoArl] || 0;
                const arl = Math.round(ibc * arlFactor);
                const total = salud + pension + fsp + arl;

                this.resultados = {
                    montoContrato: monto,
                    ibc: ibc,
                    salud: salud,
                    pension: pension,
                    fsp: fsp,
                    arl: arl,
                    totalPagar: total
                };
            } else if (this.tipoCotizante === 'RENTISTA_CAPITAL') {
                const monto = Number(this.montoContratoCanonico) || 0;
                if (monto <= 0) {
                    this.tieneCalculo = false;
                    return;
                }
                this.tieneCalculo = true;

                const presuncion = Math.ceil(monto * (this.porcPresuncionRentista / 100));
                const baseNeta = Math.max(0, monto - presuncion);
                this.avisoBajoSmmlv = baseNeta < sm;

                const ibcBase = baseNeta * 0.40;
                const ibc = Math.ceil(Math.min(Math.max(ibcBase, sm), 25 * sm));
                const salud = Math.round(ibc * 0.125);
                const pension = Math.round(ibc * 0.16);

                let fspPorc = 0;
                if (ibc >= 20 * sm) fspPorc = 0.02;
                else if (ibc >= 19 * sm) fspPorc = 0.016;
                else if (ibc >= 18 * sm) fspPorc = 0.014;
                else if (ibc >= 17 * sm) fspPorc = 0.012;
                else if (ibc >= 16 * sm) fspPorc = 0.01;
                else if (ibc >= 4 * sm) fspPorc = 0.01;

                const fsp = Math.round(ibc * fspPorc);
                const arlFactor = arlTarifas[this.claseRiesgoArl] || 0;
                const arl = Math.round(ibc * arlFactor);
                const total = salud + pension + fsp + arl;

                this.resultados = {
                    montoContrato: monto,
                    presuncionCostos: presuncion,
                    baseNeta: baseNeta,
                    ibc: ibc,
                    salud: salud,
                    pension: pension,
                    fsp: fsp,
                    arl: arl,
                    totalPagar: total
                };
            }
        },

        onFormSubmit(e) {
            // Permite al formulario hacer submit natural al backend para validación y persistencia
        },

        abrirModalPago() {
            if (this.resultados.totalPagar <= 0) {
                alert('Calcula primero los valores de seguridad social antes de proceder al pago.');
                return;
            }
            this.modalPagoAbierto = true;
        }
    };
}
</script>
@endsection
