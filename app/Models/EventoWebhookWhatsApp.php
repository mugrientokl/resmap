<?php

namespace App\Models;

use Database\Factories\EventoWebhookWhatsAppFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventoWebhookWhatsApp extends Model
{
    /** @use HasFactory<EventoWebhookWhatsAppFactory> */
    use HasFactory;

    protected $table = 'eventos_webhook_whatsapp';

    protected $primaryKey = 'id_evento_webhook';

    protected $fillable = [
        'id_proveedor',
        'payload_sha256',
        'payload',
        'procesado_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'procesado_at' => 'datetime',
        ];
    }
}
