<?php

namespace App\Contracts\M1_Portal;

interface PortalServiceInterface
{
    /**
     * Obtener productos y comercios destacados para la página de inicio.
     */
    public function getDestacados(): array;

    /**
     * Búsqueda pública de productos por término y filtros.
     */
    public function buscar(string $query, array $filtros = []): array;
}
