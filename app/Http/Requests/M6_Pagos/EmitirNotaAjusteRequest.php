<?php

declare(strict_types=1);

namespace App\Http\Requests\M6_Pagos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/m6/v1/facturas/{id_factura}/notas-credito y /notas-debito
 * (GenerarNotaCredito, GenerarNotaDebito). RN-004: monto mayor que cero.
 * Si algo falla, el Núcleo responde ERR-005 (422) con el detalle por campo.
 */
class EmitirNotaAjusteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'motivo' => ['required', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'monto.gt' => 'El monto de la nota debe ser mayor que cero.',
            'monto.decimal' => 'El monto admite como máximo dos decimales.',
            'motivo.required' => 'Indique el motivo de la nota.',
        ];
    }
}
