@props(['paginator'])
<nav class="ui-pagination" aria-label="Paginación" {{ $attributes }}>
    <p>{{ $paginator->total() }} registros · Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</p>
    <form method="GET" action="{{ url()->current() }}" class="ui-inline">
        @foreach(request()->except(['page', 'por_pagina']) as $key => $value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
        <label for="per-page">Registros por página</label><select id="per-page" name="por_pagina">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected($paginator->perPage() === $size)>{{ $size }}</option>@endforeach</select><x-ui.button type="submit" variant="secondary">Aplicar</x-ui.button>
    </form>
    <div class="ui-inline">@if($paginator->previousPageUrl())<x-ui.button :href="$paginator->withQueryString()->previousPageUrl()" variant="secondary">Anterior</x-ui.button>@endif @if($paginator->hasMorePages())<x-ui.button :href="$paginator->withQueryString()->nextPageUrl()" variant="secondary">Siguiente</x-ui.button>@endif</div>
</nav>
