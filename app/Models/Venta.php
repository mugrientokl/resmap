<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    use HasFactory;

    protected $table = 'ventas';

    protected $primaryKey = 'id_venta';

    protected $fillable = [
        'fecha',
        'tipo_documento',
        'folio_sii',
        'neto',
        'iva',
        'total',
        'medio_pago',
        'estado_sii',
        'user_id',
        'id_cliente',
        'cotizacion_version_id',
        'estado_venta',
        'estado_pago',
        'estado_facturacion',
        'estado_entrega',
        'monto_pagado',
        'metodo_entrega',
        'direccion_despacho',
        'comuna_despacho',
        'ciudad_despacho',
        'region_despacho',
        'referencias_despacho',
        'credito_autorizado_por',
        'credito_autorizado_at',
        'stock_descontado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'credito_autorizado_at' => 'datetime',
            'stock_descontado_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function creditoAutorizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credito_autorizado_por', 'id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class, 'id_venta', 'id_venta');
    }

    public function cotizacionVersion(): BelongsTo
    {
        return $this->belongsTo(CotizacionVersion::class, 'cotizacion_version_id', 'id_version');
    }

    public function eventosFacturacion(): HasMany
    {
        return $this->hasMany(EventoFacturacion::class, 'venta_id', 'id_venta');
    }

    public function ordenesCompra(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'venta_id', 'id_venta');
    }
}
