@props(['lines' => 3])
<div {{ $attributes->class('ui-skeleton') }} role="status"><span class="sr-only">Cargando…</span>@for($i = 0; $i < $lines; $i++)<div aria-hidden="true"></div>@endfor</div>
