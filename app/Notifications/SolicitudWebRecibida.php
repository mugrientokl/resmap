<?php

namespace App\Notifications;

use App\Models\Producto;
use App\Models\SolicitudWeb;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class SolicitudWebRecibida extends Notification
{
    use Queueable;

    public function __construct(public SolicitudWeb $solicitud) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cliente = $this->solicitud->cliente;
        $detalles = collect($this->solicitud->detalles_productos ?? []);
        $productos = Producto::whereIn('id_producto', $detalles->pluck('id_producto')->filter())
            ->get()
            ->keyBy('id_producto');
        $items = $detalles->map(function (array $detalle) use ($productos): array {
            $producto = $productos->get($detalle['id_producto'] ?? null);
            $cantidad = (int) ($detalle['cantidad'] ?? 0);

            return [
                'nombre' => $producto?->nombre ?? 'Producto no disponible',
                'codigo' => $producto?->codigo_origen ?: $producto?->codigo_barra ?: 'Sin código',
                'cantidad' => $cantidad,
                'precio' => (float) ($producto?->precio ?? 0),
                'subtotal' => $cantidad * (float) ($producto?->precio ?? 0),
            ];
        });

        return (new MailMessage)
            ->subject("Nueva Solicitud #{$this->solicitud->id_solicitud} - {$this->solicitud->tipo_solicitud}")
            ->view('emails.solicitud-web', [
                'solicitud' => $this->solicitud,
                'cliente' => $cliente,
                'items' => $items,
                'notifiable' => $notifiable,
                'faltantes' => $this->faltantes(),
                'logoUrl' => 'cid:resmap-logo',
                'urlSolicitud' => route('solicitudes.show', $this->solicitud->id_solicitud),
            ])
            ->withSymfonyMessage(function (Email $message): void {
                $message->embedFromPath(public_path('images/resmap sin fondo.png'), 'resmap-logo');
            });
    }

    public function toArray(object $notifiable): array
    {
        $faltantes = $this->faltantes();

        return [
            'tipo' => 'solicitud',
            'tipo_solicitud' => $this->solicitud->tipo_solicitud,
            'titulo' => 'Nueva solicitud web',
            'mensaje' => 'Solicitud #'.$this->solicitud->id_solicitud.' de '.$this->solicitud->cliente->nombre.($faltantes ? ' requiere revisar disponibilidad de stock.' : ''),
            'faltantes_stock' => $faltantes,
            'url' => route('solicitudes.show', $this->solicitud->id_solicitud),
        ];
    }

    private function faltantes(): array
    {
        $detalles = collect($this->solicitud->detalles_productos ?? [])
            ->groupBy('id_producto')
            ->map(fn ($items): int => $items->sum(fn (array $item): int => (int) ($item['cantidad'] ?? 0)));
        $productos = Producto::whereIn('id_producto', $detalles->keys())->get()->keyBy('id_producto');

        return $detalles->filter(function (int $cantidad, $idProducto) use ($productos): bool {
            return $cantidad > (int) ($productos->get($idProducto)?->stock ?? 0);
        })->map(function (int $cantidad, $idProducto) use ($productos): array {
            $producto = $productos->get($idProducto);

            return [
                'nombre' => $producto?->nombre ?? 'Producto no disponible',
                'solicitado' => $cantidad,
                'disponible' => (int) ($producto?->stock ?? 0),
            ];
        })->values()->all();
    }
}
