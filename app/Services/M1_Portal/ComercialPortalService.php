<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\ComercialPortalContract;

/**
 * Implementación de ComercialPortalContract (contrato M1 1.5.0, SC-08 y
 * SC-11): lo que otros módulos consultan desde su backend sobre destacados y
 * cupones. Delega en los servicios de M1.
 */
class ComercialPortalService implements ComercialPortalContract
{
    public function __construct(
        private DestacadosService $destacados,
        private CuponesService $cupones,
    ) {}

    public function listarCargosDestacado(int $idComercio, string $desde, string $hasta): array
    {
        return $this->destacados->listarCargos($idComercio, $desde, $hasta);
    }

    public function validarCupon(string $codigo, int $idCliente, float $subtotal, array $idsComercios = [], array $subtotalesPorComercio = []): array
    {
        return $this->cupones->validar($codigo, $idCliente, $subtotal, $idsComercios, $subtotalesPorComercio);
    }

    public function registrarUsoCupon(int $idCupon, int $idCliente, int $idPedido): bool
    {
        return $this->cupones->registrarUso($idCupon, $idCliente, $idPedido);
    }
}
