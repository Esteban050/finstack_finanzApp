@props(['message' => null])
<div id="ui-toast" class="ui-toast" role="status" aria-live="polite" @if(!$message) hidden @endif><span data-toast-message>{{ $message }}</span><x-ui.button variant="ghost" data-dismiss-toast aria-label="Cerrar mensaje"><x-icon name="ui-cerrar" :size="18" /></x-ui.button></div>
