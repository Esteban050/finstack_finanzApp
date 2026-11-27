<x-app-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            Mi Perfil Financiero, Tributario y de Seguridad
        </h1>
    </x-slot>

    <div class="py-12 profile-page">
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

            <div class="ui-inline">
                <a class="ui-button ui-button--secondary" href="{{ route('perfil.alertas.index') }}">Alertas y notificaciones</a>
                <a class="ui-button ui-button--secondary" href="{{ route('profile.themes') }}">Temas</a>
            </div>

            <!-- Resumen de Cuenta e Identidad -->
            <div class="bg-white p-6 shadow sm:rounded-xl">
                <div class="flex items-center justify-between border-b pb-4 mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">{{ $usuario->nombres }} {{ $usuario->apellidos }}</h3>
                        <p class="text-xs text-gray-500">Información de cuenta, datos tributarios y seguridad.</p>
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
                        @if ($perfilActivo)
                            <p class="text-sm font-bold text-indigo-700 mt-1">
                                {{ implode(', ', $perfilActivo->ocupaciones ?? []) }}
                            </p>
                            <p class="text-xs text-gray-500">
                                Responsable IVA: {{ $perfilActivo->responsable_iva ? 'Sí (Declara Renta)' : 'No' }}
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
                        <a href="#mfa-section" class="text-xs text-indigo-600 font-semibold hover:underline">Gestionar en sección 3 &rarr;</a>
                    </div>
                </div>
            </div>

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
                            <x-text-input id="fecha_nacimiento" type="date" name="fecha_nacimiento" max="{{ now('America/Bogota')->subYears(18)->toDateString() }}" :value="old('fecha_nacimiento', $perfilActivo?->fecha_nacimiento?->format('Y-m-d'))" required class="w-full mt-1" />
                        </x-ui.field>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-ui.field name="ciudad_residencia" label="Ciudad de Residencia" :required="true">
                            <x-text-input id="ciudad_residencia" type="text" name="ciudad_residencia" :value="old('ciudad_residencia', $perfilActivo?->ciudad_residencia)" required class="w-full mt-1" />
                        </x-ui.field>
                        <x-ui.field name="telefono" label="Teléfono (10 dígitos)">
                            <x-text-input id="telefono" type="text" name="telefono" inputmode="numeric" maxlength="10" :value="old('telefono', $perfilActivo?->telefono)" class="w-full mt-1" />
                        </x-ui.field>
                        {{-- RN-008.1: últimos dos dígitos del NIT o del documento, para el calendario tributario (RF-016). --}}
                        <x-ui.field name="ultimos_digitos_nit" label="Últimos 2 dígitos del NIT o documento" help="Se usan para tus fechas de vencimiento con la DIAN.">
                            <x-text-input id="ultimos_digitos_nit" type="text" name="ultimos_digitos_nit" inputmode="numeric" pattern="[0-9]{2}" minlength="2" maxlength="2" :value="old('ultimos_digitos_nit', $perfilActivo?->ultimos_digitos_nit ?? substr(preg_replace('/\D/', '', (string) $usuario->numero_documento), -2))" class="w-full mt-1" />
                        </x-ui.field>
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
                    <x-ui.field :group="true" name="ocupaciones" label="Perfil de ocupación (puedes seleccionar varias)" :required="true">
                        @php
                            // Los datos antiguos pueden venir en mayúsculas o con otro nombre (p. ej. "ASALARIADO",
                            // "INVERSIONISTA"): se normalizan a las opciones vigentes para marcarlas en el formulario.
                            $equivalenciasOcupacion = ['inversionista' => 'rentista de capital', 'rentista' => 'rentista de capital'];
                            $ocupacionesActuales = collect(old('ocupaciones', $perfilActivo?->ocupaciones ?? []))
                                ->map(function ($valor) use ($equivalenciasOcupacion) {
                                    $clave = mb_strtolower(trim((string) $valor));
                                    return $equivalenciasOcupacion[$clave] ?? $clave;
                                })
                                ->all();
                        @endphp
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 mt-2 text-sm">
                            @foreach (['Asalariado', 'Independiente por cuenta propia', 'Contratista por prestación de servicios', 'Rentista de capital', 'Pensionado'] as $oc)
                                <label class="inline-flex items-center p-2 rounded border border-gray-200 bg-gray-50 hover:bg-gray-100 cursor-pointer">
                                    <input type="checkbox" name="ocupaciones[]" value="{{ $oc }}" {{ in_array(mb_strtolower($oc), $ocupacionesActuales, true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                    <span class="ms-2 text-gray-700 text-xs font-medium">{{ $oc }}</span>
                                </label>
                            @endforeach
                        </div>
                    </x-ui.field>

                    <!-- Opciones para Asalariado y Contratistas -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4 bg-indigo-50/50 rounded-lg border border-indigo-100">
                        <x-ui.field name="es_salario_integral" label="¿Tiene Salario Integral? (Asalariado)">
                            <select id="es_salario_integral" name="es_salario_integral" class="w-full mt-1 border-gray-300 rounded-md text-sm">
                                <option value="0" @selected(!in_array(old('es_salario_integral', $perfilActivo?->es_salario_integral), [1, '1', true], true))>No</option>
                                <option value="1" @selected(in_array(old('es_salario_integral', $perfilActivo?->es_salario_integral), [1, '1', true], true))>Sí</option>
                            </select>
                        </x-ui.field>
                        <x-ui.field name="clase_riesgo_arl" label="Clase de Riesgo ARL (Indep./Contratista)">
                            <select id="clase_riesgo_arl" name="clase_riesgo_arl" class="w-full mt-1 border-gray-300 rounded-md text-sm">
                                <option value="">No aplica (sin ARL)</option>
                                @foreach ([1 => 'Clase I (0,522 %)', 2 => 'Clase II (1,044 %)', 3 => 'Clase III (2,436 %)', 4 => 'Clase IV (4,350 %)', 5 => 'Clase V (6,960 %)'] as $k => $v)
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

                    {{-- CA-008.2 / RN-008.6: los cambios se aplican desde la fecha de vigencia indicada. --}}
                    @php($vigenciaMinima = $perfilActivo?->vigente_desde?->toDateString())
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-ui.field name="vigente_desde" label="Aplicar cambios desde" :required="true" help="Fecha desde la que rigen estos datos para tus cálculos. Por defecto, hoy.">
                            <x-text-input id="vigente_desde" type="date" name="vigente_desde" :min="$vigenciaMinima" max="{{ now('America/Bogota')->toDateString() }}" :value="old('vigente_desde', now('America/Bogota')->toDateString())" required class="w-full mt-1" />
                        </x-ui.field>
                    </div>

                    <div class="flex items-center justify-end">
                        <x-primary-button>
                            Guardar y Aplicar Cambios
                        </x-primary-button>
                    </div>
                </form>
            </div>

            <!-- CUENTAS Y TARJETAS -->
            <?php
                $cuentas = $cuentasYTarjetas['cuentas'] ?? collect();
                $tarjetas = $cuentasYTarjetas['tarjetas'] ?? collect();
                $bancos = $cuentasYTarjetas['bancos'] ?? collect();
                $pesos = fn ($v) => '$ '.number_format((float) $v, 0, ',', '.');
                $errorCuenta = $errors->getBag('cuenta');
                $errorTarjeta = $errors->getBag('tarjeta');
                $tipoInicial = $errorTarjeta->any() || old('tipo_cuenta') === 'CREDITO' ? 'CREDITO' : 'DEBITO';
            ?>
            <div id="cuentas-section" class="bg-white p-8 shadow sm:rounded-xl"
                 x-data="{ abierto: {{ ($errorCuenta->any() || $errorTarjeta->any()) ? 'true' : 'false' }}, tipo: '{{ $tipoInicial }}' }">
                <div class="flex items-center justify-between border-b pb-3 mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Cuentas y tarjetas</h3>
                        <p class="text-xs text-gray-500">Registra tus cuentas de débito/ahorro y tus tarjetas de crédito; aparecen primero en tu resumen.</p>
                    </div>
                    <button type="button" x-on:click="abierto = !abierto" data-cuentas-toggle
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow">
                        <span x-text="abierto ? 'Cerrar' : 'Agregar cuenta o tarjeta'">Agregar cuenta o tarjeta</span>
                    </button>
                </div>

                <div x-show="abierto" x-cloak class="mb-6 border rounded-lg p-4 bg-gray-50 space-y-4" @if(! ($errorCuenta->any() || $errorTarjeta->any())) style="display:none" @endif>
                    <div>
                        <x-input-label for="tipo_cuenta" value="Tipo *" />
                        <select id="tipo_cuenta" name="tipo_cuenta" x-model="tipo" class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                            <option value="DEBITO">Débito / Ahorro</option>
                            <option value="CREDITO">Crédito (tarjeta)</option>
                        </select>
                    </div>

                    <form x-show="tipo === 'DEBITO'" method="POST" action="{{ route('perfil.cuentas.store') }}" class="space-y-4 max-w-xl">
                        @csrf
                        <input type="hidden" name="tipo_cuenta" value="DEBITO">
                        @if($errorCuenta->any())
                            <div class="p-3 bg-red-100 border border-red-300 text-red-700 text-sm rounded-lg">
                                @foreach($errorCuenta->all() as $mensaje)<p>{{ $mensaje }}</p>@endforeach
                            </div>
                        @endif
                        <div x-data="{ banco: @js(old('entidad_financiera_id') ?? (old('banco_otro') ? 'OTRO' : '')) }">
                            <x-input-label for="cuenta_banco" value="Banco *" />
                            <select id="cuenta_banco" name="entidad_financiera_id" x-model="banco" required class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                                <option value="">Selecciona un banco</option>
                                @foreach($bancos as $banco)<option value="{{ $banco->id }}">{{ $banco->nombre }}</option>@endforeach
                                <option value="OTRO">Otro banco</option>
                            </select>
                            <div x-show="banco === 'OTRO'" x-cloak class="mt-2">
                                <x-input-label for="cuenta_banco_otro" value="Nombre del banco *" />
                                <x-text-input id="cuenta_banco_otro" type="text" name="banco_otro" maxlength="100" x-bind:required="banco === 'OTRO'" x-bind:disabled="banco !== 'OTRO'" :value="old('banco_otro')" class="w-full mt-1" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="cuenta_alias" value="Nombre o alias (opcional)" />
                            <x-text-input id="cuenta_alias" type="text" name="alias" maxlength="80" :value="old('alias')" class="w-full mt-1" />
                        </div>
                        <div>
                            <x-input-label for="cuenta_saldo" value="Monto o saldo inicial (COP) *" />
                            <x-text-input id="cuenta_saldo" type="number" step="0.01" min="0" data-value-input data-precision="2" name="saldo_inicial" required :value="old('saldo_inicial')" class="w-full mt-1" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="es_exenta_gmf" value="1" @checked(old('es_exenta_gmf')) class="rounded border-gray-300">
                            Cuenta exenta de GMF (4x1000)
                        </label>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow">Guardar cuenta</button>
                    </form>

                    <form x-show="tipo === 'CREDITO'" method="POST" action="{{ route('perfil.tarjetas.store') }}" class="space-y-4 max-w-xl">
                        @csrf
                        <input type="hidden" name="tipo_cuenta" value="CREDITO">
                        @if($errorTarjeta->any())
                            <div class="p-3 bg-red-100 border border-red-300 text-red-700 text-sm rounded-lg">
                                @foreach($errorTarjeta->all() as $mensaje)<p>{{ $mensaje }}</p>@endforeach
                            </div>
                        @endif
                        <div x-data="{ banco: @js(old('entidad_financiera_id') ?? (old('banco_otro') ? 'OTRO' : '')) }">
                            <x-input-label for="tarjeta_banco" value="Banco *" />
                            <select id="tarjeta_banco" name="entidad_financiera_id" x-model="banco" required class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                                <option value="">Selecciona un banco</option>
                                @foreach($bancos as $banco)<option value="{{ $banco->id }}">{{ $banco->nombre }}</option>@endforeach
                                <option value="OTRO">Otro banco</option>
                            </select>
                            <div x-show="banco === 'OTRO'" x-cloak class="mt-2">
                                <x-input-label for="tarjeta_banco_otro" value="Nombre del banco *" />
                                <x-text-input id="tarjeta_banco_otro" type="text" name="banco_otro" maxlength="80" x-bind:required="banco === 'OTRO'" x-bind:disabled="banco !== 'OTRO'" :value="old('banco_otro')" class="w-full mt-1" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="tarjeta_cupo" value="Cupo aprobado (COP, pesos enteros) *" />
                            <x-text-input id="tarjeta_cupo" type="number" data-value-input data-precision="0" step="1" min="1" name="cupo" required :value="old('cupo')" class="w-full mt-1" />
                        </div>
                        <div>
                            <x-input-label for="tarjeta_tasa" value="Tasa de interés E.A. % (opcional)" />
                            <x-text-input id="tarjeta_tasa" type="number" step="0.01" min="0" data-value-input data-precision="2" name="tasa_interes_anual" :value="old('tasa_interes_anual')" class="w-full mt-1" />
                            <p class="text-xs text-gray-500 mt-1">Si la dejas vacía se usa 0 %; puedes corregirla en Créditos.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="tarjeta_dia_corte" value="Día de corte (1 a 31) *" />
                                <x-text-input id="tarjeta_dia_corte" type="number" data-value-input data-precision="0" step="1" min="1" max="31" name="dia_corte" required :value="old('dia_corte')" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label for="tarjeta_dia_pago" value="Día de pago (1 a 31) *" />
                                <x-text-input id="tarjeta_dia_pago" type="number" data-value-input data-precision="0" step="1" min="1" max="31" name="dia_pago" required :value="old('dia_pago')" class="w-full mt-1" />
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 -mt-2">Las compras hasta el día de corte entran en ese extracto; las posteriores, en el siguiente. Se pagan el día de pago que sigue al corte.</p>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow">Guardar tarjeta</button>
                    </form>
                </div>

                <h4 class="text-sm font-bold text-gray-800 mb-2">Cuentas de débito / ahorro</h4>
                <ul class="divide-y mb-6" data-lista-cuentas>
                    @forelse($cuentas as $cuenta)
                        <li class="py-2 flex items-center justify-between text-sm">
                            <span>
                                <strong>{{ $cuenta['banco'] }}</strong>@if($cuenta['alias']) · {{ $cuenta['alias'] }}@endif
                                @if($cuenta['exenta'])<span class="text-xs text-emerald-700">· exenta GMF</span>@endif
                                <span class="text-gray-500">· saldo {{ $pesos($cuenta['saldo']) }}</span>
                            </span>
                            <form method="POST" action="{{ route('perfil.cuentas.destroy', $cuenta['id']) }}"
                                  data-confirm-title="Eliminar cuenta"
                                  data-confirm-label="Eliminar"
                                  data-confirm-description="La cuenta dejará de mostrarse; sus movimientos se conservan. ¿Deseas continuar?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-bold text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-500">Aún no registras cuentas de débito o ahorro.</li>
                    @endforelse
                </ul>

                <h4 class="text-sm font-bold text-gray-800 mb-2">Tarjetas de crédito</h4>
                <ul class="divide-y" data-lista-tarjetas>
                    @forelse($tarjetas as $tarjeta)
                        <li class="py-2 text-sm">
                            <strong>{{ $tarjeta->banco }}</strong>
                            <span class="text-gray-500">· cupo {{ $tarjeta->cupo !== null ? $pesos($tarjeta->cupo) : 'sin definir' }}</span>
                            @if($tarjeta->dia_corte !== null && $tarjeta->dia_pago !== null)
                                <span class="text-gray-500">· corte día {{ $tarjeta->dia_corte }}, pago día {{ $tarjeta->dia_pago }}</span>
                            @endif
                            <a href="{{ route('creditos.show', $tarjeta->id) }}" class="text-xs text-indigo-600 font-semibold hover:underline ml-2">Ver en Créditos</a>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-500">Aún no registras tarjetas de crédito.</li>
                    @endforelse
                </ul>
            </div>

            <!-- 2. CAMBIO DE CONTRASEÑA -->
            <div class="bg-white p-8 shadow sm:rounded-xl">
                <h3 class="text-lg font-bold text-gray-900 border-b pb-3 mb-6">2. Cambio de Contraseña</h3>
                <x-auth.change-password :email="$usuario->correo" :document="$usuario->numero_documento" />
            </div>

            <!-- 3. VERIFICACIÓN EN DOS PASOS (MFA) -->
            <div id="mfa-section" class="bg-white p-8 shadow sm:rounded-xl">
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
                    <div class="profile-mfa-setup">
                        <p class="ui-help">Para activar 2FA, solicita un código a tu correo e ingrésalo a continuación:</p>
                        <form method="POST" action="{{ route('profile.mfa.code') }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary">
                                Enviar Código de Confirmación a mi Correo
                            </x-ui.button>
                        </form>

                        <form method="POST" action="{{ route('profile.mfa.toggle') }}" class="ui-filter-row">
                            @csrf
                            <x-ui.field name="codigo" id="mfa-enable-code" label="Código de verificación" :required="true"><input id="mfa-enable-code" type="text" name="codigo" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required aria-describedby="mfa-enable-code-error" /></x-ui.field>
                            <div class="ui-filter-actions"><x-ui.button type="submit">Activar 2FA</x-ui.button></div>
                        </form>
                    </div>
                @else
                    <p class="ui-help">Para desactivar la verificación en dos pasos, solicita un código e ingrésalo con tu contraseña.</p>
                    <form method="POST" action="{{ route('profile.mfa.code') }}">@csrf<x-ui.button type="submit" variant="secondary">Enviar código de desactivación</x-ui.button></form>
                    <form method="POST" action="{{ route('profile.mfa.toggle') }}" class="space-y-4">
                        @csrf
                        <x-auth.password id="mfa-current-password" label="Contraseña actual" autocomplete="current-password" />
                        <x-ui.field name="codigo" id="mfa-disable-code" label="Código de verificación" :required="true" help="Los seis dígitos enviados a tu correo."><input id="mfa-disable-code" name="codigo" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required aria-describedby="mfa-disable-code-help mfa-disable-code-error" value="{{ old('codigo','') }}"></x-ui.field>
                        <x-ui.button type="submit" variant="danger">Desactivar 2FA</x-ui.button>
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
                        @unless(old('revocar_autorizacion'))<x-input-error :messages="$errors->get('password')" class="mt-1" />@endunless
                    </div>
                    <div>
                        <x-input-label for="confirmacion_palabra" value="Escribe exactamente la palabra ELIMINAR para confirmar *" />
                        <x-text-input id="confirmacion_palabra" type="text" name="confirmacion_palabra" required autocomplete="off" class="w-full mt-1 font-mono" placeholder="ELIMINAR" />
                        @unless(old('revocar_autorizacion'))<x-input-error :messages="$errors->get('confirmacion_palabra')" class="mt-1" />@endunless
                    </div>
                    <button type="submit" data-confirm-delete class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-lg shadow">
                        Confirmar Solicitud de Supresión
                    </button>
                </form>

                {{-- RN-009.4: revocar la autorización implica pedir la supresión; se advierte antes de confirmar. --}}
                <details class="mt-6 border-t border-red-100 pt-4" @if(old('revocar_autorizacion')) open @endif>
                    <summary class="text-sm font-bold text-red-600 cursor-pointer">Revocar la autorización de tratamiento de datos</summary>
                    <div role="alert" class="mt-3 text-sm text-gray-700">
                        <p><strong>Importante:</strong> sin tu autorización no podemos tratar tus datos, así que revocarla implica solicitar la supresión de tu cuenta.</p>
                        <p class="mt-1">Tu cuenta se bloqueará y tendrás 15 días calendario para cancelar la solicitud con el enlace que recibirás por correo. Si la cancelas, deberás aceptar de nuevo la política de tratamiento de datos.</p>
                    </div>
                    <form method="POST" action="{{ route('profile.delete.account') }}" class="space-y-4 max-w-xl mt-4"
                          data-confirm-title="Revocar autorización"
                          data-confirm-label="Revocar y solicitar supresión"
                          data-confirm-description="Revocar la autorización implica solicitar la supresión de tu cuenta. Se cerrará tu sesión y la cuenta quedará bloqueada; tendrás 15 días para cancelar la solicitud. ¿Deseas continuar?">
                        @csrf
                        <input type="hidden" name="revocar_autorizacion" value="1">
                        <div>
                            <x-input-label for="revocacion_pass" value="Ingresa tu Contraseña Actual *" />
                            <x-text-input id="revocacion_pass" type="password" name="password" required class="w-full mt-1" />
                            @if(old('revocar_autorizacion'))<x-input-error :messages="$errors->get('password')" class="mt-1" />@endif
                        </div>
                        <div>
                            <x-input-label for="revocacion_palabra" value="Escribe exactamente la palabra ELIMINAR para confirmar *" />
                            <x-text-input id="revocacion_palabra" type="text" name="confirmacion_palabra" required autocomplete="off" class="w-full mt-1 font-mono" placeholder="ELIMINAR" />
                            @if(old('revocar_autorizacion'))<x-input-error :messages="$errors->get('confirmacion_palabra')" class="mt-1" />@endif
                        </div>
                        <button type="submit" data-confirm-delete class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-lg shadow">
                            Revocar autorización y solicitar supresión
                        </button>
                    </form>
                </details>
            </div>
        </div>
    </div>
    @if(isset($historialPerfiles))
    <x-ui.card title="Historial del perfil tributario">
        <p class="ui-help">Versiones disponibles para consultar la vigencia de tus datos.</p>
        <table class="ui-table"><thead><tr><th>Desde</th><th>Hasta</th><th>Ocupaciones</th><th>Ciudad</th></tr></thead><tbody>
            @forelse($historialPerfiles as $version)<tr><td>{{ $version->vigente_desde?->format('d/m/Y') }}</td><td>{{ $version->vigente_hasta?->format('d/m/Y') ?? 'Vigente' }}</td><td>{{ implode(', ', $version->ocupaciones ?? []) }}</td><td>{{ $version->ciudad_residencia }}</td></tr>@empty<tr><td colspan="4">No hay versiones del perfil disponibles.</td></tr>@endforelse
        </tbody></table>
    </x-ui.card>
    @endif
</x-app-layout>
