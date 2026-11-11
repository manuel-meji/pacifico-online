<?php

declare(strict_types=1);

namespace App\Http\Requests\M3_Catalogo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las solicitudes de escritura de M3.
 *
 * El rol (Encargado del comercio) lo exige el middleware RequiereRol en la ruta,
 * por eso authorize() devuelve true. expectsJson() devuelve true para que los
 * errores de validación salgan siempre como ErrorEstandar (ERR-005, 422) por el
 * ManejadorErrores del núcleo, incluso si el cliente olvida el encabezado Accept.
 */
abstract class SolicitudCatalogo extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function expectsJson(): bool
    {
        return true;
    }
}