<?php

namespace App\Http\Requests\M4_Inventario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ReservaSolicitud (POST /reservas) y encabezado Idempotency-Key.
 */
class ReservarExistenciasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_comercio' => ['required', 'integer', 'min:1'],
            'id_variacion' => ['required', 'integer', 'min:1'],
            'id_ubicacion' => ['nullable', 'integer', 'min:1'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'expiracion_minutos' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function claveIdempotencia(): ?string
    {
        $clave = $this->header('Idempotency-Key');

        return $clave === null || $clave === '' ? null : (string) $clave;
    }
}
