<?php

namespace App\Services\M5_Pedidos\Dobles;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;

/**
 * Doble de prueba formal (Stub) para M3 Catálogo y Productos.
 * Implementa App\Contracts\M3_Catalogo\CatalogoServiceInterface.
 */
class CatalogoStub implements CatalogoServiceInterface
{
    public const VARIACION_DEFAULT = 1;

    public const VARIACION_INEXISTENTE = 999;

    private ?bool $forzarInexistente = null;

    private int $idComercio = 2;

    public function setIdComercio(int $idComercio): self
    {
        $this->idComercio = $idComercio;

        return $this;
    }

    public function setVariacionInexistente(bool $inexistente = true): self
    {
        $this->forzarInexistente = $inexistente;

        return $this;
    }

    /**
     * Consultar detalle de producto y sus variaciones.
     * Invocado por M1 y M5 en el recorrido transversal.
     *
     * @return array<string, mixed>|null
     */
    public function obtenerDetalleProducto(int $productoId): ?array
    {
        if ($productoId === self::VARIACION_INEXISTENTE || $this->forzarInexistente) {
            return null;
        }

        return [
            'id_producto' => $productoId,
            'nombre' => 'Producto de prueba',
            'descripcion' => 'Descripción del producto de prueba',
            'id_comercio' => $this->idComercio,
            'activo' => true,
            'variaciones' => [
                [
                    'id_variacion' => 1,
                    'nombre' => 'Variación estándar',
                    'precio' => 15000.00,
                    'tarifa_impuesto' => 13.00,
                    'sku' => 'SKU-001',
                ],
            ],
        ];
    }

    /**
     * Consultar información de una variación específica (SKU, precio, atributos).
     *
     * @return array<string, mixed>|null
     *
     * @throws ErrorProveedorException ERR-M3-004 si la variación no existe (422)
     */
    public function obtenerVariacion(int $variacionId): ?array
    {
        $inexistente = $this->forzarInexistente ?? ($variacionId === self::VARIACION_INEXISTENTE);

        if ($inexistente) {
            throw ErrorProveedorException::noEncontrado('Variación inexistente o no disponible');
        }

        return [
            'id_variacion' => $variacionId,
            'id_producto' => 1,
            'nombre' => 'Producto de prueba',
            'sku' => 'SKU-VAR-'.$variacionId,
            'precio' => 15000.00,
            'tarifa_impuesto' => 13.00,
            'id_comercio' => $this->idComercio,
        ];
    }
}
