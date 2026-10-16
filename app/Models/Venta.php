<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use HasFactory;

    protected $table = 'ventas';

    protected $primaryKey = 'id_venta';

    protected $fillable = [
        'fecha',
        'tipo_documento',
        'folio_sii',
        'codigo_dte_temporal',
        'neto',
        'iva',
        'total',
        'medio_pago',
        'estado_sii',
        'estado_dte',
        'track_id_sii',
        'xml_dte',
        'pdf_dte',
        'fecha_envio_sii',
        'fecha_respuesta_sii',
        'error_sii',
        'user_id',
        'id_cliente',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'fecha_envio_sii' => 'datetime',
            'fecha_respuesta_sii' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class, 'id_venta', 'id_venta');
    }

    public function getCodigoDteAttribute(): int
    {
        return $this->tipo_documento === 'Factura Electrónica' ? 33 : 39;
    }
}
