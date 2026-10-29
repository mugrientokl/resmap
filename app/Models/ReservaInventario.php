<?php

namespace App\Models;

use Database\Factories\ReservaInventarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservaInventario extends Model
{
    /** @use HasFactory<ReservaInventarioFactory> */
    use HasFactory;

    protected $table = 'reservas_inventario';

    protected $primaryKey = 'id_reserva';

    protected $fillable = [
        'cotizacion_id',
        'version_id',
        'producto_id',
        'cantidad',
        'estado',
        'expira_at',
        'consumida_at',
        'liberada_at',
    ];

    protected function casts(): array
    {
        return [
            'expira_at' => 'datetime',
            'consumida_at' => 'datetime',
            'liberada_at' => 'datetime',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id', 'id_cotizacion');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'id_producto');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoReserva::class, 'reserva_id', 'id_reserva');
    }
}
