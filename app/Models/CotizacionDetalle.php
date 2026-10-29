<?php

namespace App\Models;

use Database\Factories\CotizacionDetalleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionDetalle extends Model
{
    /** @use HasFactory<CotizacionDetalleFactory> */
    use HasFactory;

    protected $table = 'cotizacion_detalles';

    protected $primaryKey = 'id_detalle';

    protected $fillable = [
        'version_id',
        'producto_id',
        'nombre_producto',
        'codigo_producto',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(CotizacionVersion::class, 'version_id', 'id_version');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'id_producto');
    }
}
