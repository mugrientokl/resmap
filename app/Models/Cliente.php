<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'id_cliente', 'id_cliente');
    }

    public function solicitudesWeb()
    {
        return $this->hasMany(SolicitudWeb::class, 'id_cliente', 'id_cliente');
    }
}
