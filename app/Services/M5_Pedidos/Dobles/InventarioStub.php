<?php

namespace App\Services\M5_Pedidos\Dobles;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use Illuminate\Support\Str;

/**
 * Doble de prueba formal (Stub) para M4 Inventario Multiempresa.
 * Implementa App\Contracts\M4_Inventario\InventarioModuleInterface
 * (que a su vez extiende InventarioServiceInterface).
 */
class InventarioStub implements InventarioModuleInterface
{
    public const STOCK_POR_DEFECTO = 10;

    public const CANTIDAD_SIN_STOCK = 100;

    private ?bool $forzarSinStock = null;

    public function setSinStock(bool $sinStock = true): self
    {
        $this->forzarSinStock = $sinStock;

        return $this;
    }

    // =========================================================================
    // InventarioServiceInterface (Compatibilidad operativa)
    // =========================================================================

    /**
     * Consultar existencias disponibles para una variación.
     */
    public function consultarExistencias(int $variacionId): int
    {
        if ($this->forzarSinStock === true) {
            return 0;
        }

        return self::STOCK_POR_DEFECTO;
    }

    /**
     * Solicitar reserva atómica de existencias con vencimiento.
     * Invocado en el paso 4 del recorrido transversal (Regla RN-04).
     *
     * @return array<string, mixed>
     *
     * @throws ErrorProveedorException ERR-M4-001 si no hay existencias suficientes (409)
     */
    public function solicitarReserva(int $variacionId, int $cantidad, int $pedidoId): array
    {
        $sinStock = $this->forzarSinStock ?? ($cantidad >= self::CANTIDAD_SIN_STOCK);

        if ($sinStock) {
            throw ErrorProveedorException::sinStock('Existencia insuficiente para reserva');
        }

        return [
            'id_reserva' => '3f2b8a52-6c1e-4d0b-89a7-bbc5d8d53f99',
            'id_variacion' => $variacionId,
            'cantidad' => $cantidad,
            'id_pedido' => $pedidoId,
            'expiracion' => now()->addMinutes(15),
            'estado' => 'activa',
        ];
    }

    /**
     * Convertir reserva en salida de inventario al despachar el pedido.
     * Invocado en el paso 7 del recorrido transversal.
     */
    public function confirmarSalidaPorDespacho(int $reservaId): bool
    {
        return true;
    }

    // =========================================================================
    // InventarioModuleInterface (Contrato OpenAPI v1.2.0)
    // =========================================================================

    /**
     * @return array{id_comercio: int, id_variacion: int, id_ubicacion: int|null, cantidad_disponible: int, estado_disponibilidad: string, bajo_stock: bool}
     */
    public function consultarStockDisponible(int $idComercio, int $idVariacion, ?int $idUbicacion = null): array
    {
        $disponible = $this->forzarSinStock ? 0 : self::STOCK_POR_DEFECTO;

        return [
            'id_comercio' => $idComercio,
            'id_variacion' => $idVariacion,
            'id_ubicacion' => $idUbicacion,
            'cantidad_disponible' => $disponible,
            'estado_disponibilidad' => $disponible > 0 ? 'disponible' : 'agotado',
            'bajo_stock' => $disponible > 0 && $disponible <= 3,
        ];
    }

    /**
     * @param  list<array{id_comercio: int, id_variacion: int, id_ubicacion?: int}>  $items
     * @return list<array{id_comercio: int, id_variacion: int, id_ubicacion: int|null, cantidad_disponible: int, estado_disponibilidad: string, bajo_stock: bool}>
     */
    public function consultarDisponibilidadLote(array $items): array
    {
        return array_map(
            fn (array $item) => $this->consultarStockDisponible(
                (int) $item['id_comercio'],
                (int) $item['id_variacion'],
                isset($item['id_ubicacion']) ? (int) $item['id_ubicacion'] : null
            ),
            $items
        );
    }

    /**
     * @return array{id_comercio: int, id_producto: int, estado_disponibilidad: string}
     */
    public function consultarDisponibilidadProducto(int $idComercio, int $idProducto): array
    {
        $disponible = ! $this->forzarSinStock;

        return [
            'id_comercio' => $idComercio,
            'id_producto' => $idProducto,
            'estado_disponibilidad' => $disponible ? 'disponible' : 'agotado',
        ];
    }

    /**
     * @param  list<array{id_comercio: int, id_producto: int}>  $items
     * @return list<array{id_comercio: int, id_producto: int, estado_disponibilidad: string}>
     */
    public function consultarDisponibilidadProductoLote(array $items): array
    {
        return array_map(
            fn (array $item) => $this->consultarDisponibilidadProducto(
                (int) $item['id_comercio'],
                (int) $item['id_producto']
            ),
            $items
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function reservarExistencias(
        int $idComercio,
        int $idVariacion,
        int $cantidad,
        ?int $idUbicacion = null,
        ?string $claveIdempotencia = null,
        ?int $expiracionMinutos = null,
    ): array {
        $sinStock = $this->forzarSinStock ?? ($cantidad >= self::CANTIDAD_SIN_STOCK);

        if ($sinStock) {
            throw ErrorProveedorException::sinStock('Existencia insuficiente para reserva');
        }

        $minutos = $expiracionMinutos ?? 15;

        return [
            'id_reserva' => (string) Str::uuid(),
            'id_comercio' => $idComercio,
            'id_variacion' => $idVariacion,
            'id_ubicacion' => $idUbicacion ?? 1,
            'cantidad_reservada' => $cantidad,
            'clave_idempotencia' => $claveIdempotencia,
            'fecha_expiracion' => now()->addMinutes($minutos)->toIso8601String(),
            'estado' => 'activa',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function liberarReserva(string $idReserva, ?string $motivo = null): array
    {
        return [
            'id_reserva' => $idReserva,
            'estado' => 'liberada',
            'motivo' => $motivo ?? 'Liberación solicitada',
            'fecha_liberacion' => now()->toIso8601String(),
        ];
    }
}
