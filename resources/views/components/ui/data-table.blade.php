@props(['columns', 'rows', 'caption', 'state' => 'success'])
<x-ui.view-state :state="$state === 'success' && count($rows) === 0 ? 'empty' : $state">
    <table class="ui-table" {{ $attributes }}><caption>{{ $caption }}</caption><thead><tr>@foreach($columns as $column)<th scope="col" data-align="{{ ($column['format'] ?? '') === 'money' ? 'right' : 'left' }}">{{ $column['label'] }}</th>@endforeach</tr></thead>
    <tbody>@foreach($rows as $row)<tr>@foreach($columns as $key => $column)<td data-label="{{ $column['label'] }}">@if(($column['format'] ?? '') === 'money')<span data-money="{{ data_get($row, $key) }}">{{ data_get($row, $key) }}</span>@elseif(($column['format'] ?? '') === 'date')<time data-date="{{ data_get($row, $key) }}">{{ data_get($row, $key) }}</time>@else{{ data_get($row, $key) }}@endif</td>@endforeach</tr>@endforeach</tbody></table>
    {{ $slot }}
</x-ui.view-state>
