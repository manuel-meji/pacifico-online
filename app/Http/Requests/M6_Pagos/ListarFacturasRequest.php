<?php

declare(strict_types=1);

namespace App\Http\Requests\M6_Pagos;

use Illuminate\Foundation\Http\FormRequest;

/** GET /api/m6/v1/facturas?id_pedido= (operationId ListarFacturasPorPedido). */
class ListarFacturasRequest extends FormRequest
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
        ];
    }
}
