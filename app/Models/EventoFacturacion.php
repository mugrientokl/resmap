<?php

namespace App\Models;

use Database\Factories\EventoFacturacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoFacturacion extends Model
{
    /** @use HasFactory<EventoFacturacionFactory> */
    use HasFactory;

    protected $table = 'eventos_facturacion';

    protected $primaryKey = 'id_evento';

    protected $fillable = [
        'venta_id',
        'user_id',
        'proveedor',
        'resultado',
        'clave_idempotencia',
        'respuesta',
    ];

    protected function casts(): array
    {
        return ['respuesta' => 'array'];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id', 'id_venta');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
