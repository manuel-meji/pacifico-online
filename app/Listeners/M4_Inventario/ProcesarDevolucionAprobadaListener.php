<?php

namespace App\Listeners\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Events\M5_Pedidos\DevolucionAprobada;
use App\Services\M4_Inventario\ReingresoDevolucionService;
use Illuminate\Support\Facades\Log;

/**
 * DevolucionAprobada (M5) → reingreso al kardex de las unidades devueltas (RF-021).
 *
 * Mismo patrón que ProcesarEventosPedidoListener: idempotente por id_evento, se ejecuta en el
 * mismo proceso que publica el evento y nunca lanza excepciones al publicador.
 */
class ProcesarDevolucionAprobadaListener
{
    use ProcesaEventosUnaVez;

    public function __construct(
        private readonly ReingresoDevolucionService $reingresos,
        private readonly NucleoDependencia $nucleo,
    ) {}

    public function alAprobarDevolucion(DevolucionAprobada $evento): void
    {
        $this->procesarUnaVez($this->nucleo, $evento->id_evento, 'DevolucionAprobada', function () use ($evento): void {
            foreach ($evento->lineas as $linea) {
                $idReserva = $linea['id_reserva'] ?? null;

                // Líneas sin reserva en M4 (p. ej. pedidos creados con dobles) no tienen kardex que reingresar.
                if ($idReserva === null || $idReserva === '') {
                    Log::notice("M4 DevolucionAprobada #{$evento->id_devolucion}: la línea {$linea['id_linea']} no tiene reserva; no se reingresa.");

                    continue;
                }

                $this->porReserva('DevolucionAprobada', (string) $idReserva, fn () => $this->reingresos->registrar(
                    (string) $idReserva,
                    $evento->id_pedido,
                    $evento->id_subpedido,
                    $evento->id_devolucion,
                    (int) $linea['cantidad'],
                    $evento->motivo,
                ));
            }
        });
    }
}
