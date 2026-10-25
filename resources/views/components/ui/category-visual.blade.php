@props(['name', 'icon' => null, 'color' => null])
<span data-category-name="{{ $name }}" data-category-icon="{{ $icon }}" data-category-color="{{ $color }}" {{ $attributes->class('ui-inline') }}>{{ $name }}</span>
