<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id_movimiento
 * @property int $id_producto
 * @property int|null $user_id
 * @property string $tipo
 * @property int $cantidad
 * @property int $stock_anterior
 * @property int $stock_nuevo
 * @property string|null $motivo
 * @property string|null $proveedor
 * @property string|null $contacto_proveedor
 * @property string|null $documento_proveedor
 * @property string|null $precio_entrada
 * @property int|null $cantidad_adquirida
 * @property string|null $observaciones
 * @property string|null $costo_unitario_neto
 * @property string|null $costo_total_neto
 * @property Carbon|null $fecha_evento
 * @property Carbon|null $created_at
 */
class InventarioMovimiento extends Model
{
    protected $table = 'inventario_movimientos';

    protected $primaryKey = 'id_movimiento';

    protected $fillable = [
        'id_producto', 'user_id', 'tipo', 'cantidad', 'stock_anterior',
        'stock_nuevo', 'motivo', 'fecha_evento', 'id_venta', 'proveedor', 'documento_proveedor',
        'precio_entrada', 'cantidad_adquirida', 'observaciones', 'proveedor_id', 'contacto_proveedor',
        'costo_unitario_neto', 'costo_total_neto',
    ];

    protected function casts(): array
    {
        return [
            'precio_entrada' => 'decimal:2',
            'costo_unitario_neto' => 'decimal:2',
            'costo_total_neto' => 'decimal:2',
            'fecha_evento' => 'date',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'id_venta', 'id_venta');
    }
}
