<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ui-button ui-button--danger']) }}>
    {{ $slot }}
</button>
