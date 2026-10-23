<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Hemos enviado un código numérico de 6 dígitos a tu correo registrado. Ingrésalo a continuación para completar tu inicio de sesión (vence en 10 minutos).
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('mfa.verify') }}">
        @csrf

        <!-- Código de 6 dígitos -->
        <div>
            <x-input-label for="codigo" value="Código de Verificación (6 dígitos)" />
            <x-text-input id="codigo" class="block mt-1 w-full text-center tracking-widest text-2xl font-mono"
                          type="text"
                          name="codigo"
                          maxlength="6"
                          required
                          autofocus />
            <x-input-error :messages="$errors->get('codigo')" class="mt-2" />
        </div>

        <!-- Recordar dispositivo -->
        <div class="block mt-4">
            <label for="recordar_dispositivo" class="inline-flex items-center">
                <input id="recordar_dispositivo" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="recordar_dispositivo" value="1">
                <span class="ms-2 text-sm text-gray-600">Recordar este dispositivo por 30 días</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-6">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none" href="{{ route('login') }}">
                Cancelar y volver al inicio
            </a>

            <x-primary-button class="ms-3">
                Verificar Código
            </x-primary-button>
        </div>
    </form>

    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between text-sm">
        <span class="text-gray-500">¿No recibiste el código o expiró?</span>
        <form method="POST" action="{{ route('mfa.resend') }}" class="inline">
            @csrf
            <button type="submit" class="underline text-indigo-600 hover:text-indigo-900 font-semibold focus:outline-none">
                Reenviar código
            </button>
        </form>
    </div>
</x-guest-layout>
