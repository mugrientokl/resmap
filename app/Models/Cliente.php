<?php

namespace App\Models;

use App\Support\Rut;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $primaryKey = 'id_cliente';

    protected $fillable = [
        'rut',
        'nombre',
        'contacto',
        'razon_social',
        'giro',
        'correo',
        'telefono',
        'telefono_whatsapp',
        'direccion',
        'region',
        'comuna',
        'ciudad',
        'consentimiento_whatsapp_at',
        'consentimiento_whatsapp_origen',
    ];

    protected function casts(): array
    {
        return ['consentimiento_whatsapp_at' => 'datetime'];
    }

    protected function rut(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor): ?string => Rut::normalizar($valor),
        );
    }

    protected function rutFormateado(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => Rut::formatear($this->attributes['rut'] ?? null),
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function snapshotDatos(): array
    {
        return [
            'rut' => $this->rut,
            'nombre' => $this->nombre,
            'contacto' => $this->contacto,
            'razon_social' => $this->razon_social,
            'giro' => $this->giro,
            'correo' => $this->correo,
            'telefono' => $this->telefono,
            'telefono_whatsapp' => $this->telefono_whatsapp,
            'direccion' => $this->direccion,
            'region' => $this->region,
            'comuna' => $this->comuna,
            'ciudad' => $this->ciudad,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeSnapshot(array $datos): self
    {
        return new self($datos);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'id_cliente', 'id_cliente');
    }

    public function solicitudesWeb(): HasMany
    {
        return $this->hasMany(SolicitudWeb::class, 'id_cliente', 'id_cliente');
    }
}
