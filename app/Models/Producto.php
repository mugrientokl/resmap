<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'fila_origen',
        'estado_importacion',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function detallesVenta()
    {
        return $this->hasMany(DetalleVenta::class, 'id_producto', 'id_producto');
    }

    public function movimientosInventario()
    {
        return $this->hasMany(InventarioMovimiento::class, 'id_producto', 'id_producto');
    }
}
