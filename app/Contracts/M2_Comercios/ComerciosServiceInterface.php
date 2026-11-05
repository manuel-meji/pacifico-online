<?php

namespace App\Contracts\M2_Comercios;

/**
 * Interfaz PHP de M2 — Micrositios y administración de comercios.
 *
 * TRANSICIÓN (DEF de M2, 4/11/2026). Esta interfaz todavía es el borrador
 * preliminar del Comité. El contrato vigente es el OpenAPI v1.2.1
 * (contracts/m2-comercios/v1/openapi.yaml), con 8 operaciones:
 *
 *   listarComerciosHabilitados, obtenerResumenComerciosLote,
 *   obtenerDatosComercioPublico, obtenerComercioActivo, obtenerSedesComercio,
 *   obtenerPoliticasEnvio, obtenerDatosFiscalesComercio, obtenerComisionSubpedido.
 *
 * App\Services\M2_Comercios\ComerciosService ya las implementa. En el segundo PR
 * de M2 esta interfaz se reemplaza por esas 8 firmas y se eliminan los dos métodos
 * de abajo. Para que ese cambio no rompa los dobles de otros módulos, cada doble
 * debe extender App\Services\M2_Comercios\Dobles\ComerciosDobleBase en lugar de
 * implementar esta interfaz directamente.
 */
interface ComerciosServiceInterface
{
    /**
     * @deprecated Usar obtenerDatosComercioPublico u obtenerComercioActivo (contrato v1.2.1).
     */
    public function obtenerMicrositio(int $comercioId): array;

    /**
     * @deprecated El contrato v1.2.1 registra la comisión al consumir el evento
     *             SubpedidoDespachado de M5; M7 la consulta con obtenerComisionSubpedido.
     */
    public function registrarComision(int $pedidoId, float $subtotalPedido): bool;
}
