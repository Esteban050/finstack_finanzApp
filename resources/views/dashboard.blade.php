<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Resumen · FinanzApp
        </h2>
    </x-slot>

    <section data-dashboard data-income-url="{{ route('ingresos.index') }}" data-expense-url="{{ route('gastos.index') }}" aria-label="Resumen financiero del periodo">
        <form data-dashboard-period class="income-two-fields">
            <x-ui.field name="periodo" label="Periodo"><select name="periodo" id="periodo"><option value="mes-actual">Mes actual</option><option value="mes-anterior">Mes anterior</option><option value="trimestre">Trimestre</option><option value="ano">Año en curso</option><option value="personalizado">Rango personalizado</option></select></x-ui.field>
            <div class="income-two-fields"><x-ui.field name="fecha_desde" label="Desde"><input id="fecha_desde" name="fecha_desde" type="date" required></x-ui.field><x-ui.field name="fecha_hasta" label="Hasta"><input id="fecha_hasta" name="fecha_hasta" type="date" required></x-ui.field></div>
            <x-ui.button type="submit">Consultar periodo</x-ui.button>
        </form>
        <p class="ui-help">Solo movimientos confirmados del periodo. Los pendientes no se incluyen.</p>
        <div data-dashboard-loading><x-ui.skeleton /></div>
        <p data-dashboard-status class="ui-help" role="status" aria-live="polite" hidden></p>
        <div data-dashboard-results class="income-source-list" hidden>
            <x-ui.card title="Balance del periodo"><div class="income-net"><output data-dashboard-money="balance">—</output><p data-dashboard-balance-text class="ui-help"></p></div></x-ui.card>
            <div class="income-two-fields">
                <x-ui.card title="Ingresos brutos"><output data-dashboard-money="bruto">—</output></x-ui.card>
                <x-ui.card title="Retenciones"><output data-dashboard-money="retencion">—</output></x-ui.card>
                <x-ui.card title="Aportes"><output data-dashboard-money="aportes">—</output></x-ui.card>
                <x-ui.card title="Ingresos netos"><output data-dashboard-money="neto">—</output></x-ui.card>
                <x-ui.card title="Gastos"><output data-dashboard-money="gastos">—</output></x-ui.card>
            </div>
        </div>
    </section>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- CA-008.1: Advertencia si no ha completado el perfil de ocupación -->
            @if (!$perfil || empty($perfil->ocupaciones))
                <div class="p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-900 rounded-r-lg shadow-sm flex items-center justify-between">
                    <div>
                        <p class="font-bold text-sm">Completa tu perfil financiero para usar este módulo</p>
                        <p class="text-xs text-amber-700 mt-0.5">Para habilitar el cálculo de seguridad social y renta, debes registrar tu ocupación y datos tributarios.</p>
                    </div>
                    <a href="{{ route('profile.show') }}" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-md shadow whitespace-nowrap ms-4">
                        Completar Perfil
                    </a>
                </div>
            @endif

            <!-- Tarjeta de Bienvenida y Estado -->
            <div class="bg-white overflow-hidden shadow sm:rounded-xl p-6">
                <div class="flex items-center justify-between border-b pb-4 mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">¡Hola, {{ $usuario->nombres }}!</h3>
                        <p class="text-xs text-gray-500">Bienvenido a tu panel de finanzas y seguridad.</p>
                    </div>
                    <span class="px-3 py-1 text-xs font-bold bg-green-100 text-green-800 rounded-full">
                        Cuenta {{ $usuario->estado }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2">
                    <div class="p-4 rounded-lg bg-gray-50 border border-gray-100">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Identificación</span>
                        <p class="text-base font-bold text-gray-900 mt-1">{{ $usuario->tipo_documento }} {{ $usuario->numero_documento }}</p>
                        <p class="text-xs text-gray-500">{{ $usuario->correo }}</p>
                    </div>

                    <div class="p-4 rounded-lg bg-gray-50 border border-gray-100">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Perfil Tributario</span>
                        @if ($perfil)
                            <p class="text-sm font-bold text-indigo-700 mt-1">
                                {{ implode(', ', $perfil->ocupaciones ?? []) }}
                            </p>
                            <p class="text-xs text-gray-500">
                                Responsable IVA: {{ $perfil->responsable_iva ? 'Sí (Declara Renta)' : 'No' }}
                            </p>
                        @else
                            <p class="text-xs text-amber-600 font-semibold mt-2">Pendiente por diligenciar</p>
                        @endif
                    </div>

                    <div class="p-4 rounded-lg bg-gray-50 border border-gray-100">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Seguridad (2FA)</span>
                        <p class="text-base font-bold text-gray-900 mt-1">
                            {{ $usuario->mfa_activo ? '✓ 2FA Activado' : '✕ 2FA Desactivado' }}
                        </p>
                        <a href="{{ route('profile.show') }}" class="text-xs text-indigo-600 font-semibold hover:underline">Configurar seguridad &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- Acciones Principales: Ingresos y Gastos -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <a href="{{ route('ingresos.create') }}" class="p-6 bg-gradient-to-br from-emerald-50 to-green-50 rounded-xl shadow-sm border border-emerald-200 hover:shadow-md transition flex items-center justify-between group">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-extrabold text-base shadow-sm">+</span>
                            <h4 class="font-bold text-gray-900 group-hover:text-emerald-700 text-lg">Registrar Ingreso</h4>
                        </div>
                        <p class="text-xs text-gray-600">Salarios, honorarios, arriendos o dividendos con clasificación tributaria (Art. 330 ET).</p>
                    </div>
                    <span class="text-emerald-600 font-bold text-sm ml-4 group-hover:translate-x-1 transition-transform">&rarr;</span>
                </a>

                <a href="{{ route('gastos.create') }}" class="p-6 bg-gradient-to-br from-rose-50 to-red-50 rounded-xl shadow-sm border border-rose-200 hover:shadow-md transition flex items-center justify-between group">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-lg bg-rose-600 text-white flex items-center justify-center font-extrabold text-base shadow-sm">−</span>
                            <h4 class="font-bold text-gray-900 group-hover:text-rose-700 text-lg">Registrar Gasto</h4>
                        </div>
                        <p class="text-xs text-gray-600">Gastos fijos, variables o deducibles con categoría e IVA/INC desglosado.</p>
                    </div>
                    <span class="text-rose-600 font-bold text-sm ml-4 group-hover:translate-x-1 transition-transform">&rarr;</span>
                </a>
            </div>

            <!-- Accesos Rápidos Complementarios -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <a href="{{ route('profile.show') }}" class="p-5 bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition">
                    <h4 class="font-bold text-gray-900 mb-1 text-sm">Mi Perfil Financiero</h4>
                    <p class="text-xs text-gray-500">Actualiza tus ocupaciones, ARL, dependientes y datos de contacto.</p>
                </a>

                <a href="#movimientos" class="p-5 bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition">
                    <h4 class="font-bold text-gray-900 mb-1 text-sm">Historial de movimientos</h4>
                    <p class="text-sm text-gray-500">Consulta tus ingresos y gastos en un solo lugar.</p>
                </a>
            </div>
            <section id="movimientos" data-movements-history class="ui-card" aria-labelledby="movements-title">
                <h2 id="movements-title">Historial de movimientos</h2>
                <div role="tablist" aria-label="Tipo de movimiento" class="ui-inline">
                    <button type="button" role="tab" id="history-income" aria-controls="history-panel" aria-selected="true" class="ui-button ui-button--secondary" data-history-url="{{ route('ingresos.index', ['panel' => 'movimientos']) }}">Ingresos</button>
                    <button type="button" role="tab" id="history-expenses" aria-controls="history-panel" aria-selected="false" tabindex="-1" class="ui-button ui-button--secondary" data-history-url="{{ route('gastos.index', ['panel' => 'movimientos']) }}">Gastos</button>
                </div>
                <p data-history-loading role="status">Cargando movimientos…</p>
                <div id="history-panel" role="tabpanel" aria-labelledby="history-income">
                    <iframe data-movements-frame title="Historial de ingresos" src="{{ route('ingresos.index', ['panel' => 'movimientos']) }}" class="ui-history-frame"></iframe>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
