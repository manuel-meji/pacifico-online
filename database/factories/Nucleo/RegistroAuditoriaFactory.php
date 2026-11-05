<?php

declare(strict_types=1);

namespace Database\Factories\Nucleo;

use App\Models\Nucleo\RegistroAuditoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistroAuditoria>
 */
class RegistroAuditoriaFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id_usuario' => null,
            'id_comercio' => null,
            'fecha_hora' => now(),
            'accion' => 'registro_creado',
            'modulo' => 'NUCLEO',
            'entidad' => 'pruebas',
            'entidad_id' => 1,
            'resultado' => 'exitoso',
            'motivo' => null,
        ];
    }
}
