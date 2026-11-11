<?php

namespace Tests\Doubles\M1;

use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Services\M6_Pagos\Clients\ClienteClient;
use App\Services\M6_Pagos\Clients\ClienteDTO;

/** Fake de M1. Cliente 77 existe; cualquier otro lanza CLIENTE_NO_ENCONTRADO. */
final class FakeClienteClient implements ClienteClient
{
    public function obtenerCliente(int $idCliente): ClienteDTO
    {
        if ($idCliente !== 77) {
            throw new ErrorContratoException('CLIENTE_NO_ENCONTRADO');
        }

        return new ClienteDTO(77, 'Fulano Castillo', '116540892', 'fulano@example.com');
    }
}
