<x-guest-layout>
    <x-slot name="title">Demasiados intentos</x-slot>
    {{-- RNF-012: mensaje seguro, sin trazas ni detalles técnicos. --}}
    <h1>Demasiados intentos</h1>
    <p>Has realizado demasiados intentos. Espera un momento e intenta nuevamente.</p>
    <x-ui.button :href="url('/')" variant="primary">Volver al inicio</x-ui.button>
</x-guest-layout>
