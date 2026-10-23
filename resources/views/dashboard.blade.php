<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard — Sistema de Control Financiero Personal (SCFP)
        </h2>
    </x-slot>

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

            <!-- Accesos Rápidos -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <a href="{{ route('profile.show') }}" class="p-6 bg-white rounded-xl shadow border border-gray-100 hover:shadow-md transition">
                    <h4 class="font-bold text-gray-900 mb-1">Mi Perfil Financiero</h4>
                    <p class="text-xs text-gray-500">Actualiza tus ocupaciones, ARL, dependientes y datos de contacto.</p>
                </a>

                <a href="{{ route('profile.export') }}" class="p-6 bg-white rounded-xl shadow border border-gray-100 hover:shadow-md transition">
                    <h4 class="font-bold text-gray-900 mb-1">Exportar Mis Datos</h4>
                    <p class="text-xs text-gray-500">Descarga un paquete .zip estructurado en JSON y CSV de tu información.</p>
                </a>

                <a href="{{ route('politica.mostrar') }}" class="p-6 bg-white rounded-xl shadow border border-gray-100 hover:shadow-md transition">
                    <h4 class="font-bold text-gray-900 mb-1">Tratamiento de Datos</h4>
                    <p class="text-xs text-gray-500">Consulta la política vigente de Habeas Data y tu historial de aceptaciones.</p>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
