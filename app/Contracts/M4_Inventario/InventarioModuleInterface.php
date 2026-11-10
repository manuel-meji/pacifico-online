<?php

namespace App\Contracts\M4_Inventario;

use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Interfaz PHP del contrato de M4 (x-interfaz-php del openapi v1.3.0): las 20
 * operaciones del contrato (paso 2 de SC-06, después de que los dobles de M1 y
 * M5 extendieran InventarioDobleBase).
 *
 * Extiende InventarioServiceInterface para que sus consumidores anteriores sigan
 * funcionando. Cada método corresponde a una operación del contrato (operationId)
 * y devuelve su mismo cuerpo, con los nombres de campo de los schemas. Los demás
 * módulos llaman a M4 por esta interfaz dentro del mismo proceso; los
 * controladores de la API /api/m4/v1 (que usa el frontend React) también.
 */
interface InventarioModuleInterface extends InventarioServiceInterface
{
    /**
     * verificarEstado (GET /health).
     *
     * @return array{status: string, module: string}
     */
    public function verificarEstado(): array;

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

    /**
     * obtenerReserva (GET /reservas/{id_reserva}).
     *
     * @return array<string, mixed> Reserva.
     *
     * @throws ErrorInventarioException ERR-M4-004, ERR-003
     */
    public function obtenerReserva(string $idReserva): array;

    /**
     * confirmarReserva (POST /reservas/{id_reserva}/confirmar). ACTIVA → CONFIRMADA;
     * repetirla con el mismo vínculo devuelve la reserva sin cambios.
     *
     * @return array<string, mixed> Reserva.
     *
     * @throws ErrorInventarioException ERR-M4-004, ERR-M4-005, ERR-M4-006, ERR-M4-007
     */
    public function confirmarReserva(string $idReserva, int $idPedido, int $idSubpedido): array;

    /**
     * confirmarSalidaPorReserva (POST /reservas/{id_reserva}/salida). CONFIRMADA → CONSUMIDA,
     * con salida física valorizada y kardex; idempotente.
     *
     * @return array<string, mixed> SalidaReserva.
     *
     * @throws ErrorInventarioException ERR-M4-004, ERR-M4-005, ERR-M4-006, ERR-M4-007, ERR-M4-009, ERR-006
     */
    public function confirmarSalidaPorReserva(string $idReserva, int $idPedido, int $idSubpedido): array;

    /**
     * registrarReingresoPorReserva (POST /reservas/{id_reserva}/reingreso).
     *
     * @param  array{id_pedido: int, id_subpedido: int, id_recepcion: string, justificacion: string}  $solicitud
     * @return array<string, mixed> Movimiento.
     *
     * @throws ErrorInventarioException ERR-M4-004, ERR-M4-006, ERR-M4-007, ERR-M4-010, ERR-M4-011
     */
    public function registrarReingresoPorReserva(string $idReserva, array $solicitud): array;

    /**
     * listarExistencias (GET /existencias).
     *
     * @param  array{id_ubicacion?: int, id_variacion?: int, bajo_minimo?: bool, pagina?: int, por_pagina?: int}  $filtros
     * @return array{datos: list<array<string, mixed>>, paginacion: array{pagina: int, por_pagina: int, total: int, total_paginas: int}}
     *
     * @throws ErrorInventarioException ERR-005, ERR-003
     */
    public function listarExistencias(int $idComercio, array $filtros = []): array;

    /**
     * fijarStockMinimo (PUT /existencias/{id_existencia}/stock-minimo). Publica
     * AlertaStockMinimo si el disponible queda en o bajo el mínimo.
     *
     * @return array<string, mixed> Existencia.
     *
     * @throws ErrorInventarioException ERR-M4-002, ERR-005, ERR-003
     */
    public function fijarStockMinimo(int $idExistencia, int $stockMinimo): array;

    /**
     * registrarMovimientoManual (POST /movimientos): entrada con costo o salida.
     *
     * @param  array{id_comercio: int, id_variacion: int, id_ubicacion: int, tipo_movimiento: string, cantidad: int, costo_unitario?: float, justificacion: string}  $solicitud
     * @return array<string, mixed> Movimiento.
     *
     * @throws ErrorInventarioException ERR-M4-001, ERR-M4-013, ERR-M4-014, ERR-M4-015, ERR-005, ERR-006, ERR-003
     */
    public function registrarMovimientoManual(array $solicitud): array;

    /**
     * registrarTransferencia (POST /transferencias): traslado autorizado entre dos
     * ubicaciones del mismo comercio (RN-M4-012). Dos movimientos con la misma referencia.
     *
     * @param  array<string, mixed>  $solicitud  TransferenciaSolicitud.
     * @return array<string, mixed> TransferenciaRespuesta.
     *
     * @throws ErrorInventarioException ERR-M4-001, ERR-M4-013, ERR-M4-014, ERR-M4-015, ERR-002, ERR-003, ERR-005, ERR-006
     */
    public function registrarTransferencia(array $solicitud): array;

    /**
     * registrarAjusteInventario (POST /ajustes): conteo físico y ajuste autorizado.
     *
     * @param  array<string, mixed>  $solicitud  AjusteSolicitud.
     * @return array<string, mixed> Ajuste.
     *
     * @throws ErrorInventarioException ERR-M4-002, ERR-M4-015, ERR-M4-016, ERR-M4-017, ERR-002, ERR-005, ERR-006
     */
    public function registrarAjusteInventario(array $solicitud): array;

    /**
     * consultarKardex (GET /kardex), del movimiento más reciente al más antiguo.
     *
     * @param  array{id_ubicacion?: int, fecha_inicio?: string, fecha_fin?: string, pagina?: int, por_pagina?: int}  $filtros
     * @return array{datos: list<array<string, mixed>>, paginacion: array<string, int>} KardexRespuesta.
     *
     * @throws ErrorInventarioException ERR-005, ERR-M4-012, ERR-003
     */
    public function consultarKardex(int $idComercio, int $idVariacion, array $filtros = []): array;

    /**
     * obtenerKardexValorizado (GET /kardex/valorizado).
     *
     * @param  array{tipo_movimiento?: string, pagina?: int, por_pagina?: int}  $filtros
     * @return array{datos: list<array<string, mixed>>, paginacion: array<string, int>} KardexRespuesta.
     *
     * @throws ErrorInventarioException ERR-M4-012, ERR-005, ERR-003
     */
    public function obtenerKardexValorizado(int $idComercio, string $fechaInicio, string $fechaFin, array $filtros = []): array;

    /**
     * consultarInventarioAuditoria (GET /auditoria/inventario). Solo lectura, rol de
     * auditoría de la plataforma; cada consulta queda en la bitácora del núcleo.
     *
     * @param  list<int>  $idsComercio
     * @return array{datos: list<array{id_comercio: int, existencias: list<array<string, mixed>>, kardex: list<array<string, mixed>>}>}
     *
     * @throws ErrorInventarioException ERR-M4-012, ERR-005, ERR-002
     */
    public function consultarInventarioAuditoria(array $idsComercio, ?string $fechaInicio = null, ?string $fechaFin = null): array;

    /**
     * ObtenerCostoProductosVendidos (GET /subpedidos/{id_subpedido}/costos-productos-vendidos), para M7.
     *
     * @return array{id_subpedido: int, id_comercio: int, variaciones: list<array{id_variacion: int, cantidad: int, costo_unitario: float, costo_total: float}>, costo_total: float}
     *
     * @throws ErrorInventarioException ERR-M4-018, ERR-M4-019, ERR-003
     */
    public function obtenerCostoProductosVendidos(int $idSubpedido): array;
}
