<?php

namespace Tests\Doubles\M1;

use App\Services\M1_Portal\Dobles\CatalogoFake;

/**
 * M3 responde, pero el comercio no tiene productos publicados (CU-009 A2).
 */
class CatalogoSinProductos extends CatalogoFake
{
    public function buscarProductos(array $filtros = []): array
    {
        return [
            'pagina' => $filtros['pagina'] ?? 1,
            'por_pagina' => $filtros['por_pagina'] ?? 20,
            'total_resultados' => 0,
            'productos' => [],
        ];
    }
}
