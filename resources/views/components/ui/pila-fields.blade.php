@props(['payment' => null, 'prefix' => 'pila'])
<div class="income-two-fields">
<x-ui.field name="periodo" :id="$prefix.'-periodo'" label="Periodo cotizado" :required="true"><input id="{{ $prefix }}-periodo" name="periodo" type="month" required value="{{ old('periodo', $payment['periodo'] ?? now('America/Bogota')->startOfMonth()->subMonth()->format('Y-m')) }}"></x-ui.field>
<x-ui.field name="fecha_pago" :id="$prefix.'-fecha'" label="Fecha de pago" :required="true"><input id="{{ $prefix }}-fecha" name="fecha_pago" type="date" data-date-no-future required value="{{ old('fecha_pago', substr($payment['fecha_pago'] ?? now('America/Bogota')->format('Y-m-d'),0,10)) }}"></x-ui.field>
<x-ui.field name="numero_planilla" :id="$prefix.'-planilla'" label="Número de planilla" help="Opcional. Hasta 20 caracteres."><input id="{{ $prefix }}-planilla" name="numero_planilla" maxlength="20" value="{{ old('numero_planilla',$payment['numero_planilla'] ?? '') }}"></x-ui.field>
@php($paymentLabels = require resource_path('views/components/expense/labels.php'))
<x-ui.field name="medio_pago" :id="$prefix.'-medio'" label="Medio de pago"><select id="{{ $prefix }}-medio" name="medio_pago">@foreach(\App\Models\Gasto::MEDIOS_PAGO as $code)<option value="{{ $code }}" @selected(old('medio_pago', $payment['gasto']['medio_pago'] ?? 'TRANSFERENCIA') === $code)>{{ $paymentLabels[$code] ?? $code }}</option>@endforeach</select></x-ui.field>
</div>
<label class="ui-check"><input type="checkbox" name="es_correccion" value="1" @checked((string) old('es_correccion', $payment['es_correccion'] ?? false) === '1')> Planilla de corrección</label><p class="ui-help">La corrección registra la diferencia frente al pago original del periodo.</p>
<div class="pila-autofill-box" data-pila-autofill-container>
    <div class="pila-autofill-header">
        <div>
            <h4 class="pila-autofill-title">Autocompletar desde ingresos de rentas de trabajo</h4>
            <p class="ui-help">Consulta tus honorarios o servicios del mes cotizado y calcula automáticamente los aportes sugeridos.</p>
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
                <span class="pila-metric-item">Total ingresos: <strong data-pila-metric-ingresos>$ 0</strong></span>
                <span class="pila-metric-item">IBC estimado: <strong data-pila-metric-ibc>$ 0</strong></span>
                <span class="pila-metric-item">Total aportes: <strong data-pila-metric-total>$ 0</strong></span>
            </div>
            <ul class="pila-autofill-items" data-pila-autofill-items></ul>
        </div>
    </div>
</div>
<h3>Valores pagados por concepto</h3><div class="income-two-fields">@foreach(['salud'=>'Salud','pension'=>'Pensión','fsp'=>'Fondo de Solidaridad Pensional','arl'=>'Riesgos laborales','intereses_mora'=>'Intereses de mora'] as $key=>$label)<x-ui.money-input :name="$key" :id="$prefix.'-'.$key" :label="$label" :allow-zero="true" :required="in_array($key,['salud','pension'],true)" :value="(string) ($payment[$key] ?? '0')" />@endforeach</div>
