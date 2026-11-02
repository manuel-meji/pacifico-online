<?php

declare(strict_types=1);

namespace App\Contracts\Nucleo;

use App\Enums\Nucleo\RolUsuario;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\Nucleo\Usuario;

interface ServicioIdentidadInterface
{
    /**
     * Crea una cuenta de acceso. Es el único punto del sistema que escribe en la tabla usuarios
     * (PC3, 5.1). No abre una transacción propia: si el módulo que la llama ya tiene una
     * (por ejemplo, M1 al registrar un cliente), la cuenta se revierte junto con ella.
     *
     * @param  array{nombre?: string, correo?: string, contrasena?: string|null}  $datos
     *
     * @throws ErrorNucleoException ERR-005 si los datos no son válidos.
     */
    public function crearCuenta(array $datos, RolUsuario|string $rol, ?int $idComercio = null): Usuario;
}
