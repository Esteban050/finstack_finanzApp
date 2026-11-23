@props(['currency' => 'COP', 'amount' => null, 'rate' => null])
{{-- Informativo: la TRM con la que se convirtió a pesos una operación en USD. No interviene en ningún cálculo. --}}
@if(strtoupper(trim((string) $currency)) === 'USD' && $rate !== null && (float) $rate > 0)
    @php($formatted = rtrim(rtrim(number_format((float) $rate, 4, ',', '.'), '0'), ','))
    <p class="ui-help" data-trm-used>@if($amount !== null)USD {{ number_format((float) $amount, 2, ',', '.') }} · @endif TRM usada: $ {{ $formatted }}</p>
@endif
