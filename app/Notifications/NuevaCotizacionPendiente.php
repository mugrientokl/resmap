<?php

namespace App\Notifications;

use App\Models\Cotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NuevaCotizacionPendiente extends Notification
{
    use Queueable;

    public function __construct(public Cotizacion $cotizacion) {}

    public function via(object $notifiable): array
    {
        return $notifiable->rol === 'Administrador'
            ? ['database', 'mail']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nueva cotización B2B pendiente')
            ->greeting('Hola '.$notifiable->name)
            ->line('Se recibió una nueva cotización #'.$this->cotizacion->id_cotizacion.'.')
            ->action('Revisar cotización', route('solicitudes.show', $this->cotizacion->solicitud_id))
            ->line('La reserva inicial vence el '.$this->cotizacion->reservas()->min('expira_at').'.');
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
