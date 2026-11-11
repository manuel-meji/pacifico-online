<?php

namespace App\Http\Requests\M4_Inventario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * MovimientoSolicitud (POST /movimientos): entrada con costo o salida manual.
 * Los permisos los exige la ruta (RequiereRol); las reglas de negocio, el servicio.
 */
class RegistrarMovimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_comercio' => ['required', 'integer', 'min:1'],
            'id_variacion' => ['required', 'integer', 'min:1'],
            'id_ubicacion' => ['required', 'integer', 'min:1'],
            'tipo_movimiento' => ['required', 'in:entrada,salida'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'costo_unitario' => ['required_if:tipo_movimiento,entrada', 'nullable', 'numeric', 'min:0'],
            'justificacion' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Solicitud con los nombres y tipos del contrato.
     *
     * @return array<string, mixed>
     */
    public function aSolicitud(): array
    {
        $datos = $this->validated();

        return [
            'id_comercio' => (int) $datos['id_comercio'],
            'id_variacion' => (int) $datos['id_variacion'],
            'id_ubicacion' => (int) $datos['id_ubicacion'],
            'tipo_movimiento' => $datos['tipo_movimiento'],
            'cantidad' => (int) $datos['cantidad'],
            'costo_unitario' => isset($datos['costo_unitario']) ? (float) $datos['costo_unitario'] : null,
            'justificacion' => $datos['justificacion'],
        ];
    }
}
