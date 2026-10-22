<?php

namespace Tests\Doubles\M4;

/** M5 no responde: obtenerPedido y obtenerSubpedido lanzan ERR-006 (503). */
class PedidosNoDisponible extends PedidosContratoM5
{
    public function obtenerPedido(int $idPedido): array
    {
        throw ErrorProveedorException::noDisponible('M5');
    }

    public function obtenerSubpedido(int $idSubpedido, ?int $idComercioSesion = null): array
    {
        throw ErrorProveedorException::noDisponible('M5');
    }
}
