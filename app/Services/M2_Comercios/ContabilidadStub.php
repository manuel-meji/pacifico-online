<?php

namespace App\Services\M2_Comercios;

use DateTimeImmutable;
use RuntimeException;

/**
 * Stub de M7 para obtenerResumenFinancieroComercio.
 *
 * Imita el contrato M7 ERP Contable v1.0.0 consumido por M2.
 */

class ContabilidadStub
{
    public function __construct(
        private bool $autenticado = true,
        private bool $autorizado = true,
    ) {}

    /**
     * Simula M7.obtenerResumenFinancieroComercio.
     *
     * @return array<string, mixed>
     */
    
    public function obtenerResumenFinancieroComercio(
        int $idComercio,
        string $desde,
        string $hasta
    ): array {
        if (! $this->autenticado) {
            throw new RuntimeException(
                'ERR-001: No autenticado.'
            );
        }

        if (! $this->autorizado) {
            throw new RuntimeException(
                'ERR-002: Sin permisos para consultar este comercio.'
            );
        }

        if (
            ! $this->fechaValida($desde)
            || ! $this->fechaValida($hasta)
            || $desde > $hasta
        ) {
            throw new RuntimeException(
                'ERR-M7-003: Parámetros o datos contables inválidos.'
            );
        }

        return [
            'id_comercio' => $idComercio,
            'fecha_inicio' => $desde,
            'fecha_fin' => $hasta,
            'ingresos' => 50000.00,
            'costos' => 28000.00,
            'gastos' => 7000.00,
            'utilidad' => 15000.00,
        ];
    }

    private function fechaValida(string $fecha): bool
    {
        $valor = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);

        return $valor !== false && $valor->format('Y-m-d') === $fecha;
    }
}