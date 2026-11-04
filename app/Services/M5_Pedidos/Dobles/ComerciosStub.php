<?php

namespace App\Services\M5_Pedidos\Dobles;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;

/**
 * Doble de prueba formal (Stub) para M2 Micrositios y Comercios.
 * Implementa App\Contracts\M2_Comercios\ComerciosServiceInterface.
 */
class ComerciosStub implements ComerciosServiceInterface
{
    public const COMERCIO_ACTIVO = 2;

    public const COMERCIO_SUSPENDIDO = 3;

    private ?bool $forzarSuspendido = null;

    private float $costoEnvio = 0.00;

    public function setComercioSuspendido(bool $suspendido = true): self
    {
        $this->forzarSuspendido = $suspendido;

        return $this;
    }

    public function setCostoEnvio(float $costoEnvio): self
    {
        $this->costoEnvio = $costoEnvio;

        return $this;
    }

    /**
     * Consultar información pública del micrositio de un comercio.
     *
     * @return array<string, mixed>
     *
     * @throws ErrorProveedorException ERR-M2-002 si el comercio está suspendido (409)
     */
    public function obtenerMicrositio(int $comercioId): array
    {
        $estaSuspendido = $this->forzarSuspendido ?? ($comercioId === self::COMERCIO_SUSPENDIDO);

        if ($estaSuspendido) {
            throw ErrorProveedorException::comercioSuspendido('Comercio suspendido');
        }

        return match ($comercioId) {
            1 => [
                'id_comercio' => 1,
                'nombre' => 'Café Don Manuel',
                'nombre_comercial' => 'Café Don Manuel S.A.',
                'estado' => 'activo',
                'activo' => true,
                'costo_envio' => $this->costoEnvio > 0 ? $this->costoEnvio : 1500.00,
                'logo_url' => 'https://pacifico.test/storage/comercios/1/logo.png',
            ],
            4 => [
                'id_comercio' => 4,
                'nombre' => 'Mercado Artesanal Pacífico',
                'nombre_comercial' => 'Mercado Artesanal Pacífico S.A.',
                'estado' => 'activo',
                'activo' => true,
                'costo_envio' => $this->costoEnvio > 0 ? $this->costoEnvio : 1200.00,
                'logo_url' => 'https://pacifico.test/storage/comercios/4/logo.png',
            ],
            default => [
                'id_comercio' => $comercioId,
                'nombre' => 'Comercio de prueba',
                'nombre_comercial' => 'Comercio de prueba S.A.',
                'estado' => 'activo',
                'activo' => true,
                'costo_envio' => $this->costoEnvio,
                'logo_url' => "https://pacifico.test/storage/comercios/{$comercioId}/logo.png",
            ],
        };
    }

    /**
     * Registrar la comisión de la plataforma sobre el subtotal de un pedido.
     * Invocado en el paso 9 del recorrido transversal.
     */
    public function registrarComision(int $pedidoId, float $subtotalPedido): bool
    {
        if ($this->forzarSuspendido === true) {
            return false;
        }

        return true;
    }
}
