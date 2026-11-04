<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * RF-030 — Reglas de los archivos de soporte: tipo y tamaño (RN-030.1),
 * validación por contenido (RN-030.2) y guardado cifrado con descarga por
 * enlace firmado (RN-030.3). Lo usa RN-035.1 y queda listo para los gastos.
 */
class SoporteArchivoService
{
    /** RN-030.1: 5 MB por archivo. */
    public const TAMANO_MAXIMO = 5 * 1024 * 1024;

    /** RN-030.3: el enlace de descarga vence en 5 minutos. */
    public const MINUTOS_ENLACE = 5;

    private const DISCO = 'local';

    /** RN-030.1 */
    private const EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png'];

    /** RN-030.2: firma (primeros bytes) de cada tipo permitido. */
    private const FIRMAS = [
        'application/pdf' => '%PDF-',
        'image/jpeg' => "\xFF\xD8\xFF",
        'image/png' => "\x89PNG\r\n\x1A\n",
    ];

    /**
     * RN-030.1 / RN-030.2: devuelve el tipo real del archivo o lo rechaza con
     * los mensajes de CA-030.1 y CA-030.2.
     *
     * @throws ValidationException
     */
    public static function validar(UploadedFile $archivo, string $campo): string
    {
        if ($archivo->getSize() > self::TAMANO_MAXIMO) {
            throw ValidationException::withMessages([$campo => 'El archivo supera 5 MB']);
        }

        $cabecera = (string) file_get_contents($archivo->getRealPath(), false, null, 0, 8);
        $extension = strtolower($archivo->getClientOriginalExtension());

        foreach (self::FIRMAS as $tipo => $firma) {
            if (str_starts_with($cabecera, $firma) && in_array($extension, self::EXTENSIONES, true)) {
                return $tipo;
            }
        }

        throw ValidationException::withMessages([$campo => 'Tipo de archivo no permitido']);
    }

    /** RN-030.3: guarda el contenido cifrado y devuelve su ruta. */
    public static function guardarCifrado(UploadedFile $archivo, string $carpeta): string
    {
        $ruta = $carpeta.'/'.Str::uuid().'.enc';
        Storage::disk(self::DISCO)->put($ruta, Crypt::encrypt(file_get_contents($archivo->getRealPath()), false));

        return $ruta;
    }

    /** RN-030.3: contenido original del archivo. */
    public static function leer(string $ruta): string
    {
        return Crypt::decrypt(Storage::disk(self::DISCO)->get($ruta), false);
    }

    public static function eliminar(string $ruta): void
    {
        Storage::disk(self::DISCO)->delete($ruta);
    }

    /** RN-010.3: al suprimir la cuenta se borran todos sus archivos. */
    public static function eliminarDelUsuario(string $usuarioId): void
    {
        Storage::disk(self::DISCO)->deleteDirectory("soportes/{$usuarioId}");
    }

    public static function carpeta(string $usuarioId, string $modulo): string
    {
        return "soportes/{$usuarioId}/{$modulo}";
    }
}
