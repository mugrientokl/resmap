<?php

namespace App\Notifications;

use App\Models\Cotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class NuevaCotizacionPendiente extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    private mixed $venceAt;

    public function __construct(public Cotizacion $cotizacion)
    {
        $this->venceAt = $this->cotizacion->reservas()->min('expira_at');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nueva cotización B2B pendiente · RESMAP')
            ->view('emails.cotizacion-pendiente', [
                'name' => $notifiable->name,
                'cotizacion' => $this->cotizacion,
                'urlSolicitud' => route('solicitudes.show', $this->cotizacion->solicitud_id),
                'venceAt' => $this->venceAt,
                'logoUrl' => 'cid:resmap-logo',
            ])
            ->withSymfonyMessage(function (Email $message): void {
                $message->embedFromPath(public_path('images/resmap sin fondo.png'), 'resmap-logo');
            });
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'cotizacion_pendiente',
            'cotizacion_id' => $this->cotizacion->id_cotizacion,
            'titulo' => 'Nueva cotización B2B',
            'mensaje' => 'Cotización #'.$this->cotizacion->solicitud_id.' pendiente de revisión comercial.',
            'url' => route('solicitudes.show', $this->cotizacion->solicitud_id),
        ];
    }
}
