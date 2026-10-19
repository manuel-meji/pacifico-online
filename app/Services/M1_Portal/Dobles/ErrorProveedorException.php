<?php

namespace App\Services\M1_Portal\Dobles;

use RuntimeException;

/**
 * Error que lanzan los dobles de los proveedores de M1, con el formato del núcleo
 * {"error": {"codigo", "mensaje", "detalles"}}.
 *
 * Provisional: se reemplaza cuando el núcleo publique su excepción base.
 */
class ErrorProveedorException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $detalles
     */
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
        public readonly ?array $detalles = null,
    ) {
        parent::__construct($mensaje);
    }

    /**
     * ERR-004: el recurso no existe o no está habilitado.
     */
    public static function noEncontrado(string $mensaje): self
    {
        return new self('ERR-004', $mensaje);
    }

    /**
     * ERR-005: parámetros de entrada inválidos, con el mapa campo → mensaje.
     *
     * @param  array<string, string>  $detalles
     */
    public static function datosInvalidos(array $detalles): self
    {
        return new self('ERR-005', 'Datos inválidos.', $detalles);
    }

    /**
     * ERR-006: el módulo proveedor no respondió.
     */
    public static function noDisponible(string $modulo): self
    {
        return new self('ERR-006', 'Servicio temporalmente no disponible, intentar de nuevo.', ['modulo' => $modulo]);
    }

    /**
     * @return array{error: array{codigo: string, mensaje: string, detalles: array<string, mixed>|null}}
     */
    public function toArray(): array
    {
        return [
            'error' => [
                'codigo' => $this->codigo,
                'mensaje' => $this->getMessage(),
                'detalles' => $this->detalles,
            ],
        ];
    }
}
