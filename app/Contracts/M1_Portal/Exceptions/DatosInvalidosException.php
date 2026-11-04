<?php

namespace App\Contracts\M1_Portal\Exceptions;

use Throwable;

/**
 * Datos obligatorios faltantes o con formato incorrecto (ERR-005). Lleva el
 * mapa campo → mensaje que va en error.detalles.
 */
class DatosInvalidosException extends PortalPublicoException
{
    protected string $codigo = 'ERR-005';

    /**
     * @param  array<string, string>  $detalles  Campo → mensaje.
     */
    public function __construct(private array $detalles = [], ?string $mensaje = null, ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, $anterior);
    }

    /**
     * @return array<string, string>
     */
    public function detalles(): array
    {
        return $this->detalles;
    }

    /**
     * @return array<string, string>|null
     */
    public function getDetalles(): ?array
    {
        return $this->detalles === [] ? null : $this->detalles;
    }

    protected function mensajePorDefecto(): string
    {
        return 'Datos inválidos.';
    }
}
