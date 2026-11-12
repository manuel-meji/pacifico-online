<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad\Gateways;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M7_Contabilidad\ComerciosM7GatewayInterface;
use LogicException;

final class M2ComerciosGateway implements ComerciosM7GatewayInterface
{
    public function __construct(
        private readonly ComerciosServiceInterface $comercios,
    ) {}

    public function obtenerDatosFiscalesComercio(
        int $comercioId
    ): array {
        $datos = $this->comercios
            ->obtenerDatosFiscalesComercio($comercioId);

        $idComercio = (int) ($datos['id_comercio'] ?? 0);

        if ($idComercio !== $comercioId) {
            throw new LogicException(
                'El comercio devuelto por M2 no coincide con el comercio solicitado.'
            );
        }

        return $datos;
    }

    public function obtenerComisionSubpedido(
        int $subpedidoId
    ): float {
        $resultado = $this->comercios
            ->obtenerComisionSubpedido($subpedidoId);

        $idSubpedido = (int) ($resultado['id_subpedido'] ?? 0);

        if ($idSubpedido !== $subpedidoId) {
            throw new LogicException(
                'El subpedido devuelto por M2 no coincide con el subpedido solicitado.'
            );
        }

        if (! array_key_exists('monto_comision', $resultado)) {
            throw new LogicException(
                'M2 no devolvió el monto de comisión esperado.'
            );
        }

        $monto = (float) $resultado['monto_comision'];

        if ($monto < 0) {
            throw new LogicException(
                'M2 devolvió un monto de comisión inválido.'
            );
        }

        return $monto;
    }
}
