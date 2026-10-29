<?php

namespace App\Models;

use Database\Factories\MensajeWhatsAppFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MensajeWhatsApp extends Model
{
    /** @use HasFactory<MensajeWhatsAppFactory> */
    use HasFactory;

    protected $table = 'mensajes_whatsapp';

    protected $primaryKey = 'id_mensaje';

    protected $fillable = [
        'cotizacion_id',
        'version_id',
        'direccion',
        'telefono',
        'tipo',
        'estado',
        'id_proveedor',
        'clave_idempotencia',
        'contenido',
        'datos',
        'error',
    ];

    protected function casts(): array
    {
        return ['datos' => 'array'];
    }
}
