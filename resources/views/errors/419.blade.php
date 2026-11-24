<x-guest-layout>
    <x-slot name="title">Tu sesión expiró</x-slot>
    {{-- RNF-012: mensaje seguro, sin trazas ni detalles técnicos. --}}
    <h1>Tu sesión expiró</h1>
    <p>Por seguridad, la página dejó de ser válida. Recarga e intenta de nuevo; tus datos no se enviaron.</p>
    <x-ui.button :href="url()->previous()" variant="primary">Recargar la página</x-ui.button>
</x-guest-layout>
