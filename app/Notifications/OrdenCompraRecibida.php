<?php

namespace App\Notifications;

use App\Models\OrdenCompra;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OrdenCompraRecibida extends Notification
{
    use Queueable;

    public function __construct(public OrdenCompra $ordenCompra) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email)
            ? ['database', 'mail']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $version = $this->ordenCompra->version;
        $solicitud = $version->cotizacion->solicitud;
        $cliente = $solicitud->cliente;
        $archivoAdjunto = null;
        if ($this->ordenCompra->archivo_path !== null) {
            $archivoAdjunto = Storage::disk('local')->path($this->ordenCompra->archivo_path);
            if (! is_file($archivoAdjunto)) {
                throw new RuntimeException('No se encontró el archivo adjunto de la orden de compra.');
            }
        }

        $correo = (new MailMessage)
            ->subject('Orden de compra recibida · Cotización #'.$version->cotizacion_id)
            ->view('emails.orden-compra-recibida', [
                'ordenCompra' => $this->ordenCompra,
                'version' => $version,
                'venta' => $this->ordenCompra->venta,
                'cliente' => $cliente,
                'urlDetalle' => route('solicitudes.show', $solicitud->id_solicitud),
                'urlOrdenCompra' => route('cotizaciones.orden-compra', $this->ordenCompra),
            ]);

        if ($archivoAdjunto !== null) {
            $extension = strtolower(pathinfo($archivoAdjunto, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'pdf' => 'application/pdf',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                default => 'application/octet-stream',
            };
            $correo->attach($archivoAdjunto, [
                'as' => 'orden-compra-'.($this->ordenCompra->numero ?: $this->ordenCompra->id_orden_compra).'.'.$extension,
                'mime' => $mime,
            ]);
        }

        return $correo;
    }

    public function toArray(object $notifiable): array
    {
        $solicitud = $this->ordenCompra->version->cotizacion->solicitud;

        return [
            'tipo' => 'orden_compra_recibida',
            'titulo' => 'Orden de compra recibida',
            'mensaje' => 'OC '.($this->ordenCompra->numero ?: 'sin número').' para la cotización #'.$this->ordenCompra->version->cotizacion_id.' de '.$solicitud->cliente->nombre.'.',
            'url' => route('solicitudes.show', $solicitud->id_solicitud),
        ];
    }
}
