<?php

namespace App\Http\Requests\M4_Inventario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /existencias/{id_existencia}/stock-minimo.
 */
class FijarStockMinimoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['stock_minimo' => ['required', 'integer', 'min:0']];
    }

    public function stockMinimo(): int
    {
        return (int) $this->validated('stock_minimo');
    }
}
