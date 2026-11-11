<?php

namespace Tests\Doubles\M7;

use App\Contracts\M7_Contabilidad\ComerciosM7GatewayInterface;
use RuntimeException;

class ComerciosM7Stub implements ComerciosM7GatewayInterface
{
    private array $datosFiscales = [];

    private array $comisiones = [];

    private bool $datosFiscalesNoDisponibles = false;

    private bool $comisionNoDisponible = false;

    public function configurarDatosFiscales(
        int $comercioId,
        array $datos
    ): void {
        $this->datosFiscales[$comercioId] = $datos;
        $this->datosFiscalesNoDisponibles = false;
    }

    public function configurarComision(
        int $subpedidoId,
        float $monto
    ): void {
        $this->comisiones[$subpedidoId] = $monto;
        $this->comisionNoDisponible = false;
    }

    public function configurarDatosFiscalesNoDisponibles(): void
    {
        $this->datosFiscalesNoDisponibles = true;
    }

    public function configurarComisionNoDisponible(): void
    {
        $this->comisionNoDisponible = true;
    }

    public function obtenerDatosFiscalesComercio(int $comercioId): array
    {
        if ($this->datosFiscalesNoDisponibles) {
            throw new RuntimeException(
                'DATOS_FISCALES_NO_DISPONIBLES',
                503
            );
        }

        if (!isset($this->datosFiscales[$comercioId])) {
            throw new RuntimeException(
                'COMERCIO_NO_ENCONTRADO',
                404
            );
        }

        return $this->datosFiscales[$comercioId];
    }

    public function obtenerComisionSubpedido(int $subpedidoId): float
    {
        if ($this->comisionNoDisponible) {
            throw new RuntimeException(
                'COMISION_NO_DISPONIBLE',
                503
            );
        }

        if (!array_key_exists($subpedidoId, $this->comisiones)) {
            throw new RuntimeException(
                'COMISION_SUBPEDIDO_NO_ENCONTRADA',
                404
            );
        }

        return $this->comisiones[$subpedidoId];
    }
}
