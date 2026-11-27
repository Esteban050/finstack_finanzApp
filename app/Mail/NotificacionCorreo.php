<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * RN-059.1: correo de una notificación. Va en HTML con la marca y en texto plano
 * para los clientes que no muestran HTML; los dos dicen lo mismo.
 */
class NotificacionCorreo extends Mailable
{
    use Queueable;

    /**
     * @param  array<int, array{etiqueta: string, valor: string, tono?: string}>  $cifras
     * @param  array{porcentaje: string, ancho: int, tono: string, texto: string}|null  $barra
     */
    public function __construct(
        public string $asunto,
        public string $nombre,
        public string $titulo,
        public string $detalle,
        public string $prioridad,
        public array $cifras,
        public ?array $barra,
        public string $url,
        public string $urlAlertas,
        public string $motivo,
        public string $fecha,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.notificacion', text: 'mail.notificacion-texto');
    }
}
