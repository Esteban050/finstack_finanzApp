<x-app-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            Panel de Administración — Parámetros y Configuración
        </h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Grid de Módulos de Administración -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Parámetros Normativos -->
                <div class="bg-white p-6 rounded-xl shadow border border-gray-100 hover:shadow-lg transition">
                    <div class="flex items-center space-x-3 text-indigo-600 mb-3">
                        <x-icon name="act-clasificacion-tributaria" :size="32" />
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
                        <x-icon name="ui-categorias" :size="32" />
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
                        <x-icon name="ui-calendario" :size="32" />
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
                        <x-icon name="ui-usuario" :size="32" />
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
