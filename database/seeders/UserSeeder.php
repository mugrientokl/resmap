<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('UserSeeder solo se puede ejecutar en entornos local o testing.');
        }

        foreach ([
            [
                'username' => 'admin',
                'name' => 'Administrador RESMAP',
                'email' => 'admin@resmap.cl',
                'rol' => 'Administrador',
            ],
            [
                'username' => 'vendedor1',
                'name' => 'Carlos Vendedor',
                'email' => 'carlos@resmap.cl',
                'rol' => 'Vendedor',
            ],
        ] as $attributes) {
            $password = Str::password(24, symbols: false, spaces: false);
            User::updateOrCreate(
                ['username' => $attributes['username']],
                [...$attributes, 'password' => Hash::make($password)],
            );
            $this->command?->info($attributes['username'].' contraseña temporal: '.$password);
        }
    }
}
