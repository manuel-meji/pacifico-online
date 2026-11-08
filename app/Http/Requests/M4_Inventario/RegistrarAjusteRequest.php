<?php

namespace App\Http\Requests\M4_Inventario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AjusteSolicitud (POST /ajustes): conteo físico y, si hay diferencia, ajuste autorizado.
 */
class RegistrarAjusteRequest extends FormRequest
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
            'cantidad_sistema_vista' => ['required', 'integer', 'min:0'],
            'cantidad_contada' => ['required', 'integer', 'min:0'],
            'motivo_ajuste' => ['required', 'in:merma,robo,danio,sobrante,error_conteo,otro'],
            'justificacion' => ['required', 'string', 'max:255'],
            'id_autorizacion' => ['nullable', 'string', 'min:1'],
        ];
    }

    /** @return array<string, mixed> */
    public function aSolicitud(): array
    {
        $datos = $this->validated();

        foreach (['id_comercio', 'id_variacion', 'id_ubicacion', 'cantidad_sistema_vista', 'cantidad_contada'] as $campo) {
            $datos[$campo] = (int) $datos[$campo];
        }

        return $datos;
    }
}
