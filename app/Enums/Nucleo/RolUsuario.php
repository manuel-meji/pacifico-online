<?php

declare(strict_types=1);

namespace App\Enums\Nucleo;

/**
 * Los tres roles de la plataforma (PC3, sección 5.2).
 * Los valores coinciden con la restricción CHECK de la tabla usuarios.
 */
enum RolUsuario: string
{
    case EncargadoPlataforma = 'encargado_plataforma';
    case EncargadoComercio = 'encargado_comercio';
    case Cliente = 'cliente';
}
