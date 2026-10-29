<?php

namespace App\Models;

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
        'razon_social',
        'giro',
        'correo',
        'telefono',
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

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'id_cliente', 'id_cliente');
    }

    public function solicitudesWeb(): HasMany
    {
        return $this->hasMany(SolicitudWeb::class, 'id_cliente', 'id_cliente');
    }
}
