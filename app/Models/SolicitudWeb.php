<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudWeb extends Model
{
    use HasFactory;

    protected $table = 'solicitud_webs';

    protected $primaryKey = 'id_solicitud';

    protected $fillable = [
        'fecha',
        'estado',
        'tipo_solicitud',
        'tipo_servicio',
        'descripcion_servicio',
        'id_cliente',
        'venta_id',
        'detalles_productos',
        'observaciones',
        'atendida_at',
        'observaciones',
        'atendida_at',
        'metodo_entrega',
        'direccion_calle',
        'direccion_numero',
        'direccion_comuna',
        'direccion_ciudad',
        'direccion_region',
        'direccion_referencias',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'detalles_productos' => 'array',
        'atendida_at' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id', 'id_venta');
    }

    public function cotizacion(): HasOne
    {
        return $this->hasOne(Cotizacion::class, 'solicitud_id', 'id_solicitud');
    }
}
