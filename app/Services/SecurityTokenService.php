<?php

namespace App\Services;

use App\Models\TokenSeguridad;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;

class SecurityTokenService
{
    /** RN-002.2 y RN-012.4: espera mínima entre reenvíos. */
    public const ESPERA_REENVIO_SEGUNDOS = 60;

    /** RN-002.2: reenvíos del correo de verificación permitidos en 24 horas. */
    public const MAX_REENVIOS_VERIFICACION = 5;

    /**
     * Generar token de verificación de correo (RF-002, RN-002.1, RN-002.2)
     * Retorna array con ['token_plano' => ..., 'error' => ...]
     */
    public static function generarTokenVerificacion(Usuario $usuario): array
    {
        $ahora = now();
        $ultimoToken = TokenSeguridad::where('usuario_id', $usuario->id)
            ->where('tipo', 'VERIFICACION_CORREO')
            ->orderByDesc('creado_en')
            ->first();
        $reenviosEnVentana = 0;

        if ($ultimoToken) {
            // RN-002.2: Reenvío máx cada 60 segundos. La diferencia se toma en valor
            // absoluto: en Carbon 3 now()->diffInSeconds(pasado) es negativa.
            $segundosPasados = (int) floor($ultimoToken->creado_en->diffInSeconds($ahora, true));
            if ($segundosPasados < self::ESPERA_REENVIO_SEGUNDOS) {
                $segundosFaltantes = self::ESPERA_REENVIO_SEGUNDOS - $segundosPasados;
                return [
                    'error' => "Debes esperar {$segundosFaltantes} segundos antes de solicitar otro correo.",
                    'segundos_faltantes' => $segundosFaltantes,
                ];
            }

            // RN-002.2: Máximo 5 reenvíos en 24 horas. El correo del registro (el
            // primer token de la cuenta) no cuenta como reenvío.
            $primerTokenId = TokenSeguridad::where('usuario_id', $usuario->id)
                ->where('tipo', 'VERIFICACION_CORREO')
                ->orderBy('creado_en')
                ->value('id');
            $reenvios = TokenSeguridad::where('usuario_id', $usuario->id)
                ->where('tipo', 'VERIFICACION_CORREO')
                ->where('id', '!=', $primerTokenId)
                ->where('creado_en', '>', $ahora->copy()->subHours(24))
                ->orderBy('creado_en')
                ->get(['creado_en']);
            $reenviosEnVentana = $reenvios->count();

            if ($reenviosEnVentana >= self::MAX_REENVIOS_VERIFICACION) {
                $disponible = $reenvios->first()->creado_en->copy()->addHours(24)->timezone('America/Bogota');
                return [
                    'error' => 'Ya no hay más reenvíos permitidos: alcanzaste el máximo de 5 en 24 horas. Podrás solicitar otro correo el '
                        . $disponible->format('d/m/Y') . ' a las ' . $disponible->format('H:i') . '.',
                    'limite_alcanzado' => true,
                ];
            }

            // RN-002.2: Cada reenvío invalida los tokens anteriores
            TokenSeguridad::where('usuario_id', $usuario->id)
                ->where('tipo', 'VERIFICACION_CORREO')
                ->whereNull('usado_en')
                ->update(['usado_en' => $ahora]);
        }

        // RN-002.1: Token de al menos 128 bits (16 bytes = 128 bits = 32 caracteres hexadecimales)
        $tokenPlano = bin2hex(random_bytes(16));
        $tokenHash = Hash::make($tokenPlano);

        TokenSeguridad::create([
            'usuario_id' => $usuario->id,
            'tipo' => 'VERIFICACION_CORREO',
            'token_hash' => $tokenHash,
            'intentos' => 0,
            // 0 en el registro; 1 a 5 en los reenvíos de la ventana de 24 h (la
            // columna solo admite 0–5).
            'reenvios_contador' => $ultimoToken ? $reenviosEnVentana + 1 : 0,
            'expira_en' => $ahora->copy()->addHours(24),
            'ip_origen' => Request::ip(),
            'creado_en' => $ahora,
        ]);

        return ['token_plano' => $tokenPlano];
    }

    /**
     * Verificar token de correo (RF-002, CA-002.1, CA-002.2)
     */
    public static function validarTokenVerificacion(string $tokenPlano): array
    {
        $tokens = TokenSeguridad::where('tipo', 'VERIFICACION_CORREO')
            ->whereNull('usado_en')
            ->where('expira_en', '>', now())
            ->get();

        foreach ($tokens as $token) {
            if (Hash::check($tokenPlano, $token->token_hash)) {
                $token->update(['usado_en' => now()]);
                $usuario = $token->usuario;
                $usuario->update([
                    'estado' => 'ACTIVA',
                    'intentos_fallidos' => 0,
                    'bloqueado_hasta' => null,
                ]);
                return [
                    'valido' => true,
                    'usuario' => $usuario,
                    'error' => null,
                    'token_usado' => false,
                ];
            }
        }

        // Si no está entre los vigentes y no usados, verificar si ya fue utilizado
        $tokensUsados = TokenSeguridad::where('tipo', 'VERIFICACION_CORREO')
            ->whereNotNull('usado_en')
            ->orderByDesc('usado_en')
            ->get();

        foreach ($tokensUsados as $token) {
            if (Hash::check($tokenPlano, $token->token_hash)) {
                return [
                    'valido' => false,
                    'usuario' => $token->usuario,
                    'error' => 'El token ya se usó',
                    'token_usado' => true,
                ];
            }
        }

        return [
            'valido' => false,
            'usuario' => null,
            'error' => 'El enlace no es válido o expiró',
            'token_usado' => false,
        ];
    }

    /**
     * Generar token de recuperación de contraseña (RF-005, RN-005.1)
     */
    public static function generarTokenRecuperacion(Usuario $usuario): string
    {
        // Invalidar tokens previos de recuperación
        TokenSeguridad::where('usuario_id', $usuario->id)
            ->where('tipo', 'RECUPERACION_PASS')
            ->whereNull('usado_en')
            ->update(['usado_en' => now()]);

        $tokenPlano = bin2hex(random_bytes(24));
        $tokenHash = Hash::make($tokenPlano);

        TokenSeguridad::create([
            'usuario_id' => $usuario->id,
            'tipo' => 'RECUPERACION_PASS',
            'token_hash' => $tokenHash,
            'intentos' => 0,
            'expira_en' => now()->addMinutes(30),
            'ip_origen' => Request::ip(),
            'creado_en' => now(),
        ]);

        return $tokenPlano;
    }

    /**
     * Validar token de recuperación
     */
    public static function validarTokenRecuperacion(string $tokenPlano): ?TokenSeguridad
    {
        $tokens = TokenSeguridad::where('tipo', 'RECUPERACION_PASS')
            ->whereNull('usado_en')
            ->where('expira_en', '>', now())
            ->get();

        foreach ($tokens as $token) {
            if (Hash::check($tokenPlano, $token->token_hash)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Generar código MFA de 6 dígitos numéricos (RF-012, RN-012.2, RN-012.4)
     */
    public static function generarCodigoMFA(Usuario $usuario, bool $esReenvio = false): array
    {
        $ultimoCodigo = TokenSeguridad::where('usuario_id', $usuario->id)
            ->where('tipo', 'MFA_CODIGO')
            ->whereNull('usado_en')
            ->where('expira_en', '>', now())
            ->orderByDesc('creado_en')
            ->first();

        $contadorReenvios = 0;

        if ($esReenvio) {
            if ($ultimoCodigo) {
                $segundos = (int) floor($ultimoCodigo->creado_en->diffInSeconds(now(), true));
                if ($segundos < self::ESPERA_REENVIO_SEGUNDOS) {
                    return [
                        'error' => 'Debes esperar ' . (self::ESPERA_REENVIO_SEGUNDOS - $segundos) . ' segundos para solicitar un nuevo código.',
                    ];
                }
                if ($ultimoCodigo->reenvios_contador >= 3) {
                    return [
                        'error' => 'Ya no hay más reenvíos de código permitidos: alcanzaste el máximo de 3. Vuelve a iniciar sesión para recibir un código nuevo.',
                        'limite_alcanzado' => true,
                    ];
                }
                $contadorReenvios = $ultimoCodigo->reenvios_contador + 1;
            }
        }

        // Invalidar anteriores
        TokenSeguridad::where('usuario_id', $usuario->id)
            ->where('tipo', 'MFA_CODIGO')
            ->whereNull('usado_en')
            ->update(['usado_en' => now()]);

        // 6 dígitos criptográficamente seguros
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $tokenHash = Hash::make($codigo);

        TokenSeguridad::create([
            'usuario_id' => $usuario->id,
            'tipo' => 'MFA_CODIGO',
            'token_hash' => $tokenHash,
            'intentos' => 0,
            'reenvios_contador' => $contadorReenvios,
            'expira_en' => now()->addMinutes(10),
            'ip_origen' => request()->ip(),
            'creado_en' => now(),
        ]);

        return ['codigo' => $codigo];
    }

    /**
     * Validar código MFA (RF-012, RN-012.3)
     */
    public static function validarCodigoMFA(Usuario $usuario, string $codigoIngresado): array
    {
        $token = TokenSeguridad::where('usuario_id', $usuario->id)
            ->where('tipo', 'MFA_CODIGO')
            ->whereNull('usado_en')
            ->orderByDesc('creado_en')
            ->first();

        if (!$token) {
            return ['valido' => false, 'mensaje' => 'No hay un código pendiente o ya fue utilizado.'];
        }

        if ($token->haExpirado()) {
            return ['valido' => false, 'mensaje' => 'El código expiró. Solicita uno nuevo.'];
        }

        if (Hash::check($codigoIngresado, $token->token_hash)) {
            $token->update(['usado_en' => now()]);
            return ['valido' => true];
        }

        // Incrementar intentos
        $token->increment('intentos');
        $intentosRestantes = 5 - $token->intentos;

        if ($intentosRestantes <= 0) {
            $token->update(['usado_en' => now()]);
            // Contar como fallo en cuenta
            AuthSecurityService::registrarIntentoFallido($usuario);
            return [
                'valido' => false,
                'mensaje' => 'Has agotado el número máximo de intentos para este código.',
                'agotado' => true,
            ];
        }

        return [
            'valido' => false,
            'mensaje' => "Código incorrecto. Te quedan {$intentosRestantes} intentos.",
            'intentos_restantes' => $intentosRestantes,
        ];
    }

    /**
     * Generar token para cancelar supresión (RF-010, RN-010.2)
     */
    public static function generarTokenCancelarSupresion(Usuario $usuario): string
    {
        $tokenPlano = bin2hex(random_bytes(24));
        $tokenHash = Hash::make($tokenPlano);

        TokenSeguridad::create([
            'usuario_id' => $usuario->id,
            'tipo' => 'CANCELAR_SUPRESION',
            'token_hash' => $tokenHash,
            'intentos' => 0,
            'expira_en' => now()->addDays(15),
            'ip_origen' => Request::ip(),
            'creado_en' => now(),
        ]);

        return $tokenPlano;
    }
}
