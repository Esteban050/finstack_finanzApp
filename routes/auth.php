<?php

use App\Http\Controllers\Auth\ScfpAuthController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

// Rutas de Visitante / Huésped
Route::middleware('guest')->group(function () {
    // RF-001: Registro
    Route::get('register', [ScfpAuthController::class, 'showRegister'])->name('register');
    Route::post('register', [ScfpAuthController::class, 'register']);

    // RF-003 & RF-004: Login
    Route::get('login', [ScfpAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [ScfpAuthController::class, 'login']);

    // RF-002: Verificación de correo
    Route::get('verify-email/{token}', [ScfpAuthController::class, 'verifyEmail'])->name('verification.verify.token');
    Route::post('verify-email/resend', [ScfpAuthController::class, 'resendVerification'])->name('verification.resend.token');

    // RF-005: Recuperación de contraseña
    Route::get('forgot-password', fn () => view('auth.forgot-password'))->name('password.request');
    Route::post('forgot-password', [ScfpAuthController::class, 'forgotPassword'])->name('password.email');
    Route::get('reset-password/{token}', [ScfpAuthController::class, 'showResetPassword'])->name('password.reset.custom');
    Route::post('reset-password', [ScfpAuthController::class, 'resetPassword'])->name('password.update.custom');

    // RF-012: MFA
    Route::get('mfa/verificar', [ScfpAuthController::class, 'showMfa'])->name('mfa.view');
    Route::post('mfa/verificar', [ScfpAuthController::class, 'verifyMfa'])->name('mfa.verify');
    Route::post('mfa/reenviar', [ScfpAuthController::class, 'resendMfa'])->name('mfa.resend');

    // RF-010: Cancelar supresión de cuenta
    Route::get('cuenta/cancelar-supresion/{token}', [UserProfileController::class, 'cancelarSupresion'])->name('cuenta.cancelar.supresion');
});

// Rutas de Usuario Autenticado
Route::middleware('auth')->group(function () {
    // RF-006: Logout
    Route::post('logout', [ScfpAuthController::class, 'logout'])->name('logout');

    // RF-007: Cambio de contraseña
    Route::put('password/cambiar', [ScfpAuthController::class, 'changePassword'])->name('password.custom.change');
});
