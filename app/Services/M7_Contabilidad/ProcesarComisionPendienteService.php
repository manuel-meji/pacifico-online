<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ComerciosM7GatewayInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;

final class ProcesarComisionPendienteService
{
    public function __construct(
        private readonly ComerciosM7GatewayInterface $comercios,
        private readonly ComisionPendienteRepository $pendientes,
    ) {}

    public function intentar(int $subpedidoId): bool
    {
        try {
            $monto = $this->comercios
                ->obtenerComisionSubpedido($subpedidoId);
        } catch (ErrorNucleoException $error) {
            if ($error->codigo === 'ERR-004') {
                $this->pendientes->registrarIntentoPendiente(
                    $subpedidoId,
                    'Comisión todavía no calculada por M2.'
                );

                return false;
            }

            throw $error;
        }

        $this->pendientes->marcarDisponible(
            $subpedidoId,
            $monto
        );

        return true;
    }
}
