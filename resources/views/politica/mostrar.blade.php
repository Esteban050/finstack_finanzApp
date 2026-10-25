<x-guest-layout>
    <x-slot name="title">Política de tratamiento de datos</x-slot>
    @php($yaAcepto = auth()->check() && auth()->user()->aceptacionesPolitica()->where('version_politica', $versionVigente)->whereNull('revocada_en')->exists())
    <h1>Política de tratamiento de datos</h1><p>Versión {{ $versionVigente }}</p>
    <x-auth.status />
    @include('politica.partials.texto')
    @auth
        @if(!$yaAcepto)
            <form method="POST" action="{{ route('politica.aceptar') }}" data-auth-form="policy" novalidate>
                @csrf
                <div data-field="acepto"><label for="acepto" class="auth-checkbox"><input id="acepto" name="acepto" type="checkbox" value="1" required aria-describedby="acepto-error acepto-client-error"><span>He leído y acepto la versión {{ $versionVigente }} de la política de tratamiento de datos personales. *</span></label>
                <p id="acepto-error" class="ui-error" data-field-error aria-live="polite" @if(!$errors->has('acepto')) hidden @endif>{{ $errors->first('acepto') }}</p><p id="acepto-client-error" class="ui-error" data-auth-client-error="acepto" aria-live="polite" hidden></p></div>
                <x-auth.submit label="Aceptar y continuar" :locked="true" />
            </form>
        @else<p class="auth-status" role="status">Has aceptado la versión vigente de esta política.</p><a href="{{ route('dashboard') }}">Volver al resumen</a>@endif
        @if($historial->isNotEmpty())<section class="auth-policy-history"><h2>Historial de aceptaciones</h2><table><thead><tr><th>Versión</th><th>Fecha y hora</th><th>IP de origen</th><th>Medio</th></tr></thead><tbody>@foreach($historial as $item)<tr><td>{{ $item->version_politica }}</td><td>{{ $item->fecha_aceptacion->timezone('America/Bogota')->format('d/m/Y H:i') }}</td><td>{{ $item->ip_origen }}</td><td>{{ $item->medio }}</td></tr>@endforeach</tbody></table></section>@endif
    @endauth
</x-guest-layout>
