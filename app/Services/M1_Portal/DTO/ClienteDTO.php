<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Datos del cliente que se exponen; direccionEnvio es la dirección principal
 * en texto. direccionEnvio no se guarda (tercera forma normal): se arma al
 * leer a partir de direcciones_cliente y se ignora al registrar o actualizar.
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class ClienteDTO
{
    public function __construct(
        public ?int $idCliente,
        public string $nombre,
        public string $apellidos,
        public string $correoElectronico,
        public string $telefono,
        public string $cedula,
        public string $direccionEnvio,
    ) {}
}
