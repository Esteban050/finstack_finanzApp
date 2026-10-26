<?php

namespace App\Services;

use App\Models\TokenSeguridad;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;

class SecurityTokenService
{
    /**
     * Generar token de verificación de correo (RF-002, RN-002.1, RN-002.2)
     * Retorna array con ['token_plano' => ..., 'error' => ...]
     */
    public static function generarTokenVerificacion(Usuario $usuario): array
    {
        $ultimoToken = TokenSeguridad::where('usuario_id', $usuario->id)
            ->where('tipo', 'VERIFICACION_CORREO')
            ->orderByDesc('creado_en')
            ->first();

        if ($ultimoToken) {
            // RN-002.2: Reenvío máx cada 60 segundos
            $segundosPasados = now()->diffInSeconds($ultimoToken->creado_en);
            if ($segundosPasados < 60) {
                $segundosFaltantes = 60 - $segundosPasados;
                return [
                    'error' => "Debes esperar {$segundosFaltantes} segundos antes de solicitar otro correo.",
                    'segundos_faltantes' => $segundosFaltantes,
                ];
            }

            // RN-002.2: Máximo 5 veces en 24 horas
            $reenvios24h = TokenSeguridad::where('usuario_id', $usuario->id)
                ->where('tipo', 'VERIFICACION_CORREO')
                ->where('creado_en', '>=', now()->subHours(24))
                ->count();

            if ($reenvios24h >= 5) {
                return [
                    'error' => 'Has alcanzado el límite máximo de 5 solicitudes de verificación en 24 horas.',
                ];
            }

            // RN-002.2: Cada reenvío invalida los tokens anteriores
            TokenSeguridad::where('usuario_id', $usuario->id)
                ->where('tipo', 'VERIFICACION_CORREO')
                ->whereNull('usado_en')
                ->update(['usado_en' => now()]);
        }

        // RN-002.1: Token de al menos 128 bits (16 bytes = 128 bits = 32 caracteres hexadecimales)
        $tokenPlano = bin2hex(random_bytes(16));
        $tokenHash = Hash::make($tokenPlano);

        TokenSeguridad::create([
            'usuario_id' => $usuario->id,
            'tipo' => 'VERIFICACION_CORREO',
            'token_hash' => $tokenHash,
            'intentos' => 0,
            'reenvios_contador' => ($ultimoToken?->reenvios_contador ?? 0) + 1,
            'expira_en' => now()->addHours(24),
            'ip_origen' => Request::ip(),
            'creado_en' => now(),
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
                $segundos = now()->diffInSeconds($ultimoCodigo->creado_en);
                if ($segundos < 60) {
                    return [
                        'error' => 'Debes esperar ' . (60 - $segundos) . ' segundos para solicitar un nuevo código.',
                    ];
                }
                if ($ultimoCodigo->reenvios_contador >= 3) {
                    return [
                        'error' => 'Has alcanzado el límite máximo de 3 reenvíos de código.',
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
