<button {{ $attributes->merge(['type' => 'button', 'class' => 'ui-button ui-button--secondary']) }}>
    {{ $slot }}
</button>
