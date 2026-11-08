<?php

namespace App\Services\M4_Inventario\Dependencias;

use App\Contracts\M4_Inventario\Dependencias\PedidosDependencia;
use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Throwable;

/**
 * M5 real, llamado dentro del mismo proceso por su interfaz PHP. M5 se resuelve
 * al usarlo y no en el constructor: M5 también depende de M4 y resolverlos juntos
 * crearía un ciclo (el mismo problema que tuvieron M2 y M5).
 */
class PedidosReal implements PedidosDependencia
{
    public function obtenerSubpedido(int $idSubpedido): ?array
    {
        try {
            $subpedido = app(SubpedidosServiceInterface::class)->obtenerSubpedido($idSubpedido);
        } catch (Throwable $e) {
            if ($this->codigo($e) === 'ERR-004') {
                return null; // M5 no tiene ese subpedido.
            }

            throw ErrorInventarioException::dependenciaNoDisponible('M5');
        }

        if (! is_array($subpedido) || ! isset($subpedido['id_subpedido'], $subpedido['id_pedido'], $subpedido['id_comercio'])) {
            return null;
        }

        return [
            'id_subpedido' => (int) $subpedido['id_subpedido'],
            'id_pedido' => (int) $subpedido['id_pedido'],
            'id_comercio' => (int) $subpedido['id_comercio'],
            'estado_subpedido' => strtolower((string) ($subpedido['estado_subpedido'] ?? '')),
        ];
    }

    private function codigo(Throwable $e): ?string
    {
        if (method_exists($e, 'getCodigo')) {
            return $e->getCodigo();
        }

        return property_exists($e, 'codigo') ? (string) $e->codigo : null;
    }
}
