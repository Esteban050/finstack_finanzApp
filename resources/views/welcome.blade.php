<x-guest-layout>
    <x-slot name="minimal">1</x-slot>
    <h1 class="sr-only">FinanzApp</h1>
    <div class="ui-inline"><x-ui.button :href="route('login')">Iniciar sesión</x-ui.button><x-ui.button :href="route('register')" variant="secondary">Crear cuenta</x-ui.button></div>
</x-guest-layout>
