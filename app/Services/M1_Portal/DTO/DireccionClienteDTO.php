<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Dirección de envío del cliente.
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class DireccionClienteDTO
{
    public function __construct(
        public ?int $idDireccionCliente,
        public ?int $idCliente,
        public string $aliasDireccion,
        public string $nombreDestinatario,
        public string $telefonoDestinatario,
        public string $provincia,
        public string $canton,
        public string $distrito,
        public string $otrasSenas,
        public bool $esPrincipal,
    ) {}
}
