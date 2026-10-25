<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mi Perfil Financiero, Tributario y de Seguridad
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <x-auth-session-status :status="session('status')" />

            @if ($errors->any())
                <div class="p-4 bg-red-100 border border-red-300 text-red-700 text-sm rounded-lg">
                    <p class="font-bold mb-1">Se encontraron los siguientes problemas:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->messages() as $fieldName => $messages)
                            <li><a href="#{{ $fieldName }}">{{ preg_match('/validation\.|SQL|trace|exception|\.php\b/i', (string) $messages[0]) ? 'Revisa este campo' : $messages[0] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- 1. GESTIÓN DEL PERFIL TRIBUTARIO Y PERSONAL (RF-008) -->
            <div class="bg-white p-8 shadow sm:rounded-xl">
                <div class="border-b pb-3 mb-6">
                    <h3 class="text-lg font-bold text-gray-900">1. Perfil Personal y Tributario</h3>
                    <p class="text-xs text-gray-500">De este perfil dependen los cálculos automáticos de seguridad social y renta.</p>
                </div>

                <form method="POST" action="{{ route('profile.update') }}" data-profile-form class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <!-- Datos básicos -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-ui.field name="nombres" label="Nombres" :required="true">
                            <x-text-input id="nombres" type="text" name="nombres" :value="old('nombres', $usuario->nombres)" required class="w-full mt-1" />
                        </x-ui.field>
                        <x-ui.field name="apellidos" label="Apellidos" :required="true">
                            <x-text-input id="apellidos" type="text" name="apellidos" :value="old('apellidos', $usuario->apellidos)" required class="w-full mt-1" />
                        </x-ui.field>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-ui.field name="correo_no_editable" label="Correo Electrónico (No editable)">
                            <input type="text" id="correo_no_editable" disabled value="{{ $usuario->correo }}" class="w-full mt-1 bg-gray-100 text-gray-500 border-gray-300 rounded-md text-sm" />
                        </x-ui.field>
                        <x-ui.field name="doc_no_editable" label="Documento (No editable)">
                            <input type="text" id="doc_no_editable" disabled value="{{ $usuario->tipo_documento }} - {{ $usuario->numero_documento }}" class="w-full mt-1 bg-gray-100 text-gray-500 border-gray-300 rounded-md text-sm" />
                        </x-ui.field>
                        <x-ui.field name="fecha_nacimiento" label="Fecha de Nacimiento (Mayor de 18)" :required="true">
                            <x-text-input id="fecha_nacimiento" type="date" name="fecha_nacimiento" :value="old('fecha_nacimiento', $perfilActivo?->fecha_nacimiento?->format('Y-m-d'))" required class="w-full mt-1" />
                        </x-ui.field>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-ui.field name="ciudad_residencia" label="Ciudad de Residencia" :required="true">
                            <x-text-input id="ciudad_residencia" type="text" name="ciudad_residencia" :value="old('ciudad_residencia', $perfilActivo?->ciudad_residencia)" required class="w-full mt-1" />
                        </x-ui.field>
                        <x-ui.field name="telefono" label="Teléfono (10 dígitos)">
                            <x-text-input id="telefono" type="text" name="telefono" maxlength="10" :value="old('telefono', $perfilActivo?->telefono)" class="w-full mt-1" />
                        </x-ui.field>
                        <input type="hidden" name="ultimos_digitos_nit" value="{{ substr(preg_replace('/\D/', '', (string) $usuario->numero_documento), -2) }}">
                    </div>

                    <!-- Personas a cargo y Responsable IVA -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-ui.field name="personas_a_cargo" label="Número de Personas a Cargo (0 - 10)" :required="true">
                            <x-text-input id="personas_a_cargo" type="number" min="0" max="10" name="personas_a_cargo" :value="old('personas_a_cargo', $perfilActivo?->personas_a_cargo ?? 0)" required class="w-full mt-1" />
                        </x-ui.field>
                        <x-ui.field name="responsable_iva" label="¿Responsable de IVA?" :required="true">
                            <select id="responsable_iva" name="responsable_iva" class="w-full mt-1 border-gray-300 rounded-md text-sm">
                                <option value="0" @selected(!in_array(old('responsable_iva', $perfilActivo?->responsable_iva), [1, '1', true], true))>No</option>
                                <option value="1" @selected(in_array(old('responsable_iva', $perfilActivo?->responsable_iva), [1, '1', true], true))>Sí (Obliga a declarar renta automáticamente)</option>
                            </select>
                        </x-ui.field>
                    </div>

                    <!-- Perfil de Ocupación RN-008.2 -->
                    <x-ui.field name="ocupaciones" label="Perfil de ocupación (puedes seleccionar varias)" :required="true">
                        @php
                            $ocupacionesActuales = old('ocupaciones', $perfilActivo?->ocupaciones ?? []);
                        @endphp
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 mt-2 text-sm">
                            @foreach (['Asalariado', 'Independiente por cuenta propia', 'Contratista por prestación de servicios', 'Rentista de capital', 'Pensionado'] as $oc)
                                <label class="inline-flex items-center p-2 rounded border border-gray-200 bg-gray-50 hover:bg-gray-100 cursor-pointer">
                                    <input type="checkbox" name="ocupaciones[]" value="{{ $oc }}" {{ in_array($oc, $ocupacionesActuales) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                    <span class="ms-2 text-gray-700 text-xs font-medium">{{ $oc }}</span>
                                </label>
                            @endforeach
                        </div>
                    </x-ui.field>

                    <!-- Opciones para Asalariado y Contratistas -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-indigo-50/50 rounded-lg border border-indigo-100">
                        <x-ui.field name="es_salario_integral" label="¿Tiene Salario Integral? (Asalariado)">
                            <select id="es_salario_integral" name="es_salario_integral" class="w-full mt-1 border-gray-300 rounded-md text-sm">
                                <option value="0" @selected(!in_array(old('es_salario_integral', $perfilActivo?->es_salario_integral), [1, '1', true], true))>No</option>
                                <option value="1" @selected(in_array(old('es_salario_integral', $perfilActivo?->es_salario_integral), [1, '1', true], true))>Sí</option>
                            </select>
                        </x-ui.field>
                        <x-ui.field name="clase_riesgo_arl" label="Clase de Riesgo ARL (Indep./Contratista)">
                            <select id="clase_riesgo_arl" name="clase_riesgo_arl" class="w-full mt-1 border-gray-300 rounded-md text-sm">
                                <option value="">-- No aplica / Sin ARL --</option>
                                @foreach ([1 => 'Clase I (0.522%)', 2 => 'Clase II (1.044%)', 3 => 'Clase III (2.436%)', 4 => 'Clase IV (4.350%)', 5 => 'Clase V (6.960%)'] as $k => $v)
                                    <option value="{{ $k }}" {{ old('clase_riesgo_arl', $perfilActivo?->clase_riesgo_arl) == $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <x-ui.field name="arl_obligatoria" label="Afiliación ARL">
                            <select id="arl_obligatoria" name="arl_obligatoria" class="w-full mt-1 border-gray-300 rounded-md text-sm">
                                <option value="1" @selected(in_array(old('arl_obligatoria', $perfilActivo?->arl_obligatoria), [1, '1', true], true))>Obligatoria</option>
                                <option value="0" @selected(!in_array(old('arl_obligatoria', $perfilActivo?->arl_obligatoria), [1, '1', true], true))>Voluntaria</option>
                            </select>
                        </x-ui.field>
                    </div>

                    <div class="flex items-center justify-end">
                        <x-primary-button>
                            Guardar y Aplicar Cambios
                        </x-primary-button>
                    </div>
                </form>
            </div>

            <!-- 2. CAMBIO DE CONTRASEÑA -->
            <div class="bg-white p-8 shadow sm:rounded-xl">
                <h3 class="text-lg font-bold text-gray-900 border-b pb-3 mb-6">2. Cambio de Contraseña</h3>
                <x-auth.change-password :email="$usuario->correo" :document="$usuario->numero_documento" />
            </div>

            <!-- 3. VERIFICACIÓN EN DOS PASOS (MFA) -->
            <div class="bg-white p-8 shadow sm:rounded-xl">
                <div class="flex items-center justify-between border-b pb-3 mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">3. Verificación en Dos Pasos (MFA)</h3>
                        <p class="text-xs text-gray-500">Envío de código de 6 dígitos al correo registrado en cada inicio de sesión.</p>
                    </div>
                    <span class="px-3 py-1 text-xs font-bold rounded-full {{ $usuario->mfa_activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                        {{ $usuario->mfa_activo ? 'MFA ACTIVO' : 'MFA INACTIVO' }}
                    </span>
                </div>

                @if (!$usuario->mfa_activo)
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">Para activar 2FA, solicita un código a tu correo e ingrésalo a continuación:</p>
                        <form method="POST" action="{{ route('profile.mfa.code') }}" class="inline">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-xs font-semibold">
                                Enviar Código de Confirmación a mi Correo
                            </button>
                        </form>

                        <form method="POST" action="{{ route('profile.mfa.toggle') }}" class="mt-4 flex space-x-3 items-center">
                            @csrf
                            <input type="text" name="codigo" maxlength="6" placeholder="Código de 6 dígitos" required class="border-gray-300 rounded-md text-sm font-mono w-48" />
                            <x-primary-button>
                                Activar 2FA
                            </x-primary-button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('profile.mfa.toggle') }}" class="space-y-4">
                        @csrf
                        <p class="text-sm text-gray-600">Para desactivar 2FA, ingresa tu contraseña y solicita un código:</p>
                        <form method="POST" action="{{ route('profile.mfa.code') }}" class="inline">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded text-xs font-semibold">
                                Enviar Código de Desactivación
                            </button>
                        </form>
                        <div class="flex space-x-3 items-center pt-2">
                            <input type="password" name="password" placeholder="Tu Contraseña" required class="border-gray-300 rounded-md text-sm" />
                            <input type="text" name="codigo" maxlength="6" placeholder="Código 6 dígitos" required class="border-gray-300 rounded-md text-sm font-mono w-36" />
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-xs font-bold hover:bg-red-700">
                                Desactivar 2FA
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <!-- 4. EXPORTACIÓN DE DATOS PERSONALES -->
            <div class="bg-white p-8 shadow sm:rounded-xl flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">4. Exportar Datos Personales</h3>
                    <p class="text-xs text-gray-500">Descarga un archivo .zip con tus datos en formatos JSON y CSV (máximo 1 exportación cada 24 horas).</p>
                </div>
                <a href="{{ route('profile.export') }}" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow">
                    Descargar Exportación (.zip)
                </a>
            </div>

            <!-- 5. SUPRESIÓN DE LA CUENTA -->
            <div class="bg-white p-8 shadow sm:rounded-xl border border-red-200">
                <div class="border-b border-red-100 pb-3 mb-4">
                    <h3 class="text-lg font-bold text-red-600">5. Zona de Peligro: Supresión de la Cuenta</h3>
                    <p class="text-xs text-gray-500">
                        Esta acción iniciará el proceso de supresión de tu cuenta y todos tus datos personales y financieros. La cuenta se bloqueará y tendrás 15 días calendario para cancelar la solicitud con el enlace que recibirás por correo.
                    </p>
                </div>

                <form method="POST" action="{{ route('profile.delete.account') }}" class="space-y-4 max-w-xl">
                    @csrf
                    <div>
                        <x-input-label for="supresion_pass" value="Ingresa tu Contraseña Actual *" />
                        <x-text-input id="supresion_pass" type="password" name="password" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="confirmacion_palabra" value="Escribe exactamente la palabra ELIMINAR para confirmar *" />
                        <x-text-input id="confirmacion_palabra" type="text" name="confirmacion_palabra" required class="w-full mt-1 font-mono uppercase" placeholder="ELIMINAR" />
                    </div>
                    <button type="submit" data-confirm-delete class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-lg shadow">
                        Confirmar Solicitud de Supresión
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
