<?php

namespace App\Mail;

use App\Models\CotizacionVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class CotizacionCliente extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CotizacionVersion $version,
        public string $urlRespuesta,
        public string $urlPdf,
        public string $pdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cotización RESMAP - versión '.$this->version->numero_version,
            using: [
                fn (Email $message) => $message->embedFromPath(public_path('images/resmap sin fondo.png'), 'resmap-logo'),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cotizacion-cliente',
            with: [
                'version' => $this->version,
                'cliente' => $this->version->cotizacion->solicitud->cliente,
                'urlRespuesta' => $this->urlRespuesta,
                'urlPdf' => $this->urlPdf,
                'logoUrl' => 'cid:resmap-logo',
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => $this->pdf,
                'cotizacion-resmap-'.$this->version->id_version.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
