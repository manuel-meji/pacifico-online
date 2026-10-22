<?php

namespace App\Contracts\M5_Pedidos;

/**
 * Contrato de operaciones sobre el Carrito de Compras (M5).
 * Basado en la especificación OpenAPI de M5 (v1.1.0) - Tag "Carrito".
 * Propietario de entidades: pedidos_carritos, items_carrito.
 */
interface CarritoServiceInterface
{
    /**
     * Obtener el carrito de compras activo de un cliente y sus ítems.
     * Equivale a: GET /api/m5/v1/carrito
     * Consumidores: Frontend del portal, M1 Portal público y búsqueda.
     *
     * @param  int  $idCliente  Identificador único del cliente (M1).
     * @return array{id_carrito: int, id_cliente: int, monto_estimado: ?float, created_at: ?string, updated_at: ?string, items: list<array<string, mixed>>} Estructura del esquema Carrito.
     */
    public function obtenerCarrito(int $idCliente): array;

    /**
     * Vaciar el carrito de compras activo del cliente.
     * Equivale a: DELETE /api/m5/v1/carrito
     * Consumidores: Frontend del portal.
     *
     * @param  int  $idCliente  Identificador único del cliente (M1).
     * @return array{mensaje: string} Estructura del esquema MensajeOperacion.
     */
    public function vaciarCarrito(int $idCliente): array;

    /**
     * Agregar una variación de producto al carrito de compras activo.
     * Valida existencia en M3 (obtenerVariacion) y existencias en M4 (consultarStockDisponible).
     * Equivale a: POST /api/m5/v1/carrito/items
     * Consumidores: Frontend del portal, M1 Portal público y búsqueda.
     *
     * @param  int  $idCliente  Identificador único del cliente (M1).
     * @param  array{id_variacion: int, cantidad: int, id_reserva?: ?string}  $datos  Datos de la variación y cantidad.
     * @return array{id_carrito: int, id_cliente: int, monto_estimado: ?float, created_at: ?string, updated_at: ?string, items: list<array<string, mixed>>} Carrito actualizado.
     */
    public function agregarItemCarrito(int $idCliente, array $datos): array;

    /**
     * Modificar la cantidad de un ítem existente en el carrito activo.
     * Equivale a: PUT /api/m5/v1/carrito/items/{id_item_carrito}
     * Consumidores: Frontend del portal.
     *
     * @param  int  $idItemCarrito  Identificador único del ítem en items_carrito.
     * @param  int  $cantidad  Nueva cantidad deseada (> 0).
     * @param  int|null  $idCliente  Identificador opcional del cliente para verificación de pertenencia.
     * @return array{id_carrito: int, id_cliente: int, monto_estimado: ?float, created_at: ?string, updated_at: ?string, items: list<array<string, mixed>>} Carrito actualizado.
     */
    public function actualizarCantidadItemCarrito(int $idItemCarrito, int $cantidad, ?int $idCliente = null): array;

    /**
     * Eliminar un ítem individual del carrito de compras.
     * Equivale a: DELETE /api/m5/v1/carrito/items/{id_item_carrito}
     * Consumidores: Frontend del portal.
     *
     * @param  int  $idItemCarrito  Identificador único del ítem en items_carrito.
     * @param  int|null  $idCliente  Identificador opcional del cliente para verificación de pertenencia.
     * @return array{id_carrito: int, id_cliente: int, monto_estimado: ?float, created_at: ?string, updated_at: ?string, items: list<array<string, mixed>>} Carrito actualizado.
     */
    public function eliminarItemCarrito(int $idItemCarrito, ?int $idCliente = null): array;
}
