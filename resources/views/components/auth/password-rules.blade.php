@props(['id' => 'password-rules'])
<div id="{{ $id }}" class="auth-password-rules" data-password-rules>
    <p>La contraseña debe cumplir estas condiciones:</p>
    <ul>@foreach(['length' => 'Entre 10 y 64 caracteres', 'upper' => 'Una letra mayúscula', 'lower' => 'Una letra minúscula', 'digit' => 'Un dígito', 'special' => 'Un carácter especial', 'personal' => 'No contener tu correo ni tu documento'] as $key => $text)
        <li data-password-rule="{{ $key }}"><span data-rule-pending><x-icon name="ui-info" :size="16" /></span><span data-rule-pass hidden><x-icon name="ui-exito" :size="16" /></span><span>{{ $text }}</span><span class="auth-rule-state" data-rule-state>Pendiente</span></li>
    @endforeach</ul>
</div>
