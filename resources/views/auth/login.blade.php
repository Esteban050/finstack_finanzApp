<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if ($errors->has('verificacion_error'))
        <div class="mb-4 p-3 bg-red-100 border border-red-300 text-red-700 text-sm rounded-lg">
            {{ $errors->first('verificacion_error') }}
            <div class="mt-2">
                <a href="{{ route('password.request') }}" class="underline text-indigo-700 font-semibold">¿Necesitas ayuda?</a>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="correo" value="Correo Electrónico" />
            <x-text-input id="correo" class="block mt-1 w-full" type="email" name="correo" :value="old('correo')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('correo')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Device (RF-012 RN-012.6) -->
        <div class="block mt-4">
            <label for="recordar_dispositivo" class="inline-flex items-center">
                <input id="recordar_dispositivo" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="recordar_dispositivo" value="1">
                <span class="ms-2 text-sm text-gray-600">Recordar este dispositivo por 30 días</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-6">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif

            <x-primary-button class="ms-3">
                Iniciar Sesión
            </x-primary-button>
        </div>

        <div class="mt-6 text-center text-sm text-gray-500">
            ¿No tienes cuenta? <a href="{{ route('register') }}" class="text-indigo-600 font-semibold hover:underline">Regístrate aquí</a>
        </div>
    </form>
</x-guest-layout>
