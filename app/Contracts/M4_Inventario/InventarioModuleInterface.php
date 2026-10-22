<?php

namespace App\Contracts\M4_Inventario;

use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Interfaz PHP del contrato de M4 (x-interfaz-php del openapi v1.2.0).
 *
 * Extiende InventarioServiceInterface para que sus consumidores actuales
 * (el doble de M1 y las pruebas de M5) sigan funcionando sin cambios. Cada
 * método corresponde a una operación del contrato (operationId del openapi)
 * y devuelve su mismo cuerpo. Los demás módulos y los controladores de
 * Inertia llaman a M4 por esta interfaz, dentro del mismo proceso.
 */
interface InventarioModuleInterface extends InventarioServiceInterface
{
    /**
     * consultarStockDisponible. Sin existencia registrada devuelve
     * cantidad_disponible 0 y estado agotado; no lanza excepción.
     *
     * @return array{id_comercio: int, id_variacion: int, id_ubicacion: int|null, cantidad_disponible: int, estado_disponibilidad: string, bajo_stock: bool}
     *
     * @throws ErrorInventarioException ERR-005
     */
    public function consultarStockDisponible(int $idComercio, int $idVariacion, ?int $idUbicacion = null): array;

    /**
     * consultarDisponibilidadLote. Entre 1 y 100 elementos; respuesta en el mismo orden.
     *
     * @param  list<array{id_comercio: int, id_variacion: int, id_ubicacion?: int}>  $items
     * @return list<array{id_comercio: int, id_variacion: int, id_ubicacion: int|null, cantidad_disponible: int, estado_disponibilidad: string, bajo_stock: bool}>
     *
     * @throws ErrorInventarioException ERR-005
     */
    public function consultarDisponibilidadLote(array $items): array;

    /**
     * consultarDisponibilidadProducto. Disponible si al menos una
     * variación del producto tiene unidades en el comercio.
     *
     * @return array{id_comercio: int, id_producto: int, estado_disponibilidad: string}
     *
     * @throws ErrorInventarioException ERR-005
     */
    public function consultarDisponibilidadProducto(int $idComercio, int $idProducto): array;

    /**
     * consultarDisponibilidadProductoLote. Entre 1 y 100 elementos; mismo orden.
     *
     * @param  list<array{id_comercio: int, id_producto: int}>  $items
     * @return list<array{id_comercio: int, id_producto: int, estado_disponibilidad: string}>
     *
     * @throws ErrorInventarioException ERR-005
     */
    public function consultarDisponibilidadProductoLote(array $items): array;

    /**
     * reservarExistencias. Reserva atómica bajo bloqueo pesimista (ADR-002). Si se
     * omite la ubicación, M4 elige una con toda la cantidad disponible.
     * La misma clave de idempotencia con los mismos datos devuelve la misma reserva.
     *
     * @return array<string, mixed> Reserva del contrato (id_reserva es UUID).
     *
     * @throws ErrorInventarioException ERR-M4-001, ERR-M4-002, ERR-M4-003, ERR-M4-013, ERR-M4-015, ERR-005, ERR-006
     */
    public function reservarExistencias(
        int $idComercio,
        int $idVariacion,
        int $cantidad,
        ?int $idUbicacion = null,
        ?string $claveIdempotencia = null,
        ?int $expiracionMinutos = null,
    ): array;

    /**
     * liberarReserva. Repetirla no descuenta dos veces.
     *
     * @return array<string, mixed> Reserva del contrato.
     *
     * @throws ErrorInventarioException ERR-M4-004, ERR-M4-008
     */
    public function liberarReserva(string $idReserva, ?string $motivo = null): array;
}
