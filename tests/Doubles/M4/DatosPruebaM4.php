<?php

namespace Tests\Doubles\M4;

/**
 * Datos fijos que comparten los dobles de prueba de M4. Los identificadores coinciden con los
 * ejemplos del contrato M4 v1.2.0 (comercio 104, variación 501, ubicaciones 12 y 13).
 *
 *  Comercio 104  Activo      sedes 12 (bodega) y 13 (tienda) habilitadas; 14 deshabilitada
 *  Comercio 105  Suspendido  sede 21
 *  Comercio 106  Pendiente   sin sedes
 *  Comercio 107  Rechazado   sin sedes
 *  Variaciones   501 y 502 (producto 301, comercio 104); 601 (producto 401, comercio 105)
 *  Pedidos       9001 pagado (subpedido 9101, comercio 104); 9002 pendiente (subpedido 9102);
 *                9003 pagado de otro comercio (subpedido 9103, comercio 105)
 */
final class DatosPruebaM4
{
    public const RESERVA_PAGADA = '3f2b8a52-6c1e-4d0b-9a3e-1f6f0d9b7a10';

    public const RESERVA_PENDIENTE = '7c1d9e40-2b7a-4f3e-8d61-5a0c2e9b4f21';

    /** @return array<int, array{id_comercio: int, estado: string, motivo_estado: string|null}> */
    public static function comercios(): array
    {
        return [
            104 => ['id_comercio' => 104, 'estado' => 'Activo', 'motivo_estado' => null],
            105 => ['id_comercio' => 105, 'estado' => 'Suspendido', 'motivo_estado' => 'Membresía vencida'],
            106 => ['id_comercio' => 106, 'estado' => 'Pendiente', 'motivo_estado' => null],
            107 => ['id_comercio' => 107, 'estado' => 'Rechazado', 'motivo_estado' => 'Documentación incompleta'],
        ];
    }

    /** @return array<int, list<array{id_sede: int, nombre: string, direccion: string, habilitada: bool}>> */
    public static function sedes(): array
    {
        return [
            104 => [
                ['id_sede' => 12, 'nombre' => 'Bodega central', 'direccion' => 'Liberia, Guanacaste', 'habilitada' => true],
                ['id_sede' => 13, 'nombre' => 'Tienda Liberia centro', 'direccion' => 'Liberia, Guanacaste', 'habilitada' => true],
                ['id_sede' => 14, 'nombre' => 'Bodega Nicoya', 'direccion' => 'Nicoya, Guanacaste', 'habilitada' => false],
            ],
            105 => [
                ['id_sede' => 21, 'nombre' => 'Tienda Santa Cruz', 'direccion' => 'Santa Cruz, Guanacaste', 'habilitada' => true],
            ],
            106 => [],
            107 => [],
        ];
    }

    /** @return array<int, array<string, mixed>> Estructura VariacionDetalle de M3 v1.2.1. */
    public static function variaciones(): array
    {
        $producto301 = ['id_producto' => 301, 'id_comercio' => 104, 'id_categoria' => 7, 'nombre' => 'Camiseta algodón Pacífico', 'estado_producto' => 'publicado'];
        $producto401 = ['id_producto' => 401, 'id_comercio' => 105, 'id_categoria' => 9, 'nombre' => 'Hamaca artesanal', 'estado_producto' => 'publicado'];

        return [
            501 => ['id_variacion' => 501, 'id_producto' => 301, 'sku' => 'CAM-PAC-AZ-M', 'precio' => 12500.0, 'tarifa_impuesto' => 13.0, 'disponible' => true,
                'atributos' => [['nombre' => 'Color', 'valor' => 'Azul'], ['nombre' => 'Talla', 'valor' => 'M']], 'producto' => $producto301],
            502 => ['id_variacion' => 502, 'id_producto' => 301, 'sku' => 'CAM-PAC-AZ-L', 'precio' => 12500.0, 'tarifa_impuesto' => 13.0, 'disponible' => true,
                'atributos' => [['nombre' => 'Color', 'valor' => 'Azul'], ['nombre' => 'Talla', 'valor' => 'L']], 'producto' => $producto301],
            601 => ['id_variacion' => 601, 'id_producto' => 401, 'sku' => 'HAM-ART-01', 'precio' => 38000.0, 'tarifa_impuesto' => 13.0, 'disponible' => true,
                'atributos' => [['nombre' => 'Color', 'valor' => 'Natural']], 'producto' => $producto401],
        ];
    }

    /** @return array<int, array<string, mixed>> Estructura PedidoSubpedido de M5 v1.1.0. */
    public static function subpedidos(): array
    {
        return [
            9101 => ['id_subpedido' => 9101, 'id_pedido' => 9001, 'id_comercio' => 104, 'subtotal' => 25000.0, 'costo_envio' => 2500.0, 'impuesto' => 3250.0,
                'estado_subpedido' => 'pagado', 'lineas' => [
                    ['id_linea' => 1, 'id_subpedido' => 9101, 'id_variacion' => 501, 'id_reserva' => self::RESERVA_PAGADA, 'cantidad' => 2, 'precio_unitario' => 12500.0, 'tarifa_impuesto' => 13.0],
                ]],
            9102 => ['id_subpedido' => 9102, 'id_pedido' => 9002, 'id_comercio' => 104, 'subtotal' => 12500.0, 'costo_envio' => 2500.0, 'impuesto' => 1625.0,
                'estado_subpedido' => 'pendiente', 'lineas' => [
                    ['id_linea' => 2, 'id_subpedido' => 9102, 'id_variacion' => 502, 'id_reserva' => self::RESERVA_PENDIENTE, 'cantidad' => 1, 'precio_unitario' => 12500.0, 'tarifa_impuesto' => 13.0],
                ]],
            9103 => ['id_subpedido' => 9103, 'id_pedido' => 9003, 'id_comercio' => 105, 'subtotal' => 38000.0, 'costo_envio' => 0.0, 'impuesto' => 4940.0,
                'estado_subpedido' => 'pagado', 'lineas' => [
                    ['id_linea' => 3, 'id_subpedido' => 9103, 'id_variacion' => 601, 'id_reserva' => null, 'cantidad' => 1, 'precio_unitario' => 38000.0, 'tarifa_impuesto' => 13.0],
                ]],
        ];
    }

    /** @return array<int, array{id_pedido: int, id_cliente: int, estado_pedido: string, monto_total: float}> */
    public static function pedidos(): array
    {
        return [
            9001 => ['id_pedido' => 9001, 'id_cliente' => 55, 'estado_pedido' => 'pagado', 'monto_total' => 30750.0],
            9002 => ['id_pedido' => 9002, 'id_cliente' => 56, 'estado_pedido' => 'pendiente', 'monto_total' => 16625.0],
            9003 => ['id_pedido' => 9003, 'id_cliente' => 57, 'estado_pedido' => 'pagado', 'monto_total' => 42940.0],
        ];
    }

    /**
     * Autorizaciones registradas por el Administrador del comercio (supuesto del núcleo).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function autorizaciones(): array
    {
        return [
            'autorizacion-0001' => ['operacion' => 'transferencia', 'id_comercio' => 104, 'id_variacion' => 501, 'ubicaciones' => [12, 13], 'cantidad' => 5,
                'id_autorizador' => 2, 'fecha_autorizacion' => '2026-10-19T14:00:00Z', 'vence' => '2099-12-31T23:59:59Z'],
            'autorizacion-0002' => ['operacion' => 'ajuste', 'id_comercio' => 104, 'id_variacion' => 501, 'ubicaciones' => [12], 'cantidad' => 2,
                'id_autorizador' => 2, 'fecha_autorizacion' => '2026-10-19T14:05:00Z', 'vence' => '2099-12-31T23:59:59Z'],
            'autorizacion-vencida' => ['operacion' => 'transferencia', 'id_comercio' => 104, 'id_variacion' => 501, 'ubicaciones' => [12, 13], 'cantidad' => 5,
                'id_autorizador' => 2, 'fecha_autorizacion' => '2026-01-10T09:00:00Z', 'vence' => '2026-01-10T10:00:00Z'],
            'autorizacion-otro-comercio' => ['operacion' => 'transferencia', 'id_comercio' => 105, 'id_variacion' => 601, 'ubicaciones' => [21, 22], 'cantidad' => 1,
                'id_autorizador' => 9, 'fecha_autorizacion' => '2026-10-19T14:00:00Z', 'vence' => '2099-12-31T23:59:59Z'],
        ];
    }
}
