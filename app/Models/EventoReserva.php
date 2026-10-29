<?php

namespace App\Models;

use Database\Factories\EventoReservaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoReserva extends Model
{
    /** @use HasFactory<EventoReservaFactory> */
    use HasFactory;

    protected $table = 'eventos_reserva';

    protected $primaryKey = 'id_evento_reserva';

    protected $fillable = [
        'reserva_id',
        'user_id',
        'evento',
        'expiracion_anterior',
        'expiracion_nueva',
        'datos',
    ];

    protected function casts(): array
    {
        return [
            'expiracion_anterior' => 'datetime',
            'expiracion_nueva' => 'datetime',
            'datos' => 'array',
        ];
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(ReservaInventario::class, 'reserva_id', 'id_reserva');
    }
}
