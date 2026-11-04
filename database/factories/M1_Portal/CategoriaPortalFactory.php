<?php

namespace Database\Factories\M1_Portal;

use App\Models\M1_Portal\CategoriaPortal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos de prueba de CategoriaPortal que cumplen las restricciones de su migración.
 *
 * @extends Factory<CategoriaPortal>
 */
class CategoriaPortalFactory extends Factory
{
    protected $model = CategoriaPortal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_categoria' => fake()->unique()->numberBetween(1, 100000),
            'orden_visualizacion' => fake()->numberBetween(1, 50),
            'visible_en_menu' => true,
            'icono_url' => null,
        ];
    }
}
