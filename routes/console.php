<?php

use App\Models\BitacoraAuditoria;
use App\Models\Usuario;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Commands & Scheduled Jobs (SCFP)
|--------------------------------------------------------------------------
*/

// RN-002.3: Purgar cuentas pendientes de verificación tras 7 días
Artisan::command('scfp:purgar-pendientes', function () {
    $limite = now()->subDays(7);
    $cuentas = Usuario::where('estado', 'PENDIENTE_VERIFICACION')
        ->where('creado_en', '<=', $limite)
        ->get();

    $total = $cuentas->count();
    foreach ($cuentas as $cuenta) {
        $cuenta->delete();
    }

    $this->info("Se eliminaron físicamente {$total} cuentas pendientes de verificación (RN-002.3).");
})->purpose('Eliminar cuentas sin verificar luego de 7 días');

// RN-010.3: Purgar físicamente cuentas en supresión tras 15 días
Artisan::command('scfp:purgar-supresiones', function () {
    $limite = now()->subDays(15);
    $cuentas = Usuario::where('estado', 'PENDIENTE_SUPRESION')
        ->where('fecha_solicitud_supresion', '<=', $limite)
        ->get();

    $total = $cuentas->count();
    foreach ($cuentas as $cuenta) {
        $uuidAnonimo = (string) $cuenta->id;

        // Registrar evento anónimo en la bitácora antes del borrado físico (RN-010.3)
        BitacoraAuditoria::create([
            'usuario_id' => null, // Anónimo
            'accion' => 'SUPRESION_DEFINITIVA_CUENTA',
            'entidad_afectada' => 'usuarios',
            'entidad_id' => null,
            'justificacion' => "Supresión física definitiva cumplidos 15 días calendario (ID anónimo procesado: {$uuidAnonimo})",
            'fecha_evento' => now(),
        ]);

        $cuenta->delete();
    }

    $this->info("Se eliminaron definitivamente {$total} cuentas solicitadas para supresión (RN-010.3).");
})->purpose('Eliminación física definitiva de cuentas con supresión cumplida');

// Programación de tareas automáticas diarias
Schedule::command('scfp:purgar-pendientes')->daily();
Schedule::command('scfp:purgar-supresiones')->daily();
