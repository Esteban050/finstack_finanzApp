<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" id="registerForm">
        @csrf

        <!-- Mensaje de error general -->
        @if ($errors->has('registro_error'))
            <div class="mb-4 p-3 bg-red-100 border border-red-300 text-red-700 text-sm rounded-lg">
                {{ $errors->first('registro_error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Nombres -->
            <div>
                <x-input-label for="nombres" value="Nombres *" />
                <x-text-input id="nombres" class="block mt-1 w-full" type="text" name="nombres" :value="old('nombres')" required autofocus />
                <x-input-error :messages="$errors->get('nombres')" class="mt-2" />
            </div>

            <!-- Apellidos -->
            <div>
                <x-input-label for="apellidos" value="Apellidos *" />
                <x-text-input id="apellidos" class="block mt-1 w-full" type="text" name="apellidos" :value="old('apellidos')" required />
                <x-input-error :messages="$errors->get('apellidos')" class="mt-2" />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
            <!-- Tipo de Documento -->
            <div>
                <x-input-label for="tipo_documento" value="Tipo de Doc. *" />
                <select id="tipo_documento" name="tipo_documento" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                    <option value="CC" {{ old('tipo_documento') == 'CC' ? 'selected' : '' }}>CC - Cédula de Ciudadanía</option>
                    <option value="CE" {{ old('tipo_documento') == 'CE' ? 'selected' : '' }}>CE - Cédula de Extranjería</option>
                    <option value="PA" {{ old('tipo_documento') == 'PA' ? 'selected' : '' }}>PA - Pasaporte</option>
                    <option value="PPT" {{ old('tipo_documento') == 'PPT' ? 'selected' : '' }}>PPT - Permiso Protección Temporal</option>
                </select>
                <x-input-error :messages="$errors->get('tipo_documento')" class="mt-2" />
            </div>

            <!-- Número de Documento -->
            <div class="md:col-span-2">
                <x-input-label for="numero_documento" value="Número de Documento *" />
                <x-text-input id="numero_documento" class="block mt-1 w-full" type="text" name="numero_documento" :value="old('numero_documento')" required />
                <x-input-error :messages="$errors->get('numero_documento')" class="mt-2" />
            </div>
        </div>

        <!-- Correo Electrónico -->
        <div class="mt-4">
            <x-input-label for="correo" value="Correo Electrónico *" />
            <x-text-input id="correo" class="block mt-1 w-full" type="email" name="correo" :value="old('correo')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('correo')" class="mt-2" />
        </div>

        <!-- Contraseña -->
        <div class="mt-4">
            <x-input-label for="password" value="Contraseña *" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />

            <!-- Indicador en tiempo real de política de contraseña (RN-001.3, CA-001.3) -->
            <div class="mt-2 text-xs space-y-1 bg-gray-50 p-3 rounded-md border border-gray-200" id="passwordRulesBox">
                <p class="font-semibold text-gray-700">Requisitos de la contraseña:</p>
                <div id="rule-length" class="text-red-500">✕ Entre 10 y 64 caracteres</div>
                <div id="rule-upper" class="text-red-500">✕ Al menos una letra mayúscula</div>
                <div id="rule-lower" class="text-red-500">✕ Al menos una letra minúscula</div>
                <div id="rule-digit" class="text-red-500">✕ Al menos un dígito</div>
                <div id="rule-special" class="text-red-500">✕ Al menos un carácter especial (@, $, !, %, *, #, etc.)</div>
                <div id="rule-noinfo" class="text-red-500">✕ No contener tu correo ni número de documento</div>
            </div>
        </div>

        <!-- Confirmar Contraseña -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Confirmar Contraseña *" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <div id="passwordMatchError" class="text-xs text-red-600 mt-1 hidden">Las contraseñas no coinciden</div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <!-- Aceptación de Política de Datos (RN-001.4) -->
        <div class="mt-4">
            <label for="acepta_politica" class="inline-flex items-start">
                <input id="acepta_politica" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 mt-1" name="acepta_politica" value="1" required {{ old('acepta_politica') ? 'checked' : '' }}>
                <span class="ms-2 text-xs text-gray-600 leading-relaxed">
                    Acepto expresamente la <a href="{{ route('politica.mostrar') }}" target="_blank" class="text-indigo-600 underline font-semibold hover:text-indigo-900">política de tratamiento de datos personales</a> (Ley 1581 de 2012) y los términos de uso.
                </span>
            </label>
            <x-input-error :messages="$errors->get('acepta_politica')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between mt-6">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                ¿Ya tienes una cuenta? Inicia sesión
            </a>

            <!-- Botón de registro (CA-001.3: no se habilita hasta cumplir requisitos) -->
            <x-primary-button id="btnSubmit" class="ms-4">
                Crear Cuenta
            </x-primary-button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const pwd = document.getElementById('password');
            const pwdConfirm = document.getElementById('password_confirmation');
            const correo = document.getElementById('correo');
            const numDoc = document.getElementById('numero_documento');
            const btnSubmit = document.getElementById('btnSubmit');
            const matchError = document.getElementById('passwordMatchError');

            const ruleLength = document.getElementById('rule-length');
            const ruleUpper = document.getElementById('rule-upper');
            const ruleLower = document.getElementById('rule-lower');
            const ruleDigit = document.getElementById('rule-digit');
            const ruleSpecial = document.getElementById('rule-special');
            const ruleNoInfo = document.getElementById('rule-noinfo');

            function validarPassword() {
                const val = pwd.value || '';
                const emailVal = (correo.value || '').toLowerCase().trim();
                const docVal = (numDoc.value || '').trim();

                const isLength = val.length >= 10 && val.length <= 64;
                const isUpper = /[A-Z]/.test(val);
                const isLower = /[a-z]/.test(val);
                const isDigit = /[0-9]/.test(val);
                const isSpecial = /[^A-Za-z0-9]/.test(val);

                let containsInfo = false;
                if (emailVal && val.toLowerCase().includes(emailVal)) containsInfo = true;
                const localPart = emailVal.split('@')[0];
                if (localPart && localPart.length >= 3 && val.toLowerCase().includes(localPart)) containsInfo = true;
                if (docVal && docVal.length >= 3 && val.includes(docVal)) containsInfo = true;

                updateRule(ruleLength, isLength, 'Entre 10 y 64 caracteres');
                updateRule(ruleUpper, isUpper, 'Al menos una letra mayúscula');
                updateRule(ruleLower, isLower, 'Al menos una letra minúscula');
                updateRule(ruleDigit, isDigit, 'Al menos un dígito');
                updateRule(ruleSpecial, isSpecial, 'Al menos un carácter especial (@, $, !, %, *, #, etc.)');
                updateRule(ruleNoInfo, !containsInfo, 'No contener tu correo ni número de documento');

                const isValid = isLength && isUpper && isLower && isDigit && isSpecial && !containsInfo;

                const isMatch = val === pwdConfirm.value && val.length > 0;
                if (pwdConfirm.value.length > 0 && !isMatch) {
                    matchError.classList.remove('hidden');
                } else {
                    matchError.classList.add('hidden');
                }

                btnSubmit.disabled = !isValid || !isMatch;
                if (btnSubmit.disabled) {
                    btnSubmit.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

            function updateRule(el, valid, text) {
                if (valid) {
                    el.className = 'text-green-600 font-medium';
                    el.innerText = '✓ ' + text;
                } else {
                    el.className = 'text-red-500';
                    el.innerText = '✕ ' + text;
                }
            }

            pwd.addEventListener('input', validarPassword);
            pwdConfirm.addEventListener('input', validarPassword);
            correo.addEventListener('input', validarPassword);
            numDoc.addEventListener('input', validarPassword);

            validarPassword();
        });
    </script>
</x-guest-layout>
