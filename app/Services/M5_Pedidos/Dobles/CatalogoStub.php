<?php

namespace App\Services\M5_Pedidos\Dobles;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Services\M1_Portal\Dobles\DatosPrueba;

/**
 * Doble de prueba formal (Stub) para M3 Catálogo y Productos.
 * Implementa App\Contracts\M3_Catalogo\CatalogoServiceInterface.
 */
class CatalogoStub implements CatalogoServiceInterface
{
    public const VARIACION_DEFAULT = 1;

    public const VARIACION_INEXISTENTE = 999;

    private ?bool $forzarInexistente = null;

    private ?int $idComercio = null;

    private string $imagen = '';

    public function setIdComercio(int $idComercio): self
    {
        $this->idComercio = $idComercio;

        return $this;
    }

    public function setImagen(string $imagen): self
    {
        $this->imagen = $imagen;

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
            'id_comercio' => $this->idComercio ?? 2,
            'activo' => true,
            'variaciones' => [
                [
                    'id_variacion' => 1,
                    'nombre' => 'Variación estándar',
                    'precio' => 15000.00,
                    'tarifa_impuesto' => 13.00,
                    'sku' => 'SKU-001',
                    'imagen' => $this->imagen,
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

        // Si la variación existe en el conjunto de DatosPrueba del portal (M1/M3), usar sus datos reales
        $datosPortal = DatosPrueba::buscarVariacion($variacionId);
        if ($this->idComercio === null && $datosPortal !== null) {
            ['producto' => $producto, 'variacion' => $variacion] = $datosPortal;
            $nombreVar = ! empty($variacion['atributos'])
                ? ($producto['nombre'].' - '.implode(', ', array_column($variacion['atributos'], 'valor')))
                : $producto['nombre'];

            return [
                'id_variacion' => $variacionId,
                'id_producto' => (int) $producto['id_producto'],
                'nombre' => $nombreVar,
                'sku' => $variacion['sku'] ?? ('SKU-VAR-'.$variacionId),
                'imagen' => $this->imagen ?: ($producto['imagenes'][0] ?? ''),
                'precio' => (float) ($variacion['precio'] ?? 10000.00),
                'tarifa_impuesto' => 13.00,
                'id_comercio' => (int) ($producto['id_comercio'] ?? 35),
                'producto' => [
                    'id_producto' => (int) $producto['id_producto'],
                    'id_comercio' => (int) ($producto['id_comercio'] ?? 35),
                    'nombre' => $producto['nombre'],
                ],
            ];
        }

        if ($this->idComercio === null && $variacionId === 2) {
            return [
                'id_variacion' => 2,
                'id_producto' => 2,
                'nombre' => 'Café Tarrazú Reserva Especial',
                'sku' => 'SKU-CAFE-002',
                'imagen' => $this->imagen ?: 'https://pacifico.test/storage/productos/cafe-tarrazu.png',
                'precio' => 10000.00,
                'tarifa_impuesto' => 13.00,
                'id_comercio' => 1,
            ];
        }

        if ($this->idComercio === null && $variacionId === 3) {
            return [
                'id_variacion' => 3,
                'id_producto' => 3,
                'nombre' => 'Miel Orgánica del Pacífico',
                'sku' => 'SKU-MIEL-003',
                'imagen' => $this->imagen ?: 'https://pacifico.test/storage/productos/miel-pacifico.png',
                'precio' => 8500.00,
                'tarifa_impuesto' => 13.00,
                'id_comercio' => 4,
            ];
        }

        return [
            'id_variacion' => $variacionId,
            'id_producto' => 1,
            'nombre' => 'Producto de prueba',
            'sku' => 'SKU-VAR-'.$variacionId,
            'imagen' => $this->imagen,
            'precio' => 15000.00,
            'tarifa_impuesto' => 13.00,
            'id_comercio' => $this->idComercio ?? 2,
        ];
    }
}
