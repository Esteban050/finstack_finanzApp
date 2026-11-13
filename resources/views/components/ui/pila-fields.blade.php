@props(['payment' => null, 'prefix' => 'pila'])
@php
    $userProfile = auth()->user()?->perfilTributarioActivo;
    $esRentistaPerfil = in_array(\App\Services\SeguridadSocialService::OCUPACION_RENTISTA, $userProfile?->ocupaciones ?? [], true);
    $tipoServicioInicial = old('tipo_servicio', $esRentistaPerfil ? 'RENTISTA_CAPITAL' : 'PRESTACION_SERVICIOS');
@endphp
<div class="income-two-fields">
<x-ui.field name="periodo" :id="$prefix.'-periodo'" label="Periodo cotizado" :required="true"><input id="{{ $prefix }}-periodo" name="periodo" type="month" required value="{{ old('periodo', $payment['periodo'] ?? now('America/Bogota')->startOfMonth()->subMonth()->format('Y-m')) }}"></x-ui.field>
<x-ui.field name="fecha_pago" :id="$prefix.'-fecha'" label="Fecha de pago" :required="true"><input id="{{ $prefix }}-fecha" name="fecha_pago" type="date" data-date-no-future required value="{{ old('fecha_pago', substr($payment['fecha_pago'] ?? now('America/Bogota')->format('Y-m-d'),0,10)) }}"></x-ui.field>
<x-ui.field name="numero_planilla" :id="$prefix.'-planilla'" label="Número de planilla" help="Opcional. Hasta 20 caracteres."><input id="{{ $prefix }}-planilla" name="numero_planilla" maxlength="20" value="{{ old('numero_planilla',$payment['numero_planilla'] ?? '') }}"></x-ui.field>
@php($paymentLabels = require resource_path('views/components/expense/labels.php'))
<x-ui.field name="medio_pago" :id="$prefix.'-medio'" label="Medio de pago"><select id="{{ $prefix }}-medio" name="medio_pago">@foreach(\App\Models\Gasto::MEDIOS_PAGO as $code)<option value="{{ $code }}" @selected(old('medio_pago', $payment['gasto']['medio_pago'] ?? 'TRANSFERENCIA') === $code)>{{ $paymentLabels[$code] ?? $code }}</option>@endforeach</select></x-ui.field>
</div>
<label class="ui-check"><input type="checkbox" name="es_correccion" value="1" @checked((string) old('es_correccion', $payment['es_correccion'] ?? false) === '1')> Planilla de corrección</label><p class="ui-help">La corrección registra la diferencia frente al pago original del periodo.</p>
<div class="pila-autofill-box" data-pila-autofill-container>
    <div class="income-two-fields" style="align-items: start;">
        <x-ui.field name="tipo_servicio" :id="$prefix.'-tipo-servicio'" label="Tipo de cotizante / servicio" help="Selecciona si cotizas por honorarios o como rentista de capital.">
            <select id="{{ $prefix }}-tipo-servicio" name="tipo_servicio" data-pila-service-select data-target-prefix="{{ $prefix }}">
                <option value="PRESTACION_SERVICIOS" @selected($tipoServicioInicial === 'PRESTACION_SERVICIOS')>Prestación de servicios (Contratista / Independiente)</option>
                <option value="RENTISTA_CAPITAL" @selected($tipoServicioInicial === 'RENTISTA_CAPITAL')>Rentista de capital (Arrendamientos, dividendos, etc.)</option>
            </select>
        </x-ui.field>

        <div id="{{ $prefix }}-rentista-gastos-box" data-pila-rentista-box @if($tipoServicioInicial !== 'RENTISTA_CAPITAL') hidden style="display: none;" @endif>
            <x-ui.money-input
                name="gastos_asociados"
                :id="$prefix.'-gastos-asociados'"
                label="Gastos asociados deducibles"
                help="Digita cuánto se gastó en reparaciones, administración o mantenimiento para descontarlo de los ingresos brutos."
                :allow-zero="true"
                value="0"
            />
        </div>
    </div>

    <div class="pila-autofill-header">
        <div>
            <h4 class="pila-autofill-title" data-pila-autofill-title>
                {{ $tipoServicioInicial === 'RENTISTA_CAPITAL' ? 'Autocompletar aportes para Rentista de Capital' : 'Autocompletar desde ingresos de rentas de trabajo' }}
            </h4>
            <p class="ui-help" data-pila-autofill-desc>
                {{ $tipoServicioInicial === 'RENTISTA_CAPITAL' ? 'Consulta tus ingresos, descuenta los gastos asociados registrados y calcula automáticamente el IBC (40% sobre la base neta) y los aportes sugeridos.' : 'Consulta tus honorarios o servicios del mes cotizado y calcula automáticamente los aportes sugeridos.' }}
            </p>
        </div>
        <button type="button" class="ui-button ui-button--secondary" data-pila-autofill-btn data-url="{{ route('seguridad-social.pagos.ingresos-periodo') }}" data-target-prefix="{{ $prefix }}">
            <span data-pila-autofill-btn-text>Traer datos de ingresos</span>
            <span data-pila-autofill-btn-loading hidden>Consultando...</span>
        </button>
    </div>
    <div class="pila-autofill-status" data-pila-autofill-status hidden role="region" aria-live="polite">
        <div class="pila-autofill-status-inner">
            <p class="pila-autofill-msg" data-pila-autofill-msg></p>
            <div class="pila-autofill-action" data-pila-autofill-action hidden>
                <a href="{{ route('ingresos.create') }}" class="ui-button ui-button--secondary">Registrar ingresos</a>
            </div>
        </div>
        <div class="pila-autofill-details" data-pila-autofill-details hidden>
            <div class="pila-autofill-metrics">
                <span class="pila-metric-item">Ingresos brutos: <strong data-pila-metric-ingresos>$ 0</strong></span>
                <span class="pila-metric-item" data-pila-metric-gastos-wrapper hidden>Gastos asociados: <strong data-pila-metric-gastos>-$ 0</strong></span>
                <span class="pila-metric-item" data-pila-metric-neto-wrapper hidden>Base neta: <strong data-pila-metric-neto>$ 0</strong></span>
                <span class="pila-metric-item">IBC estimado (40%): <strong data-pila-metric-ibc>$ 0</strong></span>
                <span class="pila-metric-item">Total aportes: <strong data-pila-metric-total>$ 0</strong></span>
            </div>
            <ul class="pila-autofill-items" data-pila-autofill-items></ul>
        </div>
    </div>
</div>
<h3>Porcentajes y valores calculados</h3>
<x-ui.field name="ibc" :id="$prefix.'-ibc'" label="IBC base de cálculo" help="El porcentaje de cada concepto se aplica sobre este valor."><input id="{{ $prefix }}-ibc" name="ibc" type="number" min="0" step="0.01" value="{{ old('ibc', $payment['ibc'] ?? '') }}"></x-ui.field>
<div class="income-two-fields">@foreach(['salud'=>'Salud','pension'=>'Pensión','fsp'=>'Fondo de Solidaridad Pensional','arl'=>'Riesgos laborales'] as $key=>$label)<x-ui.field :name="'porcentaje_'.$key" :id="$prefix.'-porcentaje-'.$key" :label="$label.' (%)'"><input id="{{ $prefix }}-porcentaje-{{ $key }}" name="porcentaje_{{ $key }}" type="number" min="0" max="100" step="0.01" value="{{ old('porcentaje_'.$key, $payment['porcentaje_'.$key] ?? '') }}"></x-ui.field>@endforeach</div>
<p class="ui-help">Los valores de salud, pensión, FSP y ARL se calculan automáticamente al guardar con base en el IBC y el porcentaje indicado.</p>
<h3>Otros valores pagados</h3><div class="income-two-fields"><x-ui.money-input name="intereses_mora" :id="$prefix.'-intereses_mora'" label="Intereses de mora" :allow-zero="true" :required="false" :value="(string) ($payment['intereses_mora'] ?? '0')" /></div>
