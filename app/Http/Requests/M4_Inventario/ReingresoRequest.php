<?php

namespace App\Http\Requests\M4_Inventario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ReingresoSolicitud (POST /reservas/{id}/reingreso).
 */
class ReingresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_pedido' => ['required', 'integer', 'min:1'],
            'id_subpedido' => ['required', 'integer', 'min:1'],
            'id_recepcion' => ['required', 'string', 'min:1'],
            'justificacion' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array{id_pedido: int, id_subpedido: int, id_recepcion: string, justificacion: string} */
    public function aSolicitud(): array
    {
        return [
            'id_pedido' => (int) $this->validated('id_pedido'),
            'id_subpedido' => (int) $this->validated('id_subpedido'),
            'id_recepcion' => (string) $this->validated('id_recepcion'),
            'justificacion' => (string) $this->validated('justificacion'),
        ];
    }
}
