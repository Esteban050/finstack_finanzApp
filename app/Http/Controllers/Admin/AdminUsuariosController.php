<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SesionUsuario;
use App\Models\Usuario;
use App\Rules\PasswordPolicyRule;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AdminUsuariosController extends Controller
{
    /**
     * RF-018 RN-018.1 & CA-018.2: Listado de usuarios con minimización de datos (sin datos financieros)
     */
    public function index(Request $request): View
    {
        $busqueda = $request->query('buscar');
        $estado = $request->query('estado');

        $query = Usuario::select([
            'id',
            'nombres',
            'apellidos',
            'correo',
            'rol',
            'estado',
            'intentos_fallidos',
            'bloqueado_hasta',
            'creado_en',
            'ultimo_acceso',
        ]);

        if ($busqueda) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombres', 'ilike', "%{$busqueda}%")
                  ->orWhere('apellidos', 'ilike', "%{$busqueda}%")
                  ->orWhere('correo', 'ilike', "%{$busqueda}%");
            });
        }

        if ($estado) {
            $query->where('estado', $estado);
        }

        $usuarios = $query->orderByDesc('creado_en')->paginate(20);

        return view('admin.usuarios.index', compact('usuarios', 'busqueda', 'estado'));
    }

    /**
     * RF-018 RN-018.2 & RN-018.3 & CA-018.1: Inactivar o activar cuenta
     */
    public function cambiarEstado(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'nuevo_estado' => ['required', 'in:ACTIVA,INACTIVA'],
            'justificacion' => ['required_if:nuevo_estado,INACTIVA', 'nullable', 'string', 'min:10'],
        ], [
            'justificacion.required_if' => 'Para inactivar una cuenta se exige una justificación obligatoria.',
            'justificacion.min' => 'La justificación debe tener mínimo 10 caracteres.',
        ]);

        $usuario = Usuario::findOrFail($id);
        $adminActual = Auth::user();

        // RN-018.3: Un Administrador no puede inactivarse a sí mismo
        if ($usuario->id === $adminActual->id && $request->nuevo_estado === 'INACTIVA') {
            return back()->withErrors(['estado' => 'No puedes inactivar tu propia cuenta de Administrador.']);
        }

        // RN-018.3: El sistema debe conservar siempre al menos un Administrador activo
        if ($usuario->esAdmin() && $request->nuevo_estado === 'INACTIVA') {
            $adminsActivos = Usuario::where('rol', 'ADMIN')
                ->where('estado', 'ACTIVA')
                ->where('id', '!=', $usuario->id)
                ->count();

            if ($adminsActivos < 1) {
                return back()->withErrors(['estado' => 'No es posible inactivar al único Administrador activo del sistema.']);
            }
        }

        $estadoAnterior = $usuario->estado;
        $usuario->update(['estado' => $request->nuevo_estado]);

        // CA-018.1: Al inactivar una cuenta, sus sesiones activas se cierran inmediatamente
        if ($request->nuevo_estado === 'INACTIVA') {
            SesionUsuario::where('usuario_id', $usuario->id)
                ->where('revocada', false)
                ->update(['revocada' => true]);

            // RN-018.2: Notificar al usuario por correo
            try {
                Mail::raw(
                    "Hola {$usuario->nombres},\n\nTu cuenta en SCFP ha sido inactivada por el equipo de administración.\nMotivo: {$request->justificacion}\n\nSi consideras que se trata de un error, por favor comunícate con soporte.",
                    function ($m) use ($usuario) {
                        $m->to($usuario->correo)->subject('Notificación sobre el estado de tu cuenta - SCFP');
                    }
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("No se pudo enviar correo de inactivación: {$e->getMessage()}");
            }
        }

        BitacoraService::registrar(
            accion: 'CAMBIO_ESTADO_USUARIO_POR_ADMIN',
            entidadAfectada: 'usuarios',
            entidadId: $usuario->id,
            valorAnterior: ['estado' => $estadoAnterior],
            valorNuevo: ['estado' => $request->nuevo_estado],
            justificacion: $request->justificacion,
            usuarioId: $adminActual->id
        );

        return back()->with('status', "Estado del usuario actualizado a {$request->nuevo_estado}.");
    }

    /**
     * RF-018: Desbloquear cuenta manualmente
     */
    public function desbloquear(string $id): RedirectResponse
    {
        $usuario = Usuario::findOrFail($id);

        $usuario->update([
            'estado' => 'ACTIVA',
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ]);

        BitacoraService::registrar(
            accion: 'DESBLOQUEO_CUENTA_POR_ADMIN',
            entidadAfectada: 'usuarios',
            entidadId: $usuario->id,
            justificacion: 'Desbloqueo manual por el Administrador',
            usuarioId: Auth::id()
        );

        return back()->with('status', "La cuenta de {$usuario->correo} ha sido desbloqueada.");
    }

    /**
     * RF-018: Crear nuevo Administrador
     */
    public function storeAdmin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombres' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\pL\s]+$/u'],
            'apellidos' => ['required', 'string', 'min:2', 'max:60'],
            'tipo_documento' => ['required', 'in:CC,CE,PA,PPT'],
            'numero_documento' => ['required', 'string', 'min:5', 'max:15', 'alpha_num'],
            'correo' => ['required', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'confirmed', new PasswordPolicyRule($request->correo, $request->numero_documento)],
        ]);

        $correoLower = strtolower(trim($validated['correo']));

        $existe = Usuario::whereRaw('LOWER(correo) = ?', [$correoLower])
            ->orWhere(function ($q) use ($validated) {
                $q->where('tipo_documento', $validated['tipo_documento'])
                  ->where('numero_documento', trim($validated['numero_documento']));
            })->exists();

        if ($existe) {
            return back()->withInput()->withErrors(['correo' => 'El correo o documento ya se encuentra registrado.']);
        }

        $admin = Usuario::create([
            'nombres' => trim($validated['nombres']),
            'apellidos' => trim($validated['apellidos']),
            'tipo_documento' => $validated['tipo_documento'],
            'numero_documento' => trim($validated['numero_documento']),
            'correo' => $correoLower,
            'password_hash' => Hash::make($validated['password']),
            'rol' => 'ADMIN',
            'estado' => 'ACTIVA',
        ]);

        BitacoraService::registrar(
            accion: 'CREAR_ADMINISTRADOR',
            entidadAfectada: 'usuarios',
            entidadId: $admin->id,
            valorNuevo: ['correo' => $correoLower, 'rol' => 'ADMIN'],
            justificacion: 'Creación de nuevo administrador desde el panel',
            usuarioId: Auth::id()
        );

        return back()->with('status', "Administrador {$admin->correo} creado exitosamente.");
    }
}
