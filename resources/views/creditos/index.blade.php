@extends('layouts.app')
@section('title', 'Créditos')
@section('content')
@php
    $labels = ['LIBRE_INVERSION'=>'Libre inversión','LIBRANZA'=>'Libranza','VEHICULO'=>'Vehículo','VIVIENDA'=>'Vivienda','TARJETA_CREDITO'=>'Tarjeta de crédito','OTRO'=>'Otro','SEMANAL'=>'Semanal','QUINCENAL'=>'Quincenal','MENSUAL'=>'Mensual','BIMESTRAL'=>'Bimestral','TRIMESTRAL'=>'Trimestral','SEMESTRAL'=>'Semestral','ANUAL'=>'Anual','FRANCES'=>'Cuota fija','ALEMAN'=>'Abono constante a capital'];
@endphp
<div class="finance-page credit-page" data-credit-app data-base="{{ route('creditos.index') }}" data-credit-id="{{ $creditoId ?? '' }}">
<nav class="ui-tabs" aria-label="Secciones de créditos"><a href="{{ route('creditos.index') }}">Mis créditos</a><a href="{{ route('creditos.endeudamiento') }}">Endeudamiento</a></nav>
<header class="income-page-header"><div><h1 data-credit-heading>Créditos</h1><p data-credit-subtitle>Saldo, próximas cuotas y peso de tus créditos sobre tus ingresos.</p></div><div class="ui-inline"><a class="ui-button ui-button--secondary" href="{{ route('creditos.index') }}" data-credit-back hidden>Volver a Mis créditos</a><a class="ui-button ui-button--primary" href="{{ route('creditos.index') }}?vista=crear" data-credit-new>Registrar crédito</a></div></header>
<div data-credit-status role="alert" hidden></div><div data-credit-loading><x-ui.skeleton :lines="3" /></div>
<section data-credit-screen="list" hidden><div data-credit-indicator></div><x-ui.card title="Mis créditos"><div data-credit-list></div><nav class="ui-pagination" aria-label="Paginación de créditos"><x-ui.button type="button" variant="secondary" data-credit-prev disabled>Anterior</x-ui.button><p data-credit-page aria-live="polite"></p><x-ui.button type="button" variant="secondary" data-credit-next disabled>Siguiente</x-ui.button><label>Registros por página <select data-credit-size><option>10</option><option selected>20</option><option>50</option><option>100</option></select></label></nav></x-ui.card></section>
<section data-credit-screen="debt" hidden><div data-credit-debt></div></section>
<section data-credit-screen="form" hidden>
<x-ui.card title="Resumen del crédito"><p class="ui-help">Vista previa calculada por el sistema con las condiciones ingresadas. No registra el crédito.</p><div data-credit-preview aria-live="polite">Completa las condiciones para consultar la cuota y el plan.</div><x-ui.button type="button" variant="secondary" data-credit-calculate>Actualizar cálculo</x-ui.button></x-ui.card>
<form method="POST" action="{{ route('creditos.store') }}" data-credit-form novalidate>@csrf
<x-ui.card title="Datos generales"><div class="income-two-fields">
<x-ui.field name="proposito" label="Nombre del crédito" :required="true" help="Entre 3 y 60 caracteres."><input id="proposito" name="proposito" required minlength="3" maxlength="60"></x-ui.field>
<x-ui.field name="banco" label="Entidad" :required="true" help="Hasta 80 caracteres."><input id="banco" name="banco" required maxlength="80"></x-ui.field>
<x-ui.select name="tipo_credito" label="Tipo de crédito" :required="true">@foreach(\App\Services\CreditoService::TIPOS_CREDITO as $code)<option value="{{ $code }}">{{ $labels[$code] }}</option>@endforeach</x-ui.select>
</div><p class="ui-help">Moneda: COP.</p><p data-credit-edit-notice class="ui-notice" hidden>Este crédito tiene pagos: solo puedes cambiar el nombre y la entidad.</p></x-ui.card>
<x-ui.card title="Condiciones financieras"><div class="income-two-fields">
<x-ui.money-input name="monto" id="credit-monto" label="Monto desembolsado" />
<x-ui.field name="tasa" label="Tasa de interés (%)" :required="true" help="Usa coma decimal, hasta seis decimales."><input id="tasa" name="tasa" inputmode="decimal" required></x-ui.field>
<x-ui.select name="tipo_tasa" label="Expresión de la tasa" :required="true" :options="['EA'=>'Efectiva anual (EA)','MENSUAL'=>'Mes vencido (MV)','NOMINAL'=>'Nominal anual']" />
<x-ui.field name="capitalizacion" label="Capitalización de la tasa nominal"><select id="capitalizacion" name="capitalizacion">@foreach(array_keys(\App\Services\CreditoService::PERIODICIDADES) as $code)<option value="{{ $code }}" @selected($code==='MENSUAL')>{{ $labels[$code] }}</option>@endforeach</select></x-ui.field>
<x-ui.select name="periodicidad" label="Periodicidad de pago" :required="true" value="MENSUAL">@foreach(array_keys(\App\Services\CreditoService::PERIODICIDADES) as $code)<option value="{{ $code }}" @selected($code==='MENSUAL')>{{ $labels[$code] }}</option>@endforeach</x-ui.select>
<x-ui.select name="sistema_amortizacion" label="Sistema de amortización" :required="true" :options="['FRANCES'=>'Cuota fija','ALEMAN'=>'Abono constante a capital']" />
<label class="ui-field">Expresar plazo en<select data-credit-term-mode><option value="months">Meses</option><option value="installments">Número de cuotas</option></select></label>
<x-ui.field name="numero_cuotas" label="Número de cuotas" hidden><input id="numero_cuotas" name="numero_cuotas" type="number" min="1" max="9999" step="1" disabled></x-ui.field>
<x-ui.field name="plazo_meses" label="Plazo en meses" :required="true" :help="'Entre '.config('scfp.creditos.plazo_min_meses').' y '.config('scfp.creditos.plazo_max_meses').' meses; vivienda desde '.config('scfp.creditos.vivienda_plazo_min_meses').' meses.'"><input id="plazo_meses" name="plazo_meses" type="number" min="{{ config('scfp.creditos.plazo_min_meses') }}" max="{{ config('scfp.creditos.plazo_max_meses') }}" step="1" required></x-ui.field>
<x-ui.date-input name="fecha_inicio" label="Fecha de desembolso" :allow-future="true" />
<x-ui.date-input name="fecha_primer_pago" label="Fecha de primera cuota" :allow-future="true" :required="false" />
<x-ui.field name="seguro_porc_mensual" label="Seguro mensual sobre saldo (%)" :help="'Opcional, entre 0 y '.config('scfp.creditos.seguro_porc_mensual_max').' %. No es un importe fijo.'"><input id="seguro_porc_mensual" name="seguro_porc_mensual" inputmode="decimal"></x-ui.field>
<x-ui.money-input name="cargo_fijo" id="credit-cargo" label="Cargo fijo por cuota" :required="false" :allow-zero="true" />
<x-ui.field name="tasa_mora" label="Tasa de mora (% EA)" help="Opcional. La mora se calcula con la tasa informada; no ingreses aquí un monto."><input id="tasa_mora" name="tasa_mora" inputmode="decimal"></x-ui.field>
<div data-credit-housing hidden><x-ui.money-input name="valor_inmueble" id="credit-property" label="Valor del inmueble" :required="false" /><label class="ui-check"><input name="es_vis" type="checkbox" value="1"> Vivienda de interés social (VIS)</label></div>
</div></x-ui.card><x-ui.module-status /><div class="ui-inline"><a class="ui-button ui-button--secondary" href="{{ route('creditos.index') }}">Cancelar</a><x-ui.button type="submit" data-credit-save>Registrar crédito</x-ui.button></div></form></section>
<section data-credit-screen="detail" hidden><div data-credit-detail></div></section>
<section data-credit-screen="abono" hidden><x-ui.card title="Abono extraordinario a capital"><p>Consulta cómo se aplicará el pago y compara las alternativas antes de confirmar.</p><div data-credit-abono-context></div></x-ui.card><div data-credit-abono-host></div></section>
<dialog class="ui-dialog credit-payment-dialog" data-credit-payment-dialog aria-labelledby="credit-payment-title"><h2 id="credit-payment-title">Registrar pago</h2><div data-credit-payment-host></div><x-ui.button type="button" variant="secondary" data-credit-close-payment>Cerrar</x-ui.button></dialog>
<template data-credit-payment-template><form data-credit-payment-form novalidate><div class="income-two-fields">
<x-ui.date-input name="fecha" label="Fecha de pago" />
<x-ui.money-input name="valor" id="credit-payment-amount" label="Valor pagado" />
<x-ui.select name="medio" label="Medio de pago" :required="true">@foreach(\App\Models\Gasto::MEDIOS_PAGO as $code)<option value="{{ $code }}">{{ ['EFECTIVO'=>'Efectivo','TARJETA_DEBITO'=>'Tarjeta débito','TARJETA_CREDITO'=>'Tarjeta de crédito','TRANSFERENCIA'=>'Transferencia','BILLETERA_DIGITAL'=>'Billetera digital','OTRO'=>'Otro'][$code] }}</option>@endforeach</x-ui.select>
<x-ui.field name="referencia" label="Referencia" help="Opcional, hasta 60 caracteres."><input id="referencia" name="referencia" maxlength="60"></x-ui.field>
<x-ui.select name="aplicacion_excedente" label="Si el pago tiene excedente" :options="[''=>'Selecciona cómo aplicarlo','ANTICIPO'=>'Anticipar cuotas','ABONO_CAPITAL'=>'Abonar a capital']" />
<x-ui.select name="reducir" label="Qué reducir con el abono" :options="['PLAZO'=>'Reducir plazo','CUOTA'=>'Reducir cuota']" />
</div><p class="ui-help">Ingresa el pago en pesos enteros, sin centavos. La simulación indica a qué cuotas se aplica el pago y calcula su mora. Al confirmar se generan los gastos correspondientes.</p><x-ui.module-status /><div class="ui-inline"><x-ui.button type="button" variant="secondary" data-credit-simulate>Simular pago</x-ui.button><x-ui.button type="submit" disabled data-credit-confirm>Confirmar pago</x-ui.button></div><div data-credit-payment-preview aria-live="polite"></div></form></template>
<dialog class="ui-dialog" data-credit-reverse-dialog aria-labelledby="credit-reverse-title"><h2 id="credit-reverse-title">Reversar último pago</h2><p>Se revertirán el pago y sus gastos asociados.</p><form data-credit-reverse-form novalidate><x-ui.field name="motivo" label="Motivo del reverso" :required="true" help="Entre 10 y 200 caracteres."><textarea id="motivo" name="motivo" minlength="10" maxlength="200" required></textarea></x-ui.field><x-ui.module-status /><div class="ui-inline"><x-ui.button type="button" variant="secondary" data-credit-close-reverse>Cancelar</x-ui.button><x-ui.button type="submit" variant="danger">Confirmar reverso</x-ui.button></div></form></dialog>
<x-ui.calculation-notice />
</div>
@endsection
