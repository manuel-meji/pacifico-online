<?php

namespace App\Http\Requests\M4_Inventario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /kardex: comercio y variación obligatorios; ubicación, periodo y paginación opcionales.
 * Las fechas se validan en el servicio (ERR-M4-012).
 */
class ConsultarKardexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_comercio' => ['required', 'integer'],
            'id_variacion' => ['required', 'integer'],
            'id_ubicacion' => ['nullable', 'integer'],
            'fecha_inicio' => ['nullable', 'string'],
            'fecha_fin' => ['nullable', 'string'],
            'pagina' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, mixed> */
    public function filtros(): array
    {
        return array_filter(
            $this->safe()->only(['id_ubicacion', 'fecha_inicio', 'fecha_fin', 'pagina', 'por_pagina']),
            fn ($valor): bool => $valor !== null && $valor !== '',
        );
    }
}
