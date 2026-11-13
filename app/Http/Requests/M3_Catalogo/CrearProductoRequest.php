<?php

declare(strict_types=1);

namespace App\Http\Requests\M3_Catalogo;

use Illuminate\Validation\Rule;

/**
 * POST /productos (crearProducto).
 * Acepta id_comercio opcional para verificarlo, pero el comercio
 * siempre se determina desde la sesión autenticada (RN-12).
 */
class CrearProductoRequest extends SolicitudCatalogo
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_comercio' => ['sometimes', 'required', 'integer', 'min:1'],
            'id_categoria' => ['required', 'integer', 'exists:categorias,id_categoria'],
            'id_marca' => ['nullable', 'integer', 'exists:marcas,id_marca'],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'destacado' => ['sometimes', 'boolean'],
            // Un producto nuevo no tiene variación ni imagen, así que solo puede nacer en borrador.
            'estado_producto' => ['sometimes', Rule::in(['borrador'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'id_categoria.required' => 'Elige una categoría.',
            'id_categoria.exists' => 'La categoría que elegiste no existe.',
            'id_marca.exists' => 'La marca que elegiste no existe.',
            'nombre.required' => 'Escribe el nombre del producto.',
            'nombre.max' => 'El nombre no puede tener más de 150 caracteres.',
            'video_url.url' => 'El video debe ser un enlace válido.',
            'estado_producto.in' => 'Un producto nuevo empieza en borrador. Publícalo cuando tenga una variación y una imagen.',
        ];
    }
}
