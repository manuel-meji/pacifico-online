<?php

namespace App\Services\M6_Pagos\Clients;

use App\Exceptions\M6_Pagos\ErrorContratoException;

/** Puerto de M6 hacia M1 (propietario de la entidad Cliente). */
interface ClienteClient
{
    /** @throws ErrorContratoException CLIENTE_NO_ENCONTRADO */
    public function obtenerCliente(int $idCliente): ClienteDTO;
}
