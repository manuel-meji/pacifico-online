<?php

declare(strict_types=1);

namespace App\Models\Nucleo;

trait VerificaRol
{
    protected function tieneRol($usuario, Rol ...$roles): bool
    {
        if ($usuario === null || ! $usuario->activo) {
            return false;
        }

        $actual = $usuario->rol instanceof Rol
            ? $usuario->rol
            : Rol::tryFrom((string) $usuario->rol);

        return $actual !== null && in_array($actual, $roles, true);
    }

    protected function esEncargadoPlataforma($u): bool { return $this->tieneRol($u, Rol::EncargadoPlataforma); }
    protected function esEncargadoComercio($u): bool   { return $this->tieneRol($u, Rol::EncargadoComercio); }
    protected function esCliente($u): bool             { return $this->tieneRol($u, Rol::Cliente); }
}