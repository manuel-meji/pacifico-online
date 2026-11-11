<?php

namespace App\Services\M1_Portal\DTO;

use DateTimeInterface;

/**
 * Promoción para mostrar o publicar; idDestino es el producto, comercio o
 * categoría según tipoDestino (producto, comercio, categoria o enlace).
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class PromocionDTO
{
    public function __construct(
        public ?int $idPromocion,
        public string $titulo,
        public ?string $descripcion,
        public string $imagenUrl,
        public string $tipoDestino,
        public ?int $idDestino,
        public ?string $enlaceUrl,
        public int $orden,
        public DateTimeInterface $fechaInicio,
        public DateTimeInterface $fechaFin,
    ) {}
}
