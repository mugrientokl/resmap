<?php

namespace App\Models;

use Database\Factories\CotizacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cotizacion extends Model
{
    /** @use HasFactory<CotizacionFactory> */
    use HasFactory;

    protected $table = 'cotizaciones';

    protected $primaryKey = 'id_cotizacion';

    protected $fillable = [
        'solicitud_id',
        'creada_por',
        'estado',
        'ultima_version',
        'revisada_at',
        'comentarios_cliente',
    ];

    protected function casts(): array
    {
        return ['revisada_at' => 'datetime'];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudWeb::class, 'solicitud_id', 'id_solicitud');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(CotizacionVersion::class, 'cotizacion_id', 'id_cotizacion');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(ReservaInventario::class, 'cotizacion_id', 'id_cotizacion');
    }
}
