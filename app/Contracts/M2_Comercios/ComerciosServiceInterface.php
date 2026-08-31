<?php

namespace App\Contracts\M2_Comercios;

interface ComerciosServiceInterface
{
    /**
     * Consultar información pública del micrositio de un comercio.
     */
    public function obtenerMicrositio(int $comercioId): array;

    /**
     * Registrar la comisión de la plataforma sobre el subtotal de un pedido.
     * Invocado en el paso 9 del recorrido transversal.
     */
    public function registrarComision(int $pedidoId, float $subtotalPedido): bool;
}
