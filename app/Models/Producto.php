<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Producto extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $producto): void {
            if ($producto->isDirty('nombre')) {
                $producto->nombre_normalizado = self::claveNombre($producto->nombre);
            }
        });
    }

    public static function claveNombre(string $nombre): string
    {
        return hash('sha256', mb_convert_case(trim($nombre), MB_CASE_FOLD, 'UTF-8'));
    }

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

    public static function crearConCodigo(array $datos): self
    {
        return DB::transaction(function () use ($datos): self {
            $datos['codigo_barra'] = 'TEMP-'.Str::uuid();
            $datos['codigo_origen'] = null;
            $producto = self::create($datos);
            $codigo = sprintf('RESMAP-%06d', $producto->id_producto);
            $producto->update(['codigo_barra' => $codigo, 'codigo_origen' => $codigo]);

            return $producto;
        });
    }

    protected function stockDisponible(): Attribute
    {
        return Attribute::get(
            fn (): int => max(0, (int) $this->stock - (int) ($this->attributes['stock_reservado'] ?? 0)),
        );
    }

    public function scopeBuscarCoincidencias(Builder $query, string $buscar): Builder
    {
        $palabras = preg_split('/\s+/u', trim($buscar), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($palabras as $palabra) {
            $query->where(function (Builder $coincidencias) use ($palabra): void {
                $coincidencias->where('nombre', 'like', '%'.$palabra.'%')
                    ->orWhere('codigo_barra', 'like', '%'.$palabra.'%')
                    ->orWhere('codigo_origen', 'like', '%'.$palabra.'%');
            });
        }

        return $query;
    }

    public function scopeConStockDisponible(Builder $query): Builder
    {
        return $query->where('stock', '>', function (QueryBuilder $reservas): void {
            $reservas->from('reservas_inventario')
                ->selectRaw('COALESCE(SUM(cantidad), 0)')
                ->whereColumn('producto_id', 'productos.id_producto')
                ->where('estado', 'activa')
                ->where('expira_at', '>', now());
        });
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
