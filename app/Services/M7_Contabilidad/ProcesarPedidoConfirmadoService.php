<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Services\M7_Contabilidad\DTOs\VentaDTO;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class ProcesarPedidoConfirmadoService
{
    public function __construct(
        private readonly AsientoService $asientos,
        private readonly EventoProcesadoRepository $eventos,
        private readonly ContextoComercio $contexto,
    ) {}

    /**
     * PC3 colectivo: la venta se registra al confirmar el pedido, por subtotal.
     * El nombre y datos del evento se coordinarán con M5 y el integrador de API.
     *
     * @param  list<VentaDTO>  $ventas
     */
    public function procesar(string $eventoId, array $ventas): void
    {
        if (! $this->contexto->esModoSistema()) {
            throw new LogicException('El procesamiento de eventos requiere contexto de sistema.');
        }
        if ($ventas === []) {
            throw new InvalidArgumentException('El pedido confirmado no contiene subpedidos.');
        }
        $subpedidos = [];
        $pedidoId = null;
        foreach ($ventas as $venta) {
            if (! $venta instanceof VentaDTO || $venta->eventoId !== $eventoId || isset($subpedidos[$venta->subpedidoId]) || ($pedidoId !== null && $venta->pedidoId !== $pedidoId)) {
                throw new InvalidArgumentException('Los datos de venta no corresponden al mismo evento y pedido o repiten un subpedido.');
            }
            $subpedidos[$venta->subpedidoId] = true;
            $pedidoId = $venta->pedidoId;
        }
        usort($ventas, fn (VentaDTO $a, VentaDTO $b): int => [$a->comercioId, $a->subpedidoId] <=> [$b->comercioId, $b->subpedidoId]);

        $this->eventos->procesar($eventoId, 'PedidoConfirmado', function (bool $yaProcesado) use ($ventas, $eventoId): void {
            if ($yaProcesado && DB::table('asientos_contables')->where('evento_id', $eventoId)->count() > count($ventas)) {
                throw new LogicException('El evento procesado contiene más asientos que los datos recibidos.');
            }
            foreach ($ventas as $venta) {
                $this->asientos->crearAsientoVenta($venta, $yaProcesado);
            }
        });
    }

    public function yaProcesado(string $eventoId): bool
    {
        return $this->eventos->yaProcesado($eventoId);
    }
}
