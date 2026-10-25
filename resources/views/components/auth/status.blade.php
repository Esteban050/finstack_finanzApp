@if(session('status'))<p class="auth-status" role="status">{{ session('status') }}</p>@endif
@foreach(['registro_error', 'verificacion_error', 'token', 'auth_error'] as $key)
    @if($errors->has($key))<p class="ui-error" role="alert" tabindex="-1" data-auth-general-error>{{ $errors->first($key) }}</p>@endif
@endforeach
