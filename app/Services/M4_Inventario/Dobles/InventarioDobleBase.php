<?php

namespace App\Services\M4_Inventario\Dobles;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Base para los dobles de M4 que construyen los módulos consumidores (M1, M5, ...).
 *
 * Declara las 20 operaciones del contrato de M4 v1.3.0 (x-interfaz-php) y las tres
 * de la interfaz anterior (InventarioServiceInterface), con sus firmas exactas. Por
 * defecto cada una responde ERR-006 (dependencia no disponible): el doble solo
 * sobrescribe las operaciones que su módulo consume.
 *
 *     class InventarioStub extends InventarioDobleBase
 *     {
 *         public function consultarStockDisponible(int $idComercio, int $idVariacion, ?int $idUbicacion = null): array { ... }
 *     }
 *
 * Extender esta clase (en lugar de implementar InventarioModuleInterface) hace que el
 * doble siga funcionando cuando la interfaz pase de 6 a las 20 operaciones del
 * contrato (paso 2 del cambio, SC-06).
 */
abstract class InventarioDobleBase implements InventarioModuleInterface
{
    // ------------------------------------------------------------------ interfaz anterior

    public function consultarExistencias(int $variacionId): int
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function solicitarReserva(int $variacionId, int $cantidad, int $pedidoId): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function confirmarSalidaPorDespacho(int $reservaId): bool
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    // ------------------------------------------------------------------ contrato v1.3.0

    public function verificarEstado(): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function consultarStockDisponible(int $idComercio, int $idVariacion, ?int $idUbicacion = null): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function consultarDisponibilidadLote(array $items): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function consultarDisponibilidadProducto(int $idComercio, int $idProducto): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function consultarDisponibilidadProductoLote(array $items): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function reservarExistencias(
        int $idComercio,
        int $idVariacion,
        int $cantidad,
        ?int $idUbicacion = null,
        ?string $claveIdempotencia = null,
        ?int $expiracionMinutos = null,
    ): array {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function liberarReserva(string $idReserva, ?string $motivo = null): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function obtenerReserva(string $idReserva): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function confirmarReserva(string $idReserva, int $idPedido, int $idSubpedido): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function confirmarSalidaPorReserva(string $idReserva, int $idPedido, int $idSubpedido): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function registrarReingresoPorReserva(string $idReserva, array $solicitud): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function listarExistencias(int $idComercio, array $filtros = []): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function fijarStockMinimo(int $idExistencia, int $stockMinimo): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function registrarMovimientoManual(array $solicitud): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function registrarTransferencia(array $solicitud): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function registrarAjusteInventario(array $solicitud): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function consultarKardex(int $idComercio, int $idVariacion, array $filtros = []): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function obtenerKardexValorizado(int $idComercio, string $fechaInicio, string $fechaFin, array $filtros = []): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function consultarInventarioAuditoria(array $idsComercio, ?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function obtenerCostoProductosVendidos(int $idSubpedido): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    protected function noSimulada(string $operacion): ErrorInventarioException
    {
        return ErrorInventarioException::dependenciaNoDisponible('M4', "El doble no simula {$operacion}.");
    }
}
