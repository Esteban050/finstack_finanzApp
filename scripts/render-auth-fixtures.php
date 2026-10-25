<?php
/** Fixtures de presentación, solo CLI: no registrar cuentas, enviar correos ni guardar sesiones. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$session = $app['session']->driver();
$app['request']->setLaravelSession($session);
$out = [];
$render = function (string $view, array $data = [], array $messages = [], ?string $status = null) use ($app, $session): string {
    $session->flush(); $session->put('_token', str_repeat('x', 40));
    if ($status !== null) $session->put('status', $status);
    $errors = new Illuminate\Support\ViewErrorBag();
    $errors->put('default', new Illuminate\Support\MessageBag($messages));
    $app['view']->share('errors', $errors);
    return view($view, $data)->render();
};
foreach (['login', 'register', 'forgot-password', 'mfa', 'verify-email', 'confirm-password'] as $name) $out[$name] = $render('auth.'.$name);
$out['reset-password'] = $render('auth.reset-password', ['token' => 'token-ficticio-sin-cuenta']);
$out['blocked-login'] = $render('auth.login', [], ['correo' => ['Tu cuenta se encuentra bloqueada temporalmente por intentos fallidos. Intenta más tarde.']]);
$out['duplicate-register'] = $render('auth.register', [], ['registro_error' => ['No fue posible completar el registro con los datos suministrados.']]);
$out['verified'] = $render('auth.login', [], [], 'Cuenta verificada');
$out['expired'] = $render('auth.login', [], ['verificacion_error' => ['El enlace no es válido o expiró']]);
$out['recovery-status'] = $render('auth.forgot-password', [], [], 'Si el correo está registrado, recibirás un enlace');
$out['mfa-error'] = $render('auth.mfa', [], ['codigo' => ['Código incorrecto. Te quedan 4 intentos.']]);
$out['welcome'] = $render('welcome');
$out['policy'] = $render('politica.mostrar', ['versionVigente' => 'v1.0', 'historial' => collect()]);
$out['change-password'] = $render('components.auth.change-password', ['email' => 'prueba@example.invalid', 'document' => 'ABC12345']);
echo json_encode($out, JSON_THROW_ON_ERROR);
