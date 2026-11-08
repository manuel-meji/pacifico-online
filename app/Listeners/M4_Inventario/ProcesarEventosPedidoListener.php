<?php

namespace App\Listeners\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Enums\M4_Inventario\MotivoCierre;
use App\Events\M5_Pedidos\PedidoCancelado;
use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M5_Pedidos\SubpedidoCancelado;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Events\M6_Pagos\PagoRechazado;
use App\Services\M4_Inventario\ReservaService;

/**
 * Eventos de M5 y M6 que cambian el estado de las reservas (figura 4.6c del PC3,
 * patrón Observer). Idempotentes por id_evento y, además, por identidad de negocio:
 * las operaciones de ReservaService no repiten efectos sobre una reserva cerrada.
 *
 * Se ejecutan en el mismo proceso que publica el evento (sin cola), para que el
 * recorrido transversal no dependa de un worker; nunca lanzan excepciones al publicador.
 */
class ProcesarEventosPedidoListener
{
    use ProcesaEventosUnaVez;

    public function __construct(
        private readonly ReservaService $reservas,
        private readonly NucleoDependencia $nucleo,
    ) {}

    /** PedidoConfirmado (M5): ACTIVA → CONFIRMADA con el pedido y el subpedido de cada reserva. */
    public function alConfirmarPedido(PedidoConfirmado $evento): void
    {
        $this->procesarUnaVez($this->nucleo, $evento->id_evento, 'PedidoConfirmado', function () use ($evento): void {
            foreach ($evento->subpedidos as $subpedido) {
                foreach ($subpedido['reservas'] ?? [] as $idReserva) {
                    $this->porReserva('PedidoConfirmado', $idReserva, fn () => $this->reservas->confirmar(
                        $idReserva, $evento->id_pedido, (int) $subpedido['id_subpedido'],
                    ));
                }
            }
        });
    }

    /** PagoRechazado (M6): libera las reservas del pedido que aún no salieron. */
    public function alRechazarPago(PagoRechazado $evento): void
    {
        $this->procesarUnaVez($this->nucleo, $evento->idEvento, 'PagoRechazado', function () use ($evento): void {
            foreach ($this->reservas->reservasDelPedido($evento->idPedido) as $idReserva) {
                $this->porReserva('PagoRechazado', $idReserva, fn () => $this->reservas->liberar($idReserva, MotivoCierre::PagoRechazado));
            }
        });
    }

    /**
     * PedidoCancelado (M5): libera las reservas no despachadas. Si una ya salió
     * (ERR-M4-008), no se suma inventario: queda pendiente de reingreso con recepción física.
     */
    public function alCancelarPedido(PedidoCancelado $evento): void
    {
        $this->procesarUnaVez($this->nucleo, $evento->id_evento, 'PedidoCancelado', function () use ($evento): void {
            foreach ($evento->reservas as $idReserva) {
                $this->porReserva('PedidoCancelado', $idReserva, fn () => $this->reservas->liberar($idReserva, MotivoCierre::PedidoCancelado));
            }
        });
    }

    /** SubpedidoDespachado (M5): salida física de las reservas del subpedido (paso 7 del recorrido). */
    public function alDespacharSubpedido(SubpedidoDespachado $evento): void
    {
        $this->procesarUnaVez($this->nucleo, $evento->id_evento, 'SubpedidoDespachado', function () use ($evento): void {
            foreach ($evento->reservas as $idReserva) {
                $this->porReserva('SubpedidoDespachado', $idReserva, fn () => $this->reservas->registrarSalida(
                    $idReserva, $evento->id_pedido, $evento->id_subpedido,
                ));
            }
        });
    }

    /** SubpedidoCancelado (M5, SC-02): libera solo las reservas de ese subpedido. */
    public function alCancelarSubpedido(SubpedidoCancelado $evento): void
    {
        $this->procesarUnaVez($this->nucleo, $evento->id_evento, 'SubpedidoCancelado', function () use ($evento): void {
            foreach ($evento->reservas as $idReserva) {
                $this->porReserva('SubpedidoCancelado', $idReserva, fn () => $this->reservas->liberar($idReserva, MotivoCierre::PedidoCancelado));
            }
        });
    }
}
