<?php

namespace Database\Factories\M1_Portal;

use App\Models\M1_Portal\ProductoDestacado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos de prueba de ProductoDestacado que cumplen las restricciones de su migración.
 *
 * @extends Factory<ProductoDestacado>
 */
class ProductoDestacadoFactory extends Factory
{
    protected $model = ProductoDestacado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_producto' => fake()->numberBetween(1, 100000),
            'posicion' => fake()->numberBetween(1, 20),
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addMonth(),
            'activo' => true,
        ];
    }
}
