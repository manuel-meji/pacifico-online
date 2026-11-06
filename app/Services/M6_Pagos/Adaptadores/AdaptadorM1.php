<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos\Adaptadores;

use App\Contracts\M1_Portal\Exceptions\ClienteNoEncontradoException;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Services\M6_Pagos\Clients\ClienteClient;
use App\Services\M6_Pagos\Clients\ClienteDTO;

final class AdaptadorM1 implements ClienteClient
{
    public function __construct(private readonly PortalPublicoContract $portal) {}

    public function obtenerCliente(int $idCliente): ClienteDTO
    {
        try {
            $cliente = $this->portal->obtenerCliente($idCliente);
        } catch (ClienteNoEncontradoException) {
            throw ErrorContratoException::de(CodigoErrorM6::ClienteNoEncontrado);
        }

        return new ClienteDTO(
            idCliente: (int) $cliente['id_cliente'],
            nombre: trim($cliente['nombre'].' '.($cliente['apellidos'] ?? '')),
            identificacionTributaria: (string) ($cliente['identificacion_tributaria'] ?? ''),
            correo: (string) $cliente['correo_electronico'],
        );
    }
}
