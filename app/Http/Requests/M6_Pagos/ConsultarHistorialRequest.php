<?php

declare(strict_types=1);

namespace App\Http\Requests\M6_Pagos;

use Illuminate\Foundation\Http\FormRequest;

/** GET /api/m6/v1/historial?id_comercio=&id_cliente= (ConsultarHistorialPagosFacturas). */
class ConsultarHistorialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_comercio' => ['nullable', 'integer', 'min:1'],
            'id_cliente' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
