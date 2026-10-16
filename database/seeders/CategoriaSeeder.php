<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Categoria;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        Categoria::create([
            'nombre_categoria' => 'Filtros y Lubricantes',
            'descripcion' => 'Filtros de aceite, aire, combustible y aceites para maquinaria pesada.'
        ]);

        Categoria::create([
            'nombre_categoria' => 'Tren de Rodaje',
            'descripcion' => 'Zapatas, rodillos, cadenas y ruedas guía para excavadoras y bulldozers.'
        ]);

        Categoria::create([
            'nombre_categoria' => 'Sistema Hidráulico',
            'descripcion' => 'Bombas hidráulicas, mangueras de alta presión, cilindros y sellos.'
        ]);

        Categoria::create([
            'nombre_categoria' => 'Motor y Transmisión',
            'descripcion' => 'Componentes de motor, turbos, inyectores y repuestos de caja.'
        ]);
    }
}