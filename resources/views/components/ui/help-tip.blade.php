@props(['text', 'label' => 'Ayuda'])
<details {{ $attributes->class('ui-help-tip') }}><summary aria-label="{{ $label }}"><x-icon name="ui-ayuda" :size="18" /></summary><p role="note">{{ $text }}</p></details>
