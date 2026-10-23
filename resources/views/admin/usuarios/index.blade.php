<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Gestión de Cuentas de Usuario
            </h2>
            <button onclick="document.getElementById('modalCrearAdmin').classList.remove('hidden')" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg text-sm shadow">
                + Crear Administrador
            </button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-auth-session-status :status="session('status')" />

            @if ($errors->any())
                <div class="p-4 bg-red-100 border border-red-300 text-red-700 text-sm rounded-lg">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Filtros de Búsqueda -->
            <div class="p-4 bg-white shadow sm:rounded-xl">
                <form method="GET" action="{{ route('admin.usuarios.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div class="md:col-span-2">
                        <x-input-label for="buscar" value="Buscar por Nombre o Correo" />
                        <x-text-input id="buscar" type="text" name="buscar" :value="$busqueda" placeholder="Ej: Perez o juan@correo.com" class="w-full mt-1" />
                    </div>

                    <div>
                        <x-input-label for="estado" value="Filtrar por Estado" />
                        <select id="estado" name="estado" class="w-full mt-1 border-gray-300 rounded-md text-sm">
                            <option value="">-- Todos los estados --</option>
                            <option value="ACTIVA" {{ $estado === 'ACTIVA' ? 'selected' : '' }}>ACTIVA</option>
                            <option value="BLOQUEADA" {{ $estado === 'BLOQUEADA' ? 'selected' : '' }}>BLOQUEADA</option>
                            <option value="INACTIVA" {{ $estado === 'INACTIVA' ? 'selected' : '' }}>INACTIVA</option>
                            <option value="PENDIENTE_VERIFICACION" {{ $estado === 'PENDIENTE_VERIFICACION' ? 'selected' : '' }}>PENDIENTE VERIFICACION</option>
                            <option value="PENDIENTE_SUPRESION" {{ $estado === 'PENDIENTE_SUPRESION' ? 'selected' : '' }}>PENDIENTE SUPRESION</option>
                        </select>
                    </div>

                    <div>
                        <x-primary-button class="w-full justify-center">
                            Filtrar
                        </x-primary-button>
                    </div>
                </form>
            </div>

            <!-- Tabla de Usuarios -->
            <div class="bg-white shadow sm:rounded-xl overflow-hidden">
                <div class="p-4 bg-gray-50 border-b border-gray-200 text-xs text-gray-500">
                    <strong>Principio de Minimización de Datos:</strong> Este panel únicamente muestra datos de identidad y estado de la cuenta. Ninguna ruta administrativa tiene acceso a información financiera o movimientos de los usuarios.
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left font-bold">Nombre Completo</th>
                                <th class="px-6 py-3 text-left font-bold">Correo Electrónico</th>
                                <th class="px-6 py-3 text-left font-bold">Rol</th>
                                <th class="px-6 py-3 text-left font-bold">Estado</th>
                                <th class="px-6 py-3 text-left font-bold">Fecha Registro</th>
                                <th class="px-6 py-3 text-left font-bold">Último Acceso</th>
                                <th class="px-6 py-3 text-center font-bold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($usuarios as $u)
                                <tr>
                                    <td class="px-6 py-4 font-semibold text-gray-900">{{ $u->nombres }} {{ $u->apellidos }}</td>
                                    <td class="px-6 py-4 font-mono text-xs">{{ $u->correo }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 text-xs font-bold rounded {{ $u->esAdmin() ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ $u->rol }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if ($u->estado === 'ACTIVA')
                                            <span class="px-2.5 py-0.5 text-xs font-semibold bg-green-100 text-green-800 rounded-full">Activa</span>
                                        @elseif ($u->estado === 'BLOQUEADA')
                                            <span class="px-2.5 py-0.5 text-xs font-semibold bg-red-100 text-red-800 rounded-full">Bloqueada</span>
                                        @elseif ($u->estado === 'INACTIVA')
                                            <span class="px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-600 rounded-full">Inactiva</span>
                                        @elseif ($u->estado === 'PENDIENTE_SUPRESION')
                                            <span class="px-2.5 py-0.5 text-xs font-semibold bg-amber-100 text-amber-800 rounded-full">En Supresión</span>
                                        @else
                                            <span class="px-2.5 py-0.5 text-xs font-semibold bg-yellow-100 text-yellow-800 rounded-full">{{ $u->estado }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500">{{ $u->creado_en?->format('Y-m-d') }}</td>
                                    <td class="px-6 py-4 text-xs text-gray-500">{{ $u->ultimo_acceso ? $u->ultimo_acceso->diffForHumans() : 'Nunca' }}</td>
                                    <td class="px-6 py-4 text-center space-x-2">
                                        @if ($u->estado === 'BLOQUEADA')
                                            <form method="POST" action="{{ route('admin.usuarios.desbloquear', $u->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs font-bold text-amber-600 hover:text-amber-900">
                                                    Desbloquear
                                                </button>
                                            </form>
                                        @endif

                                        @if ($u->estado === 'ACTIVA')
                                            <button onclick="abrirModalInactivar('{{ $u->id }}', '{{ $u->correo }}')" class="text-xs font-bold text-red-600 hover:text-red-900">
                                                Inactivar
                                            </button>
                                        @elseif ($u->estado === 'INACTIVA')
                                            <form method="POST" action="{{ route('admin.usuarios.cambiar-estado', $u->id) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="nuevo_estado" value="ACTIVA">
                                                <button type="submit" class="text-xs font-bold text-green-600 hover:text-green-900">
                                                    Activar
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                        No se encontraron cuentas de usuario con los filtros indicados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $usuarios->links() }}
        </div>
    </div>

    <!-- Modal Inactivar Cuenta (RN-018.2: Justificación obligatoria) -->
    <div id="modalInactivar" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-2">Inactivar Cuenta</h3>
            <p class="text-xs text-gray-500 mb-4" id="inactivarUsuarioTexto"></p>

            <form id="formInactivar" method="POST" action="">
                @csrf
                @method('PATCH')
                <input type="hidden" name="nuevo_estado" value="INACTIVA">

                <div>
                    <x-input-label for="justificacion" value="Motivo / Justificación (mínimo 10 caracteres) *" />
                    <textarea name="justificacion" id="justificacion" rows="3" required class="w-full mt-1 border-gray-300 rounded-md text-sm" placeholder="Ej: Solicitud formal de suspensión temporal o conducta fraudulenta..."></textarea>
                </div>

                <div class="flex items-center justify-end space-x-3 mt-6">
                    <button type="button" onclick="document.getElementById('modalInactivar').classList.add('hidden')" class="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-semibold hover:bg-red-700">
                        Confirmar Inactivación
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Crear Administrador -->
    <div id="modalCrearAdmin" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2">Crear Nuevo Administrador</h3>

            <form method="POST" action="{{ route('admin.usuarios.store-admin') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="admin_nombres" value="Nombres *" />
                        <x-text-input id="admin_nombres" type="text" name="nombres" required class="w-full mt-1 text-sm" />
                    </div>
                    <div>
                        <x-input-label for="admin_apellidos" value="Apellidos *" />
                        <x-text-input id="admin_apellidos" type="text" name="apellidos" required class="w-full mt-1 text-sm" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <x-input-label for="admin_tipo_doc" value="Tipo Doc. *" />
                        <select id="admin_tipo_doc" name="tipo_documento" class="w-full mt-1 border-gray-300 rounded-md text-sm">
                            <option value="CC">CC</option>
                            <option value="CE">CE</option>
                            <option value="PA">PA</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <x-input-label for="admin_num_doc" value="Número Doc. *" />
                        <x-text-input id="admin_num_doc" type="text" name="numero_documento" required class="w-full mt-1 text-sm" />
                    </div>
                </div>

                <div>
                    <x-input-label for="admin_correo" value="Correo Electrónico *" />
                    <x-text-input id="admin_correo" type="email" name="correo" required class="w-full mt-1 text-sm" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="admin_password" value="Contraseña *" />
                        <x-text-input id="admin_password" type="password" name="password" required class="w-full mt-1 text-sm" />
                    </div>
                    <div>
                        <x-input-label for="admin_password_conf" value="Confirmar *" />
                        <x-text-input id="admin_password_conf" type="password" name="password_confirmation" required class="w-full mt-1 text-sm" />
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="document.getElementById('modalCrearAdmin').classList.add('hidden')" class="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <x-primary-button>
                        Crear Administrador
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalInactivar(id, correo) {
            const form = document.getElementById('formInactivar');
            form.action = `/admin/usuarios/${id}/estado`;
            document.getElementById('inactivarUsuarioTexto').innerText = `Vas a inactivar la cuenta de ${correo}. Esto cerrará sus sesiones activas de inmediato.`;
            document.getElementById('modalInactivar').classList.remove('hidden');
        }
    </script>
</x-app-layout>
