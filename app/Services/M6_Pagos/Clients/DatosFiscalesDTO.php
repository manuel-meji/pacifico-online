<?php

namespace App\Services\M6_Pagos\Clients;

final class DatosFiscalesDTO
{
    public function __construct(
        public readonly int $idComercio,
        public readonly string $razonSocial,
        public readonly string $cedulaJuridica,
        public readonly string $condicionContribuyente,
    ) {}
}
