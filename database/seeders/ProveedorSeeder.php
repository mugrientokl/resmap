<?php

namespace Database\Seeders;

use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class ProveedorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Proveedor::firstOrCreate(['nombre' => 'Imbra'], ['contacto' => 'Ricardo Soto']);
        Proveedor::firstOrCreate(['nombre' => 'Brajovic'], ['contacto' => 'Victoria Rodriguez']);
    }
}
