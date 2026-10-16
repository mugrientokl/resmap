<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoImportado extends Model
{
    protected $table = 'productos_importados';

    protected $fillable = [
        'archivo_origen',
        'fila_origen',
        'it',
        'ubicacion',
        'stock_origen',
        'detalle',
        'codigo_origen',
        'unidad',
        'precio_iva_origen',
        'categoria_origen',
        'precio_neto_origen',
        'datos_originales',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'datos_originales' => 'array',
        ];
    }
}
