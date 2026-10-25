@props(['name', 'label', 'required' => false, 'help' => null, 'error' => null])
@php
    $fieldError = $error ?? $errors->first($name);
    if (preg_match('/SQL|trace|exception|stack|server|servidor|\.php\b/i', (string) $fieldError)) $fieldError = 'RNG-05: revisa la validación de este campo.';
@endphp
<div {{ $attributes->class('ui-field') }} data-field="{{ $name }}">
    <label for="{{ $name }}">{{ $label }} @if($required)<span aria-hidden="true">*</span><span class="sr-only"> obligatorio</span>@endif</label>
    {{ $slot }}
    @if($help)<p id="{{ $name }}-help" class="ui-help">{{ $help }}</p>@endif
    <p id="{{ $name }}-error" class="ui-error" aria-live="polite" data-field-error @if(!$fieldError) hidden @endif>{{ $fieldError }}</p>
</div>
