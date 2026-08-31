<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\PortalServiceInterface;

class PortalService implements PortalServiceInterface
{
    public function getDestacados(): array
    {
        return [
            'comercios_destacados' => [],
            'productos_destacados' => [],
        ];
    }

    public function buscar(string $query, array $filtros = []): array
    {
        return [
            'query' => $query,
            'total' => 0,
            'resultados' => [],
        ];
    }
}
