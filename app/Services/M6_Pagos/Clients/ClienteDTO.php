<?php

namespace App\Services\M6_Pagos\Clients;

final class ClienteDTO
{
    public function __construct(
        public readonly int $idCliente,
        public readonly string $nombre,
        public readonly string $identificacionTributaria,
        public readonly string $correo,
    ) {}
}
