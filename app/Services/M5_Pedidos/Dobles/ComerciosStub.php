<?php

namespace App\Services\M5_Pedidos\Dobles;

use App\Services\M1_Portal\Dobles\DatosPrueba;
use App\Services\M2_Comercios\Dobles\ComerciosDobleBase;

/**
 * Doble de prueba formal (Stub) para M2 Micrositios y Comercios.
 * Extiende App\Services\M2_Comercios\Dobles\ComerciosDobleBase (Contrato OpenAPI v1.2.1).
 */
class ComerciosStub extends ComerciosDobleBase
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
     * Consulta políticas y tarifas de envío de un comercio (Contrato OpenAPI v1.2.1).
     *
     * @return array{id_comercio: int, politicas: list<array<string, mixed>>, tarifas: list<array<string, mixed>>}
     */
    public function obtenerPoliticasEnvio(int $idComercio): array
    {
        $costoGam = $this->costoEnvio > 0 ? $this->costoEnvio : 1500.00;
        $costoResto = $this->costoEnvio > 0 ? ($this->costoEnvio + 1000.0) : 2500.00;

        return [
            'id_comercio' => $idComercio,
            'politicas' => [],
            'tarifas' => [
                [
                    'zona' => 'GAM',
                    'costo' => $costoGam,
                    'moneda' => 'CRC',
                ],
                [
                    'zona' => 'Resto del país',
                    'costo' => $costoResto,
                    'moneda' => 'CRC',
                ],
            ],
        ];
    }

    /**
     * Consulta el estado de activación de un comercio (Contrato OpenAPI v1.2.1).
     *
     * @return array{id_comercio: int, estado: string, activo: bool, motivo_estado: ?string}
     *
     * @throws ErrorProveedorException ERR-M2-002 si el comercio está suspendido (409)
     */
    public function obtenerComercioActivo(int $idComercio): array
    {
        $estaSuspendido = $this->forzarSuspendido ?? ($idComercio === self::COMERCIO_SUSPENDIDO);

        if ($estaSuspendido) {
            throw ErrorProveedorException::comercioSuspendido('Comercio suspendido');
        }

        return [
            'id_comercio' => $idComercio,
            'estado' => 'Activo',
            'activo' => true,
            'motivo_estado' => null,
        ];
    }

    /**
     * Obtiene el resumen de comercios por lote (Contrato OpenAPI v1.2.1).
     * M2 omite los comercios suspendidos o no habilitados.
     *
     * @param  list<int>  $idsComercio
     * @return array{comercios: list<array<string, mixed>>}
     */
    public function obtenerResumenComerciosLote(array $idsComercio): array
    {
        $comercios = [];
        $portalComercios = DatosPrueba::comercios();

        foreach ($idsComercio as $id) {
            $estaSuspendido = $this->forzarSuspendido ?? ($id === self::COMERCIO_SUSPENDIDO);
            if (! $estaSuspendido) {
                if (isset($portalComercios[$id])) {
                    $c = $portalComercios[$id];
                    $comercios[] = [
                        'id_comercio' => $id,
                        'nombre_comercial' => $c['nombre_comercial'] ?? $c['nombre'] ?? 'Comercio '.$id,
                        'logo_url' => $c['logo_url'] ?? "https://pacifico.test/storage/comercios/{$id}/logo.png",
                        'estado' => 'Activo',
                        'destacado' => false,
                    ];

                    continue;
                }

                $nombre = match ($id) {
                    1 => 'Café Don Manuel S.A.',
                    4 => 'Mercado Artesanal Pacífico S.A.',
                    default => 'Comercio de prueba S.A.',
                };
                $comercios[] = [
                    'id_comercio' => $id,
                    'nombre_comercial' => $nombre,
                    'logo_url' => "https://pacifico.test/storage/comercios/{$id}/logo.png",
                    'estado' => 'Activo',
                    'destacado' => false,
                ];
            }
        }

        return ['comercios' => $comercios];
    }

    /**
     * Consultar información pública del micrositio de un comercio.
     *
     * @deprecated Usar obtenerComercioActivo u obtenerResumenComerciosLote (contrato v1.2.1).
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

        $portalComercios = DatosPrueba::comercios();
        if (isset($portalComercios[$comercioId])) {
            $c = $portalComercios[$comercioId];

            return [
                'id_comercio' => $comercioId,
                'nombre' => $c['nombre_comercial'] ?? $c['nombre'] ?? 'Comercio '.$comercioId,
                'nombre_comercial' => $c['nombre_comercial'] ?? $c['nombre'] ?? 'Comercio '.$comercioId,
                'estado' => 'activo',
                'activo' => true,
                'costo_envio' => $this->costoEnvio > 0 ? $this->costoEnvio : 1500.00,
                'logo_url' => $c['logo_url'] ?? "https://pacifico.test/storage/comercios/{$comercioId}/logo.png",
            ];
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
     *
     * @deprecated Retirado en contrato v1.2.1; M2 consume SubpedidoDespachado.
     */
    public function registrarComision(int $pedidoId, float $subtotalPedido): bool
    {
        if ($this->forzarSuspendido === true) {
            return false;
        }

        return true;
    }
}
