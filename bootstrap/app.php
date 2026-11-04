<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdminMiddleware::class,
            'politica.check' => \App\Http\Middleware\CheckPoliticaAceptadaMiddleware::class,
            'inactividad' => \App\Http\Middleware\InactivityTimeoutMiddleware::class,
            'perfil.completo' => \App\Http\Middleware\PerfilCompletoMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\InactivityTimeoutMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->respond(function ($response, \Throwable $exception, Request $request) {
            $status = $response->getStatusCode();
            if ($status < 400 || $status === 422) {
                return $response;
            }

            $messages = [
                401 => 'Tu sesión expiró por inactividad',
                403 => 'Acceso denegado',
                404 => 'Recurso no encontrado',
                405 => 'Esta acción no está disponible.',
                419 => 'Tu sesión expiró. Recarga la página para continuar',
                429 => 'Has realizado demasiados intentos. Intenta nuevamente más tarde.',
            ];
            $data = ['message' => $messages[$status] ?? 'No fue posible completar la operación. Intenta nuevamente.'];
            if ($status >= 500) {
                $data['error_id'] = (string) \Illuminate\Support\Str::uuid();
                \Illuminate\Support\Facades\Log::error('Error de aplicación', ['error_id' => $data['error_id'], 'exception' => $exception]);
            }
            if ($request->expectsJson() || $request->is('api/*')) {
                $response->setContent(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $response->headers->set('Content-Type', 'application/json');
            } elseif ($status >= 500) {
                $response->setContent(view('errors.500', ['errorId' => $data['error_id']])->render());
            }
            return $response;
        });
    })->create();
