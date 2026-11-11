<?php

declare(strict_types=1);

namespace App\Http\Requests\M3_Catalogo;

/**
 * POST /productos/{id_producto}/imagenes (agregarImagen).
 *
 * Acepta el archivo en el campo "archivo" (multipart/form-data) o una "imagen_url"
 * (JSON, como en el contrato). El máximo de 8 imágenes lo valida ProductoService.
 */
class AgregarImagenRequest extends SolicitudCatalogo
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'archivo' => ['required_without:imagen_url', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'imagen_url' => ['required_without:archivo', 'url', 'max:500', 'regex:/\.(jpe?g|png)(\?.*)?$/i'],
            'id_variacion' => ['nullable', 'integer', 'min:1'],
            'orden' => ['nullable', 'integer', 'between:1,8'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'archivo.required_without' => 'Sube una imagen.',
            'archivo.uploaded' => 'No se pudo subir la imagen. Revisa que pese menos de 5 MB.',
            'archivo.file' => 'Sube un archivo de imagen válido.',
            'archivo.mimes' => 'La imagen debe ser JPG o PNG.',
            'archivo.max' => 'La imagen no puede pesar más de 5 MB.',
            'imagen_url.required_without' => 'Sube una imagen o indica su enlace.',
            'imagen_url.url' => 'El enlace de la imagen no es válido.',
            'imagen_url.regex' => 'La imagen debe ser JPG o PNG.',
            'orden.between' => 'La posición debe estar entre 1 y 8.',
        ];
    }
}