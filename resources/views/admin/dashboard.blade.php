<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Panel de Administración — Parámetros y Configuración
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Grid de Módulos de Administración -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Parámetros Normativos -->
                <div class="bg-white p-6 rounded-xl shadow border border-gray-100 hover:shadow-lg transition">
                    <div class="flex items-center space-x-3 text-indigo-600 mb-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <h3 class="font-bold text-lg text-gray-900">Parámetros Normativos</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        Gestión por vigencia de UVT, SMMLV, tarifas de salud, pensión, topes de renta y GMF.
                    </p>
                    <a href="{{ route('admin.parametros.index') }}" class="inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-900">
                        Gestionar Vigencias &rarr;
                    </a>
                </div>

                <!-- Catálogo de Categorías -->
                <div class="bg-white p-6 rounded-xl shadow border border-gray-100 hover:shadow-lg transition">
                    <div class="flex items-center space-x-3 text-emerald-600 mb-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <h3 class="font-bold text-lg text-gray-900">Catálogo de Categorías</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        Catálogo global de ingresos y gastos predeterminados asignados a los usuarios.
                    </p>
                    <a href="{{ route('admin.categorias.index') }}" class="inline-flex items-center text-sm font-semibold text-emerald-600 hover:text-emerald-900">
                        Gestionar Categorías &rarr;
                    </a>
                </div>

                <!-- Calendario Tributario -->
                <div class="bg-white p-6 rounded-xl shadow border border-gray-100 hover:shadow-lg transition">
                    <div class="flex items-center space-x-3 text-amber-600 mb-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <h3 class="font-bold text-lg text-gray-900">Calendario Tributario</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        Fechas de vencimiento por dígitos de NIT y carga masiva mediante archivos CSV.
                    </p>
                    <a href="{{ route('admin.calendario.index') }}" class="inline-flex items-center text-sm font-semibold text-amber-600 hover:text-amber-900">
                        Gestionar Calendario &rarr;
                    </a>
                </div>

                <!-- Gestión de Usuarios -->
                <div class="bg-white p-6 rounded-xl shadow border border-gray-100 hover:shadow-lg transition">
                    <div class="flex items-center space-x-3 text-rose-600 mb-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <h3 class="font-bold text-lg text-gray-900">Cuentas de Usuario</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        Activación, inactivación, desbloqueo y creación de administradores con minimización de datos.
                    </p>
                    <a href="{{ route('admin.usuarios.index') }}" class="inline-flex items-center text-sm font-semibold text-rose-600 hover:text-rose-900">
                        Gestionar Cuentas &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
