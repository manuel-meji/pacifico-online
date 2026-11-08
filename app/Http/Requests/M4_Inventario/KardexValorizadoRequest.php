<?php

namespace App\Http\Requests\M4_Inventario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /kardex/valorizado: comercio y periodo obligatorios; tipo de movimiento y
 * paginación opcionales. El rango de fechas lo valida el servicio (ERR-M4-012).
 */
class KardexValorizadoRequest extends FormRequest
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
            'fecha_inicio' => ['required', 'string'],
            'fecha_fin' => ['required', 'string'],
            'tipo_movimiento' => ['nullable', 'string'],
            'pagina' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, mixed> */
    public function filtros(): array
    {
        return array_filter(
            $this->safe()->only(['tipo_movimiento', 'pagina', 'por_pagina']),
            fn ($valor): bool => $valor !== null && $valor !== '',
        );
    }
}
