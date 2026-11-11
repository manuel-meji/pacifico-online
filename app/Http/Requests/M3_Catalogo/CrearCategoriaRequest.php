<?php

declare(strict_types=1);

namespace App\Http\Requests\M3_Catalogo;

use Illuminate\Validation\Rule;

/** POST /categorias (crearCategoria): categoría raíz o subcategoría. */
class CrearCategoriaRequest extends SolicitudCatalogo
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $nombre = ['required', 'string', 'max:100'];
        $idPadre = $this->input('id_categoria_padre');

        // La base no admite dos subcategorías con el mismo nombre bajo el mismo padre.
        if (is_numeric($idPadre)) {
            $nombre[] = Rule::unique('categorias', 'nombre')->where('id_categoria_padre', (int) $idPadre);
        }

        return [
            'nombre' => $nombre,
            'descripcion' => ['nullable', 'string'],
            'id_categoria_padre' => ['nullable', 'integer', 'exists:categorias,id_categoria'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre de la categoría.',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres.',
            'nombre.unique' => 'Ya existe una subcategoría con ese nombre en la categoría elegida.',
            'id_categoria_padre.integer' => 'La categoría superior no es válida.',
            'id_categoria_padre.exists' => 'La categoría superior que elegiste no existe.',
        ];
    }
}
