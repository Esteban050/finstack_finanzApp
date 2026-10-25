@props(['name', 'size' => 24, 'strokeWidth' => 1.75, 'label' => null, 'className' => ''])
<span data-icon-name="{{ $name }}" data-icon-size="{{ $size }}" data-icon-stroke-width="{{ $strokeWidth }}" @if($label) data-icon-label="{{ $label }}" @endif {{ $attributes->merge(['class' => $className]) }}></span>
