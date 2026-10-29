<?php

namespace App\Models;

use Database\Factories\CotizacionVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CotizacionVersion extends Model
{
    /** @use HasFactory<CotizacionVersionFactory> */
    use HasFactory;

    protected $table = 'cotizacion_versiones';

    protected $primaryKey = 'id_version';

    protected $fillable = [
        'cotizacion_id',
        'revisada_por',
        'numero_version',
        'estado',
        'neto',
        'iva',
        'costo_despacho',
        'total',
        'metodo_entrega',
        'calle',
        'numero',
        'comuna',
        'ciudad',
        'region',
        'referencias',
        'vigente_hasta',
        'token_cliente_hash',
        'token_expira_at',
        'enviada_at',
        'email_estado',
        'whatsapp_estado',
        'aceptada_at',
        'rechazada_at',
        'cambios_solicitados_at',
        'telefono_aceptacion',
        'ip_aceptacion',
    ];

    protected function casts(): array
    {
        return [
            'vigente_hasta' => 'datetime',
            'token_expira_at' => 'datetime',
            'enviada_at' => 'datetime',
            'aceptada_at' => 'datetime',
            'rechazada_at' => 'datetime',
            'cambios_solicitados_at' => 'datetime',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id', 'id_cotizacion');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CotizacionDetalle::class, 'version_id', 'id_version');
    }

    public function ordenesCompra(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'version_id', 'id_version');
    }

    public function venta(): HasOne
    {
        return $this->hasOne(Venta::class, 'cotizacion_version_id', 'id_version');
    }
}
