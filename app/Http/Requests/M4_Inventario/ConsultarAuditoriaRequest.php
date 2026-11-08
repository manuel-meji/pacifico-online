<?php

namespace App\Http\Requests\M4_Inventario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /auditoria/inventario: ids_comercio separados por coma y periodo opcional.
 * El rol lo exige la ruta (Encargado de plataforma); el rango lo valida el servicio.
 */
class ConsultarAuditoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ids_comercio' => ['required', 'string', 'regex:/^\d+(,\d+)*$/'],
            'fecha_inicio' => ['nullable', 'string'],
            'fecha_fin' => ['nullable', 'string'],
        ];
    }

    /** @return list<int> */
    public function idsComercio(): array
    {
        return array_map('intval', explode(',', (string) $this->validated('ids_comercio')));
    }
}
