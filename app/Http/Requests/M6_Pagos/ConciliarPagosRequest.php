<?php

declare(strict_types=1);

namespace App\Http\Requests\M6_Pagos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/m6/v1/conciliaciones (ConciliarPagosPeriodo).
 * Que fecha_fin no sea anterior a fecha_inicio lo valida el servicio
 * (PERIODO_INVALIDO, ERR-M6-019), como pide el contrato.
 */
class ConciliarPagosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
