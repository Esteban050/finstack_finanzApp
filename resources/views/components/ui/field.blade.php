@props(['name', 'label', 'required' => false, 'help' => null, 'error' => null, 'id' => null, 'group' => false])
@php
    $fieldError = $error ?? (isset($errors) ? $errors->first($name) : null);
    $fieldId = $id ?? $name;
    if (preg_match('/^validation\.|^[a-z][a-z_-]*(?:\.[a-z_\d-]+)+$/i', (string) $fieldError)) $fieldError = 'Revisa este campo';
    if (preg_match('/SQL|trace|exception|stack|server|servidor|\.php\b/i', (string) $fieldError)) $fieldError = 'Revisa los datos de este campo.';
@endphp
@if($group)<fieldset {{ $attributes->class('ui-field ui-field-group') }} data-field="{{ $name }}" aria-describedby="{{ $fieldId }}-error">@else<div {{ $attributes->class('ui-field') }} data-field="{{ $name }}">@endif
    @if($group)<legend>@else<label for="{{ $fieldId }}">@endif{{ $label }} @if($required)<span aria-hidden="true">*</span><span class="sr-only"> obligatorio</span>@endif @if($group)</legend>@else</label>@endif
    {{ $slot }}
    @if($help)<p id="{{ $fieldId }}-help" class="ui-help">{{ $help }}</p>@endif
    <p id="{{ $fieldId }}-error" class="ui-error" aria-live="polite" data-field-error @if(!$fieldError) hidden @endif>{{ $fieldError }}</p>
@if($group)</fieldset>@else</div>@endif
