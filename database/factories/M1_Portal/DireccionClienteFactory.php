<?php

namespace Database\Factories\M1_Portal;

use App\Models\M1_Portal\Cliente;
use App\Models\M1_Portal\DireccionCliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos de prueba de DireccionCliente que cumplen las restricciones de su migración.
 *
 * @extends Factory<DireccionCliente>
 */
class DireccionClienteFactory extends Factory
{
    protected $model = DireccionCliente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_cliente' => Cliente::factory(),
            'alias_direccion' => fake()->unique()->randomElement(['Casa', 'Trabajo', 'Familia', 'Oficina']),
            'nombre_destinatario' => fake()->name(),
            'telefono_destinatario' => fake()->numerify('8###-####'),
            'provincia' => 'Guanacaste',
            'canton' => 'Liberia',
            'distrito' => 'Liberia',
            'otras_senas' => fake()->streetAddress(),
            'es_principal' => false,
        ];
    }
}
