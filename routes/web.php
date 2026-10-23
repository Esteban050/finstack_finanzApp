<?php

use App\Http\Controllers\Admin\AdminUsuariosController;
use App\Http\Controllers\Admin\CalendarioTributarioController;
use App\Http\Controllers\Admin\CategoriaCatalogoController;
use App\Http\Controllers\Admin\ParametroNormativoController;
use App\Http\Controllers\Admin\TarifaEscalonadaController;
use App\Http\Controllers\PoliticaTratamientoController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Rutas de Usuario Autenticado
Route::middleware(['auth', 'politica.check'])->group(function () {
    // RF-003 RN-003.5: Dashboard de Usuario
    Route::get('/dashboard', function () {
        $usuario = auth()->user();
        $perfil = $usuario->perfilTributarioActivo;
        return view('dashboard', compact('usuario', 'perfil'));
    })->name('dashboard');

    // RF-008: Perfil tributario y personal
    Route::get('/perfil', [UserProfileController::class, 'show'])->name('profile.show');
    Route::patch('/perfil', [UserProfileController::class, 'update'])->name('profile.update');

    // RF-012: MFA (Gestión desde el perfil)
    Route::post('/perfil/mfa/solicitar-codigo', [UserProfileController::class, 'requestMfaActivationCode'])->name('profile.mfa.code');
    Route::post('/perfil/mfa/toggle', [UserProfileController::class, 'toggleMfa'])->name('profile.mfa.toggle');

    // RF-011: Exportación de datos personales
    Route::get('/perfil/exportar', [UserProfileController::class, 'exportarDatos'])->name('profile.export');

    // RF-010: Solicitud de supresión de cuenta
    Route::post('/perfil/solicitar-supresion', [UserProfileController::class, 'solicitarSupresion'])->name('profile.delete.account');
});

// RF-009: Política de tratamiento de datos personales (Pública sin autenticación)
Route::get('/politica-datos', [PoliticaTratamientoController::class, 'mostrarPolitica'])->name('politica.mostrar');
Route::post('/politica-datos/aceptar', [PoliticaTratamientoController::class, 'aceptarPolitica'])->middleware('auth')->name('politica.aceptar');

// MOD-02: Rutas exclusivas de Administrador (CA-003.2, CA-013.3)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard de Administrador
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    // RF-013: Parámetros normativos
    Route::get('/parametros', [ParametroNormativoController::class, 'index'])->name('parametros.index');
    Route::get('/parametros/crear', [ParametroNormativoController::class, 'create'])->name('parametros.create');
    Route::post('/parametros', [ParametroNormativoController::class, 'store'])->name('parametros.store');
    Route::get('/parametros/{id}/editar', [ParametroNormativoController::class, 'edit'])->name('parametros.edit');
    Route::put('/parametros/{id}', [ParametroNormativoController::class, 'update'])->name('parametros.update');

    // RF-014: Tablas de tarifas escalonadas
    Route::get('/parametros/{id}/tarifas', [TarifaEscalonadaController::class, 'index'])->name('tarifas.index');
    Route::post('/parametros/{id}/tarifas', [TarifaEscalonadaController::class, 'guardarTabla'])->name('tarifas.guardar');
    Route::post('/parametros/{id}/tarifas/probar', [TarifaEscalonadaController::class, 'probarCalculoRenta'])->name('tarifas.probar');

    // RF-015: Catálogo de categorías predeterminadas
    Route::get('/categorias', [CategoriaCatalogoController::class, 'index'])->name('categorias.index');
    Route::post('/categorias', [CategoriaCatalogoController::class, 'store'])->name('categorias.store');
    Route::patch('/categorias/{id}/toggle', [CategoriaCatalogoController::class, 'toggleActiva'])->name('categorias.toggle');

    // RF-016: Calendario tributario y de aportes
    Route::get('/calendario', [CalendarioTributarioController::class, 'index'])->name('calendario.index');
    Route::post('/calendario/importar', [CalendarioTributarioController::class, 'importarCsv'])->name('calendario.importar');
    Route::get('/calendario/consultar-nit', [CalendarioTributarioController::class, 'consultarPorNit'])->name('calendario.consultar');

    // RF-018: Gestión de cuentas de usuario
    Route::get('/usuarios', [AdminUsuariosController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios/crear-admin', [AdminUsuariosController::class, 'storeAdmin'])->name('usuarios.store-admin');
    Route::patch('/usuarios/{id}/estado', [AdminUsuariosController::class, 'cambiarEstado'])->name('usuarios.cambiar-estado');
    Route::post('/usuarios/{id}/desbloquear', [AdminUsuariosController::class, 'desbloquear'])->name('usuarios.desbloquear');
});

require __DIR__.'/auth.php';
