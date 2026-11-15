<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Services\M7_Contabilidad\DTOs\CostoVentaDTO;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use LogicException;

final class ProcesarSubpedidoDespachadoService
{
    public function __construct(
        private readonly AsientoService $asientos,
        private readonly EventoProcesadoRepository $eventos,
        private readonly ContextoComercio $contexto,
    ) {
    }

    /**
     * Procesa el costo de ventas originado por el despacho de un subpedido.
     * Política de costo cero: M4 admite costo 0.00; M7 registra auditoría e idempotencia
     * sin generar asiento de partida doble en cero. Costos negativos son rechazados.
     */
    public function procesar(CostoVentaDTO $datos): void
    {
        if (!$this->contexto->esModoSistema()) {
            throw new LogicException('El procesamiento de eventos requiere contexto de sistema.');
        }

        $costoNumerico = (float) $datos->costoTotal;

        if ($costoNumerico < 0) {
            throw new InvalidArgumentException('El costo de productos vendidos requiere un importe positivo o cero.');
        }

        $this->eventos->procesar($datos->eventoId, 'SubpedidoDespachado', function (bool $yaProcesado) use ($datos, $costoNumerico): void {
            if ($costoNumerico == 0.0) {
                if (!$yaProcesado) {
                    Log::info('M7: despacho procesado con costo cero (sin asiento de costo de ventas).', [
                        'evento_id' => $datos->eventoId,
                        'id_comercio' => $datos->comercioId,
                        'id_pedido' => $datos->pedidoId,
                        'id_subpedido' => $datos->subpedidoId,
                        'fecha' => $datos->fecha,
                    ]);
                }

                return;
            }

            $this->asientos->crearAsientoCostoVenta($datos, $yaProcesado);
        });
    }

    public function yaProcesado(string $eventoId): bool
    {
        return $this->eventos->yaProcesado($eventoId);
    }
}
