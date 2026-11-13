<?php

declare(strict_types=1);

namespace App\Http\Requests\M3_Catalogo;

/**
 * PATCH /variaciones/{id_variacion}
 * Actualiza exclusivamente la disponibilidad de una variación.
 */
class ActualizarDisponibilidadVariacionRequest extends SolicitudCatalogo
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'disponible' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_bool($value)) {
                        $fail('El campo disponible debe ser true o false.');
                    }
                },
            ],
        ];
    }
}
