<?php

namespace Database\Factories\M4_Inventario;

use App\Models\M4_Inventario\Existencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Existencias de prueba de M4. Por defecto: comercio 104 (escenario de
 * demostración), ubicación 12 (sede «Bodega central» de M2) y sin reservas.
 *
 * @extends Factory<Existencia>
 */
class ExistenciaFactory extends Factory
{
    protected $model = Existencia::class;

    public function definition(): array
    {
        return [
            'id_comercio' => 104,
            'id_producto' => 301,
            'id_variacion' => $this->faker->unique()->numberBetween(600, 999),
            'id_ubicacion' => 12,
            'cantidad_fisica' => 10,
            'cantidad_reservada' => 0,
            'stock_minimo' => 0,
            'costo_promedio' => 8500,
        ];
    }

    public function sinUnidades(): static
    {
        return $this->state(['cantidad_fisica' => 0, 'cantidad_reservada' => 0]);
    }

    public function conMinimo(int $stockMinimo): static
    {
        return $this->state(['stock_minimo' => $stockMinimo]);
    }
}
