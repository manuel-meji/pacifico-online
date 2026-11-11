<?php

declare(strict_types=1);

namespace App\Http\Requests\M6_Pagos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/m6/v1/pedidos/{id_pedido}/reembolsos (GestionarReembolso).
 * id_subpedido es opcional: sin él se reembolsa el saldo total del pedido.
 * Si algo falla, el Núcleo responde ERR-005 (422) con el detalle por campo.
 */
class GestionarReembolsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_subpedido' => ['nullable', 'integer', 'min:1'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'motivo' => ['required', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'monto.gt' => 'El monto del reembolso debe ser mayor que cero.',
            'monto.decimal' => 'El monto admite como máximo dos decimales.',
            'motivo.required' => 'Indique el motivo del reembolso.',
        ];
    }
}
