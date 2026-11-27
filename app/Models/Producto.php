<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id_producto
 * @property string $codigo_barra
 * @property string|null $codigo_origen
 * @property string $nombre
 * @property string|null $nombre_normalizado
 * @property float|string $precio
 * @property float|string|null $precio_oferta
 * @property float|string|null $porcentaje_descuento
 * @property string|null $costo_promedio_neto
 * @property string|null $margen_objetivo
 * @property string $modo_precio
 * @property int $stock
 * @property int $stock_critico
 * @property int|null $id_categoria
 * @property int $stock_disponible
 */
class Producto extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $producto): void {
            if (filled($producto->codigo_barra) || filled($producto->codigo_origen)) {
                return;
            }

            $prefijo = self::prefijoCodigo((string) $producto->nombre);
            if (strlen($prefijo) !== 3) {
                throw ValidationException::withMessages([
                    'nombre' => 'El nombre del repuesto debe contener al menos tres letras.',
                ]);
            }

            $ultimoNumero = 0;
            $codigos = self::query()
                ->whereRaw('LOWER(codigo_barra) LIKE ?', [strtolower($prefijo).'%'])
                ->orWhereRaw('LOWER(codigo_origen) LIKE ?', [strtolower($prefijo).'%'])
                ->get(['codigo_barra', 'codigo_origen']);

            foreach ($codigos as $existente) {
                foreach ([$existente->codigo_barra, $existente->codigo_origen] as $codigoExistente) {
                    $ultimoNumero = max($ultimoNumero, self::consecutivoCodigo($codigoExistente, $prefijo));
                }
            }

            if ($ultimoNumero >= 999) {
                throw ValidationException::withMessages([
                    'codigo_barra' => "Se agotaron los códigos disponibles para el prefijo {$prefijo}.",
                ]);
            }

            $codigo = $prefijo.sprintf('%03d', $ultimoNumero + 1);
            $producto->setAttribute('codigo_barra', $codigo);
            $producto->setAttribute('codigo_origen', $codigo);
        });

        static::saving(function (self $producto): void {
            if ($producto->exists && ($producto->isDirty('codigo_barra') || $producto->isDirty('codigo_origen'))) {
                throw ValidationException::withMessages([
                    'codigo_barra' => 'El código del repuesto se genera automáticamente y no se puede modificar.',
                ]);
            }

            if ($producto->isDirty('nombre')) {
                $producto->setAttribute('nombre', self::normalizarNombre((string) $producto->getAttribute('nombre')));
                $producto->setAttribute('nombre_normalizado', self::claveNombre((string) $producto->getAttribute('nombre')));
            }
        });
    }

    public static function normalizarNombre(string $nombre): string
    {
        return trim(preg_replace('/\s+/u', ' ', $nombre));
    }

    public static function calcularPrecioVentaRecomendado(float $costoNetoUnitario, float $margenObjetivo): float
    {
        $precioNeto = $costoNetoUnitario * (1 + $margenObjetivo / 100);

        return round($precioNeto * 1.19, 2);
    }

    public static function calcularCostoPromedioPonderado(
        int $stockActual,
        ?float $costoPromedioActual,
        int $cantidadNueva,
        float $costoUnitarioNuevo,
    ): float {
        $stockNuevo = $stockActual + $cantidadNueva;
        if ($stockNuevo <= 0) {
            return 0.0;
        }

        $costoAnterior = $costoPromedioActual === null ? 0.0 : (float) $costoPromedioActual;
        $totalAnterior = $stockActual * $costoAnterior;
        $totalNuevo = $cantidadNueva * $costoUnitarioNuevo;

        return round(($totalAnterior + $totalNuevo) / $stockNuevo, 2);
    }

    public static function claveNombre(string $nombre): string
    {
        $nombre = mb_convert_case(Str::ascii(trim($nombre)), MB_CASE_FOLD, 'UTF-8');
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre));

        return hash('sha256', $nombre);
    }

    public static function nombreExiste(string $nombre, ?int $productoId = null): bool
    {
        $clave = self::claveNombre($nombre);
        $consulta = self::query()
            ->when($productoId !== null, fn (Builder $query) => $query->whereKeyNot($productoId));

        if ((clone $consulta)->where('nombre_normalizado', $clave)->exists()) {
            return true;
        }

        return (clone $consulta)->whereNull('nombre_normalizado')
            ->pluck('nombre')
            ->contains(fn (string $existente): bool => self::claveNombre($existente) === $clave);
    }

    public static function prefijoCodigo(string $nombre): string
    {
        return substr(preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($nombre))), 0, 3);
    }

    protected $table = 'productos';

    protected $primaryKey = 'id_producto';

    protected $fillable = [
        'nombre',
        'descripcion',
        'imagen',
        'precio',
        'precio_oferta',
        'porcentaje_descuento',
        'oferta_inicio_at',
        'oferta_fin_at',
        'costo_promedio_neto',
        'margen_objetivo',
        'modo_precio',
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
            'costo_promedio_neto' => 'decimal:2',
            'margen_objetivo' => 'decimal:2',
            'precio' => 'decimal:2',
            'precio_oferta' => 'decimal:2',
            'porcentaje_descuento' => 'decimal:2',
            'oferta_inicio_at' => 'datetime',
            'oferta_fin_at' => 'datetime',
        ];
    }

    public static function codigoExiste(string $codigo): bool
    {
        $codigo = mb_strtolower(trim($codigo), 'UTF-8');

        $productoExiste = self::query()
            ->whereRaw('LOWER(TRIM(codigo_barra)) = ?', [$codigo])
            ->orWhereRaw('LOWER(TRIM(codigo_origen)) = ?', [$codigo])
            ->exists();
        $recodificacionExiste = Schema::hasTable('producto_recodificaciones')
            && DB::table('producto_recodificaciones')
                ->whereRaw('LOWER(TRIM(codigo_nuevo)) = ?', [$codigo])
                ->exists();

        return $productoExiste || $recodificacionExiste;
    }

    public static function crearConCodigo(array $datos): self
    {
        $prefijo = self::prefijoCodigo($datos['nombre']);
        if (\strlen($prefijo) !== 3) {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre del repuesto debe contener al menos tres letras.',
            ]);
        }

        for ($intento = 0; $intento < 10; $intento++) {
            $codigo = null;
            try {
                return DB::transaction(function () use ($datos, $prefijo, &$codigo): self {
                    $ultimoNumero = 0;
                    $codigos = self::query()
                        ->whereRaw('LOWER(codigo_barra) LIKE ?', [strtolower($prefijo).'%'])
                        ->orWhereRaw('LOWER(codigo_origen) LIKE ?', [strtolower($prefijo).'%'])
                        ->get(['codigo_barra', 'codigo_origen']);

                    foreach ($codigos as $productoExistente) {
                        foreach ([$productoExistente->codigo_barra, $productoExistente->codigo_origen] as $codigoExistente) {
                            $ultimoNumero = max($ultimoNumero, self::consecutivoCodigo($codigoExistente, $prefijo));
                        }
                    }

                    if (Schema::hasTable('producto_recodificaciones')) {
                        $anteriores = DB::table('producto_recodificaciones')
                            ->whereRaw('LOWER(codigo_barra_anterior) LIKE ?', [strtolower($prefijo).'%'])
                            ->orWhereRaw('LOWER(codigo_origen_anterior) LIKE ?', [strtolower($prefijo).'%'])
                            ->get(['codigo_barra_anterior', 'codigo_origen_anterior']);
                        foreach ($anteriores as $anterior) {
                            foreach ([$anterior->codigo_barra_anterior, $anterior->codigo_origen_anterior] as $codigoAnterior) {
                                $ultimoNumero = max($ultimoNumero, self::consecutivoCodigo($codigoAnterior, $prefijo));
                            }
                        }
                    }

                    if ($ultimoNumero >= 999) {
                        throw ValidationException::withMessages([
                            'codigo_barra' => "Se agotaron los códigos disponibles para el prefijo {$prefijo}.",
                        ]);
                    }

                    $codigo = $prefijo.\sprintf('%03d', $ultimoNumero + 1);
                    $producto = new self($datos);
                    $producto->setAttribute('codigo_barra', $codigo);
                    $producto->setAttribute('codigo_origen', $codigo);
                    $producto->save();

                    return $producto;
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($codigo === null || self::nombreExiste($datos['nombre']) || ! self::query()
                    ->where('codigo_barra', $codigo)
                    ->orWhere('codigo_origen', $codigo)
                    ->exists()) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'codigo_barra' => 'No se pudo generar un código único para el repuesto. Inténtalo nuevamente.',
        ]);
    }

    private static function consecutivoCodigo(?string $codigo, string $prefijo): int
    {
        if ($codigo === null || ! preg_match('/^'.preg_quote($prefijo, '/').'(\d{3})$/i', $codigo, $coincidencias)) {
            return 0;
        }

        return (int) $coincidencias[1];
    }

    protected function stockDisponible(): Attribute
    {
        return Attribute::get(
            fn (): int => max(0, (int) $this->getAttribute('stock') - (int) ($this->attributes['stock_reservado'] ?? 0)),
        );
    }

    public function estaEnOferta(?Carbon $fecha = null): bool
    {
        $fecha = $fecha ?? now();

        return (bool) $this->activo
            && $this->precio_oferta !== null
            && $this->oferta_inicio_at !== null
            && $this->oferta_fin_at !== null
            && $this->oferta_inicio_at->lte($fecha)
            && $this->oferta_fin_at->gte($fecha);
    }

    public function precioVigente(?Carbon $fecha = null): float
    {
        return $this->estaEnOferta($fecha)
            ? (float) $this->precio_oferta
            : (float) $this->precio;
    }

    public function scopeEnOferta(Builder $query): Builder
    {
        return $query
            ->where('activo', true)
            ->whereNotNull('precio_oferta')
            ->whereNotNull('oferta_inicio_at')
            ->whereNotNull('oferta_fin_at')
            ->where('oferta_inicio_at', '<=', now())
            ->where('oferta_fin_at', '>=', now());
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
