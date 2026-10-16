<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cliente;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        Cliente::create([
            'rut' => '76.123.456-K',
            'nombre' => 'Constructora e Ingeniería del Biobío Ltda.',
            'correo' => 'contacto@construbiobiyo.cl',
            'telefono' => '+56912345678',
            'direccion' => 'Parque Industrial Coronel, Lote 4'
        ]);

        Cliente::create([
            'rut' => '77.987.654-3',
            'nombre' => 'Faenas y Movimiento de Tierra SPA',
            'correo' => 'operaciones@fmtmaquinaria.cl',
            'telefono' => '+56987654321',
            'direccion' => 'Camino a Cabrero Km 12, Los Ángeles'
        ]);
    }
}