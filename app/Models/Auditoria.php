<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Auditoria extends Model
{
    protected $table = 'auditorias';

    protected $primaryKey = 'id_auditoria';

    protected $fillable = [
        'user_id', 'actor_nombre', 'modelo', 'modelo_id', 'accion', 'categoria',
        'resultado', 'descripcion', 'datos_anteriores', 'datos_nuevos', 'ip',
        'correlation_id', 'lote_id', 'user_agent', 'hash_previo', 'hash',
    ];

    protected static function booted(): void
    {
        $inmutable = static function (): never {
            throw new LogicException('Los registros de actividad no se pueden modificar ni eliminar.');
        };

        static::updating($inmutable);
        static::deleting($inmutable);
    }

    protected function casts(): array
    {
        return [
            'datos_anteriores' => 'array',
            'datos_nuevos' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getEtiquetaModeloAttribute(): string
    {
        return config("actividad.modelos.{$this->modelo}.etiqueta") ?? class_basename($this->modelo);
    }

    public function getEtiquetaCategoriaAttribute(): string
    {
        return config("actividad.categorias.{$this->categoria}", ucfirst($this->categoria));
    }

    public function getNombreActorAttribute(): string
    {
        return $this->usuario?->name ?? $this->actor_nombre ?? 'Sistema';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function scopeFiltrar(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['categoria'] ?? null, fn (Builder $q, string $v) => $q->where('categoria', $v))
            ->when($filtros['accion'] ?? null, fn (Builder $q, string $v) => $q->where('accion', $v))
            ->when($filtros['modelo'] ?? null, fn (Builder $q, string $v) => $q->where('modelo', $v))
            ->when($filtros['usuario'] ?? null, fn (Builder $q, int $v) => $q->where('user_id', $v))
            ->when($filtros['registro'] ?? null, fn (Builder $q, int $v) => $q->where('modelo_id', $v))
            ->when($filtros['resultado'] ?? null, fn (Builder $q, string $v) => $q->where('resultado', $v))
            ->when($filtros['lote'] ?? null, fn (Builder $q, string $v) => $q->where('lote_id', $v))
            ->when($filtros['desde'] ?? null, fn (Builder $q, string $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, string $v) => $q->whereDate('created_at', '<=', $v));
    }
}
