<?php

declare(strict_types=1);

namespace App\Policies\Nucleo;

use App\Models\Nucleo\Usuario;
use App\Models\Nucleo\VerificaRol;

/**
 * Funciones comunes para las Policies del Núcleo.
 *
 * No representa autorización de una entidad específica.
 * Las Policies concretas de cada módulo deben extender o reutilizar
 * estas comprobaciones.
 */
abstract class PolicyBaseNucleo
{
    use VerificaRol;

    protected function usuarioActivo(Usuario $usuario): bool
    {
        return $usuario->activo === true;
    }

    protected function perteneceAlComercio(
        Usuario $usuario,
        int $idComercio
    ): bool {
        /*
         * TODO PERSONA 6:
         * Cuando estén disponibles PerteneceAComercio y
         * EstablecerComercio, revisar si esta comprobación debe
         * delegarse al contexto/trait común para evitar duplicación.
         */
        return $this->esEncargadoComercio($usuario)
            && $usuario->id_comercio === $idComercio;
    }
}
