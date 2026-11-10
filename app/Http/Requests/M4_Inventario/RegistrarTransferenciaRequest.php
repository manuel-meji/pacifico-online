<?php

namespace App\Http\Requests\M4_Inventario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TransferenciaSolicitud (POST /transferencias): traslado autorizado entre dos ubicaciones.
 */
class RegistrarTransferenciaRequest extends FormRequest
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
            'id_ubicacion_origen' => ['required', 'integer', 'min:1'],
            'id_ubicacion_destino' => ['required', 'integer', 'min:1', 'different:id_ubicacion_origen'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'justificacion' => ['required', 'string', 'max:255'],
            'id_autorizacion' => ['required', 'string', 'min:1'],
        ];
    }

    /** @return array<string, mixed> */
    public function aSolicitud(): array
    {
        $datos = $this->validated();

        foreach (['id_comercio', 'id_variacion', 'id_ubicacion_origen', 'id_ubicacion_destino', 'cantidad'] as $campo) {
            $datos[$campo] = (int) $datos[$campo];
        }

        return $datos;
    }
}
