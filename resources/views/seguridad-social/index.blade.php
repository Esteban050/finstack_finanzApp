@extends('layouts.app')
@section('title', 'Seguridad Social · Vencimientos y Alertas')
@section('content')
<div class="space-y-6">
    <header class="income-page-header">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Seguridad Social</h1>
            <p class="text-sm text-gray-600">Calendario de pagos PILA, liquidación de aportes y control de obligaciones laborales.</p>
        </div>
        <div class="ui-inline">
            <x-ui.button :href="route('seguridad-social.liquidaciones.index')" variant="secondary">
                <x-icon name="cat-seguridad-social" :size="18" />
                Liquidar independiente
            </x-ui.button>
            <x-ui.button :href="route('seguridad-social.pagos.index')">
                <x-icon name="act-gasto" :size="18" />
                Registrar pago PILA
            </x-ui.button>
        </div>
    </header>

    @if(session('status'))
        <div class="p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm flex items-center gap-2">
            <x-icon name="ui-exito" :size="20" class="text-green-600" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    {{-- BANNER DE DETECCIÓN DE FUENTES DE INGRESO --}}
    @if($esDobleObligacion)
        <div class="p-5 rounded-xl border-2 border-indigo-200 bg-indigo-50/80 shadow-sm space-y-3">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-indigo-600 text-white rounded-lg shadow-sm">
                    <x-icon name="ui-info" :size="24" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-indigo-950">Doble obligación de seguridad social detectada</h2>
                    <p class="text-sm text-indigo-900 mt-1">
                        Detectamos que cuentas con contratos o fuentes activas de <strong>Asalariado</strong> y simultáneamente de <strong>Contratista por prestación de servicios / Honorarios</strong>.
                    </p>
                    <div class="mt-3 p-3 bg-white/90 rounded-lg border border-indigo-100 text-sm text-gray-800 space-y-2">
                        <p class="font-semibold text-indigo-900">En Colombia debes cotizar a seguridad social por separado (2 veces):</p>
                        <ul class="list-disc list-inside space-y-1 text-xs sm:text-sm text-gray-700">
                            <li><strong>Como Asalariado:</strong> Tu empleador realiza la retención de aportes (4% salud, 4% pensión, FSP si aplica) directamente en tu desprendible de nómina.</li>
                            <li><strong>Como Contratista / Independiente:</strong> Debes liquidar y pagar por tu cuenta a través de planilla PILA sobre el 40% del ingreso bruto de tus honorarios o prestación de servicios (mínimo 1 SMMLV).</li>
                        </ul>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <x-ui.button :href="route('seguridad-social.asalariado.index')" variant="secondary">
                            Calcular aportes de nómina (Asalariado)
                        </x-ui.button>
                        <x-ui.button :href="route('seguridad-social.liquidaciones.index')">
                            Liquidar planilla PILA (Prestación de servicios)
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    @elseif($esVoluntario)
        <div class="p-4 rounded-xl border border-sky-200 bg-sky-50 text-sky-950 flex items-start gap-3">
            <div class="p-2 bg-sky-600 text-white rounded-lg">
                <x-icon name="ui-ayuda" :size="20" />
            </div>
            <div class="space-y-1">
                <h2 class="font-bold text-base text-sky-900">Cotización voluntaria disponible</h2>
                <p class="text-xs sm:text-sm text-sky-800">
                    No detectamos fuentes con obligación de cotizar como independiente (o tus ingresos son inferiores a 1 SMMLV).
                    La normativa colombiana indica que con ingresos menores a 1 SMMLV no existe obligación legal, pero puedes realizar aportes voluntarios para salud y pensión calculados sobre la base mínima de 1 SMMLV ($ {{ number_format($parametro->valor_smmlv ?? 1423500, 0, ',', '.') }}).
                </p>
                <div class="pt-2">
                    <x-ui.button :href="route('seguridad-social.liquidaciones.index')" variant="secondary" class="text-xs">
                        Simular cotización voluntaria
                    </x-ui.button>
                </div>
            </div>
        </div>
    @else
        <div class="p-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-950 flex items-start gap-3">
            <div class="p-2 bg-emerald-600 text-white rounded-lg">
                <x-icon name="act-confirmar" :size="20" />
            </div>
            <div>
                <h2 class="font-bold text-base text-emerald-900">Obligación como Independiente / Contratista</h2>
                <p class="text-xs sm:text-sm text-emerald-800">
                    Detectamos fuentes activas de rentas de trabajo no asalariadas. Debes liquidar y pagar mensualmente tu seguridad social sobre el 40% del ingreso bruto.
                </p>
            </div>
        </div>
    @endif

    {{-- SECCIÓN DE CALENDARIO TRIBUTARIO Y FECHAS LÍMITE --}}
    <x-ui.card title="Fechas Límite de Pago PILA (Calendario Tributario)">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <div class="space-y-1">
                    <span class="text-xs font-semibold uppercase text-gray-500 tracking-wider">Documento de identidad</span>
                    <p class="text-lg font-bold text-gray-900">
                        {{ $usuario->tipo_documento }} {{ $usuario->numero_documento }}
                    </p>
                    <p class="text-xs text-gray-600">
                        Dígitos aplicables para el calendario: <strong class="text-indigo-600">{{ $digitos ?? 'Sin registrar' }}</strong>
                    </p>
                </div>
                @if($calendario)
                    <div class="text-right space-y-1">
                        <span class="text-xs font-semibold uppercase text-gray-500 tracking-wider">Plazo límite de pago mensual</span>
                        <p class="text-2xl font-black text-indigo-700">
                            {{ $calendario->fecha_vencimiento->day }}° Día Hábil
                        </p>
                        <p class="text-xs text-gray-500">Rango de dígitos: {{ $calendario->digito_desde }} a {{ $calendario->digito_hasta }} (Decreto 1990/2016)</p>
                    </div>
                @else
                    <div class="text-amber-800 text-xs bg-amber-50 p-2 rounded border border-amber-200">
                        @if(!$hayCalendario)
                            No hay calendario de aportes configurado para la vigencia actual. Contacte al administrador.
                        @else
                            No se encontró fecha en el calendario para los dígitos {{ $digitos }}.
                        @endif
                    </div>
                @endif
            </div>

            {{-- ALERTAS ACTIVAS --}}
            @if(count($alertas) > 0)
                <div class="space-y-2">
                    <h3 class="text-sm font-bold text-gray-800">Alertas de vencimiento activas (Mes actual):</h3>
                    @foreach($alertas as $alerta)
                        <div class="p-3 rounded-lg border flex items-center justify-between gap-3 {{ $alerta['estado'] === 'VENCIDO' ? 'bg-red-50 border-red-300 text-red-900' : 'bg-amber-50 border-amber-300 text-amber-900' }}">
                            <div class="flex items-center gap-2">
                                <x-icon name="{{ $alerta['estado'] === 'VENCIDO' ? 'ui-error' : 'ui-advertencia' }}" :size="20" class="{{ $alerta['estado'] === 'VENCIDO' ? 'text-red-600' : 'text-amber-600' }}" />
                                <div>
                                    <p class="font-bold text-sm">
                                        Periodo {{ $alerta['periodo'] }} · Fecha límite: {{ $alerta['fecha_limite'] }}
                                        <span class="ms-2 px-2 py-0.5 text-xs font-bold rounded-full {{ $alerta['estado'] === 'VENCIDO' ? 'bg-red-200 text-red-900' : 'bg-amber-200 text-amber-900' }}">
                                            {{ $alerta['estado'] === 'VENCIDO' ? 'VENCIDO' : 'POR VENCER' }}
                                        </span>
                                    </p>
                                    @if(!empty($alerta['advertencia']))
                                        <p class="text-xs font-medium text-red-700 mt-0.5">{{ $alerta['advertencia'] }}</p>
                                    @endif
                                </div>
                            </div>
                            <x-ui.button :href="route('seguridad-social.pagos.index')" class="text-xs">
                                Pagar ahora
                            </x-ui.button>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- TABLA DE PERIODOS ALREDEDOR DE HOY --}}
            @if(count($periodos) > 0)
                <div class="overflow-x-auto pt-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Historial y estado de periodos:</h4>
                    <table class="w-full text-left text-xs sm:text-sm text-gray-700 border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-gray-100 text-gray-800 uppercase text-xs font-semibold">
                            <tr>
                                <th class="p-3">Periodo Cotizado</th>
                                <th class="p-3">Fecha Límite de Pago</th>
                                <th class="p-3">Estado</th>
                                <th class="p-3 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($periodos as $p)
                                <tr class="hover:bg-gray-50 {{ $p['periodo'] === ($periodoActual ?? '') ? 'bg-indigo-50/40' : '' }}">
                                    <td class="p-3 font-semibold text-gray-900">
                                        {{ $p['periodo'] }}
                                        @if($p['periodo'] === ($periodoActual ?? ''))
                                            <span class="ms-1.5 px-2 py-0.5 text-[10px] font-bold bg-indigo-100 text-indigo-700 rounded-full">Mes actual</span>
                                        @endif
                                    </td>
                                    <td class="p-3">{{ $p['fecha_limite'] ?? 'No programada' }}</td>
                                    <td class="p-3">
                                        @switch($p['estado'])
                                            @case('PAGADO')
                                                <span class="px-2 py-1 text-xs font-bold bg-green-100 text-green-800 rounded-full">Pagado</span>
                                                @break
                                            @case('POR_VENCER')
                                                <span class="px-2 py-1 text-xs font-bold bg-amber-100 text-amber-800 rounded-full">Por vencer</span>
                                                @break
                                            @case('VENCIDO')
                                                <span class="px-2 py-1 text-xs font-bold bg-red-100 text-red-800 rounded-full">Vencido</span>
                                                @break
                                            @default
                                                <span class="px-2 py-1 text-xs font-bold bg-gray-100 text-gray-800 rounded-full">Pendiente</span>
                                        @endswitch
                                    </td>
                                    <td class="p-3 text-right">
                                        @if($p['estado'] !== 'PAGADO')
                                            <a href="{{ route('seguridad-social.pagos.index') }}" class="text-indigo-600 hover:text-indigo-800 font-semibold text-xs">
                                                Registrar pago
                                            </a>
                                        @else
                                            <span class="text-xs text-gray-400">Al día</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-ui.card>

    {{-- TARJETAS DE INDICADORES Y RESUMEN ANUAL --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-ui.card title="Liquidaciones Estimadas">
            <div class="space-y-2">
                <p class="text-3xl font-extrabold text-gray-900">{{ $totalLiquidaciones }}</p>
                <p class="text-xs text-gray-500">Liquidaciones guardadas en el historial.</p>
                <div class="pt-2">
                    <a href="{{ route('seguridad-social.liquidaciones.index') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                        Gestionar liquidaciones →
                    </a>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Pagos Registrados (PILA)">
            <div class="space-y-2">
                <p class="text-3xl font-extrabold text-gray-900">{{ $totalPagos }}</p>
                <p class="text-xs text-gray-500">Cada pago registrado genera su egreso automático en Gastos.</p>
                <div class="pt-2">
                    <a href="{{ route('seguridad-social.pagos.index') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                        Ver histórico de pagos →
                    </a>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Aportes Deducibles de Renta (Año en Curso)">
            <div class="space-y-1">
                <span class="text-xs text-gray-500">Ingreso no constitutivo de renta acumulado:</span>
                <p class="text-2xl font-black text-emerald-700">
                    $ {{ number_format((float) ($resumenAnual['ingreso_no_constitutivo'] ?? 0), 0, ',', '.') }}
                </p>
                <p class="text-xs text-gray-500">
                    Salud: $ {{ number_format((float) ($resumenAnual['salud'] ?? 0), 0, ',', '.') }} ·
                    Pensión: $ {{ number_format((float) ($resumenAnual['pension'] ?? 0), 0, ',', '.') }}
                </p>
            </div>
        </x-ui.card>
    </div>

    {{-- AVISO LEGAL OBLIGATORIO RNG-08 --}}
    <p class="ui-help text-center text-xs text-gray-500">
        {{ App\Services\SeguridadSocialService::AVISO_LEGAL }}
    </p>
</div>
@endsection

