<?php

namespace App\Http\Controllers;

use App\Models\PerfilTributario;
use App\Models\TokenSeguridad;
use App\Models\Usuario;
use App\Services\AuthSecurityService;
use App\Services\BitacoraService;
use App\Services\SecurityTokenService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use ZipArchive;

class UserProfileController extends Controller
{
    /* =========================================================================
       RF-008: GESTIÓN DEL PERFIL FINANCIERO Y TRIBUTARIO
    ========================================================================= */

    public function show(): View
    {
        $usuario = Auth::user();
        $perfilActivo = $usuario->perfilTributarioActivo;
        $historialPerfiles = $usuario->perfilesTributarios()->orderByDesc('vigente_desde')->get();
        $historialAceptaciones = $usuario->aceptacionesPolitica()->orderByDesc('fecha_aceptacion')->get();

        return view('profile.show', compact('usuario', 'perfilActivo', 'historialPerfiles', 'historialAceptaciones'));
    }

    public function update(Request $request): RedirectResponse
    {
        $usuario = Auth::user();

        $validated = $request->validate([
            'nombres' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\pL\s]+$/u'],
            'apellidos' => ['required', 'string', 'min:2', 'max:60'],
            'fecha_nacimiento' => ['required', 'date'],
            'ciudad_residencia' => ['required', 'string', 'max:80'],
            'telefono' => ['nullable', 'string', 'digits:10'],
            'ultimos_digitos_nit' => ['nullable', 'string', 'regex:/^[0-9]{2}$/'],
            'personas_a_cargo' => ['required', 'integer', 'between:0,10'],
            'ocupaciones' => ['required', 'array', 'min:1'],
            'ocupaciones.*' => ['in:Asalariado,Independiente por cuenta propia,Contratista por prestación de servicios,Rentista de capital,Pensionado'],
            'es_salario_integral' => ['nullable', 'boolean'],
            'clase_riesgo_arl' => ['nullable', 'integer', 'between:1,5'],
            'arl_obligatoria' => ['nullable', 'boolean'],
            'responsable_iva' => ['required', 'boolean'],
            'vigente_desde' => ['nullable', 'date'],
        ], [
            'nombres.regex' => 'Los nombres solo deben contener letras y espacios.',
            'ultimos_digitos_nit.regex' => 'Los últimos dígitos deben ser exactamente 2 números (00-99).',
        ]);

        // CA-008.3: Debe ser mayor de edad (>= 18 años)
        $edad = Carbon::parse($validated['fecha_nacimiento'])->age;
        if ($edad < 18) {
            return back()->withInput()->withErrors([
                'fecha_nacimiento' => 'Debes ser mayor de edad',
            ]);
        }

        DB::beginTransaction();
        try {
            // Actualizar datos personales básicos del usuario
            $usuario->update([
                'nombres' => trim($validated['nombres']),
                'apellidos' => trim($validated['apellidos']),
            ]);

            $vigenteDesde = !empty($validated['vigente_desde']) ? Carbon::parse($validated['vigente_desde']) : now();
            $perfilActivo = $usuario->perfilesTributarios()->whereNull('vigente_hasta')->first();
            $valorAnterior = $perfilActivo ? $perfilActivo->toArray() : null;

            if ($perfilActivo) {
                $fechaDesdeActivo = Carbon::parse($perfilActivo->vigente_desde)->startOfDay();
                $nuevaFechaDesde = $vigenteDesde->copy()->startOfDay();

                // Si se actualiza en la misma fecha (o anterior), se actualiza el perfil activo existente directamente
                if ($nuevaFechaDesde->lessThanOrEqualTo($fechaDesdeActivo)) {
                    $perfilActivo->update([
                        'fecha_nacimiento' => Carbon::parse($validated['fecha_nacimiento'])->toDateString(),
                        'ciudad_residencia' => trim($validated['ciudad_residencia']),
                        'telefono' => $validated['telefono'] ?? null,
                        'ultimos_digitos_nit' => $validated['ultimos_digitos_nit'] ?? null,
                        'personas_a_cargo' => $validated['personas_a_cargo'],
                        'ocupaciones' => $validated['ocupaciones'],
                        'es_salario_integral' => $validated['es_salario_integral'] ?? false,
                        'clase_riesgo_arl' => $validated['clase_riesgo_arl'] ?? null,
                        'arl_obligatoria' => $validated['arl_obligatoria'] ?? false,
                        'responsable_iva' => $validated['responsable_iva'],
                    ]);

                    $perfilGuardado = $perfilActivo;
                } else {
                    // RN-008.6: Si es una fecha posterior, cerrar vigencia del perfil anterior el día previo
                    $perfilActivo->update([
                        'vigente_hasta' => $nuevaFechaDesde->copy()->subDay()->toDateString(),
                    ]);

                    $perfilGuardado = PerfilTributario::create([
                        'usuario_id' => $usuario->id,
                        'fecha_nacimiento' => Carbon::parse($validated['fecha_nacimiento'])->toDateString(),
                        'ciudad_residencia' => trim($validated['ciudad_residencia']),
                        'telefono' => $validated['telefono'] ?? null,
                        'ultimos_digitos_nit' => $validated['ultimos_digitos_nit'] ?? null,
                        'personas_a_cargo' => $validated['personas_a_cargo'],
                        'ocupaciones' => $validated['ocupaciones'],
                        'es_salario_integral' => $validated['es_salario_integral'] ?? false,
                        'clase_riesgo_arl' => $validated['clase_riesgo_arl'] ?? null,
                        'arl_obligatoria' => $validated['arl_obligatoria'] ?? false,
                        'responsable_iva' => $validated['responsable_iva'],
                        'vigente_desde' => $nuevaFechaDesde->toDateString(),
                        'vigente_hasta' => null,
                    ]);
                }
            } else {
                // Primer perfil tributario del usuario
                $perfilGuardado = PerfilTributario::create([
                    'usuario_id' => $usuario->id,
                    'fecha_nacimiento' => Carbon::parse($validated['fecha_nacimiento'])->toDateString(),
                    'ciudad_residencia' => trim($validated['ciudad_residencia']),
                    'telefono' => $validated['telefono'] ?? null,
                    'ultimos_digitos_nit' => $validated['ultimos_digitos_nit'] ?? null,
                    'personas_a_cargo' => $validated['personas_a_cargo'],
                    'ocupaciones' => $validated['ocupaciones'],
                    'es_salario_integral' => $validated['es_salario_integral'] ?? false,
                    'clase_riesgo_arl' => $validated['clase_riesgo_arl'] ?? null,
                    'arl_obligatoria' => $validated['arl_obligatoria'] ?? false,
                    'responsable_iva' => $validated['responsable_iva'],
                    'vigente_desde' => $vigenteDesde->toDateString(),
                    'vigente_hasta' => null,
                ]);
            }

            BitacoraService::registrar(
                accion: 'ACTUALIZACION_PERFIL_TRIBUTARIO',
                entidadAfectada: 'perfiles_tributarios',
                entidadId: $perfilGuardado->id,
                valorAnterior: $valorAnterior,
                valorNuevo: $perfilGuardado->fresh()->toArray(),
                usuarioId: $usuario->id
            );

            DB::commit();

            return back()->with('status', 'Perfil actualizado');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->withErrors([
                'perfil_error' => 'Error al guardar el perfil: ' . $e->getMessage(),
            ]);
        }
    }

    /* =========================================================================
       RF-012: ACTIVAR / DESACTIVAR 2FA (MFA)
    ========================================================================= */

    public function toggleMfa(Request $request): RedirectResponse
    {
        $usuario = Auth::user();

        if ($usuario->mfa_activo) {
            // Desactivar: requiere contraseña y código válido
            $request->validate([
                'password' => 'required',
                'codigo' => 'required|digits:6',
            ]);

            if (!Hash::check($request->password, $usuario->password_hash)) {
                return back()->withErrors(['password' => 'Contraseña incorrecta']);
            }

            $res = SecurityTokenService::validarCodigoMFA($usuario, $request->codigo);
            if (!$res['valido']) {
                return back()->withErrors(['codigo' => $res['mensaje']]);
            }

            $usuario->update(['mfa_activo' => false]);

            BitacoraService::registrar(
                accion: 'DESACTIVACION_MFA',
                entidadAfectada: 'usuarios',
                entidadId: $usuario->id,
                usuarioId: $usuario->id
            );

            return back()->with('status', 'Verificación en dos pasos desactivada.');
        } else {
            // Activar: validar código enviado previamente
            $request->validate([
                'codigo' => 'required|digits:6',
            ]);

            $res = SecurityTokenService::validarCodigoMFA($usuario, $request->codigo);
            if (!$res['valido']) {
                return back()->withErrors(['codigo' => $res['mensaje']]);
            }

            $usuario->update(['mfa_activo' => true]);

            BitacoraService::registrar(
                accion: 'ACTIVACION_MFA',
                entidadAfectada: 'usuarios',
                entidadId: $usuario->id,
                usuarioId: $usuario->id
            );

            return back()->with('status', 'Verificación en dos pasos activada exitosamente.');
        }
    }

    public function requestMfaActivationCode(): RedirectResponse
    {
        $usuario = Auth::user();
        $res = SecurityTokenService::generarCodigoMFA($usuario);

        if (isset($res['error'])) {
            return back()->withErrors(['mfa_error' => $res['error']]);
        }

        try {
            Mail::raw(
                "Tu código de confirmación para activar 2FA es: {$res['codigo']}\n\nVence en 10 minutos.",
                function ($m) use ($usuario) {
                    $m->to($usuario->correo)->subject('Código de activación 2FA - SCFP');
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("No se pudo enviar email de 2FA: {$e->getMessage()}");
        }

        return back()->with('status', 'Se ha enviado un código de confirmación a tu correo.');
    }

    /* =========================================================================
       RF-010: SUPRESIÓN DE LA CUENTA
    ========================================================================= */

    public function solicitarSupresion(Request $request): RedirectResponse
    {
        $usuario = Auth::user();

        // RN-010.1: Confirmación con contraseña y palabra 'ELIMINAR'
        $request->validate([
            'password' => 'required',
            'confirmacion_palabra' => ['required', 'regex:/^ELIMINAR$/'],
        ], [
            'confirmacion_palabra.regex' => 'Debes escribir exactamente la palabra ELIMINAR para confirmar.',
        ]);

        if (!Hash::check($request->password, $usuario->password_hash)) {
            return back()->withErrors(['password' => 'La contraseña no es correcta.']);
        }

        // RN-010.2: Pasa a PENDIENTE_SUPRESION, se bloquea por 15 días
        $fechaProgramada = now()->addDays(15);
        $usuario->update([
            'estado' => 'PENDIENTE_SUPRESION',
            'fecha_solicitud_supresion' => now(),
            'bloqueado_hasta' => $fechaProgramada,
        ]);

        $tokenCancelacion = SecurityTokenService::generarTokenCancelarSupresion($usuario);
        $urlCancelacion = route('cuenta.cancelar.supresion', ['token' => $tokenCancelacion]);

        // Enviar correo con fecha programada de supresión y enlace de cancelación
        try {
            Mail::raw(
                "Hola {$usuario->nombres},\n\nTu cuenta ha entrado en estado 'Pendiente de supresión'.\nFecha programada de eliminación definitiva de tus datos: " . $fechaProgramada->format('Y-m-d H:i:s') . "\n\nSi deseas cancelar esta solicitud dentro de los próximos 15 días, haz clic en el siguiente enlace:\n{$urlCancelacion}",
                function ($m) use ($usuario) {
                    $m->to($usuario->correo)->subject('Solicitud de supresión de cuenta - SCFP');
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("No se pudo enviar correo de supresión: {$e->getMessage()}");
        }

        AuthSecurityService::cerrarTodasLasSesiones($usuario);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Solicitud de supresión recibida. Se ha cerrado tu sesión.');
    }

    public function cancelarSupresion(string $token): RedirectResponse
    {
        $tokenModel = TokenSeguridad::where('tipo', 'CANCELAR_SUPRESION')
            ->whereNull('usado_en')
            ->where('expira_en', '>', now())
            ->get()
            ->first(fn ($t) => Hash::check($token, $t->token_hash));

        if (!$tokenModel) {
            return redirect()->route('login')->withErrors([
                'correo' => 'El enlace de cancelación no es válido o ya expiró.',
            ]);
        }

        $usuario = $tokenModel->usuario;
        $usuario->update([
            'estado' => 'ACTIVA',
            'fecha_solicitud_supresion' => null,
            'bloqueado_hasta' => null,
        ]);

        $tokenModel->update(['usado_en' => now()]);

        BitacoraService::registrar(
            accion: 'CANCELACION_SUPRESION_CUENTA',
            entidadAfectada: 'usuarios',
            entidadId: $usuario->id,
            usuarioId: $usuario->id
        );

        return redirect()->route('login')->with('status', 'Tu solicitud de supresión fue cancelada. Tu cuenta vuelve a estar Activa.');
    }

    /* =========================================================================
       RF-011: EXPORTACIÓN DE DATOS PERSONALES
    ========================================================================= */

    public function exportarDatos(): mixed
    {
        $usuario = Auth::user();

        // RN-011.2: Máximo 1 exportación cada 24 horas
        if ($usuario->ultima_exportacion_en && now()->diffInHours($usuario->ultima_exportacion_en) < 24) {
            $horasFaltantes = 24 - now()->diffInHours($usuario->ultima_exportacion_en);
            return back()->withErrors([
                'exportacion_error' => "Solo se permite una exportación cada 24 horas. Podrás solicitarla nuevamente en {$horasFaltantes} horas.",
            ]);
        }

        $zipFileName = "exportacion_datos_{$usuario->numero_documento}_" . now()->format('Ymd_His') . ".zip";
        $zipPath = storage_path("app/exports/{$zipFileName}");

        if (!file_exists(storage_path('app/exports'))) {
            mkdir(storage_path('app/exports'), 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->withErrors(['exportacion_error' => 'No fue posible generar el archivo comprimido.']);
        }

        // 1. Entidad: Perfil del usuario
        $perfilData = [
            'id' => $usuario->id,
            'nombres' => $usuario->nombres,
            'apellidos' => $usuario->apellidos,
            'tipo_documento' => $usuario->tipo_documento,
            'numero_documento' => $usuario->numero_documento,
            'correo' => $usuario->correo,
            'rol' => $usuario->rol,
            'creado_en' => $usuario->creado_en?->toIso8601String(),
            'perfil_tributario' => $usuario->perfilTributarioActivo?->toArray(),
        ];
        $zip->addFromString('perfil.json', json_encode($perfilData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('perfil.csv', "campo,valor\n" .
            "nombres,\"{$usuario->nombres}\"\n" .
            "apellidos,\"{$usuario->apellidos}\"\n" .
            "tipo_documento,\"{$usuario->tipo_documento}\"\n" .
            "numero_documento,\"{$usuario->numero_documento}\"\n" .
            "correo,\"{$usuario->correo}\"\n"
        );

        // 2. Entidad: Categorías
        $categorias = $usuario->categorias()->get();
        $zip->addFromString('categorias.json', json_encode($categorias, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $csvCategorias = "id,tipo,nombre,activa\n";
        foreach ($categorias as $c) {
            $csvCategorias .= "\"{$c->id}\",\"{$c->tipo}\",\"{$c->nombre}\",\"{$c->activa}\"\n";
        }
        $zip->addFromString('categorias.csv', $csvCategorias);

        $zip->close();

        $usuario->update(['ultima_exportacion_en' => now()]);

        BitacoraService::registrar(
            accion: 'EXPORTACION_DATOS_PERSONALES',
            entidadAfectada: 'usuarios',
            entidadId: $usuario->id,
            usuarioId: $usuario->id
        );

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
