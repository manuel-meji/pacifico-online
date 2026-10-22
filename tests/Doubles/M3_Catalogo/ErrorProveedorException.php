<?php

namespace Tests\Doubles\M3_Catalogo;

use RuntimeException;

class ErrorProveedorException extends RuntimeException
{
    private string $tipo;
    private string $proveedor;
    private array $detalles;

    public function __construct(
        string $mensaje,
        string $tipo,
        string $proveedor,
        array $detalles = []
    ) {
        parent::__construct($mensaje);

        $this->tipo = $tipo;
        $this->proveedor = $proveedor;
        $this->detalles = $detalles;
    }

    public static function datosInvalidos(array $detalles = []): self
    {
        return new self(
            'Los datos enviados no son válidos.',
            'datos_invalidos',
            'local',
            $detalles
        );
    }

    public static function noEncontrado(
        string $mensaje = 'El recurso no fue encontrado.'
    ): self {
        return new self(
            $mensaje,
            'no_encontrado',
            'local'
        );
    }

    public static function noDisponible(string $proveedor): self
    {
        return new self(
            "El proveedor {$proveedor} no está disponible.",
            'proveedor_no_disponible',
            $proveedor
        );
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function getProveedor(): string
    {
        return $this->proveedor;
    }

    public function getDetalles(): array
    {
        return $this->detalles;
    }
}