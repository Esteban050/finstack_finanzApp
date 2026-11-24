<x-guest-layout>
    <x-slot name="title">Acción no disponible</x-slot>
    {{-- RNF-012: mensaje seguro, sin trazas ni detalles técnicos. --}}
    <h1>Acción no disponible</h1>
    <p>Esta acción no está disponible desde esta dirección.</p>
    <x-ui.button :href="url('/')" variant="primary">Volver al inicio</x-ui.button>
</x-guest-layout>
