<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Categoría de M3 con el orden del menú de M1 (categorias_portal).
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class CategoriaDTO
{
    public function __construct(
        public int $idCategoria,
        public string $nombre,
        public ?int $idCategoriaPadre = null,
        public ?string $iconoUrl = null,
        public ?int $ordenVisualizacion = null,
    ) {}
}
