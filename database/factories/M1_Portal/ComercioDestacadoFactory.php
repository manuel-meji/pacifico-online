<?php

namespace Database\Factories\M1_Portal;

use App\Models\M1_Portal\ComercioDestacado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos de prueba de ComercioDestacado que cumplen las restricciones de su migración.
 *
 * @extends Factory<ComercioDestacado>
 */
class ComercioDestacadoFactory extends Factory
{
    protected $model = ComercioDestacado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_comercio' => fake()->numberBetween(1, 100000),
            'posicion' => fake()->numberBetween(1, 20),
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addMonth(),
            'activo' => true,
        ];
    }
}
