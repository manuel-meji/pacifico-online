<?php

declare(strict_types=1);

namespace App\Http\Requests\M3_Catalogo;

/** POST /marcas (crearMarca). */
class CrearMarcaRequest extends SolicitudCatalogo
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', 'unique:marcas,nombre'],
            'logotipo_url' => ['nullable', 'url', 'max:500'],
            'destacada' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre de la marca.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
            'nombre.unique' => 'Ya existe una marca con ese nombre.',
            'logotipo_url.url' => 'El logotipo debe ser un enlace válido.',
        ];
    }
}