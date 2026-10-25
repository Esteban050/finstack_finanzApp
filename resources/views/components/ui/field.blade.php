@props(['name', 'label', 'required' => false, 'help' => null, 'error' => null, 'id' => null])
@php
    $fieldError = $error ?? $errors->first($name);
    $fieldId = $id ?? $name;
    if (preg_match('/^validation\.|^[a-z][a-z_-]*(?:\.[a-z_\d-]+)+$/i', (string) $fieldError)) $fieldError = 'Revisa este campo';
    if (preg_match('/SQL|trace|exception|stack|server|servidor|\.php\b/i', (string) $fieldError)) $fieldError = 'Revisa los datos de este campo.';
@endphp
<div {{ $attributes->class('ui-field') }} data-field="{{ $name }}">
    <label for="{{ $fieldId }}">{{ $label }} @if($required)<span aria-hidden="true">*</span><span class="sr-only"> obligatorio</span>@endif</label>
    {{ $slot }}
    @if($help)<p id="{{ $fieldId }}-help" class="ui-help">{{ $help }}</p>@endif
    <p id="{{ $fieldId }}-error" class="ui-error" aria-live="polite" data-field-error @if(!$fieldError) hidden @endif>{{ $fieldError }}</p>
</div>
