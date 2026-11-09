@extends('layouts.app')
@section('title', 'Inversiones')
@section('content')
<div class="finance-page">
@php
    $labels = require resource_path('views/components/ui/investment-labels.php');
@endphp

<header class="income-page-header">
    <div>
        <h1>Módulo de Inversiones</h1>
        <p>Gestión integral, tributaria y financiera para personas naturales residentes en Colombia.</p>
    </div>
    <div class="ui-inline" style="gap: 0.5rem; flex-wrap: wrap;">
        <x-ui.button :href="route('inversiones.portafolio')" variant="secondary">Portafolio Consolidado</x-ui.button>
        <x-ui.button :href="route('inversiones.reportes.retenciones')" variant="secondary">Reporte Retenciones</x-ui.button>
        <x-ui.button :href="route('inversiones.reportes.utilidades')" variant="secondary">Reporte Utilidades</x-ui.button>
        <x-ui.button :href="route('inversiones.simulaciones.index')" variant="secondary">Simulador</x-ui.button>
    </div>
</header>

@if(session('status'))
    <div class="ui-notice" style="margin-bottom: 1.5rem; border-left: 4px solid var(--color-primary);">
        <p>{{ session('status') }}</p>
    </div>
@endif

<x-ui.card>
    <details @if($errors->any()) open @endif>
        <summary style="font-weight: 600; cursor: pointer; font-size: 1.1rem; padding: 0.5rem 0;">+ Registrar Nueva Inversión</summary>
        
        @php
            $selectedTipo = old('tipo', '');
            $usesUnitsInitial = in_array($selectedTipo, ['ACCION_ETF', 'FIC', 'FPV'], true);
        @endphp

        <form method="POST" action="{{ route('inversiones.store') }}" id="investment-form" style="margin-top: 1rem;">
            @csrf

            <h3 style="margin-top: 0;">1. Identificación y Parámetros Generales</h3>
            <div class="income-two-fields">
                <x-ui.field name="nombre" label="Nombre de la inversión" :required="true" help="Entre 3 y 60 caracteres.">
                    <input id="nombre" name="nombre" minlength="3" maxlength="60" value="{{ old('nombre') }}" placeholder="Ej: CDT Bancolombia 180 días" required>
                </x-ui.field>

                <x-ui.field name="tipo" label="Tipo de producto financiero" :required="true">
                    <select id="tipo" name="tipo" required onchange="actualizarFormularioPorTipo(this.value)">
                        <option value="">Selecciona uno de los 6 productos</option>
                        @foreach($tipos as $type)
                            <option value="{{ $type }}" @selected(old('tipo') === $type)>{{ $labels[$type] ?? $type }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field name="entidad_id" label="Entidad Financiera Emisora" help="Selecciona del catálogo institucional.">
                    <select id="entidad_id" name="entidad_id">
                        <option value="">Seleccionar entidad (Opcional)</option>
                        @foreach($entidades as $ent)
                            <option value="{{ $ent->id }}" @selected(old('entidad_id') === $ent->id)>{{ $ent->nombre }} ({{ $ent->tipo }})</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field name="entidad" label="Otra Entidad / Nombre Emisor" help="Si no está en el listado.">
                    <input id="entidad" name="entidad" maxlength="80" value="{{ old('entidad') }}" placeholder="Ej: Tyba, Fiduciaria Central, etc.">
                </x-ui.field>

                <x-ui.field name="moneda" label="Moneda de la inversión" :required="true">
                    <div class="ui-inline" role="group" aria-label="Moneda" style="margin-top: 0.25rem;">
                        <label class="ui-check"><input type="radio" name="moneda" value="COP" @checked(old('moneda', 'COP') === 'COP') onchange="toggleMoneda(this.value)"> COP ($)</label>
                        <label class="ui-check"><input type="radio" name="moneda" value="USD" @checked(old('moneda') === 'USD') onchange="toggleMoneda(this.value)"> USD (US$)</label>
                    </div>
                </x-ui.field>

                <div id="tasa-cambio-wrapper" @if(old('moneda', 'COP') !== 'USD') style="display:none;" @endif>
                    <x-ui.field name="tasa_cambio_usada" label="Tasa de cambio (TRM)" help="Si se deja vacía, se tomará la TRM oficial de la fecha de apertura.">
                        <input id="tasa_cambio_usada" name="tasa_cambio_usada" type="number" step="0.0001" placeholder="Ej: 4150.00" value="{{ old('tasa_cambio_usada') }}">
                    </x-ui.field>
                </div>

                <x-ui.date-input name="fecha_apertura" label="Fecha de inicio / apertura" :value="old('fecha_apertura', now('America/Bogota')->format('Y-m-d'))" />

                <x-ui.field name="nivel_riesgo" label="Nivel de riesgo estimado">
                    <select id="nivel_riesgo" name="nivel_riesgo">
                        <option value="">Automático sugerido por el sistema</option>
                        @foreach(\App\Models\Inversion::NIVELES_RIESGO as $code)
                            <option value="{{ $code }}" @selected(old('nivel_riesgo') === $code)>{{ $labels[$code] ?? $code }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            <!-- MONTO, UNIDADES Y VALOR POR UNIDAD (AUTO-CÁLCULO 2 DE 3) -->
            <div style="background: rgba(0,0,0,0.02); padding: 1rem; border-radius: 8px; margin: 1rem 0; border: 1px dashed var(--color-border, #ddd);">
                <h4 style="margin-top: 0; margin-bottom: 0.5rem;">Aporte Inicial y Valores por Unidad</h4>
                <p class="ui-help" style="margin-bottom: 0.75rem;">Para productos con unidades (Acción, ETF, FIC, FPV): Digita 2 de los 3 valores y el sistema calculará automáticamente el tercero.</p>
                <div class="income-two-fields">
                    <x-ui.field name="monto_inicial" label="Monto total del aporte inicial" :required="true">
                        <input id="monto_inicial" name="monto_inicial" type="number" step="0.01" min="0.01" value="{{ old('monto_inicial') }}" required placeholder="Monto total en moneda base" oninput="resolverDosDeTres('monto'); calcularProyeccionCdt();">
                    </x-ui.field>

                    <div id="wrapper-unidades" style="{{ $usesUnitsInitial ? '' : 'display: none;' }}">
                        <x-ui.field name="unidades" label="Cantidad de unidades / títulos">
                            <input id="unidades" name="unidades" type="number" step="0.00000001" min="0.00000001" value="{{ old('unidades') }}" placeholder="Ej: 10.50000000" oninput="resolverDosDeTres('unidades')" @disabled(!$usesUnitsInitial)>
                        </x-ui.field>
                    </div>

                    <div id="wrapper-valor-unidad" style="{{ $usesUnitsInitial ? '' : 'display: none;' }}">
                        <x-ui.field name="valor_unidad" label="Valor / Precio de compra por unidad">
                            <input id="valor_unidad" name="valor_unidad" type="number" step="0.00000001" min="0.00000001" value="{{ old('valor_unidad') }}" placeholder="Ej: 50000.00" oninput="resolverDosDeTres('precio')" @disabled(!$usesUnitsInitial)>
                        </x-ui.field>
                    </div>
                </div>
                <div id="alerta-inconsistencia" style="color: #dc2626; font-size: 0.85rem; font-weight: 600; margin-top: 0.5rem; display: none;"></div>
            </div>

            <!-- SECCIÓN 1: ESPECÍFICO CDT / CDAT -->
            <fieldset id="section-cdt" @disabled($selectedTipo !== 'CDT') style="{{ $selectedTipo === 'CDT' ? '' : 'display: none;' }} border: 1px solid var(--color-border, #ccc); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <legend style="font-weight: 600; color: var(--color-primary);">Parámetros CDT / CDAT (ReteFuente 4% automática)</legend>
                <div class="income-two-fields">
                    <x-ui.field name="tasa_ea" label="Tasa pactada (% E.A. o Nominal)" help="Porcentaje anual. Admite hasta 8 decimales (ej. 11.9000277) para cuadrar con liquidaciones bancarias al centavo.">
                        <input id="tasa_ea_cdt" name="tasa_ea" type="number" step="any" min="0" max="100" value="{{ old('tasa_ea') }}" placeholder="Ej: 11.90 o 11.9000277" oninput="calcularProyeccionCdt()">
                    </x-ui.field>

                    <x-ui.field name="tipo_tasa" label="Tipo de tasa">
                        <select name="tipo_tasa" id="tipo_tasa" onchange="calcularProyeccionCdt()">
                            <option value="EA" @selected(old('tipo_tasa') === 'EA')>Efectiva Anual (E.A.)</option>
                            <option value="NOMINAL" @selected(old('tipo_tasa') === 'NOMINAL')>Nominal</option>
                        </select>
                    </x-ui.field>

                    <x-ui.field name="plazo_dias" label="Plazo en días" help="Elegido por el cliente (1 a 3.650 días)">
                        <input id="plazo_dias" name="plazo_dias" type="number" min="1" max="3650" value="{{ old('plazo_dias', 180) }}" oninput="calcularProyeccionCdt()">
                    </x-ui.field>

                    <x-ui.field name="periodicidad_pago" label="Modalidad de pago de intereses">
                        <select name="periodicidad_pago" id="periodicidad_pago">
                            <option value="VENCIMIENTO" @selected(old('periodicidad_pago') === 'VENCIMIENTO')>Al vencimiento</option>
                            <option value="MENSUAL" @selected(old('periodicidad_pago') === 'MENSUAL')>Mensual</option>
                            <option value="TRIMESTRAL" @selected(old('periodicidad_pago') === 'TRIMESTRAL')>Trimestral</option>
                            <option value="SEMESTRAL" @selected(old('periodicidad_pago') === 'SEMESTRAL')>Semestral</option>
                            <option value="ANUAL" @selected(old('periodicidad_pago') === 'ANUAL')>Anual</option>
                        </select>
                    </x-ui.field>

                    <x-ui.field name="base_dias" label="Base de cálculo anual">
                        <select name="base_dias" id="base_dias" onchange="calcularProyeccionCdt()">
                            <option value="365" @selected(old('base_dias') == 365)>Base 365 días (Estándar bancario)</option>
                            <option value="360" @selected(old('base_dias') == 360)>Base 360 días (Comercial)</option>
                        </select>
                    </x-ui.field>

                    <div style="padding-top: 1.5rem;">
                        <label class="ui-check"><input type="checkbox" name="es_cdat" value="1" @checked(old('es_cdat'))> Es CDAT (Certificado de Ahorro a Término)</label>
                    </div>
                </div>
                <div id="cdt-proyeccion-box" class="ui-notice" style="margin-top: 0.75rem; background: #f0fdf4; border-color: #86efac;">
                    <p><strong>Simulación al vencimiento:</strong> Rendimiento bruto estimado: <strong id="cdt-prev-bruto">$ 0</strong> · Retención 4% (Decreto 572/2025): <strong id="cdt-prev-ret">$ 0</strong> · Neto a recibir: <strong id="cdt-prev-neto">$ 0</strong></p>
                </div>
            </fieldset>

            <!-- SECCIÓN 2: ESPECÍFICO CUENTA ALTO RENDIMIENTO -->
            <fieldset id="section-cuenta" @disabled($selectedTipo !== 'CUENTA_ALTO_RENDIMIENTO') style="{{ $selectedTipo === 'CUENTA_ALTO_RENDIMIENTO' ? '' : 'display: none;' }} border: 1px solid var(--color-border, #ccc); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <legend style="font-weight: 600; color: var(--color-primary);">Parámetros Cuenta de Alto Rendimiento (ReteFuente 7% en abono)</legend>
                <div class="income-two-fields">
                    <x-ui.field name="tasa_ea" label="Tasa Fija (% E.A.)" help="Sección 9.2: Tasa EA fija para cálculo constante (admite hasta 8 decimales)">
                        <input id="tasa_ea_cuenta" name="tasa_ea" type="number" step="any" min="0" max="100" value="{{ old('tasa_ea') }}" placeholder="Ej: 12.0000">
                    </x-ui.field>

                    <x-ui.field name="periodicidad_abono" label="Periodicidad de abono de intereses">
                        <select name="periodicidad_abono">
                            <option value="MENSUAL" @selected(old('periodicidad_abono') === 'MENSUAL')>Mensual</option>
                            <option value="DIARIA" @selected(old('periodicidad_abono') === 'DIARIA')>Diaria acumulada</option>
                        </select>
                    </x-ui.field>

                    <div style="grid-column: 1 / -1; background: #eff6ff; padding: 0.75rem; border-radius: 6px;">
                        <label class="ui-check" style="font-weight: 600;">
                            <input type="checkbox" name="es_cuenta_gmf_exenta" value="1" @checked(old('es_cuenta_gmf_exenta', true))>
                            Marcar como Cuenta Exenta del 4x1000 (GMF)
                        </label>
                        <p class="ui-help" style="margin-top: 0.25rem;">
                            En cuentas marcadas como exentas, los retiros acumulados en el mes calendario hasta <strong>350 UVT ($ 18.330.900 en 2026)</strong> están exentos de GMF. Los retiros que excedan el tope pagan 0,4%. Si no está exenta, paga 0,4% pleno.
                        </p>
                    </div>
                </div>
            </fieldset>

            <!-- SECCIÓN 3: ESPECÍFICO ACCIONES / ETF -->
            <fieldset id="section-accion" @disabled($selectedTipo !== 'ACCION_ETF') style="{{ $selectedTipo === 'ACCION_ETF' ? '' : 'display: none;' }} border: 1px solid var(--color-border, #ccc); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <legend style="font-weight: 600; color: var(--color-primary);">Parámetros Renta Variable (Posición unificada con costo promedio)</legend>
                <div class="income-two-fields">
                    <x-ui.field name="simbolo" label="Símbolo / Nemotécnico / Ticker" help="Hasta 15 caracteres.">
                        <input name="simbolo" maxlength="15" value="{{ old('simbolo') }}" placeholder="Ej: PFBCOLOM, ECOPETROL, VOO">
                    </x-ui.field>

                    <x-ui.field name="nombre_instrumento" label="Nombre completo del activo">
                        <input name="nombre_instrumento" maxlength="100" value="{{ old('nombre_instrumento') }}" placeholder="Ej: Bancolombia Preferencial">
                    </x-ui.field>

                    <x-ui.field name="subtipo_renta_variable" label="Subtipo de activo">
                        <select name="subtipo_renta_variable" id="subtipo_renta_variable" onchange="actualizarCamposRentaVariable()">
                            <option value="ACCION" @selected(old('subtipo_renta_variable') === 'ACCION')>Acción individual</option>
                            <option value="ETF" @selected(old('subtipo_renta_variable') === 'ETF')>ETF (Exchange Traded Fund)</option>
                        </select>
                    </x-ui.field>

                    <x-ui.field name="mercado" label="Mercado de negociación">
                        <select name="mercado" id="mercado_select" onchange="actualizarCamposRentaVariable()">
                            <option value="BVC" @selected(old('mercado') === 'BVC')>Bolsa de Valores de Colombia (BVC)</option>
                            <option value="EXTERIOR" @selected(old('mercado') === 'EXTERIOR')>Mercado Extranjero / Internacional</option>
                        </select>
                    </x-ui.field>

                    <div id="wrapper-tipo-reparto" style="{{ (old('mercado') === 'EXTERIOR' || old('subtipo_renta_variable') === 'ETF') ? '' : 'display: none;' }}">
                        <x-ui.field name="tipo_reparto" label="Tipo de reparto de dividendos (ETFs / Exterior)">
                            <select name="tipo_reparto" id="tipo_reparto">
                                <option value="DISTRIBUTIVO" @selected(old('tipo_reparto') === 'DISTRIBUTIVO')>Distributivo (Paga dividendos periódicos - ReteFuente 30%)</option>
                                <option value="ACUMULATIVO" @selected(old('tipo_reparto') === 'ACUMULATIVO')>Acumulativo (Reinvierte dividendos - ReteFuente 15%)</option>
                                <option value="NO_APLICA" @selected(old('tipo_reparto') === 'NO_APLICA')>No aplica / Sin dividendos</option>
                            </select>
                        </x-ui.field>
                    </div>

                    <div class="income-two-fields">
                        <x-ui.field name="moneda_dividendo" label="Moneda del dividendo" help="Selecciona si el dividendo se decreta en Pesos (COP) o Dólares (USD)">
                            <select name="moneda_dividendo" id="moneda_dividendo" onchange="toggleTrmDividendo(this.value)">
                                <option value="COP" @selected(old('moneda_dividendo', 'COP') === 'COP')>COP (Pesos Colombianos)</option>
                                <option value="USD" @selected(old('moneda_dividendo') === 'USD')>USD (Dólares Americanos)</option>
                            </select>
                        </x-ui.field>

                        <x-ui.field name="valor_dividendo_por_unidad" label="Dividendo por acción decretado" :required="true" help="Valor en la moneda seleccionada. Si no decreta dividendos, digita 0.00.">
                            <input name="valor_dividendo_por_unidad" id="valor_dividendo_por_unidad" type="number" step="0.0001" min="0" value="{{ old('valor_dividendo_por_unidad', '0.00') }}" required placeholder="0.00">
                        </x-ui.field>
                    </div>

                    <div id="wrapper-trm-dividendo" style="{{ old('moneda_dividendo', 'COP') === 'USD' ? '' : 'display: none;' }} grid-column: 1 / -1;">
                        <x-ui.field name="trm_dividendo" label="TRM de conversión del dividendo (Opcional)" help="Si la dejas vacía, el sistema tomará automáticamente la TRM oficial de la base de datos para la fecha de apertura.">
                            <input name="trm_dividendo" id="trm_dividendo" type="number" step="0.0001" min="0.0001" value="{{ old('trm_dividendo') }}" placeholder="Ej: 4150.50 (Opcional: toma TRM de BD si está vacío)">
                        </x-ui.field>
                    </div>

                    <div id="bvc-dividendo-info" style="{{ (old('mercado', 'BVC') === 'BVC' && old('subtipo_renta_variable', 'ACCION') === 'ACCION') ? '' : 'display: none;' }} grid-column: 1 / -1; background: #f0fdf4; border: 1px solid #86efac; padding: 0.75rem; border-radius: 6px;">
                        <p style="margin: 0; color: #166534; font-size: 0.875rem;">
                            <strong>Tratamiento tributario BVC (Art. 242 E.T. / Ley 2277):</strong> En acciones colombianas de la BVC no aplica Distributivo/Acumulativo. Los dividendos decretados están <strong>exentos de retención en la fuente (tarifa 0%) hasta 1.090 UVT ($ 57.087.660 en 2026)</strong>. Solo los dividendos anuales que superen 1.090 UVT pagan el 15% sobre el exceso.
                        </p>
                    </div>

                    <div id="wrapper-esta-inscrito-bvc" style="{{ old('mercado', 'BVC') === 'BVC' ? '' : 'display: none;' }} grid-column: 1 / -1;">
                        <label class="ui-check"><input type="checkbox" name="esta_inscrito_bvc" value="1" @checked(old('esta_inscrito_bvc', true))> Acción inscrita en la BVC (Beneficio Art. 36-1 E.T. no gravada si venta ≤ 3% de circulación)</label>
                    </div>
                </div>
            </fieldset>

            <!-- SECCIÓN 4: ESPECÍFICO FIC -->
            <fieldset id="section-fic" @disabled($selectedTipo !== 'FIC') style="{{ $selectedTipo === 'FIC' ? '' : 'display: none;' }} border: 1px solid var(--color-border, #ccc); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <legend style="font-weight: 600; color: var(--color-primary);">Catálogo Oficial de Fondos de Inversión Colectiva (FIC)</legend>
                <div class="income-two-fields">
                    <x-ui.field name="fic_catalogo_id" label="Selecciona el Fondo de Inversión">
                        <select name="fic_catalogo_id" id="fic_catalogo_id">
                            <option value="">Selecciona un fondo del catálogo</option>
                            @foreach($fics as $fic)
                                <option value="{{ $fic->id }}" @selected(old('fic_catalogo_id') === $fic->id)>
                                    {{ $fic->nombre }} (Perfil: {{ $fic->perfil }} · Tarifa retención ponderada: {{ $fic->tarifaPonderada() }}%)
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field name="pacto_permanencia_dias" label="Pacto de permanencia (Días)">
                        <input name="pacto_permanencia_dias" type="number" min="0" value="{{ old('pacto_permanencia_dias', 0) }}" placeholder="0 si es a la vista">
                    </x-ui.field>
                </div>
                <p class="ui-help">Regla 9.2: Sin comisiones de entrada. La retención se practica únicamente al redimir, sobre la utilidad acumulada pendiente de retiro con la tarifa ponderada del fondo.</p>
            </fieldset>

            <!-- SECCIÓN 5: ESPECÍFICO FPV -->
            <fieldset id="section-fpv" @disabled($selectedTipo !== 'FPV') style="{{ $selectedTipo === 'FPV' ? '' : 'display: none;' }} border: 1px solid var(--color-border, #ccc); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <legend style="font-weight: 600; color: var(--color-primary);">Fondo Voluntario de Pensión (FPV) - Control de Topes</legend>
                <div class="income-two-fields">
                    <x-ui.field name="tipo_vinculacion" label="Tipo de vinculación">
                        <select name="tipo_vinculacion">
                            <option value="DIRECTO" @selected(old('tipo_vinculacion') === 'DIRECTO')>Directo por el partícipe</option>
                            <option value="VIA_EMPLEADOR" @selected(old('tipo_vinculacion') === 'VIA_EMPLEADOR')>Vía empleador (Descuento de nómina)</option>
                        </select>
                    </x-ui.field>

                    <x-ui.field name="ingreso_anual_estimado" label="Ingreso anual estimado del partícipe (COP)" help="Requerido para validar el tope del 30%">
                        <input name="ingreso_anual_estimado" type="number" step="0.01" min="0" value="{{ old('ingreso_anual_estimado', $perfilTributario?->ingreso_anual_estimado ?? '') }}" placeholder="Ej: 120000000">
                    </x-ui.field>
                </div>
                <div class="ui-notice" style="margin-top: 0.5rem; background: #fefce8; border-color: #fde047;">
                    <p><strong>Validación normativa de topes (Art. 126-1 ET / 9.8.2):</strong> El aporte es renta exenta deducible hasta el menor valor entre el 30% del ingreso anual y 3.800 UVT ($ 199.021.200 en 2026). Si retiras anticipadamente sin cumplir 10 años ni destino de vivienda, se aplica retención contingente del 7% y se revierte el deducible.</p>
                </div>
            </fieldset>

            <!-- SECCIÓN 6: ESPECÍFICO CESANTÍAS -->
            <fieldset id="section-cesantias" @disabled($selectedTipo !== 'CESANTIAS') style="{{ $selectedTipo === 'CESANTIAS' ? '' : 'display: none;' }} border: 1px solid var(--color-border, #ccc); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <legend style="font-weight: 600; color: var(--color-primary);">Fondo de Cesantías (Retención 0% y Exención Art. 206 Num. 4)</legend>
                <div class="income-two-fields">
                    <x-ui.field name="regimen" label="Régimen de cesantías">
                        <select name="regimen">
                            <option value="ANUALIZADO" @selected(old('regimen') === 'ANUALIZADO')>Ley 50 de 1990 (Anualizado en Fondo Privado)</option>
                            <option value="TRADICIONAL" @selected(old('regimen') === 'TRADICIONAL')>Tradicional (FNA / Retroactivo)</option>
                        </select>
                    </x-ui.field>

                    <x-ui.field name="tasa_interes_esperada" label="Rentabilidad anual esperada del fondo (%)">
                        <input name="tasa_interes_esperada" type="number" step="0.01" min="0" max="100" value="{{ old('tasa_interes_esperada', '8.00') }}">
                    </x-ui.field>

                    <x-ui.field name="ingreso_laboral_promedio_6m" label="Salario mensual promedio últimos 6 meses (COP)" help="Base para calcular el porcentaje exento por UVT">
                        <input name="ingreso_laboral_promedio_6m" type="number" step="0.01" min="0" value="{{ old('ingreso_laboral_promedio_6m', $perfilTributario?->ingreso_laboral_promedio_6m ?? '') }}" placeholder="Ej: 8000000">
                    </x-ui.field>

                    <x-ui.field name="plazo_dias" label="Plazo estimado de permanencia (Días)">
                        <input id="plazo_dias_cesantias" name="plazo_dias" type="number" min="1" value="{{ old('plazo_dias', 365) }}">
                    </x-ui.field>
                </div>
                <div class="ui-notice" style="margin-top: 0.5rem; background: #eff6ff; border-color: #bfdbfe;">
                    <p><strong>Beneficio fiscal (Art. 206 #4 ET):</strong> Retención en la fuente 0%. Si el ingreso promedio ≤ 350 UVT ($ 18.330.900), la exención es del 100%. Por encima de 350 UVT aplica tabla gradual.</p>
                </div>
            </fieldset>

            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                <x-ui.button type="submit" variant="primary">Guardar Inversión</x-ui.button>
            </div>
        </form>
    </details>
</x-ui.card>

<x-ui.card>
    <form method="GET" action="{{ route('inversiones.index') }}" class="income-filters">
        <x-ui.field name="estado" id="filter-state" label="Estado">
            <select id="filter-state" name="estado">
                <option value="">Todos los estados</option>
                @foreach($estados as $code)
                    <option value="{{ $code }}" @selected(request('estado') === $code)>{{ $labels[$code] ?? $code }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <x-ui.field name="tipo" id="filter-type" label="Producto">
            <select id="filter-type" name="tipo">
                <option value="">Todos los productos</option>
                @foreach($tipos as $code)
                    <option value="{{ $code }}" @selected(request('tipo') === $code)>{{ $labels[$code] ?? $code }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <div class="ui-inline" style="align-self: flex-end;">
            <x-ui.button type="submit" variant="secondary">Filtrar</x-ui.button>
            <a href="{{ route('inversiones.index') }}" class="ui-button" style="text-decoration: none; padding: 0.5rem 1rem;">Limpiar</a>
        </div>
    </form>
</x-ui.card>

<x-ui.card title="Portafolio de Inversiones Activas y Cerradas">
    @if($inversiones->isEmpty())
        <x-ui.empty-state title="No hay inversiones registradas para los filtros seleccionados" />
    @else
        <table class="ui-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Producto</th>
                    <th>Entidad</th>
                    <th data-align="right">Aporte Inicial</th>
                    <th data-align="right">Valor Actual</th>
                    <th data-align="right">Valorización No Realizada</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inversionesPresentadas as $inv)
                    <tr>
                        <td>
                            <strong>{{ $inv['nombre'] }}</strong>
                            @if(!empty($inv['simbolo']))
                                <span class="ui-help" style="display: block;">Ticker: {{ $inv['simbolo'] }}</span>
                            @endif
                        </td>
                        <td>{{ $labels[$inv['tipo']] ?? $inv['tipo'] }}</td>
                        <td>{{ $inv['entidad_relacion']['nombre'] ?? $inv['entidad'] ?? '—' }}</td>
                        <td class="ui-number">
                            <span data-money="{{ $inv['monto_inicial'] }}">{{ number_format((float) $inv['monto_inicial'], 2) }}</span>
                            <span class="ui-help">{{ $inv['moneda'] }}</span>
                        </td>
                        <td class="ui-number">
                            <strong data-money="{{ $inv['valor_actual'] ?? $inv['monto_inicial'] }}">{{ number_format((float) ($inv['valor_actual'] ?? $inv['monto_inicial']), 2) }}</strong>
                            <span class="ui-help">{{ $inv['moneda'] }}</span>
                        </td>
                        <td class="ui-number">
                            @if(!empty($inv['valoracion_no_realizada']))
                                @php($vnr = $inv['valoracion_no_realizada'])
                                <span style="color: {{ (float)$vnr['valor'] >= 0 ? '#16a34a' : '#dc2626' }}; font-weight: 600;">
                                    {{ (float)$vnr['valor'] >= 0 ? '+' : '' }}$ {{ number_format((float)$vnr['valor'], 2) }} ({{ $vnr['porcentaje'] }}%)
                                </span>
                            @elseif(!empty($inv['rendimiento_estimado']))
                                <span class="ui-help" title="Proyección al vencimiento">
                                    Est: +$ {{ number_format((float)$inv['rendimiento_estimado'], 2) }}
                                </span>
                            @else
                                <span class="ui-help">—</span>
                            @endif
                        </td>
                        <td>
                            <x-ui.badge tone="{{ $inv['estado'] === 'ACTIVA' ? 'success' : 'neutral' }}">
                                {{ $labels[$inv['estado']] ?? $inv['estado'] }}
                            </x-ui.badge>
                        </td>
                        <td>
                            <a href="{{ route('inversiones.show', $inv['id']) }}" class="ui-button" style="text-decoration: none; padding: 0.35rem 0.75rem; font-size: 0.85rem;">
                                Ver Detalle / Operar
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <x-ui.pagination :paginator="$inversiones" />
</x-ui.card>

<x-ui.calculation-notice />

<script>
function actualizarFormularioPorTipo(tipo) {
    const sectionMap = {
        'CDT': 'section-cdt',
        'CUENTA_ALTO_RENDIMIENTO': 'section-cuenta',
        'ACCION_ETF': 'section-accion',
        'FIC': 'section-fic',
        'FPV': 'section-fpv',
        'CESANTIAS': 'section-cesantias'
    };

    // Habilitar y mostrar únicamente la sección correspondiente al tipo seleccionado
    Object.keys(sectionMap).forEach(key => {
        const el = document.getElementById(sectionMap[key]);
        if (el) {
            const isMatch = (key === tipo);
            el.style.display = isMatch ? 'block' : 'none';
            el.disabled = !isMatch;
        }
    });

    const wrapperUnidades = document.getElementById('wrapper-unidades');
    const wrapperPrecio = document.getElementById('wrapper-valor-unidad');
    const inputUnidades = document.getElementById('unidades');
    const inputPrecio = document.getElementById('valor_unidad');
    const usesUnits = ['ACCION_ETF', 'FIC', 'FPV'].includes(tipo);

    if (wrapperUnidades) wrapperUnidades.style.display = usesUnits ? 'block' : 'none';
    if (wrapperPrecio) wrapperPrecio.style.display = usesUnits ? 'block' : 'none';
    if (inputUnidades) inputUnidades.disabled = !usesUnits;
    if (inputPrecio) inputPrecio.disabled = !usesUnits;

    if (tipo === 'CDT') {
        calcularProyeccionCdt();
    } else if (tipo === 'ACCION_ETF') {
        actualizarCamposRentaVariable();
    }
}

function toggleMoneda(val) {
    const wrap = document.getElementById('tasa-cambio-wrapper');
    if (wrap) wrap.style.display = (val === 'USD') ? 'block' : 'none';
}

// RF-INV-06: Auto-cálculo reactivo de 2 de 3 valores en productos por unidades
function resolverDosDeTres(origen) {
    const montoEl = document.getElementById('monto_inicial');
    const unidadesEl = document.getElementById('unidades');
    const precioEl = document.getElementById('valor_unidad');
    const alerta = document.getElementById('alerta-inconsistencia');

    if (!montoEl || !unidadesEl || !precioEl || !alerta) return;

    let m = parseFloat(montoEl.value) || 0;
    let u = parseFloat(unidadesEl.value) || 0;
    let p = parseFloat(precioEl.value) || 0;

    alerta.style.display = 'none';

    if (origen === 'monto' && m > 0) {
        if (p > 0) {
            unidadesEl.value = (m / p).toFixed(8);
        } else if (u > 0) {
            precioEl.value = (m / u).toFixed(8);
        }
    } else if (origen === 'unidades' && u > 0) {
        if (p > 0) {
            montoEl.value = (u * p).toFixed(2);
        } else if (m > 0) {
            precioEl.value = (m / u).toFixed(8);
        }
    } else if (origen === 'precio' && p > 0) {
        if (u > 0) {
            montoEl.value = (u * p).toFixed(2);
        } else if (m > 0) {
            unidadesEl.value = (m / p).toFixed(8);
        }
    }

    // Re-leer valores finales después de cualquier auto-cálculo para evitar estados obsoletos en memoria
    const finalM = parseFloat(montoEl.value) || 0;
    const finalU = parseFloat(unidadesEl.value) || 0;
    const finalP = parseFloat(precioEl.value) || 0;

    if (finalM > 0 && finalU > 0 && finalP > 0) {
        const esperado = finalU * finalP;
        if (Math.abs(finalM - esperado) > 1.0) {
            alerta.innerText = 'Inconsistencia detectada: Monto ($' + finalM.toFixed(2) + ') difiere de Unidades × Precio ($' + esperado.toFixed(2) + ').';
            alerta.style.display = 'block';
        }
    }
}

function actualizarCamposRentaVariable() {
    const subtipoEl = document.getElementById('subtipo_renta_variable');
    const mercadoEl = document.getElementById('mercado_select');
    const wrapperReparto = document.getElementById('wrapper-tipo-reparto');
    const bvcInfo = document.getElementById('bvc-dividendo-info');
    const estaInscritoWrap = document.getElementById('wrapper-esta-inscrito-bvc');

    if (!subtipoEl || !mercadoEl) return;

    const subtipo = subtipoEl.value;
    const mercado = mercadoEl.value;

    const esBvcAccion = (mercado === 'BVC' && subtipo === 'ACCION');
    const esEtfOExterior = (subtipo === 'ETF' || mercado === 'EXTERIOR');

    if (wrapperReparto) {
        wrapperReparto.style.display = esEtfOExterior ? 'block' : 'none';
        const selectReparto = wrapperReparto.querySelector('select');
        if (selectReparto) {
            selectReparto.disabled = !esEtfOExterior;
            if (!esEtfOExterior) {
                selectReparto.value = 'NO_APLICA';
            }
        }
    }

    if (bvcInfo) {
        bvcInfo.style.display = esBvcAccion ? 'block' : 'none';
    }

    if (estaInscritoWrap) {
        estaInscritoWrap.style.display = (mercado === 'BVC') ? 'block' : 'none';
    }
}

function toggleTrmDividendo(val) {
    const wrap = document.getElementById('wrapper-trm-dividendo');
    if (wrap) {
        wrap.style.display = (val === 'USD') ? 'block' : 'none';
    }
}

function calcularProyeccionCdt() {
    const capital = parseFloat(document.getElementById('monto_inicial').value) || 0;
    const tasa = parseFloat(document.getElementById('tasa_ea_cdt').value) || 0;
    const dias = parseInt(document.getElementById('plazo_dias').value) || 0;
    const base = parseInt(document.getElementById('base_dias').value) || 365;
    const tipoTasaEl = document.getElementById('tipo_tasa');
    const tipoTasa = tipoTasaEl ? tipoTasaEl.value : 'EA';

    if (capital > 0 && tasa > 0 && dias > 0) {
        let tasaEa = tasa;
        if (tipoTasa === 'NOMINAL') {
            const m = 12;
            tasaEa = (Math.pow(1 + (tasa / 100) / m, m) - 1) * 100;
        }
        const factor = Math.pow(1 + (tasaEa / 100), dias / base);
        const bruto = capital * (factor - 1);
        const ret = bruto * 0.04; // 4% ReteFuente renta fija
        const neto = bruto - ret;

        document.getElementById('cdt-prev-bruto').innerText = '$ ' + bruto.toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('cdt-prev-ret').innerText = '$ ' + ret.toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('cdt-prev-neto').innerText = '$ ' + neto.toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
}

// Inicializar al cargar y asegurar deshabilitado en submit
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('tipo');
    if (sel && sel.value) {
        actualizarFormularioPorTipo(sel.value);
    }

    const form = document.getElementById('investment-form');
    if (form) {
        form.addEventListener('submit', function() {
            const currentTipo = document.getElementById('tipo') ? document.getElementById('tipo').value : '';
            actualizarFormularioPorTipo(currentTipo);
        });
    }
});
</script>
</div>
@endsection
