@extends('layouts.app')
@section('title', 'Mis Inversiones · Seguimiento y Rentabilidad')
@section('content')
<div class="space-y-6">
    <header class="income-page-header">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Inversiones y Portafolio</h1>
            <p class="text-sm text-gray-600">RF-037: Registro de activos financieros, seguimiento de valor de mercado y rentabilidad.</p>
        </div>
        <div class="ui-inline">
            <x-ui.button :href="route('inversiones.portafolio')" variant="secondary">
                <x-icon name="act-balance" :size="18" />
                Ver portafolio consolidado
            </x-ui.button>
            <x-ui.button :href="route('inversiones.simulaciones.index')" variant="secondary">
                <x-icon name="ui-buscar" :size="18" />
                Simulador de inversión
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
                <span>Error al registrar la inversión:</span>
            </div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- FORMULARIO DE REGISTRO DE INVERSIÓN (RF-037) --}}
    <x-ui.card>
        <details {{ $errors->any() ? 'open' : '' }}>
            <summary class="font-bold text-base text-gray-800 cursor-pointer flex items-center gap-2 py-1">
                <x-icon name="ui-mas" :size="20" class="text-indigo-600" />
                Registrar nueva inversión (RF-037)
            </summary>
            <form method="POST" action="{{ route('inversiones.store') }}" class="mt-4 space-y-4 border-t pt-4" id="form-inversion">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-ui.field name="nombre" label="Nombre de la inversión* (3-60 caracteres)">
                        <input type="text" name="nombre" id="nombre" maxlength="60" required placeholder="Ej: CDT Bancolombia 360 días" value="{{ old('nombre') }}" class="w-full rounded-md border-gray-300 shadow-sm" />
                    </x-ui.field>

                    <x-ui.field name="tipo" label="Tipo de inversión*">
                        <select name="tipo" id="tipo_inversion" required class="w-full rounded-md border-gray-300 shadow-sm" onchange="actualizarCamposTipo(this.value)">
                            <option value="">-- Seleccionar tipo --</option>
                            <option value="CDT" @selected(old('tipo') === 'CDT')>CDT (Certificado de Depósito)</option>
                            <option value="CUENTA_AHORRO_ALTO_RENDIMIENTO" @selected(old('tipo') === 'CUENTA_AHORRO_ALTO_RENDIMIENTO')>Cuenta de ahorro de alto rendimiento</option>
                            <option value="FONDO_INVERSION_COLECTIVA" @selected(old('tipo') === 'FONDO_INVERSION_COLECTIVA')>Fondo de inversión colectiva (FIC)</option>
                            <option value="ACCIONES" @selected(old('tipo') === 'ACCIONES')>Acciones</option>
                            <option value="ETF" @selected(old('tipo') === 'ETF')>ETF</option>
                            <option value="BONOS_TES" @selected(old('tipo') === 'BONOS_TES')>Bonos / TES</option>
                            <option value="FONDO_VOLUNTARIO_PENSION" @selected(old('tipo') === 'FONDO_VOLUNTARIO_PENSION')>Fondo voluntario de pensión</option>
                            <option value="CRIPTOACTIVO" @selected(old('tipo') === 'CRIPTOACTIVO')>Criptoactivo</option>
                            <option value="INMUEBLE_RENTA" @selected(old('tipo') === 'INMUEBLE_RENTA')>Inmueble para renta</option>
                            <option value="NEGOCIO_PARTICIPACION" @selected(old('tipo') === 'NEGOCIO_PARTICIPACION')>Negocio / Participación</option>
                            <option value="OTRO" @selected(old('tipo') === 'OTRO')>Otro</option>
                        </select>
                    </x-ui.field>

                    <x-ui.field name="entidad" label="Entidad o emisor (Opcional)">
                        <input type="text" name="entidad" id="entidad" maxlength="80" placeholder="Ej: Bancolombia, Tyba, Trii" value="{{ old('entidad') }}" class="w-full rounded-md border-gray-300 shadow-sm" />
                    </x-ui.field>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <x-ui.field name="moneda" label="Moneda*">
                        <select name="moneda" id="moneda" required class="w-full rounded-md border-gray-300 shadow-sm">
                            <option value="COP" @selected(old('moneda', 'COP') === 'COP')>COP (Pesos colombianos)</option>
                            <option value="USD" @selected(old('moneda') === 'USD')>USD (Dólares estadounidenses)</option>
                        </select>
                    </x-ui.field>

                    <x-ui.field name="fecha_apertura" label="Fecha de apertura*">
                        <input type="date" name="fecha_apertura" id="fecha_apertura" max="{{ date('Y-m-d') }}" value="{{ old('fecha_apertura', date('Y-m-d')) }}" required class="w-full rounded-md border-gray-300 shadow-sm" />
                    </x-ui.field>

                    <x-ui.field name="monto_inicial" label="Monto inicial depositado*">
                        <input type="number" step="0.01" min="1" name="monto_inicial" id="monto_inicial" placeholder="Ej: 10000000" value="{{ old('monto_inicial') }}" required class="w-full rounded-md border-gray-300 shadow-sm" />
                    </x-ui.field>

                    <x-ui.field name="nivel_riesgo" label="Nivel de riesgo declarado (Opcional)">
                        <select name="nivel_riesgo" id="nivel_riesgo" class="w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">-- Sin declarar (Opcional) --</option>
                            <option value="BAJO" @selected(in_array(old('nivel_riesgo'), ['BAJO', 'Bajo'], true))>Bajo (Conservador)</option>
                            <option value="MEDIO" @selected(in_array(old('nivel_riesgo'), ['MEDIO', 'Medio'], true))>Medio (Moderado)</option>
                            <option value="ALTO" @selected(in_array(old('nivel_riesgo'), ['ALTO', 'Alto'], true))>Alto (Arriesgado)</option>
                        </select>
                    </x-ui.field>
                </div>

                {{-- CAMPOS ESPECÍFICOS PARA CDT Y BONOS/TES (RN-037.2) --}}
                <div id="seccion-tasa" class="p-4 bg-indigo-50/50 rounded-lg border border-indigo-100 space-y-4" style="display: none;">
                    <div class="flex items-center justify-between border-b pb-2">
                        <h3 class="text-xs font-bold uppercase text-indigo-900 tracking-wider flex items-center gap-1.5">
                            <x-icon name="act-balance" :size="16" class="text-indigo-600" />
                            Parámetros de Tasa Fija y Simulación de Rendimiento (CDT / Bonos)
                        </h3>
                        <span class="text-[11px] font-semibold text-indigo-700 bg-indigo-100 px-2 py-0.5 rounded">Renta Fija</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <x-ui.field name="tasa_ea" label="Tasa pactada (% EA)*">
                            <input type="number" step="0.01" min="0" max="100" name="tasa_ea" id="tasa_ea" placeholder="Ej: 10.50" value="{{ old('tasa_ea') }}" class="w-full rounded-md border-gray-300 shadow-sm font-mono" oninput="actualizarDiagramaFijo()" />
                        </x-ui.field>
                        <x-ui.field name="plazo_dias" label="Plazo en días (1 - 3650)*">
                            <input type="number" min="1" max="3650" name="plazo_dias" id="plazo_dias" placeholder="Ej: 365" value="{{ old('plazo_dias', 365) }}" class="w-full rounded-md border-gray-300 shadow-sm font-mono" oninput="actualizarDiagramaFijo()" />
                        </x-ui.field>
                        <x-ui.field name="periodicidad_intereses" label="Periodicidad intereses*">
                            <select name="periodicidad_intereses" id="periodicidad_intereses" class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="AL_VENCIMIENTO">Al vencimiento</option>
                                <option value="MENSUAL">Mensual</option>
                                <option value="TRIMESTRAL">Trimestral</option>
                                <option value="SEMESTRAL">Semestral</option>
                                <option value="ANUAL">Anual</option>
                            </select>
                        </x-ui.field>
                        <x-ui.field name="fecha_vencimiento" label="Fecha vencimiento (calculada)">
                            <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" value="{{ old('fecha_vencimiento') }}" class="w-full rounded-md border-gray-300 shadow-sm" />
                        </x-ui.field>
                    </div>

                    {{-- DIAGRAMA INTERACTIVO DE GANANCIA PACTADA EN TIEMPO REAL --}}
                    <div id="diagrama-renta-fija" class="bg-white p-4 rounded-lg border border-indigo-200 shadow-sm space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-800 uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Diagrama de Ganancia Estimada al Vencimiento
                            </span>
                            <span class="text-[11px] text-gray-500">Edita el dinero, plazo o tasa para recalcular en vivo</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 bg-gray-50 p-3 rounded-lg text-center">
                            <div class="border-r border-gray-200">
                                <span class="text-[10px] uppercase font-bold text-gray-500">Capital Base</span>
                                <p id="preview-capital-fijo" class="text-sm font-black text-gray-900 font-mono">$ 0</p>
                            </div>
                            <div class="border-r border-gray-200">
                                <span class="text-[10px] uppercase font-bold text-gray-500">Tasa Mensual Eq.</span>
                                <p id="preview-tasa-mes-fijo" class="text-sm font-bold text-indigo-700 font-mono">0.00% E.M.</p>
                            </div>
                            <div class="border-r border-gray-200">
                                <span class="text-[10px] uppercase font-bold text-emerald-700">Rendimiento Ganado</span>
                                <p id="preview-rendimiento-fijo" class="text-base font-black text-emerald-600 font-mono">+$ 0</p>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-gray-700">Total al Vencimiento</span>
                                <p id="preview-total-fijo" class="text-base font-black text-gray-900 font-mono">$ 0</p>
                            </div>
                        </div>

                        {{-- BARRAS DE PROGRESIÓN VISUAL DEL DIAGRAMA --}}
                        <div class="space-y-1.5 pt-1">
                            <div class="flex justify-between text-xs text-gray-600">
                                <span>Composición del monto final:</span>
                                <span id="preview-porcentaje-ganancia" class="font-bold text-emerald-700">+0.00% de rentabilidad total</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-4 flex overflow-hidden border">
                                <div id="barra-capital-fijo" class="bg-indigo-600 h-4 text-[10px] text-white flex items-center justify-center font-bold" style="width: 80%">Capital</div>
                                <div id="barra-ganancia-fijo" class="bg-emerald-500 h-4 text-[10px] text-white flex items-center justify-center font-bold" style="width: 20%">Ganancia</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CAMPOS ESPECÍFICOS PARA ACCIONES, ETF Y CRIPTOACTIVOS (RN-037.3) --}}
                <div id="seccion-unidades" class="p-4 bg-purple-50/50 rounded-lg border border-purple-100 space-y-4" style="display: none;">
                    <div class="flex items-center justify-between border-b pb-2">
                        <h3 class="text-xs font-bold uppercase text-purple-900 tracking-wider flex items-center gap-1.5">
                            <x-icon name="src-rendimientos-financieros" :size="16" class="text-purple-600" />
                            Parámetros de Renta Variable y Meta de Rentabilidad (Acciones / ETF / Cripto)
                        </h3>
                        <span class="text-[11px] font-semibold text-purple-700 bg-purple-100 px-2 py-0.5 rounded">Renta Variable</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-ui.field name="simbolo" label="Símbolo / Ticker (Opcional)">
                            <input type="text" maxlength="15" name="simbolo" id="simbolo" placeholder="Ej: NU, ECOPETROL, BTC, VOO" value="{{ old('simbolo') }}" class="w-full rounded-md border-gray-300 shadow-sm uppercase font-mono" />
                        </x-ui.field>
                        <x-ui.field name="unidades" label="Cantidad de unidades* (hasta 8 decimales)">
                            <input type="number" step="0.00000001" min="0.00000001" name="unidades" id="unidades" placeholder="Ej: 10.0" value="{{ old('unidades') }}" class="w-full rounded-md border-gray-300 shadow-sm font-mono" oninput="actualizarDiagramaVariable()" />
                        </x-ui.field>
                        <x-ui.field name="precio_unitario_compra" label="Precio unitario de compra*">
                            <input type="number" step="0.00000001" min="0.00000001" name="precio_unitario_compra" id="precio_unitario_compra" placeholder="Ej: 10.00" value="{{ old('precio_unitario_compra') }}" class="w-full rounded-md border-gray-300 shadow-sm font-mono" oninput="actualizarDiagramaVariable()" />
                        </x-ui.field>
                    </div>

                    {{-- METAS Y OBJETIVOS DE RENTABILIDAD ESPERADA --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                        <x-ui.field name="rentabilidad_esperada_porc" label="Rentabilidad esperada meta (% deseado)">
                            <input type="number" step="0.01" min="-100" max="10000" name="rentabilidad_esperada_porc" id="rentabilidad_esperada_porc" placeholder="Ej: 15.00 (esperas ganar 15%)" value="{{ old('rentabilidad_esperada_porc', 15) }}" class="w-full rounded-md border-gray-300 shadow-sm font-mono" oninput="actualizarDiagramaVariable()" />
                        </x-ui.field>
                        <x-ui.field name="plazo_meses_esperado" label="Plazo estimado de la meta (meses)">
                            <input type="number" min="1" max="360" name="plazo_meses_esperado" id="plazo_meses_esperado" placeholder="Ej: 3 (meses)" value="{{ old('plazo_meses_esperado', 3) }}" class="w-full rounded-md border-gray-300 shadow-sm font-mono" oninput="actualizarDiagramaVariable()" />
                        </x-ui.field>
                    </div>

                    {{-- DIAGRAMA INTERACTIVO DE META PROYECTADA EN TIEMPO REAL --}}
                    <div id="diagrama-renta-variable" class="bg-white p-4 rounded-lg border border-purple-200 shadow-sm space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-800 uppercase tracking-wider flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                                Diagrama de Meta y Proyección de Renta Variable
                            </span>
                            <span class="text-[11px] text-gray-500">Muestra el precio objetivo y la ganancia esperada</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 bg-gray-50 p-3 rounded-lg text-center">
                            <div class="border-r border-gray-200">
                                <span class="text-[10px] uppercase font-bold text-gray-500">Precio Compra</span>
                                <p id="preview-precio-compra" class="text-sm font-bold text-gray-900 font-mono">$ 0.00</p>
                            </div>
                            <div class="border-r border-gray-200">
                                <span class="text-[10px] uppercase font-bold text-purple-700">Precio Objetivo Meta</span>
                                <p id="preview-precio-meta" class="text-sm font-black text-purple-800 font-mono">$ 0.00</p>
                            </div>
                            <div class="border-r border-gray-200">
                                <span class="text-[10px] uppercase font-bold text-gray-500">Plazo Definido</span>
                                <p id="preview-plazo-variable" class="text-sm font-bold text-gray-700">3 meses</p>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-emerald-700">Ganancia Proyectada</span>
                                <p id="preview-ganancia-variable" class="text-base font-black text-emerald-600 font-mono">+$ 0.00 (+15%)</p>
                            </div>
                        </div>

                        <div class="space-y-1 pt-1">
                            <div class="flex justify-between text-xs text-gray-600">
                                <span>Trayectoria esperada de la cotización:</span>
                                <span id="preview-resumen-meta" class="font-bold text-purple-800">Objetivo: $0.00</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-3 flex overflow-hidden border">
                                <div class="bg-gray-400 h-3" style="width: 50%" title="Precio de compra inicial"></div>
                                <div class="bg-purple-600 h-3" style="width: 50%" title="Crecimiento esperado"></div>
                            </div>
                            <div class="flex justify-between text-[10px] text-gray-400 pt-0.5">
                                <span>Compra: <strong id="lbl-min-variable">$0</strong></span>
                                <span>Meta: <strong id="lbl-max-variable" class="text-purple-700">$0</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <x-ui.button type="submit">
                        <x-icon name="act-confirmar" :size="18" />
                        Guardar inversión y registrar aporte inicial (RN-037.4)
                    </x-ui.button>
                </div>
            </form>
        </details>
    </x-ui.card>

    {{-- LISTADO DE INVERSIONES ACTIVAS Y TOTALES --}}
    <x-ui.card title="Mis Inversiones Registradas">
        @if(count($inversionesPresentadas) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm border border-gray-200 rounded-lg">
                    <thead class="bg-gray-100 text-gray-700 uppercase text-xs font-semibold">
                        <tr>
                            <th class="p-3">Nombre</th>
                            <th class="p-3">Tipo</th>
                            <th class="p-3">Moneda</th>
                            <th class="p-3">Aporte Inicial</th>
                            <th class="p-3">Valor Actual Mercado</th>
                            <th class="p-3">Última Valoración</th>
                            <th class="p-3">Estado</th>
                            <th class="p-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($inversionesPresentadas as $inv)
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-bold text-gray-900">
                                    <a href="{{ route('inversiones.show', $inv['id']) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline">
                                        {{ $inv['nombre'] }}
                                    </a>
                                    @if(!empty($inv['simbolo']))
                                        <span class="ms-1 px-1.5 py-0.5 text-[10px] font-mono bg-gray-100 rounded text-gray-600">{{ $inv['simbolo'] }}</span>
                                    @endif
                                </td>
                                <td class="p-3 text-gray-600">{{ str_replace('_', ' ', $inv['tipo']) }}</td>
                                <td class="p-3 font-semibold text-gray-700">{{ $inv['moneda'] }}</td>
                                <td class="p-3 font-medium text-gray-700">$ {{ number_format((float) $inv['monto_inicial'], 2, ',', '.') }}</td>
                                <td class="p-3 font-black text-emerald-600 text-sm">$ {{ number_format((float) $inv['valor_actual'], 2, ',', '.') }}</td>
                                <td class="p-3 text-xs text-gray-500">
                                    {{ $inv['fecha_valor_actual'] ?? 'Sin valoración' }}
                                    @if(!empty($inv['valoracion_desactualizada']))
                                        <span class="block text-amber-600 font-bold text-[10px]">⚠️ Desactualizada (>35d)</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    @switch($inv['estado'])
                                        @case('ACTIVA')
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-green-100 text-green-800">Activa</span>
                                            @break
                                        @case('VENCIDA')
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-800">Vencida</span>
                                            @break
                                        @case('LIQUIDADA')
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-gray-100 text-gray-800">Liquidada</span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="p-3 text-right">
                                    <a href="{{ route('inversiones.show', $inv['id']) }}" class="px-3 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs rounded shadow-sm inline-flex items-center gap-1">
                                        Detalle y ROI
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pt-4">
                {{ $inversiones->links() }}
            </div>
        @else
            <x-ui.empty-state message="No has registrado inversiones aún. Haz clic en 'Registrar nueva inversión' para añadir tu primer CDT, cuenta de ahorro o acciones." />
        @endif
    </x-ui.card>
</div>

<script>
function actualizarCamposTipo(tipo) {
    const conTasa = ['CDT', 'BONOS_TES', 'Bonos/TES'];
    const conUnidades = ['ACCIONES', 'Acciones', 'ETF', 'CRIPTOACTIVO', 'Criptoactivo'];

    const secTasa = document.getElementById('seccion-tasa');
    const secUnidades = document.getElementById('seccion-unidades');

    if (conTasa.includes(tipo)) {
        secTasa.style.display = 'block';
        secUnidades.style.display = 'none';
        actualizarDiagramaFijo();
    } else if (conUnidades.includes(tipo)) {
        secTasa.style.display = 'none';
        secUnidades.style.display = 'block';
        actualizarDiagramaVariable();
    } else {
        secTasa.style.display = 'none';
        secUnidades.style.display = 'none';
    }
}

// DIAGRAMA INTERACTIVO DE RENTA FIJA (CDT / BONOS)
function actualizarDiagramaFijo() {
    const montoEl = document.getElementById('monto_inicial');
    const tasaEl = document.getElementById('tasa_ea');
    const plazoEl = document.getElementById('plazo_dias');
    const aperturaEl = document.getElementById('fecha_apertura');
    const vencimientoEl = document.getElementById('fecha_vencimiento');

    const capital = parseFloat(montoEl?.value) || 0;
    const tasaEa = parseFloat(tasaEl?.value) || 0;
    const dias = parseInt(plazoEl?.value) || 365;

    // Calcular fecha de vencimiento estimada
    if (aperturaEl && aperturaEl.value && vencimientoEl) {
        const fecha = new Date(aperturaEl.value);
        if (!isNaN(fecha.getTime())) {
            fecha.setDate(fecha.getDate() + dias);
            vencimientoEl.value = fecha.toISOString().split('T')[0];
        }
    }

    // Fórmula compuesta exacta: Rendimiento = Capital * ((1 + EA)^(días/365) - 1)
    const factor = Math.pow(1 + (tasaEa / 100), dias / 365);
    const rendimiento = capital > 0 && tasaEa > 0 ? capital * (factor - 1) : 0;
    const total = capital + rendimiento;
    const tasaMesEq = (Math.pow(1 + (tasaEa / 100), 1 / 12) - 1) * 100;

    const formato = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });

    document.getElementById('preview-capital-fijo').textContent = formato.format(capital);
    document.getElementById('preview-tasa-mes-fijo').textContent = tasaMesEq.toFixed(2) + '% E.M.';
    document.getElementById('preview-rendimiento-fijo').textContent = '+' + formato.format(rendimiento);
    document.getElementById('preview-total-fijo').textContent = formato.format(total);

    const rentabilidadPct = capital > 0 ? ((rendimiento / capital) * 100).toFixed(2) : '0.00';
    document.getElementById('preview-porcentaje-ganancia').textContent = '+' + rentabilidadPct + '% rentabilidad al vencimiento';

    if (total > 0) {
        const pctCap = Math.max(10, Math.min(95, (capital / total) * 100));
        const pctGan = 100 - pctCap;
        document.getElementById('barra-capital-fijo').style.width = pctCap + '%';
        document.getElementById('barra-ganancia-fijo').style.width = pctGan + '%';
    }
}

// DIAGRAMA INTERACTIVO DE RENTA VARIABLE (ACCIONES, ETF, CRIPTO)
function actualizarDiagramaVariable() {
    const unidadesEl = document.getElementById('unidades');
    const precioCompraEl = document.getElementById('precio_unitario_compra');
    const metaPctEl = document.getElementById('rentabilidad_esperada_porc');
    const plazoMesesEl = document.getElementById('plazo_meses_esperado');
    const montoInicialEl = document.getElementById('monto_inicial');

    const unidades = parseFloat(unidadesEl?.value) || 0;
    const precioCompra = parseFloat(precioCompraEl?.value) || 0;
    const metaPct = parseFloat(metaPctEl?.value) || 0;
    const meses = parseInt(plazoMesesEl?.value) || 3;

    // Calcular monto inicial automático si no lo ha ingresado o lo cambia
    const costoTotal = unidades * precioCompra;
    if (costoTotal > 0 && montoInicialEl && (!montoInicialEl.value || parseFloat(montoInicialEl.value) === 0)) {
        montoInicialEl.value = costoTotal.toFixed(2);
    }

    const precioMeta = precioCompra * (1 + (metaPct / 100));
    const gananciaTotal = costoTotal * (metaPct / 100);

    const formatoMoneda = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 4 });

    document.getElementById('preview-precio-compra').textContent = '$ ' + formatoMoneda.format(precioCompra);
    document.getElementById('preview-precio-meta').textContent = '$ ' + formatoMoneda.format(precioMeta);
    document.getElementById('preview-plazo-variable').textContent = meses + (meses === 1 ? ' mes' : ' meses');
    document.getElementById('preview-ganancia-variable').textContent = (gananciaTotal >= 0 ? '+' : '') + '$ ' + formatoMoneda.format(gananciaTotal) + ' (' + (metaPct >= 0 ? '+' : '') + metaPct.toFixed(2) + '%)';
    document.getElementById('preview-resumen-meta').textContent = 'Objetivo: $ ' + formatoMoneda.format(precioMeta) + ' en ' + meses + ' meses';

    document.getElementById('lbl-min-variable').textContent = '$ ' + formatoMoneda.format(precioCompra);
    document.getElementById('lbl-max-variable').textContent = '$ ' + formatoMoneda.format(precioMeta);
}

document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('tipo_inversion');
    if (select) actualizarCamposTipo(select.value);

    // Conectar eventos de cambio en el monto inicial
    const montoEl = document.getElementById('monto_inicial');
    if (montoEl) {
        montoEl.addEventListener('input', () => {
            actualizarDiagramaFijo();
            actualizarDiagramaVariable();
        });
    }
});
</script>
@endsection

