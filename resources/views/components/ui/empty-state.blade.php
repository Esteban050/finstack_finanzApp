@props(['illustration' => 'empty_resultados', 'title' => 'No se encontraron resultados', 'description' => null])
<div {{ $attributes->class('ui-empty') }} role="status"><span data-illustration="{{ $illustration }}" aria-hidden="true"></span><h2>{{ $title }}</h2>@if($description)<p>{{ $description }}</p>@endif{{ $slot }}</div>
