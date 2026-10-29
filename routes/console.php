<?php

use App\Models\BitacoraAuditoria;
use App\Models\Usuario;
use App\Services\AlertaSeguridadSocialService;
use App\Services\RecurrenciaService;
use App\Services\TrmService;
use Carbon\CarbonImmutable;
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

// RN-022.2: Generar las ocurrencias vencidas de los ingresos recurrentes (RF-022)
// RN-028.1: y de los gastos recurrentes (RF-028), que siguen las mismas reglas.
Artisan::command('scfp:generar-recurrencias', function () {
    $ingresos = RecurrenciaService::generarIngresosPendientes();
    $gastos = RecurrenciaService::generarGastosPendientes();

    $this->info("Se generaron {$ingresos} ocurrencias de ingresos recurrentes (RN-022.2).");
    $this->info("Se generaron {$gastos} ocurrencias de gastos recurrentes (RN-028.1).");
})->purpose('Generar las ocurrencias programadas de los ingresos y gastos recurrentes');

// RN-017.1: Traer la TRM del día de la fuente oficial (RF-017). Si ya está
// guardada no hace nada; si falla, el intento queda en bitácora y al sexto del
// día se avisa a los Administradores.
Artisan::command('scfp:sincronizar-trm', function () {
    $hoy = CarbonImmutable::today();

    if (TrmService::tieneOficialPara($hoy)) {
        $this->info("La TRM oficial del {$hoy->toDateString()} ya está registrada (RN-017.1).");

        return 0;
    }

    try {
        $trm = TrmService::sincronizar($hoy);
    } catch (\Throwable $e) {
        $intento = TrmService::registrarFallo($e, $hoy);
        $this->error("No se pudo obtener la TRM (intento {$intento} de ".TrmService::INTENTOS_DIARIOS."): {$e->getMessage()}");

        return 1;
    }

    $this->info("TRM sincronizada: $ {$trm->valor} por USD, vigente del {$trm->vigencia_desde->toDateString()} al {$trm->vigencia_hasta->toDateString()} (RN-017.1).");

    return 0;
})->purpose('Sincronizar la TRM del día con la fuente oficial');

// RF-036: Alertas de vencimiento del pago de seguridad social de los
// Independientes y Contratistas: 5 días y 1 día antes (RN-036.1) y "Vencido" al
// día siguiente de la fecha límite (RN-036.2). Sin calendario de la vigencia se
// avisa al Administrador (RN-036.3).
Artisan::command('scfp:alertas-seguridad-social', function () {
    $resultado = AlertaSeguridadSocialService::procesar(CarbonImmutable::today());

    foreach ($resultado['vigencias_sin_calendario'] as $anio) {
        $this->warn("No hay calendario de seguridad social para {$anio}: no se generan alertas de esa vigencia (RN-036.3).");
    }

    $this->info("Se enviaron {$resultado['enviadas']} alertas de vencimiento de seguridad social (RF-036).");
})->purpose('Enviar las alertas de vencimiento del pago de seguridad social');

// Programación de tareas automáticas diarias
Schedule::command('scfp:purgar-pendientes')->daily();
Schedule::command('scfp:purgar-supresiones')->daily();

// RN-022.2: "El Sistema genera cada ocurrencia a las 00:05 del día programado."
Schedule::command('scfp:generar-recurrencias')->dailyAt('00:05');

// RN-017.1: "consulta la fuente oficial todos los días a las 08:00 [...] Si la
// consulta falla, la reintenta cada hora hasta 6 veces": 08:00 a 13:00.
Schedule::command('scfp:sincronizar-trm')->hourlyAt(0)->between('08:00', '13:00');

// RF-036: una vez al día, por la mañana.
Schedule::command('scfp:alertas-seguridad-social')->dailyAt('07:00');
