<?php

declare(strict_types=1);

namespace App\Http\Requests\M6_Pagos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/m6/v1/pagos (operationId ProcesarPagoPedido).
 * Si algo falla, el Núcleo responde ERR-005 (422) con el detalle por campo.
 */
class ProcesarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // La cabecera Idempotency-Key se valida junto con el cuerpo.
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:100'],
            'id_pedido' => ['required', 'integer', 'min:1'],
            'medio_pago' => ['required', 'string', 'max:30'],
            'monto_total' => ['required', 'numeric', 'gt:0'],
            'token_medio_pago' => ['nullable', 'string', 'max:100'],
            // Seguridad PC2: M6 nunca recibe número de tarjeta ni CVV.
            'numero_tarjeta' => ['prohibited'],
            'cvv' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'La cabecera Idempotency-Key es obligatoria.',
            'numero_tarjeta.prohibited' => 'No envíe datos de tarjeta; use el token del medio de pago.',
            'cvv.prohibited' => 'No envíe el código de seguridad; use el token del medio de pago.',
        ];
    }
}
