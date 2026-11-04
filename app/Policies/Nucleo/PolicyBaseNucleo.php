<?php

declare(strict_types=1);

namespace App\Policies\Nucleo;

use App\Enums\Nucleo\RolUsuario;
use App\Models\Nucleo\Usuario;

/**
 * Funciones comunes para las Policies del Núcleo.
 *
 * No representa autorización de una entidad específica.
 * Las Policies concretas de cada módulo deben extender o reutilizar
 * estas comprobaciones.
 */
abstract class PolicyBaseNucleo
{
    protected function usuarioActivo(Usuario $usuario): bool
    {
        return $usuario->activo === true;
    }

    protected function esEncargadoPlataforma(Usuario $usuario): bool
    {
        return $this->usuarioActivo($usuario)
            && $usuario->rol === RolUsuario::EncargadoPlataforma;
    }

    protected function esEncargadoComercio(Usuario $usuario): bool
    {
        return $this->usuarioActivo($usuario)
            && $usuario->rol === RolUsuario::EncargadoComercio;
    }

    protected function esCliente(Usuario $usuario): bool
    {
        return $this->usuarioActivo($usuario)
            && $usuario->rol === RolUsuario::Cliente;
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