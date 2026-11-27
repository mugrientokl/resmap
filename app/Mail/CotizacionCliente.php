<?php

namespace App\Mail;

use App\Models\Cliente;
use App\Models\CotizacionVersion;
use App\Support\ConfiguracionSistema;
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
        public string $urlPdf,
        public string $pdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cotización RESMAP - versión '.$this->version->numero_version,
            using: [
                function (Email $message): void {
                    $rutaLogo = ConfiguracionSistema::logoRutaFisica();
                    if ($rutaLogo !== null) {
                        $message->embedFromPath($rutaLogo, 'resmap-logo');
                    }
                },
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cotizacion-cliente',
            with: [
                'version' => $this->version,
                'cliente' => Cliente::desdeSnapshot($this->version->snapshotDatosCliente()),
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
