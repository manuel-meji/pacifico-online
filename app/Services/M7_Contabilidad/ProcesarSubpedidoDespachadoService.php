<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Services\M7_Contabilidad\DTOs\CostoVentaDTO;
use App\Services\Nucleo\ContextoComercio;
use LogicException;

final class ProcesarSubpedidoDespachadoService
{
    public function __construct(
        private readonly AsientoService $asientos,
        private readonly EventoProcesadoRepository $eventos,
        private readonly ContextoComercio $contexto,
    ) {}

    /**
     * PC3 colectivo: el despacho origina el costo de venta. Coordinar con M4,
     * M5 y el integrador de API el evento y el origen definitivo del costo.
     */
    public function procesar(CostoVentaDTO $datos): void
    {
        if (! $this->contexto->esModoSistema()) {
            throw new LogicException('El procesamiento de eventos requiere contexto de sistema.');
        }

        $this->eventos->procesar($datos->eventoId, 'SubpedidoDespachado', function (bool $yaProcesado) use ($datos): void {
            $this->asientos->crearAsientoCostoVenta($datos, $yaProcesado);
        });
    }

    public function yaProcesado(string $eventoId): bool
    {
        return $this->eventos->yaProcesado($eventoId);
    }
}
