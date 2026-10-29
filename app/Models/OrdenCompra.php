<?php

namespace App\Models;

use Database\Factories\OrdenCompraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenCompra extends Model
{
    /** @use HasFactory<OrdenCompraFactory> */
    use HasFactory;

    protected $table = 'ordenes_compra';

    protected $primaryKey = 'id_orden_compra';

    protected $fillable = [
        'version_id',
        'venta_id',
        'modalidad',
        'numero',
        'archivo_path',
        'archivo_sha256',
        'autorizada_por',
        'autorizada_at',
    ];

    protected function casts(): array
    {
        return ['autorizada_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CotizacionVersion::class, 'version_id', 'id_version');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id', 'id_venta');
    }
}
