<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @method static Builder<static> completadas()
 *
 * @property int $id_venta
 * @property Carbon $fecha
 * @property string $neto
 * @property string $total
 * @property int|null $cotizacion_version_id
 * @property Carbon|null $stock_descontado_at
 * @property array<string, mixed>|null $datos_cliente
 */
class Venta extends Model
{
    use HasFactory;

    protected $table = 'ventas';

    protected $primaryKey = 'id_venta';

    protected $fillable = [
        'clave_idempotencia',
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
        'datos_cliente',
        'contacto',
        'cargo',
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
        'confirmada_at',
        'entregada_at',
        'datos_resumen',
        'resumen_interno_pdf',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'datos_cliente' => 'array',
            'credito_autorizado_at' => 'datetime',
            'stock_descontado_at' => 'datetime',
            'confirmada_at' => 'datetime',
            'entregada_at' => 'datetime',
            'datos_resumen' => 'array',
        ];
    }

    public function scopeCompletadas(Builder $query): void
    {
        self::aplicarFiltroCompletadas($query);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public static function aplicarFiltroCompletadas(Builder $query): Builder
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('cotizacion_version_id')
                ->orWhere(function (Builder $query): void {
                    $query->where('estado_venta', 'confirmada')
                        ->whereNotNull('stock_descontado_at');
                });
        });

        return $query;
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

    /**
     * @return array<string, mixed>
     */
    public function snapshotDatosCliente(): array
    {
        if (\is_array($this->datos_cliente) && $this->datos_cliente !== []) {
            return $this->datos_cliente;
        }

        return [];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class, 'id_venta', 'id_venta');
    }

    public function cotizacionVersion(): BelongsTo
    {
        return $this->belongsTo(CotizacionVersion::class, 'cotizacion_version_id', 'id_version');
    }

    public function solicitudServicio(): HasOne
    {
        return $this->hasOne(SolicitudWeb::class, 'venta_id', 'id_venta')
            ->where('tipo_solicitud', 'servicio');
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
