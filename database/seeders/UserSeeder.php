<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'username' => 'admin',
            'name' => 'Administrador RESMAP',
            'email' => 'gabriielalexiss@gmail.com',
            'password' => Hash::make('password123'),
            'rol' => 'Administrador',
        ]);

        User::create([
            'username' => 'vendedor1',
            'name' => 'Carlos Vendedor',
            'email' => 'carlos@resmap.cl',
            'password' => Hash::make('password123'),
            'rol' => 'Vendedor',
        ]);
    }
}
