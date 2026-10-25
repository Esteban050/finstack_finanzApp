@props(['variant' => 'primary', 'type' => 'button', 'href' => null])
@if($href)
<a href="{{ $href }}" {{ $attributes->class(['ui-button', 'ui-button--'.$variant]) }}>{{ $slot }}</a>
@else
<button type="{{ $type }}" {{ $attributes->class(['ui-button', 'ui-button--'.$variant]) }}>{{ $slot }}</button>
@endif
