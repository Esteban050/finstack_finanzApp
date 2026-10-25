@props(['messages'])

@if ($messages)
    <ul aria-live="polite" {{ $attributes->merge(['class' => 'ui-error text-sm space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li><x-ui.safe-message :value="$message" /></li>
        @endforeach
    </ul>
@endif
