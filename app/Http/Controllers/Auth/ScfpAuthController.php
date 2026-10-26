<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AceptacionPolitica;
use App\Models\HistorialContrasena;
use App\Models\TokenSeguridad;
use App\Models\Usuario;
use App\Rules\PasswordPolicyRule;
use App\Services\AuthSecurityService;
use App\Services\BitacoraService;
use App\Services\SecurityTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ScfpAuthController extends Controller
{
    /* =========================================================================
       RF-001: REGISTRO DE USUARIO
    ========================================================================= */

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        // RN-001.1: Validaciones de campos obligatorios y formatos
        $validated = $request->validate([
            'nombres' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\pL\s]+$/u'],
            'apellidos' => ['required', 'string', 'min:2', 'max:60'],
            'tipo_documento' => ['required', 'string', 'in:CC,CE,PA,PPT'],
            'numero_documento' => ['required', 'string', 'min:5', 'max:15', 'alpha_num'],
            'correo' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'confirmed', new PasswordPolicyRule($request->correo, $request->numero_documento)],
            'acepta_politica' => ['required', 'accepted'],
        ], [
            'nombres.regex' => 'Los nombres solo deben contener letras y espacios.',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'acepta_politica.accepted' => 'Debes aceptar la política de tratamiento de datos personales para continuar.',
        ]);

        $correoLower = strtolower(trim($validated['correo']));
        $tipoDoc = $validated['tipo_documento'];
        $numDoc = trim($validated['numero_documento']);

        // RN-001.2 & CA-001.2: Unicidad de correo e identificación con mensaje genérico
        $existe = Usuario::whereRaw('LOWER(correo) = ?', [$correoLower])
            ->orWhere(function ($q) use ($tipoDoc, $numDoc) {
                $q->where('tipo_documento', $tipoDoc)
                  ->where('numero_documento', $numDoc);
            })->exists();

        if ($existe) {
            return back()->withInput()->withErrors([
                'registro_error' => 'No fue posible completar el registro con los datos suministrados.',
            ]);
        }

        DB::beginTransaction();
        try {
            // RN-001.3 & RN-001.5: Creación de cuenta en estado PENDIENTE_VERIFICACION
            $usuario = Usuario::create([
                'nombres' => trim($validated['nombres']),
                'apellidos' => trim($validated['apellidos']),
                'tipo_documento' => $tipoDoc,
                'numero_documento' => $numDoc,
                'correo' => $correoLower,
                'password_hash' => Hash::make($validated['password']),
                'rol' => 'USUARIO',
                'estado' => 'PENDIENTE_VERIFICACION',
            ]);

            // RN-001.4: Registro obligatorio de aceptación de política
            AceptacionPolitica::create([
                'usuario_id' => $usuario->id,
                'version_politica' => config('app.version_politica_datos', 'v1.0'),
                'fecha_aceptacion' => now(),
                'ip_origen' => $request->ip() ?? '127.0.0.1',
                'medio' => 'WEB_REGISTRO',
            ]);

            // Guardar en historial de contraseñas
            HistorialContrasena::create([
                'usuario_id' => $usuario->id,
                'password_hash' => $usuario->password_hash,
                'creado_en' => now(),
            ]);

            // RN-002.1: Enviar correo de verificación
            $resultadoToken = SecurityTokenService::generarTokenVerificacion($usuario);
            if (isset($resultadoToken['token_plano'])) {
                $this->enviarCorreoVerificacion($usuario, $resultadoToken['token_plano']);
            }

            BitacoraService::registrar(
                accion: 'REGISTRO_USUARIO',
                entidadAfectada: 'usuarios',
                entidadId: $usuario->id,
                valorNuevo: ['correo' => $correoLower, 'estado' => 'PENDIENTE_VERIFICACION'],
                usuarioId: $usuario->id
            );

            DB::commit();

            return redirect()->route('login')->with('status', 'Revisa tu correo para activar la cuenta');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->withErrors([
                'registro_error' => 'Ocurrió un error inesperado al procesar el registro.',
            ]);
        }
    }

    /* =========================================================================
       RF-002: VERIFICACIÓN DE CORREO ELECTRÓNICO
    ========================================================================= */

    public function verifyEmail(string $token): RedirectResponse
    {
        $resultado = SecurityTokenService::validarTokenVerificacion($token);

        if (!$resultado['valido']) {
            return redirect()->route('login')->withErrors([
                'verificacion_error' => $resultado['error'] ?? 'El enlace no es válido o expiró',
            ]);
        }

        $usuario = $resultado['usuario'];

        BitacoraService::registrar(
            accion: 'VERIFICACION_CORREO_EXITOSA',
            entidadAfectada: 'usuarios',
            entidadId: $usuario->id,
            valorNuevo: ['estado' => 'ACTIVA'],
            usuarioId: $usuario->id
        );

        return redirect()->route('login')->with('status', 'Cuenta verificada');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $request->validate(['correo' => 'required|email']);
        $usuario = Usuario::whereRaw('LOWER(correo) = ?', [strtolower(trim($request->correo))])->first();

        if (!$usuario || $usuario->estado !== 'PENDIENTE_VERIFICACION') {
            return back()->with('status', 'Si tu cuenta está pendiente de verificación, recibirás un correo.');
        }

        $res = SecurityTokenService::generarTokenVerificacion($usuario);
        if (isset($res['error'])) {
            return back()->withErrors(['correo' => $res['error']]);
        }

        $this->enviarCorreoVerificacion($usuario, $res['token_plano']);

        return back()->with('status', 'Correo de verificación reenviado exitosamente.');
    }

    /* =========================================================================
       RF-003 & RF-004: INICIO DE SESIÓN Y BLOQUEO POR INTENTOS
    ========================================================================= */

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'correo' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $correoLower = strtolower(trim($credentials['correo']));
        $usuario = Usuario::whereRaw('LOWER(correo) = ?', [$correoLower])->first();

        // Si el usuario no existe
        if (!$usuario) {
            BitacoraService::registrar(
                accion: 'INTENTO_LOGIN_FALLIDO',
                entidadAfectada: 'usuarios',
                justificacion: 'Usuario no existe'
            );
            return back()->withInput($request->only('correo'))->withErrors([
                'correo' => 'Correo o contraseña incorrectos',
            ]);
        }

        // RN-004.1: Verificar si la cuenta está bloqueada temporalmente
        if ($usuario->estaBloqueada()) {
            return back()->withInput($request->only('correo'))->withErrors([
                'correo' => 'Tu cuenta se encuentra bloqueada temporalmente por intentos fallidos. Intenta más tarde.',
            ]);
        }

        // Si expiró el tiempo de bloqueo, desbloquearla
        if ($usuario->estado === 'BLOQUEADA' && $usuario->bloqueado_hasta && now()->greaterThan($usuario->bloqueado_hasta)) {
            $usuario->update([
                'estado' => 'ACTIVA',
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ]);
        }

        // RN-003.1: Solo cuentas en estado ACTIVA
        if ($usuario->estado !== 'ACTIVA') {
            return back()->withInput($request->only('correo'))->withErrors([
                'correo' => 'Pendiente de verificación, bloqueada o inactivada',
            ]);
        }

        // Validar contraseña
        if (!Hash::check($credentials['password'], $usuario->password_hash)) {
            AuthSecurityService::registrarIntentoFallido($usuario);
            return back()->withInput($request->only('correo'))->withErrors([
                'correo' => 'Correo o contraseña incorrectos',
            ]);
        }

        // RF-012: Verificación en dos pasos (MFA) si está activo
        if ($usuario->mfa_activo && !AuthSecurityService::dispositivoEstaRecordado($usuario)) {
            $resMfa = SecurityTokenService::generarCodigoMFA($usuario);
            if (isset($resMfa['codigo'])) {
                $this->enviarCodigoMfaPorCorreo($usuario, $resMfa['codigo']);
            }
            session(['mfa_pending_user_id' => $usuario->id]);
            return redirect()->route('mfa.view');
        }

        // Login exitoso
        Auth::login($usuario);
        $request->session()->regenerate();
        AuthSecurityService::registrarInicioSesionExitoso($usuario, $request->boolean('recordar_dispositivo'));

        // RN-003.5: Redirección según rol
        if ($usuario->esAdmin()) {
            return redirect()->intended('/admin/dashboard');
        }

        return redirect()->intended('/dashboard');
    }

    /* =========================================================================
       RF-012: VERIFICACIÓN EN DOS PASOS (MFA)
    ========================================================================= */

    public function showMfa(): View|RedirectResponse
    {
        if (!session('mfa_pending_user_id')) {
            return redirect()->route('login');
        }
        return view('auth.mfa');
    }

    public function verifyMfa(Request $request): RedirectResponse
    {
        $userId = session('mfa_pending_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $usuario = Usuario::findOrFail($userId);
        $request->validate(['codigo' => 'required|digits:6']);

        $res = SecurityTokenService::validarCodigoMFA($usuario, $request->codigo);

        if (!$res['valido']) {
            if (!empty($res['agotado'])) {
                session()->forget('mfa_pending_user_id');
                return redirect()->route('login')->withErrors(['correo' => $res['mensaje']]);
            }
            return back()->withErrors(['codigo' => $res['mensaje']]);
        }

        session()->forget('mfa_pending_user_id');
        Auth::login($usuario);
        $request->session()->regenerate();
        AuthSecurityService::registrarInicioSesionExitoso($usuario, $request->boolean('recordar_dispositivo'));

        if ($usuario->esAdmin()) {
            return redirect()->intended('/admin/dashboard');
        }

        return redirect()->intended('/dashboard');
    }

    public function resendMfa(Request $request): RedirectResponse
    {
        $userId = session('mfa_pending_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $usuario = Usuario::findOrFail($userId);
        $res = SecurityTokenService::generarCodigoMFA($usuario, esReenvio: true);

        if (isset($res['error'])) {
            return back()->withErrors(['codigo' => $res['error']]);
        }

        $this->enviarCodigoMfaPorCorreo($usuario, $res['codigo']);

        return back()->with('status', 'Hemos enviado un nuevo código de 6 dígitos a tu correo.');
    }

    /* =========================================================================
       RF-005 & RF-007: RECUPERACIÓN Y CAMBIO DE CONTRASEÑA
    ========================================================================= */

    public function forgotPassword(Request $request): RedirectResponse
    {
        $request->validate(['correo' => 'required|email']);
        $correoLower = strtolower(trim($request->correo));
        $usuario = Usuario::whereRaw('LOWER(correo) = ?', [$correoLower])->first();

        // RN-005.2: Siempre responde el mismo mensaje
        if ($usuario && $usuario->estado === 'ACTIVA') {
            $tokenPlano = SecurityTokenService::generarTokenRecuperacion($usuario);
            $this->enviarCorreoRecuperacion($usuario, $tokenPlano);
        }

        return back()->with('status', 'Si el correo está registrado, recibirás un enlace');
    }

    public function showResetPassword(string $token): View
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $tokenModel = SecurityTokenService::validarTokenRecuperacion($request->token);

        if (!$tokenModel || $tokenModel->haExpirado()) {
            return redirect()->route('login')->withErrors([
                'correo' => 'El enlace no es válido o expiró',
            ]);
        }

        $usuario = $tokenModel->usuario;

        $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                new PasswordPolicyRule($usuario->correo, $usuario->numero_documento, $usuario),
            ],
        ], [
            'password.confirmed' => 'Las contraseñas no coinciden',
        ]);

        $nuevoHash = Hash::make($request->password);
        $usuario->update([
            'password_hash' => $nuevoHash,
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'estado' => $usuario->estado === 'BLOQUEADA' ? 'ACTIVA' : $usuario->estado,
        ]);

        $tokenModel->update(['usado_en' => now()]);

        // Guardar en historial
        HistorialContrasena::create([
            'usuario_id' => $usuario->id,
            'password_hash' => $nuevoHash,
            'creado_en' => now(),
        ]);

        // RN-005.4: Cerrar todas las sesiones activas y notificar
        AuthSecurityService::cerrarTodasLasSesiones($usuario);

        BitacoraService::registrar(
            accion: 'RESTABLECIMIENTO_CONTRASENA',
            entidadAfectada: 'usuarios',
            entidadId: $usuario->id,
            usuarioId: $usuario->id
        );

        return redirect()->route('login')->with('status', 'Contraseña restablecida con éxito. Inicia sesión con tu nueva contraseña.');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $usuario = Auth::user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => [
                'required',
                'string',
                'confirmed',
                new PasswordPolicyRule($usuario->correo, $usuario->numero_documento, $usuario),
            ],
        ], [
            'password.confirmed' => 'Las contraseñas no coinciden',
        ]);

        // CA-007.1: Contraseña actual incorrecta
        if (!Hash::check($request->current_password, $usuario->password_hash)) {
            AuthSecurityService::registrarIntentoFallido($usuario);
            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta']);
        }

        $nuevoHash = Hash::make($request->password);
        $usuario->update(['password_hash' => $nuevoHash]);

        HistorialContrasena::create([
            'usuario_id' => $usuario->id,
            'password_hash' => $nuevoHash,
            'creado_en' => now(),
        ]);

        // RN-007.2: Cerrar las demás sesiones activas excepto la actual
        AuthSecurityService::cerrarOtrasSesiones($usuario);

        BitacoraService::registrar(
            accion: 'CAMBIO_CONTRASENA_VOLUNTARIO',
            entidadAfectada: 'usuarios',
            entidadId: $usuario->id,
            usuarioId: $usuario->id
        );

        return back()->with('status', 'Contraseña actualizada');
    }

    /* =========================================================================
       RF-006: CIERRE DE SESIÓN
    ========================================================================= */

    public function logout(Request $request): RedirectResponse
    {
        AuthSecurityService::cerrarSesionActual();
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /* =========================================================================
       MÉTODOS PRIVADOS DE CORREO
    ========================================================================= */

    protected function enviarCorreoVerificacion(Usuario $usuario, string $token): void
    {
        $url = route('verification.verify.token', ['token' => $token]);
        try {
            Mail::raw(
                "Hola {$usuario->nombres},\n\nGracias por registrarte en SCFP. Para activar tu cuenta, haz clic en el siguiente enlace:\n{$url}\n\nEste enlace vencerá en 24 horas y solo puede utilizarse una vez.",
                function ($m) use ($usuario) {
                    $m->to($usuario->correo)->subject('Verifica tu correo electrónico - SCFP');
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("No se pudo enviar verificación de correo: {$e->getMessage()}");
        }
    }

    protected function enviarCorreoRecuperacion(Usuario $usuario, string $token): void
    {
        $url = route('password.reset.custom', ['token' => $token]);
        try {
            Mail::raw(
                "Hola {$usuario->nombres},\n\nRecibimos una solicitud para restablecer tu contraseña. Haz clic en el enlace para continuar:\n{$url}\n\nEste enlace vencerá en 30 minutos.",
                function ($m) use ($usuario) {
                    $m->to($usuario->correo)->subject('Recuperación de contraseña - SCFP');
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("No se pudo enviar correo de recuperación: {$e->getMessage()}");
        }
    }

    protected function enviarCodigoMfaPorCorreo(Usuario $usuario, string $codigo): void
    {
        try {
            Mail::raw(
                "Tu código de acceso de un solo uso es: {$codigo}\n\nFecha y hora: " . now()->format('Y-m-d H:i:s') . "\nIP de origen: " . request()->ip() . "\n\nSi no fuiste tú, cambia tu contraseña de inmediato.",
                function ($m) use ($usuario) {
                    $m->to($usuario->correo)->subject('Código de verificación - SCFP');
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("No se pudo enviar código MFA: {$e->getMessage()}");
        }
    }
}
