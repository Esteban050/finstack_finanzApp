@props(['title' => null])
<section {{ $attributes->class('ui-card') }}>@if($title)<h2>{{ $title }}</h2>@endif{{ $slot }}</section>
