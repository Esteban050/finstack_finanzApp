<?php

namespace App\Rules;

use App\Models\Usuario;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

class PasswordPolicyRule implements ValidationRule
{
    protected ?string $correo;
    protected ?string $numeroDocumento;
    protected ?Usuario $usuario;

    public function __construct(?string $correo = null, ?string $numeroDocumento = null, ?Usuario $usuario = null)
    {
        $this->correo = $correo ? strtolower(trim($correo)) : null;
        $this->numeroDocumento = $numeroDocumento ? trim($numeroDocumento) : null;
        $this->usuario = $usuario;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;
        $length = mb_strlen($password);

        // Longitud 10-64
        if ($length < 10 || $length > 64) {
            $fail('La contraseña debe tener entre 10 y 64 caracteres.');
            return;
        }

        // Al menos una mayúscula
        if (!preg_match('/[A-Z]/', $password)) {
            $fail('La contraseña debe contener al menos una letra mayúscula.');
            return;
        }

        // Al menos una minúscula
        if (!preg_match('/[a-z]/', $password)) {
            $fail('La contraseña debe contener al menos una letra minúscula.');
            return;
        }

        // Al menos un dígito
        if (!preg_match('/[0-9]/', $password)) {
            $fail('La contraseña debe contener al menos un dígito.');
            return;
        }

        // Al menos un carácter especial
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $fail('La contraseña debe contener al menos un carácter especial (@, $, !, %, *, #, ?, &, etc.).');
            return;
        }

        // No contener el correo electrónico o parte del usuario del correo
        if ($this->correo) {
            $emailParts = explode('@', $this->correo);
            $localPart = $emailParts[0] ?? '';
            if (stripos($password, $this->correo) !== false || (strlen($localPart) >= 3 && stripos($password, $localPart) !== false)) {
                $fail('La contraseña no puede contener tu correo electrónico.');
                return;
            }
        }

        // No contener el número de documento
        if ($this->numeroDocumento && strlen($this->numeroDocumento) >= 3) {
            if (stripos($password, $this->numeroDocumento) !== false) {
                $fail('La contraseña no puede contener tu número de documento.');
                return;
            }
        }

        // RN-005.3: No reutilizar las últimas 3 contraseñas
        if ($this->usuario) {
            // Comparar contra contraseña actual
            if (Hash::check($password, $this->usuario->password_hash)) {
                $fail('No puedes reutilizar contraseñas recientes.');
                return;
            }

            // Comparar contra últimas 3 contraseñas del historial
            $historial = $this->usuario->historialContrasenas()
                ->orderByDesc('creado_en')
                ->take(3)
                ->get();

            foreach ($historial as $item) {
                if (Hash::check($password, $item->password_hash)) {
                    $fail('No puedes reutilizar contraseñas recientes.');
                    return;
                }
            }
        }
    }
}
