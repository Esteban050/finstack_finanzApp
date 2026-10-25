@props(['label', 'loading' => 'Enviando…', 'locked' => false])
<x-ui.button type="submit" class="auth-submit" data-auth-submit :disabled="$locked" :data-loading-label="$loading"><span data-auth-submit-label>{{ $label }}</span><span data-auth-submit-spinner hidden><x-icon name="ui-cargando" :size="18" /></span></x-ui.button>
