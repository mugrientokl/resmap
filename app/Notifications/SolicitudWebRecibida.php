<?php

namespace App\Notifications;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\SolicitudWeb;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class SolicitudWebRecibida extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    /** @var list<array{nombre: string, codigo: string, cantidad: int, precio: float, subtotal: float}> */
    private array $items;

    /** @var list<array{nombre: string, solicitado: int, disponible: int}> */
    private array $faltantes;

    public function __construct(public SolicitudWeb $solicitud)
    {
        $detalles = collect($this->solicitud->detalles_productos ?? []);
        $productos = Producto::query()
            ->whereIn('id_producto', $detalles->pluck('id_producto')->filter())
            ->get()
            ->keyBy('id_producto');

        $this->items = $detalles->map(function (array $detalle) use ($productos): array {
            $producto = $productos->get($detalle['id_producto'] ?? null);
            $cantidad = (int) ($detalle['cantidad'] ?? 0);

            return [
                'nombre' => $producto?->nombre ?? 'Producto no disponible',
                'codigo' => $producto?->codigo_origen ?: $producto?->codigo_barra ?: 'Sin código',
                'cantidad' => $cantidad,
                'precio' => (float) ($producto?->precio ?? 0),
                'subtotal' => $cantidad * (float) ($producto?->precio ?? 0),
            ];
        })->values()->all();

        $cantidades = $detalles
            ->groupBy('id_producto')
            ->map(fn ($items): int => $items->sum(fn (array $item): int => (int) ($item['cantidad'] ?? 0)));
        $this->faltantes = $cantidades->filter(function (int $cantidad, $idProducto) use ($productos): bool {
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

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cliente = Cliente::desdeSnapshot($this->solicitud->snapshotDatosCliente());
        $esServicio = $this->solicitud->tipo_solicitud === 'servicio';

        return (new MailMessage)
            ->subject(($esServicio ? 'Nuevo servicio' : 'Nueva cotización').' #'.$this->solicitud->id_solicitud)
            ->view('emails.solicitud-web', [
                'solicitud' => $this->solicitud,
                'esServicio' => $esServicio,
                'cliente' => $cliente,
                'items' => collect($this->items),
                'notifiable' => $notifiable,
                'faltantes' => $this->faltantes,
                'logoUrl' => 'cid:resmap-logo',
                'urlSolicitud' => route('solicitudes.show', $this->solicitud->id_solicitud),
            ])
            ->withSymfonyMessage(function (Email $message): void {
                $message->embedFromPath(public_path('images/resmap sin fondo.png'), 'resmap-logo');
            });
    }

    public function toArray(object $notifiable): array
    {
        $esServicio = $this->solicitud->tipo_solicitud === 'servicio';

        return [
            'tipo' => 'solicitud',
            'tipo_solicitud' => $this->solicitud->tipo_solicitud,
            'solicitud_id' => $this->solicitud->id_solicitud,
            'titulo' => $esServicio ? 'Nuevo servicio solicitado' : 'Nueva cotización web',
            'mensaje' => ($esServicio ? 'Servicio' : 'Cotización').' #'.$this->solicitud->id_solicitud.' de '.Cliente::desdeSnapshot($this->solicitud->snapshotDatosCliente())->nombre.($this->faltantes ? ' requiere revisar disponibilidad de stock.' : ''),
            'faltantes_stock' => $this->faltantes,
            'url' => route('solicitudes.show', $this->solicitud->id_solicitud),
        ];
    }
}
