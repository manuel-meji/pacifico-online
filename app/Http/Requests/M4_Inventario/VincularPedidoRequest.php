<?php

namespace App\Http\Requests\M4_Inventario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ReservaVinculoSolicitud: pedido y subpedido de M5 (confirmar y salida por despacho).
 */
class VincularPedidoRequest extends FormRequest
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
        ];
    }

    public function idPedido(): int
    {
        return (int) $this->validated('id_pedido');
    }

    public function idSubpedido(): int
    {
        return (int) $this->validated('id_subpedido');
    }
}
