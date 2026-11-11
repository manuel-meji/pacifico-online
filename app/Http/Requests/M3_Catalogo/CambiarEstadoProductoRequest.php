<?php

declare(strict_types=1);

namespace App\Http\Requests\M3_Catalogo;

use Illuminate\Validation\Rule;

/** PATCH /productos/{id_producto} (actualizarProducto): publicar, volver a borrador, desactivar o destacar. */
class CambiarEstadoProductoRequest extends SolicitudCatalogo
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estado_producto' => ['required_without:destacado', Rule::in(['borrador', 'publicado', 'desactivado'])],
            'destacado' => ['required_without:estado_producto', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'estado_producto.required_without' => 'Indica el nuevo estado o si el producto es destacado.',
            'estado_producto.in' => 'El estado debe ser borrador, publicado o desactivado.',
            'destacado.required_without' => 'Indica el nuevo estado o si el producto es destacado.',
            'destacado.boolean' => 'Destacado debe ser verdadero o falso.',
        ];
    }
}