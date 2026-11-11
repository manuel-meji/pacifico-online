<?php

namespace Database\Seeders;

use Database\Factories\UsuarioFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        UsuarioFactory::new()->create([
            'nombre' => 'Test User',
            'correo' => 'test@example.com',
        ]);
    }
}
