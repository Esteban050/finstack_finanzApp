<?php

namespace Tests\Feature\Auth;

use App\Rules\PasswordPolicyRule;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * RN-001: política de contraseña. Longitud en el borde (9/10/64/65) y cada regla
 * por separado, con su mensaje.
 */
class PoliticaContrasenaTest extends TestCase
{
    private function errores(string $clave, ?string $correo = 'ana.perez@scfp.test', ?string $documento = '10203040'): array
    {
        return Validator::make(['password' => $clave], ['password' => [new PasswordPolicyRule($correo, $documento, null)]])
            ->errors()->get('password');
    }

    /** Claves que solo varían en longitud: siempre con mayúscula, minúscula, dígito y especial. */
    public static function longitudes(): array
    {
        $base = fn (int $n) => 'Aa1#' . str_repeat('x', $n - 4);

        return [
            '9 caracteres: rechaza' => [$base(9), false],
            '10 caracteres: acepta' => [$base(10), true],
            '64 caracteres: acepta' => [$base(64), true],
            '65 caracteres: rechaza' => [$base(65), false],
        ];
    }

    #[DataProvider('longitudes')]
    public function test_longitud_en_el_borde(string $clave, bool $valida): void
    {
        $errores = $this->errores($clave);
        if ($valida) {
            $this->assertSame([], $errores);
        } else {
            $this->assertContains('La contraseña debe tener entre 10 y 64 caracteres.', $errores);
        }
    }

    public static function reglas(): array
    {
        return [
            'sin mayúscula' => ['aa1#xxxxxxxx', 'La contraseña debe contener al menos una letra mayúscula.'],
            'sin minúscula' => ['AA1#XXXXXXXX', 'La contraseña debe contener al menos una letra minúscula.'],
            'sin dígito' => ['Aab#xxxxxxxx', 'La contraseña debe contener al menos un dígito.'],
            'sin carácter especial' => ['Aa1xxxxxxxxx', 'La contraseña debe contener al menos un carácter especial (@, $, !, %, *, #, ?, &, etc.).'],
            'contiene el correo' => ['Aa1#ana.perez@scfp.test', 'La contraseña no puede contener tu correo electrónico.'],
            'contiene la parte local del correo' => ['Aa1#ana.perezX', 'La contraseña no puede contener tu correo electrónico.'],
            'contiene el documento' => ['Aa1#10203040x', 'La contraseña no puede contener tu número de documento.'],
        ];
    }

    #[DataProvider('reglas')]
    public function test_cada_regla_se_valida_con_su_mensaje(string $clave, string $mensaje): void
    {
        $errores = $this->errores($clave);
        $this->assertContains($mensaje, $errores);
        $this->assertCount(1, $errores, 'Solo debe fallar la regla probada: ' . implode(' | ', $errores));
    }
}
