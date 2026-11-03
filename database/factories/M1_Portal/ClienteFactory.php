<?php

namespace Database\Factories\M1_Portal;

use App\Models\M1_Portal\Cliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos de prueba de Cliente que cumplen las restricciones de su migración.
 *
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_usuario' => User::factory(),
            'cedula' => fake()->unique()->numerify('#-####-####'),
            'nombre' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'correo_electronico' => fake()->unique()->safeEmail(),
            'telefono' => fake()->numerify('8###-####'),
            'estado_cliente' => Cliente::ESTADO_ACTIVO,
        ];
    }
}
