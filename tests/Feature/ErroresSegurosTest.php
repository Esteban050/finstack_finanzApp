<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * RNG-010 / RNF-012 (CP-RNG-010): los errores 404, 405, 419, 429 y 500 dan un
 * mensaje seguro en español, sin trazas, rutas de archivos ni SQL, tanto en
 * HTML como en JSON. Se prueba con APP_DEBUG=false, como en producción.
 */
class ErroresSegurosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        Route::middleware('web')->group(function () {
            Route::get('/_prueba/error-500', fn () => throw new \RuntimeException('SQLSTATE[42P01] relation "tabla_secreta" does not exist'));
            Route::get('/_prueba/limitada', fn () => 'ok')->middleware('throttle:1,1');
            Route::post('/_prueba/formulario', fn () => 'ok');
        });
    }

    private function assertSinDetallesTecnicos(string $contenido): void
    {
        $this->assertDoesNotMatchRegularExpression('/SQLSTATE|Stack trace|vendor[\\\\\/]|\.php:\d+|tabla_secreta|Exception/i', $contenido);
    }

    public function test_404_y_405(): void
    {
        $html = $this->get('/no-existe-esta-ruta')->assertNotFound();
        $html->assertSee('Recurso no encontrado');
        $this->assertSinDetallesTecnicos($html->getContent());
        $this->getJson('/no-existe-esta-ruta')->assertNotFound()->assertExactJson(['message' => 'Recurso no encontrado']);

        $html = $this->get('/logout')->assertStatus(405);
        $html->assertSee('Esta acción no está disponible');
        $this->assertSinDetallesTecnicos($html->getContent());
        $this->getJson('/logout')->assertStatus(405)->assertExactJson(['message' => 'Esta acción no está disponible.']);
    }

    public function test_419_formulario_vencido(): void
    {
        // En pruebas la verificación CSRF se omite; se fuerza con un token inválido.
        $this->withMiddleware();
        $this->app['env'] = 'production';

        $html = $this->post('/_prueba/formulario', ['_token' => 'vencido'])->assertStatus(419);
        $html->assertSee('Tu sesión expiró');
        $this->assertSinDetallesTecnicos($html->getContent());
        $this->postJson('/_prueba/formulario', ['_token' => 'vencido'])->assertStatus(419)
            ->assertExactJson(['message' => 'Tu sesión expiró. Recarga la página para continuar']);
    }

    public function test_429_demasiados_intentos(): void
    {
        $this->get('/_prueba/limitada')->assertOk();
        $html = $this->get('/_prueba/limitada')->assertStatus(429);
        $html->assertSee('Demasiados intentos');
        $this->assertSinDetallesTecnicos($html->getContent());
        $this->getJson('/_prueba/limitada')->assertStatus(429)
            ->assertExactJson(['message' => 'Has realizado demasiados intentos. Intenta nuevamente más tarde.']);
    }

    public function test_500_con_referencia_y_sin_trazas(): void
    {
        $html = $this->get('/_prueba/error-500')->assertStatus(500);
        $html->assertSee('No fue posible completar la operación');
        $this->assertSinDetallesTecnicos($html->getContent());

        $json = $this->getJson('/_prueba/error-500')->assertStatus(500);
        $this->assertSame('No fue posible completar la operación. Intenta nuevamente.', $json->json('message'));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $json->json('error_id'));
        $this->assertSinDetallesTecnicos($json->getContent());
    }
}
