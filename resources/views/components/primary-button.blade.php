<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ui-button ui-button--primary']) }}>
    {{ $slot }}
</button>
