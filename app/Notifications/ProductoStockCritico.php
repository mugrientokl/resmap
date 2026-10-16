<?php

namespace App\Notifications;

use App\Models\Producto;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductoStockCritico extends Notification
{
    use Queueable;

    public function __construct(public Producto $producto) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'producto_id' => $this->producto->id_producto,
            'tipo' => 'stock_critico', 'titulo' => 'Stock crítico',
            'mensaje' => $this->producto->nombre.' tiene '.$this->producto->stock.' unidades.',
            'url' => route('productos.index'),
        ];
    }
}
