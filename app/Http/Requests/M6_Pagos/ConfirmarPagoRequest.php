<?php

declare(strict_types=1);

namespace App\Http\Requests\M6_Pagos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /api/m6/v1/pagos/{id_pago}/confirmaciones (operationId ConfirmarPagoRecibido). */
class ConfirmarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autenticidad la verifica el middleware VerificarFirmaPasarela.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'referencia_proveedor' => ['required', 'string', 'max:100'],
            'estado' => ['required', Rule::in(['aprobado', 'rechazado'])],
            'motivo' => ['nullable', 'string', 'max:200'],
        ];
    }
}
