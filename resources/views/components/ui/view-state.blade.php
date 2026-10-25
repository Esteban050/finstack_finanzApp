@props(['state' => 'success', 'message' => null, 'illustration' => 'empty_resultados'])
<div data-view-state="{{ $state }}" aria-busy="{{ $state === 'loading' ? 'true' : 'false' }}" {{ $attributes }}>
@if($state === 'loading')<x-ui.skeleton />
@elseif($state === 'empty')<x-ui.empty-state :illustration="$illustration" />
@elseif($state === 'error')<div role="alert" aria-live="assertive"><x-ui.empty-state illustration="error_generico" title="No fue posible completar la operación." />{{ $slot }}</div>
@else @if($message)<p role="status">{{ $message }}</p>@endif{{ $slot }} @endif
</div>
