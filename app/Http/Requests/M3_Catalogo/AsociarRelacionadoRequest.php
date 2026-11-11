<?php

declare(strict_types=1);

namespace App\Http\Requests\M3_Catalogo;

/** POST /productos/{id_producto}/relacionados (asociarRelacionado). */
class AsociarRelacionadoRequest extends SolicitudCatalogo
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_producto_relacionado' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'id_producto_relacionado.required' => 'Elige el producto que quieres relacionar.',
            'id_producto_relacionado.integer' => 'El producto relacionado no es válido.',
        ];
    }
}