<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';

    protected $primaryKey = 'id_producto';

    protected $fillable = [
        'codigo_barra',
        'codigo_origen',
        'nombre',
        'descripcion',
        'imagen',
        'precio',
        'stock',
        'stock_critico',
        'id_categoria',
        'ubicacion',
        'unidad',
        'activo',
        'fila_origen',
        'estado_importacion',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected function stockDisponible(): Attribute
    {
        return Attribute::get(
            fn (): int => max(0, (int) $this->stock - (int) ($this->attributes['stock_reservado'] ?? 0)),
        );
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function detallesVenta(): HasMany
    {
        return $this->hasMany(DetalleVenta::class, 'id_producto', 'id_producto');
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'id_producto', 'id_producto');
    }

    public function reservasActivas(): HasMany
    {
        return $this->hasMany(ReservaInventario::class, 'producto_id', 'id_producto')
            ->where('estado', 'activa')
            ->where('expira_at', '>', now());
    }
}
