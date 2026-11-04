<?php

namespace App\Services\M1_Portal\DTO;

use DateTimeInterface;

/**
 * Noticia para mostrar o publicar (borrador, publicada o archivada).
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class NoticiaDTO
{
    public function __construct(
        public ?int $idNoticia,
        public string $titulo,
        public string $slug,
        public string $resumen,
        public string $contenido,
        public ?string $imagenUrl,
        public string $estado,
        public ?DateTimeInterface $fechaPublicacion,
    ) {}
}
