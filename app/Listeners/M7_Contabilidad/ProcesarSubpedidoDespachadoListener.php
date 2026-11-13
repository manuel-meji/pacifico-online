<?php

declare(strict_types=1);

namespace App\Listeners\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Services\M7_Contabilidad\ComisionPendienteRepository;
use App\Services\M7_Contabilidad\DTOs\CostoVentaDTO;
use App\Services\M7_Contabilidad\ProcesarComisionPendienteService;
use App\Services\M7_Contabilidad\ProcesarSubpedidoDespachadoService;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

final class ProcesarSubpedidoDespachadoListener
{
    public function __construct(
        private readonly CostosProductosVendidosGatewayInterface $costos,
        private readonly ProcesarSubpedidoDespachadoService $procesador,
        private readonly ContextoComercio $contexto,
        private readonly ComisionPendienteRepository $comisionesPendientes,
        private readonly ProcesarComisionPendienteService $procesadorComision,
    ) {}

    public function handle(SubpedidoDespachado $evento): void
    {
        try {
            $this->procesar($evento);
        } catch (Throwable $error) {
            Log::error('M7: no se pudo procesar el evento SubpedidoDespachado.', [
                'id_evento' => $evento->id_evento ?? null,
                'exception' => $error,
            ]);
        }
    }

    private function procesar(SubpedidoDespachado $evento): void
    {
        $resultado = $this->costos
            ->obtenerCostosProductosVendidos($evento->id_subpedido);

        if ((int) ($resultado['id_subpedido'] ?? 0) !== $evento->id_subpedido) {
            throw new LogicException(
                'El subpedido devuelto por M4 no coincide con el evento de M5.'
            );
        }

        $idComercio = (int) ($resultado['id_comercio'] ?? 0);
        $costoTotal = (float) ($resultado['costo_total'] ?? 0);

        if ($idComercio !== $evento->id_comercio) {
            throw new LogicException(
                'El comercio devuelto por M4 no coincide con el evento de M5.'
            );
        }

        if ($costoTotal <= 0) {
            throw new LogicException(
                'M4 no devolvió un costo de productos vendidos válido.'
            );
        }

        $dto = new CostoVentaDTO(
            eventoId: $evento->id_evento,
            fecha: $evento->fecha_evento,
            pedidoId: $evento->id_pedido,
            subpedidoId: $evento->id_subpedido,
            comercioId: $evento->id_comercio,
            costoTotal: number_format(
                $costoTotal,
                2,
                '.',
                ''
            ),
        );

        $this->contexto->ejecutarComoSistema(
            fn () => $this->procesador->procesar($dto)
        );

        $this->comisionesPendientes->registrar(
            eventoId: $evento->id_evento,
            pedidoId: $evento->id_pedido,
            subpedidoId: $evento->id_subpedido,
            comercioId: $evento->id_comercio,
            fechaEvento: $evento->fecha_evento,
        );

        $this->procesadorComision->intentar(
            $evento->id_subpedido
        );

    }
}
