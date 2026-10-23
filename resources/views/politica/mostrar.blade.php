<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Política de Tratamiento de Datos Personales (Ley 1581 de 2012)
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 bg-white shadow sm:rounded-lg">
                <div class="flex items-center justify-between border-b pb-4 mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Versión Vigente: {{ $versionVigente }}</h3>
                    <span class="text-xs bg-green-100 text-green-800 font-semibold px-2.5 py-0.5 rounded">Oficial</span>
                </div>

                <div class="prose max-w-none text-sm text-gray-700 space-y-4 leading-relaxed">
                    <p>
                        En cumplimiento de la <strong>Ley Estatutaria 1581 de 2012</strong> y su Decreto Reglamentario 1377 de 2013, el <strong>Sistema de Control Financiero Personal (SCFP)</strong> informa a los usuarios que los datos personales y financieros suministrados en esta plataforma serán tratados de manera confidencial y segura.
                    </p>
                    <h4 class="font-semibold text-gray-900">1. Finalidad del Tratamiento</h4>
                    <p>
                        Los datos personales (identificación, contacto) y los datos financieros y tributarios serán utilizados exclusivamente para la gestión de su cuenta de usuario, la parametrización de sus obligaciones tributarias (renta y seguridad social) y la administración de sus presupuestos, ingresos y gastos.
                    </p>
                    <h4 class="font-semibold text-gray-900">2. Derechos del Titular</h4>
                    <p>
                        Como titular de los datos, usted tiene derecho a conocer, actualizar, rectificar y solicitar la supresión de sus datos personales, así como a revocar la autorización otorgada en cualquier momento a través de la sección de perfil de su cuenta.
                    </p>
                    <h4 class="font-semibold text-gray-900">3. Medidas de Seguridad</h4>
                    <p>
                        La plataforma implementa algoritmos de cifrado y hash seguro (Argon2id/bcrypt), controles de acceso por roles, bitácoras de auditoría inmutables y sesiones con tokens seguros con expiración automática.
                    </p>
                </div>

                @auth
                    @php
                        $yaAcepto = auth()->user()->aceptacionesPolitica()->where('version_politica', $versionVigente)->whereNull('revocada_en')->exists();
                    @endphp

                    @if (!$yaAcepto)
                        <form method="POST" action="{{ route('politica.aceptar') }}" class="mt-8 pt-6 border-t border-gray-200">
                            @csrf
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="acepto" value="1" required class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="ms-2 text-sm font-semibold text-gray-800">
                                    He leído y acepto los términos de la versión {{ $versionVigente }} de la Política de Tratamiento de Datos Personales.
                                </span>
                            </label>

                            <div class="mt-4">
                                <x-primary-button>
                                    Aceptar y Continuar
                                </x-primary-button>
                            </div>
                        </form>
                    @else
                        <div class="mt-6 p-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg flex items-center">
                            <span class="font-semibold">✓ Has aceptado la versión vigente ({{ $versionVigente }}) de esta política.</span>
                        </div>
                    @endif
                @endauth
            </div>

            @auth
                @if ($historial->isNotEmpty())
                    <div class="p-6 bg-white shadow sm:rounded-lg">
                        <h4 class="text-md font-bold text-gray-900 mb-3">Historial de Aceptaciones</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">Versión</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">Fecha y Hora</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">IP de Origen</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">Medio</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach ($historial as $item)
                                        <tr>
                                            <td class="px-4 py-2 font-mono font-bold">{{ $item->version_politica }}</td>
                                            <td class="px-4 py-2">{{ $item->fecha_aceptacion->format('Y-m-d H:i:s') }}</td>
                                            <td class="px-4 py-2 font-mono text-xs">{{ $item->ip_origen }}</td>
                                            <td class="px-4 py-2 text-xs text-gray-600">{{ $item->medio }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endauth
        </div>
    </div>
</x-app-layout>
