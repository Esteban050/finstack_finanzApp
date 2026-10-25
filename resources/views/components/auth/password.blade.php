@props(['name' => 'password', 'id' => null, 'label' => 'Contraseña', 'autocomplete' => 'new-password', 'rules' => false])
@php($inputId = $id ?? $name)
<x-ui.field :name="$name" :id="$inputId" :label="$label" :required="true">
    <div class="auth-password-control">
        <input id="{{ $inputId }}" type="password" name="{{ $name }}" autocomplete="{{ $autocomplete }}" required @if($autocomplete === 'new-password') minlength="10" @endif aria-describedby="{{ $inputId }}-error {{ $inputId }}-client-error {{ $rules ? $inputId.'-rules' : '' }}">
        <x-ui.button variant="ghost" data-password-toggle :aria-controls="$inputId" aria-pressed="false" aria-label="Mostrar contraseña"><span data-password-show><x-icon name="ui-ojo" :size="20" /></span><span data-password-hide hidden><x-icon name="ui-ojo-tachado" :size="20" /></span></x-ui.button>
    </div>
    <p id="{{ $inputId }}-client-error" data-auth-client-error="{{ $name }}" class="ui-error" aria-live="polite" hidden></p>
</x-ui.field>
@if($rules)<x-auth.password-rules :id="$inputId.'-rules'" />@endif
