<?php

namespace Tests\Doubles\M2;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\M2_Comercios\Dobles\ComerciosDobleBase;

/**
 * Doble de M2 para las pruebas de M6. Solo simula obtenerDatosFiscalesComercio,
 * que es la operación de M2 que consume M6 (contrato M2 v1.2.1, DatosFiscalesComercio).
 * Las demás operaciones responden ERR-006 (las hereda de ComerciosDobleBase).
 */
class MockComerciosService extends ComerciosDobleBase
{
    public const COMERCIO_INEXISTENTE = 999;

    public function obtenerDatosFiscalesComercio(int $idComercio): array
    {
        if ($idComercio === self::COMERCIO_INEXISTENTE) {
            throw ErrorNucleoException::paraModulo('M2_Comercios', 'ERR-004', 404, 'El comercio no existe.');
        }

        return [
            'id_comercio' => $idComercio,
            'cedula_juridica' => '3-101-888999',
            'razon_social' => 'Tienda Demostración S.A.',
            'direccion_fiscal' => 'Liberia, Guanacaste',
            'inscrito_como_contribuyente' => true,
            'comision' => [
                'tipo' => 'porcentaje',
                'valor' => 5.0,
                'base_calculo' => 'subtotal del subpedido',
                'moneda' => null,
            ],
        ];
    }

    // Métodos del borrador viejo de la interfaz. Se borran cuando M2 suba el PR 2
    public function obtenerMicrositio(int $comercioId): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    public function registrarComision(int $pedidoId, float $subtotalPedido): bool
    {
        throw $this->noSimulada(__FUNCTION__);
    }
}
