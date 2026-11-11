<?php

namespace App\Contracts\M4_Inventario\Dependencias;

use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Lo que M4 necesita de M5 (x-dependencias-consumidas del contrato de M4): antes de
 * registrar la salida por despacho verifica que el subpedido pertenezca al pedido y
 * al comercio de la reserva y que esté pagado (DS-04, CU-M4-07).
 * Se resuelve con el doble o con M5 real según config/integraciones.php, clave m4.m5.
 */
interface PedidosDependencia
{
    /** Estados del subpedido en M5 que significan «pagado» (o posterior). */
    public const ESTADOS_PAGADOS = ['pagado', 'en_preparacion', 'despachado', 'entregado'];

    /**
     * obtenerSubpedido de M5. null si M5 no lo tiene.
     *
     * @return array{id_subpedido: int, id_pedido: int, id_comercio: int, estado_subpedido: string}|null
     *
     * @throws ErrorInventarioException ERR-006 si M5 no responde.
     */
    public function obtenerSubpedido(int $idSubpedido): ?array;
}
