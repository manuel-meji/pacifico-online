<?php

declare(strict_types=1);

namespace Tests\Doubles\M5;

use App\Contracts\M5_Pedidos\GestionPedidosServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;
use LogicException;

final class PedidosContratoFake implements GestionPedidosServiceInterface, PedidosServiceInterface
{
    /** @var list<array{pedido: int, comprobante: string}> Llamadas recibidas a marcarComoPagado. */
    public array $pagados = [];

    public function __construct(private readonly bool $disponible = true, private readonly bool $aceptaPago = true) {}

    public function obtenerPedido(int $idPedido): array
    {
        $this->exigirDisponible();

        return match ($idPedido) {
            8942 => $this->pedido(8942, 'confirmado', 59840.00, [
                $this->subpedido(1001, 8942, 11, 30000.00, 3900.00, 1500.00),
                $this->subpedido(1002, 8942, 12, 21000.00, 2730.00, 710.00),
            ]),
            8943 => $this->pedido(8943, 'cancelado', 1000.00, []),
            default => throw ErrorNucleoException::recursoNoEncontrado('El pedido no existe.'),
        };
    }

    public function marcarComoPagado(int $pedidoId, string $comprobantePago): bool
    {
        $this->exigirDisponible();
        $this->pagados[] = ['pedido' => $pedidoId, 'comprobante' => $comprobantePago];

        return $this->aceptaPago;
    }

    // ---- Operaciones que M6 no consume -------------------------------------

    public function listarPedidos(?int $idCliente = null, array $filtros = []): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function crearPedidoCheckout(array $datosCheckout): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function cancelarPedidoTotal(int $idPedido, array $datosCancelacion = []): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function crearPedido(array $datosCompra): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function despacharSubpedido(int $subpedidoId, ?string $guiaTransporte = null): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function obtenerCarrito(int $idCliente): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function agregarItemCarrito(int $idCliente, array $datos): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    // ---- Auxiliares --------------------------------------------------------

    private function exigirDisponible(): void
    {
        if (! $this->disponible) {
            throw ErrorNucleoException::proveedorNoResponde('M5', 'Doble de M5 configurado como no disponible.');
        }
    }

    private function noSimulada(string $operacion): LogicException
    {
        return new LogicException("PedidosContratoFake no simula {$operacion}: M6 no debería llamarla.");
    }

    /**
     * @param  list<array<string, mixed>>  $subpedidos
     * @return array<string, mixed> Esquema PedidoMaestro del contrato.
     */
    private function pedido(int $id, string $estado, float $total, array $subpedidos): array
    {
        return [
            'id_pedido' => $id,
            'id_cliente' => 77,
            'fecha_creacion' => '2026-11-04T10:00:00-06:00',
            'monto_total' => $total,
            'estado_pedido' => $estado,
            'subpedidos' => $subpedidos,
            'historial_estados' => [],
        ];
    }

    /** @return array<string, mixed> Esquema PedidoSubpedido del contrato. */
    private function subpedido(int $id, int $idPedido, int $idComercio, float $subtotal, float $impuesto, float $envio): array
    {
        return [
            'id_subpedido' => $id,
            'id_pedido' => $idPedido,
            'id_comercio' => $idComercio,
            'subtotal' => $subtotal,
            'impuesto' => $impuesto,
            'costo_envio' => $envio,
            'estado_subpedido' => 'confirmado',
        ];
    }
}
