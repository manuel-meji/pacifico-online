<?php

namespace Database\Factories\M1_Portal;

use App\Models\M1_Portal\BusquedaUsuario;
use App\Models\M1_Portal\FiltroAplicado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos de prueba de FiltroAplicado que cumplen las restricciones de su migración.
 *
 * @extends Factory<FiltroAplicado>
 */
class FiltroAplicadoFactory extends Factory
{
    protected $model = FiltroAplicado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_busqueda_usuario' => BusquedaUsuario::factory(),
            'tipo_filtro' => fake()->randomElement(FiltroAplicado::TIPOS),
            'valor_filtro' => (string) fake()->numberBetween(1, 1000),
        ];
    }
}
