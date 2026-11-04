<?php

declare(strict_types=1);

namespace App\Contracts\Nucleo;

/**
 * Dependencia del núcleo hacia M1. El núcleo no lee la tabla clientes:
 * M1 implementa esta interfaz.
 */
interface ResuelveClienteInterface
{
    /**
     * Devuelve el id_cliente asociado al usuario, o null si el usuario no es cliente.
     */
    public function obtenerIdClientePorUsuario(int $idUsuario): ?int;
}