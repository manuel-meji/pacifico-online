<?php

declare(strict_types=1);

namespace Tests\Doubles\M2;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\M2_Comercios\Dobles\ComerciosDobleBase;

final class ComerciosFiscalFake extends ComerciosDobleBase
{
    public function __construct(private readonly bool $disponible = true) {}

    public function obtenerDatosFiscalesComercio(int $idComercio): array
    {
        if (! $this->disponible) {
            throw ErrorNucleoException::proveedorNoResponde('M2', 'Doble de M2 configurado como no disponible.');
        }

        return match ($idComercio) {
            11 => $this->datos(11, 'Comercio Pacífico Sur S.A.', '3101123456', true),
            12 => $this->datos(12, 'Ferretería El Pacífico S.A.', '3101654321', false),
            13 => $this->datos(13, '', '', true),
            default => throw ErrorNucleoException::recursoNoEncontrado('El comercio no existe.'),
        };
    }

    /**
     * Métodos del borrador del Comité que la interfaz todavía exige (ComerciosDobleBase no los
     * cubre). M6 no los usa; se eliminan cuando M2 publique las 8 operaciones en la interfaz.
     *
     * @deprecated
     */
    public function obtenerMicrositio(int $comercioId): array
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    /** @deprecated */
    public function registrarComision(int $pedidoId, float $subtotalPedido): bool
    {
        throw $this->noSimulada(__FUNCTION__);
    }

    /** @return array<string, mixed> Esquema DatosFiscalesComercio del contrato. */
    private function datos(int $id, string $razonSocial, string $cedula, bool $inscrito): array
    {
        return [
            'id_comercio' => $id,
            'cedula_juridica' => $cedula,
            'razon_social' => $razonSocial,
            'direccion_fiscal' => 'Puntarenas, Costa Rica',
            'inscrito_como_contribuyente' => $inscrito,
            'comision' => ['tipo' => 'porcentaje', 'valor' => 5.0, 'base_calculo' => 'subtotal del subpedido', 'moneda' => null],
        ];
    }
}
