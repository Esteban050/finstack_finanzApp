<?php

namespace App\Services;

use App\Models\BitacoraAuditoria;
use App\Models\TasaCambio;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * RF-017 — Tasa Representativa del Mercado (TRM) y conversión COP ⇄ USD.
 *
 * La fuente oficial es el conjunto de datos abiertos de la Superintendencia
 * Financiera en datos.gov.co (RN-017.1). Cada fila que publica trae el valor y
 * su rango de vigencia, que ya cubre fines de semana y festivos (RN-017.2).
 *
 * Toda la aritmética de montos se hace con bcmath sobre cadenas (RNG-02).
 */
class TrmService
{
    /** RN-017.4: monedas soportadas en la v1.0, además de COP. */
    public const MONEDA = 'USD';

    /** RN-017.1: "la reintenta cada hora hasta 6 veces y luego avisa al Administrador". */
    public const INTENTOS_DIARIOS = 6;

    public const ACCION_FALLO = 'FALLO_SINCRONIZACION_TRM';

    /**
     * RN-017.2: TRM vigente en una fecha.
     *
     * Primero busca una fila local que cubra la fecha. Si no hay, consulta la
     * fuente oficial (puede que la fecha aún no se haya sincronizado) y, si la
     * fuente no responde, usa la TRM local más reciente anterior a esa fecha.
     * Una corrección manual del mismo día gana a la fila oficial que la cubre,
     * porque su `vigencia_desde` es más reciente.
     */
    public static function vigenteEn(CarbonImmutable $fecha): ?TasaCambio
    {
        $dia = $fecha->toDateString();

        $cubre = TasaCambio::where('moneda', self::MONEDA)
            ->where('vigencia_desde', '<=', $dia)
            ->where('vigencia_hasta', '>=', $dia)
            ->orderByDesc('vigencia_desde')
            ->first();

        if ($cubre !== null) {
            return $cubre;
        }

        try {
            return self::consultarFuente($fecha);
        } catch (\Throwable $e) {
            Log::warning('No se pudo consultar la TRM en la fuente oficial (RN-017.2).', [
                'fecha' => $dia,
                'error' => $e->getMessage(),
            ]);
        }

        return TasaCambio::where('moneda', self::MONEDA)
            ->where('vigencia_desde', '<=', $dia)
            ->orderByDesc('vigencia_desde')
            ->first();
    }

    /**
     * RN-017.4 / CA-017.1: Tasa con la que se convierte a COP un registro en la
     * moneda dada, en la fecha del hecho económico.
     *
     * - COP no se convierte.
     * - Si el usuario envió una tasa, se respeta: la TRM es la sugerencia
     *   automática, no una imposición.
     * - USD sin tasa usa la TRM vigente en la fecha (CA-017.1).
     * - Cualquier otra moneda exige la tasa manual (RN-017.4).
     *
     * @throws ValidationException
     */
    public static function tasaPara(string $moneda, mixed $tasaEnviada, string $fecha): string
    {
        $moneda = strtoupper($moneda);

        if ($moneda === 'COP') {
            return '1.0000';
        }

        if ($tasaEnviada !== null && $tasaEnviada !== '') {
            return (string) $tasaEnviada;
        }

        if ($moneda !== self::MONEDA) {
            throw ValidationException::withMessages([
                'tasa_cambio_usada' => 'Indique la tasa de cambio usada para convertir a pesos colombianos.',
            ]);
        }

        $trm = self::vigenteEn(CarbonImmutable::parse($fecha));

        if ($trm === null) {
            throw ValidationException::withMessages([
                'tasa_cambio_usada' => "No hay TRM disponible para el {$fecha}. Indique la tasa de cambio manualmente.",
            ]);
        }

        return (string) $trm->valor;
    }

    /**
     * CA-017.1: Un registro en USD sin tasa propia queda atado a la TRM. Las
     * plantillas recurrentes lo guardan para convertir cada ocurrencia con la
     * TRM de su propia fecha, y no con la del día en que se creó la plantilla.
     */
    public static function usaTrmAutomatica(array $validated): bool
    {
        return strtoupper($validated['moneda'] ?? 'COP') === self::MONEDA
            && ($validated['tasa_cambio_usada'] ?? null) === null;
    }

    /**
     * RF-017: Convertir un monto entre COP y USD con la tasa dada (COP por 1 USD).
     */
    public static function convertir(string $monto, string $de, string $a, string $tasa): string
    {
        $de = strtoupper($de);
        $a = strtoupper($a);

        if ($de === $a) {
            return IngresoService::redondear($monto);
        }

        return $de === self::MONEDA
            ? IngresoService::convertirACop($monto, $tasa)
            : IngresoService::redondear(bcdiv($monto, $tasa, 6));
    }

    /**
     * RF-017: Forma pública de una TRM consultada para una fecha: el valor, su
     * vigencia, de dónde salió y cuándo se actualizó por última vez.
     */
    public static function presentar(TasaCambio $trm, CarbonImmutable $fecha): array
    {
        return [
            'moneda' => $trm->moneda,
            'valor' => (string) $trm->valor,
            'fecha_consultada' => $fecha->toDateString(),
            'vigencia_desde' => $trm->vigencia_desde->toDateString(),
            'vigencia_hasta' => $trm->vigencia_hasta->toDateString(),
            // RN-017.2: la fecha no tiene TRM propia y se usa la más reciente anterior.
            'es_tasa_anterior' => $trm->vigencia_hasta->lt($fecha->startOfDay()),
            'fuente' => $trm->fuente,
            'ultima_actualizacion' => $trm->consultado_en->toIso8601String(),
        ];
    }

    /**
     * RN-017.1: ¿Ya está guardada la TRM oficial que cubre la fecha?
     */
    public static function tieneOficialPara(CarbonImmutable $fecha): bool
    {
        $dia = $fecha->toDateString();

        return TasaCambio::where('moneda', self::MONEDA)
            ->where('fuente', TasaCambio::FUENTE_OFICIAL)
            ->where('vigencia_desde', '<=', $dia)
            ->where('vigencia_hasta', '>=', $dia)
            ->exists();
    }

    /**
     * RN-017.1: Traer de la fuente oficial la TRM vigente hoy.
     *
     * Se pide la de `$hoy` y no "la más reciente" porque la Superintendencia
     * publica por la tarde la TRM del día siguiente: pedir la última guardaría
     * la de mañana y dejaría el día de hoy sin TRM propia.
     *
     * Si la fuente responde pero todavía no publica la TRM que cubre `$hoy`,
     * la fila recibida se guarda igual y el intento cuenta como fallido, para
     * que el comando lo vuelva a intentar en la siguiente hora.
     *
     * @throws RuntimeException
     */
    public static function sincronizar(CarbonImmutable $hoy): TasaCambio
    {
        $trm = self::consultarFuente($hoy);

        if ($trm->vigencia_hasta->lt($hoy->startOfDay())) {
            throw new RuntimeException(
                "La fuente oficial todavía no publica la TRM del {$hoy->toDateString()}; la más reciente es del {$trm->vigencia_desde->toDateString()}."
            );
        }

        return $trm;
    }

    /**
     * RN-017.1: Registrar un intento fallido de sincronización. Al sexto del
     * día se avisa a los Administradores.
     *
     * Los intentos se cuentan en la bitácora, donde además queda la traza.
     */
    public static function registrarFallo(\Throwable $e, CarbonImmutable $hoy): int
    {
        $intento = BitacoraAuditoria::where('accion', self::ACCION_FALLO)
            ->whereDate('fecha_evento', $hoy->toDateString())
            ->count() + 1;

        BitacoraService::registrar(
            accion: self::ACCION_FALLO,
            entidadAfectada: 'tasas_cambio',
            justificacion: "Intento {$intento} de ".self::INTENTOS_DIARIOS.": {$e->getMessage()}"
        );

        if ($intento === self::INTENTOS_DIARIOS) {
            self::avisarAdministradores($hoy, $e);
        }

        return $intento;
    }

    /**
     * RN-017.3: El Administrador registra o corrige la TRM de una fecha.
     *
     * Si ya hay una fila que empieza ese día, se corrige en el sitio; si no, se
     * crea una fila de un solo día, que gana a la fila oficial que la cubra.
     * Devuelve el valor anterior (o null) para la bitácora.
     *
     * @return array{0: ?array, 1: TasaCambio}
     */
    public static function registrarManual(string $fecha, string $valor, string $justificacion, string $administradorId): array
    {
        $existente = TasaCambio::where('moneda', self::MONEDA)
            ->where('vigencia_desde', $fecha)
            ->first();

        $anterior = $existente?->toArray();

        $trm = $existente ?? new TasaCambio([
            'moneda' => self::MONEDA,
            'vigencia_desde' => $fecha,
            'vigencia_hasta' => $fecha,
        ]);

        $trm->fill([
            'valor' => $valor,
            'fuente' => TasaCambio::FUENTE_MANUAL,
            'justificacion' => trim($justificacion),
            'registrado_por' => $administradorId,
            'consultado_en' => now(),
        ])->save();

        return [$anterior, $trm->fresh()];
    }

    /**
     * RN-017.1 / RN-017.2: Consulta a datos.gov.co la TRM vigente en una fecha:
     * la última publicada con `vigenciadesde` anterior o igual a esa fecha.
     *
     * @throws RuntimeException
     */
    private static function consultarFuente(CarbonImmutable $fecha): TasaCambio
    {
        $consulta = [
            '$where' => "vigenciadesde <= '{$fecha->toDateString()}T00:00:00'",
            '$order' => 'vigenciadesde DESC',
            '$limit' => 1,
        ];

        $respuesta = Http::acceptJson()
            ->timeout((int) config('services.trm.timeout', 10))
            ->get(config('services.trm.url'), $consulta);

        if (! $respuesta->successful()) {
            throw new RuntimeException("La fuente oficial de la TRM respondió HTTP {$respuesta->status()}.");
        }

        $fila = $respuesta->json('0');

        if (! is_array($fila)
            || ! is_numeric($fila['valor'] ?? null)
            || ! isset($fila['vigenciadesde'], $fila['vigenciahasta'])) {
            throw new RuntimeException('La fuente oficial de la TRM no devolvió un valor válido.');
        }

        return self::guardarDeFuente(
            valor: (string) $fila['valor'],
            desde: substr((string) $fila['vigenciadesde'], 0, 10),
            hasta: substr((string) $fila['vigenciahasta'], 0, 10),
        );
    }

    /**
     * RN-017.3: La sincronización nunca pisa una corrección manual del
     * Administrador. CA-017.2: actualizar una fila no toca los registros que ya
     * la usaron, porque cada uno guardó su `tasa_cambio_usada`.
     */
    private static function guardarDeFuente(string $valor, string $desde, string $hasta): TasaCambio
    {
        $existente = TasaCambio::where('moneda', self::MONEDA)
            ->where('vigencia_desde', $desde)
            ->first();

        if ($existente?->esManual()) {
            return $existente;
        }

        return TasaCambio::updateOrCreate(
            ['moneda' => self::MONEDA, 'vigencia_desde' => $desde],
            [
                'valor' => $valor,
                'vigencia_hasta' => $hasta,
                'fuente' => TasaCambio::FUENTE_OFICIAL,
                'justificacion' => null,
                'registrado_por' => null,
                'consultado_en' => now(),
            ]
        );
    }

    /**
     * RN-017.1: "...y luego avisa al Administrador". No hay centro de
     * notificaciones (RF-058 no está especificado), así que el aviso va por
     * correo a cada Administrador activo y queda en el log y en la bitácora.
     */
    private static function avisarAdministradores(CarbonImmutable $hoy, \Throwable $e): void
    {
        Log::critical('La TRM no se pudo sincronizar tras '.self::INTENTOS_DIARIOS.' intentos (RN-017.1).', [
            'fecha' => $hoy->toDateString(),
            'error' => $e->getMessage(),
        ]);

        $correos = Usuario::where('rol', 'ADMIN')
            ->where('estado', 'ACTIVA')
            ->pluck('correo');

        foreach ($correos as $correo) {
            try {
                Mail::raw(
                    "Hola,\n\nLa TRM del {$hoy->toDateString()} no se pudo obtener de la fuente oficial tras ".self::INTENTOS_DIARIOS." intentos.\nÚltimo error: {$e->getMessage()}\n\nPuede registrarla manualmente desde la administración de la TRM (RN-017.3). Mientras tanto, los registros en USD usan la TRM vigente más reciente (RN-017.2).",
                    function ($m) use ($correo) {
                        $m->to($correo)->subject('SCFP - No se pudo sincronizar la TRM');
                    }
                );
            } catch (\Throwable $errorCorreo) {
                Log::warning("No se pudo enviar el aviso de TRM a un Administrador: {$errorCorreo->getMessage()}");
            }
        }

        BitacoraService::registrar(
            accion: 'AVISO_ADMIN_FALLO_TRM',
            entidadAfectada: 'tasas_cambio',
            justificacion: "Aviso enviado a {$correos->count()} administradores tras ".self::INTENTOS_DIARIOS.' intentos fallidos.'
        );
    }
}
