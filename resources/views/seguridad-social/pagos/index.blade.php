@extends('layouts.app')
@section('title', 'Registro de Pagos PILA · Seguridad Social')
@section('content')
<div class="space-y-6">
    <header class="income-page-header">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pagos de Seguridad Social (PILA)</h1>
            <p class="text-sm text-gray-600">RF-035: Registro de pagos reales de aportes y generación automática del egreso en Gastos.</p>
        </div>
        <div class="ui-inline">
            <x-ui.button :href="route('seguridad-social.index')" variant="secondary">
                <x-icon name="ui-volver" :size="18" />
                Volver a Seguridad Social
            </x-ui.button>
        </div>
    </header>

    @if(session('status'))
        <div class="p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-center gap-2">
            <x-icon name="ui-exito" :size="20" class="text-green-600" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm space-y-1">
            <div class="flex items-center gap-2 font-bold">
                <x-icon name="ui-error" :size="20" class="text-red-600" />
                <span>Error en el registro del pago:</span>
            </div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- RESUMEN ANUAL DE INGRESOS NO CONSTITUTIVOS (RN-035.4) --}}
    <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="text-xs font-bold uppercase text-emerald-800 tracking-wider">Acumulado Anual {{ $anioActual }} (RN-035.4)</span>
            <h2 class="text-xl font-extrabold text-emerald-950">Ingreso no constitutivo de renta (Salud y Pensión)</h2>
            <p class="text-xs text-emerald-700">Aportes obligatorios pagados por PILA que se descuentan en la declaración de renta (RF-043).</p>
        </div>
        <div class="text-right">
            <p class="text-3xl font-black text-emerald-800">$ {{ number_format((float) ($resumenAnual['ingreso_no_constitutivo'] ?? 0), 0, ',', '.') }}</p>
            <p class="text-xs text-emerald-700">Salud: ${{ number_format((float)($resumenAnual['salud'] ?? 0), 0, ',', '.') }} · Pensión: ${{ number_format((float)($resumenAnual['pension'] ?? 0), 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- FORMULARIO DE REGISTRO DE PAGO PILA --}}
    <x-ui.card title="Registrar Pago Real por Planilla PILA">
        <form method="POST" action="{{ route('seguridad-social.pagos.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div class="p-3 bg-indigo-50 border border-indigo-200 text-indigo-900 text-xs sm:text-sm rounded-lg flex items-center gap-2">
                <x-icon name="ui-info" :size="20" class="text-indigo-600 shrink-0" />
                <span>
                    <strong>Generación automática de egreso (RN-035.3):</strong> Al guardar este pago, el sistema creará inmediatamente un gasto en la categoría <em>"Seguridad social"</em> con fecha y monto total idénticos, evitando cualquier duplicación en tu flujo de caja.
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-ui.field name="periodo" label="Periodo cotizado (AAAA-MM)*">
                    <input type="month" name="periodo" id="periodo" value="{{ date('Y-m', strtotime('-1 month')) }}" required class="w-full rounded-md border-gray-300 shadow-sm" />
                </x-ui.field>

                <x-ui.field name="fecha_pago" label="Fecha real de pago*">
                    <input type="date" name="fecha_pago" id="fecha_pago" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required class="w-full rounded-md border-gray-300 shadow-sm" />
                </x-ui.field>

                <x-ui.field name="numero_planilla" label="Número de planilla (Opcional)">
                    <input type="text" maxlength="20" name="numero_planilla" id="numero_planilla" placeholder="Ej: 94821038" class="w-full rounded-md border-gray-300 shadow-sm" />
                </x-ui.field>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="es_correccion" id="es_correccion" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                <label for="es_correccion" class="text-xs text-gray-700"><strong>Planilla de corrección</strong> (marcar solo si corrige un pago anterior del mismo periodo, RN-035.2)</label>
            </div>

            <div class="border-t pt-4">
                <h3 class="text-sm font-bold text-gray-800 mb-3">Valores pagados por concepto ($)</h3>
                <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
                    <x-ui.field name="salud" label="Salud*">
                        <input type="number" step="100" min="0" name="salud" id="salud" placeholder="0" required class="w-full rounded-md border-gray-300 shadow-sm" />
                    </x-ui.field>
                    <x-ui.field name="pension" label="Pensión*">
                        <input type="number" step="100" min="0" name="pension" id="pension" placeholder="0" required class="w-full rounded-md border-gray-300 shadow-sm" />
                    </x-ui.field>
                    <x-ui.field name="fsp" label="FSP">
                        <input type="number" step="100" min="0" name="fsp" id="fsp" placeholder="0" value="0" class="w-full rounded-md border-gray-300 shadow-sm" />
                    </x-ui.field>
                    <x-ui.field name="arl" label="ARL">
                        <input type="number" step="100" min="0" name="arl" id="arl" placeholder="0" value="0" class="w-full rounded-md border-gray-300 shadow-sm" />
                    </x-ui.field>
                    <x-ui.field name="intereses_mora" label="Intereses de mora">
                        <input type="number" step="100" min="0" name="intereses_mora" id="intereses_mora" placeholder="0" value="0" class="w-full rounded-md border-gray-300 shadow-sm" />
                    </x-ui.field>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t pt-4">
                <x-ui.field name="medio_pago" label="Medio de pago utilizado para el egreso">
                    <select name="medio_pago" id="medio_pago" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="TRANSFERENCIA" selected>Transferencia bancaria</option>
                        <option value="DEBITO">Tarjeta Débito (PSE)</option>
                        <option value="CREDITO">Tarjeta de Crédito</option>
                        <option value="EFECTIVO">Efectivo / Ventanilla</option>
                    </select>
                </x-ui.field>

                <x-ui.field name="soporte" label="Soporte o comprobante de pago (PDF o Imagen, opcional)">
                    <input type="file" name="soporte" id="soporte" accept=".pdf,.png,.jpg,.jpeg,.webp" class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                </x-ui.field>
            </div>

            <div class="pt-2">
                <x-ui.button type="submit">
                    <x-icon name="act-gasto" :size="18" />
                    Registrar pago PILA y crear gasto
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>

    {{-- LISTA DE PAGOS REGISTRADOS --}}
    <x-ui.card title="Histórico de Pagos de Seguridad Social y Egresos">
        @if(count($pagosPresentados) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm border border-gray-200 rounded-lg">
                    <thead class="bg-gray-100 text-gray-700 uppercase text-xs font-semibold">
                        <tr>
                            <th class="p-3">Periodo</th>
                            <th class="p-3">Fecha de pago</th>
                            <th class="p-3">Planilla</th>
                            <th class="p-3">Total Pagado</th>
                            <th class="p-3">Egreso Creado (Gastos)</th>
                            <th class="p-3">Comparación vs Estimada</th>
                            <th class="p-3 text-right">Soporte</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($pagosPresentados as $p)
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-bold text-gray-900">
                                    {{ $p['periodo'] }}
                                    @if(!empty($p['es_correccion']))
                                        <span class="ms-1 px-1.5 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800 rounded">Corrección</span>
                                    @endif
                                </td>
                                <td class="p-3 text-gray-600">{{ $p['fecha_pago'] }}</td>
                                <td class="p-3 font-mono text-xs">{{ $p['numero_planilla'] ?? 'Sin planilla' }}</td>
                                <td class="p-3 font-black text-emerald-700 text-sm">$ {{ number_format((float) $p['total'], 0, ',', '.') }}</td>
                                <td class="p-3">
                                    @if(!empty($p['gasto']))
                                        <span class="inline-flex items-center gap-1 text-xs text-indigo-700 font-semibold bg-indigo-50 px-2 py-1 rounded">
                                            <x-icon name="act-gasto" :size="14" />
                                            Gasto #{{ substr($p['gasto']['id'], 0, 8) }} ($ {{ number_format((float) $p['gasto']['monto_cop'], 0, ',', '.') }})
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">Sin egreso</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    @if(isset($p['comparacion']['tiene_diferencias']) && $p['comparacion']['tiene_diferencias'])
                                        <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-800" title="Difiere en más de $1.000 con la liquidación estimada">
                                            Diferencia detectada
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs font-medium text-gray-500 bg-gray-100 rounded-full">
                                            Coincide
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3 text-right">
                                    @if(!empty($p['soporte']['url_descarga']))
                                        <a href="{{ $p['soporte']['url_descarga'] }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-semibold text-xs flex items-center justify-end gap-1">
                                            <x-icon name="ui-buscar" :size="14" />
                                            Descargar
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pt-4">
                {{ $pagos->links() }}
            </div>
        @else
            <x-ui.empty-state message="No has registrado pagos de seguridad social. Cuando pagues tu planilla PILA, regístrala aquí para crear automáticamente tu gasto." />
        @endif
    </x-ui.card>
</div>
@endsection

