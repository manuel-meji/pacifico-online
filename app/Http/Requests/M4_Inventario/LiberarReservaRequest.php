<?php

namespace App\Http\Requests\M4_Inventario;

use App\Enums\M4_Inventario\MotivoCierre;
use Illuminate\Foundation\Http\FormRequest;

/**
 * LiberarReservaSolicitud: motivo pago_rechazado, pedido_cancelado o solicitud_m5.
 */
class LiberarReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['motivo' => ['required', 'in:pago_rechazado,pedido_cancelado,solicitud_m5']];
    }

    public function motivo(): MotivoCierre
    {
        return MotivoCierre::from($this->validated('motivo'));
    }
}
