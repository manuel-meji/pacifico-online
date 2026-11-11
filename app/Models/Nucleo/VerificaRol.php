<?php

declare(strict_types=1);

namespace App\Models\Nucleo;

use App\Enums\Nucleo\RolUsuario;

trait VerificaRol
{
    protected function tieneRol($usuario, RolUsuario ...$roles): bool
    {
        if ($usuario === null || ! $usuario->activo) {
            return false;
        }

        // Usuario castea rol a RolUsuario; en otros casos puede llegar como texto.
        $valor = $usuario->rol instanceof \BackedEnum
            ? $usuario->rol->value
            : (string) $usuario->rol;

        $actual = RolUsuario::tryFrom($valor);

        return $actual !== null && in_array($actual, $roles, true);
    }

    protected function esEncargadoPlataforma($u): bool
    {
        return $this->tieneRol($u, RolUsuario::EncargadoPlataforma);
    }

    protected function esEncargadoComercio($u): bool
    {
        return $this->tieneRol($u, RolUsuario::EncargadoComercio);
    }

    protected function esCliente($u): bool
    {
        return $this->tieneRol($u, RolUsuario::Cliente);
    }
}
