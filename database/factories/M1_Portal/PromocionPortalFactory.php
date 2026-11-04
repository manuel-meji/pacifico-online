<?php

namespace Database\Factories\M1_Portal;

use App\Models\M1_Portal\PromocionPortal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos de prueba de PromocionPortal que cumplen las restricciones de su migración.
 *
 * @extends Factory<PromocionPortal>
 */
class PromocionPortalFactory extends Factory
{
    protected $model = PromocionPortal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo_promocion' => fake()->sentence(4),
            'descripcion_promocion' => fake()->optional()->sentence(),
            'imagen_url' => fake()->imageUrl(),
            'tipo_destino' => 'producto',
            'id_producto' => fake()->numberBetween(1, 100000),
            'id_comercio' => null,
            'id_categoria' => null,
            'enlace_url' => null,
            'orden_visualizacion' => fake()->numberBetween(1, 10),
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addWeek(),
            'activa' => true,
        ];
    }
}
