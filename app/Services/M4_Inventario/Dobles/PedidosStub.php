<?php

namespace App\Services\M4_Inventario\Dobles;

use App\Contracts\M4_Inventario\Dependencias\PedidosDependencia;

/**
 * Doble por defecto de M5 para M4. Solo conoce los subpedidos que se le registran
 * (registrar()); cualquier otro no existe. En el ambiente de integración se usa M5
 * real (M4_M5_MODO=real). Se sustituye en config/integraciones.php, clave m4.m5.doble.
 */
class PedidosStub implements PedidosDependencia
{
    /** @var array<int, array{id_subpedido: int, id_pedido: int, id_comercio: int, estado_subpedido: string}> */
    private array $subpedidos = [];

    public function registrar(int $idSubpedido, int $idPedido, int $idComercio, string $estado = 'pagado'): self
    {
        $this->subpedidos[$idSubpedido] = [
            'id_subpedido' => $idSubpedido,
            'id_pedido' => $idPedido,
            'id_comercio' => $idComercio,
            'estado_subpedido' => $estado,
        ];

        return $this;
    }

    public function obtenerSubpedido(int $idSubpedido): ?array
    {
        return $this->subpedidos[$idSubpedido] ?? null;
    }
}
