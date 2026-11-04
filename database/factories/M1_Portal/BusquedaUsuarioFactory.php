<?php

namespace Database\Factories\M1_Portal;

use App\Models\M1_Portal\BusquedaUsuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos de prueba de BusquedaUsuario que cumplen las restricciones de su migración.
 *
 * @extends Factory<BusquedaUsuario>
 */
class BusquedaUsuarioFactory extends Factory
{
    protected $model = BusquedaUsuario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_cliente' => null,
            'identificador_sesion' => fake()->uuid(),
            'termino_busqueda' => fake()->word(),
            'cantidad_resultados' => fake()->numberBetween(0, 200),
            'fecha_busqueda' => now(),
        ];
    }
}
