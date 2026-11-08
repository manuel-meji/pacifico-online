<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\ClienteNoEncontradoException;
use App\Contracts\Nucleo\ResuelveClienteInterface;

/**
 * Implementación de M1 de ResuelveClienteInterface (tareas 18 y 23 del plan
 * del PC4). La usa el middleware EstablecerCliente del núcleo para fijar el
 * ámbito de cliente (ContextoCliente, RN-12, ADR-006) de la petición.
 *
 * El núcleo no lee la tabla clientes; por eso M1 le entrega esta clase.
 * Devuelve null si la cuenta no pertenece a un cliente (comercio, plataforma
 * o una cuenta sin fila en clientes): nunca lanza ClienteNoEncontrado.
 */
class ResolvedorCliente implements ResuelveClienteInterface
{
    public function __construct(private ClienteService $clientes) {}

    public function obtenerIdClientePorUsuario(int $idUsuario): ?int
    {
        try {
            return $this->clientes->obtenerIdPorUsuario($idUsuario);
        } catch (ClienteNoEncontradoException) {
            return null;
        }
    }
}
