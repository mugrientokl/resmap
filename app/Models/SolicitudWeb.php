<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'detalles_productos' => 'array',
        'atendida_at' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id', 'id_venta');
    }
}
