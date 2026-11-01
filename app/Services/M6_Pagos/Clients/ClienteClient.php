<?php

namespace App\Services\M6_Pagos\Clients;

/** Puerto de M6 hacia M1 (propietario de la entidad Cliente). */
interface ClienteClient
{
    /** @throws \App\Exceptions\M6_Pagos\ErrorContratoException CLIENTE_NO_ENCONTRADO */
    public function obtenerCliente(int $idCliente): ClienteDTO;
}
