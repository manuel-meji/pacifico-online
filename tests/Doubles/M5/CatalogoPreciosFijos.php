<?php

namespace Tests\Doubles\M5;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;

/**
 * Doble de prueba de M3 con precios, tarifas y comercio definidos por la propia prueba.
 *
 * CatalogoStub (app/Services/M5_Pedidos/Dobles) devuelve precios fijos de 10.000, 8.500 y 15.000,
 * por lo que no permite armar escenarios con montos exactos como los de CP-001 a CP-003.
 */
class CatalogoPreciosFijos implements CatalogoServiceInterface
{
    /**
     * @param  array<int, array{precio: float, id_comercio: int, tarifa_impuesto?: float}>  $variaciones  Indexado por id_variacion.
     */
    public function __construct(private array $variaciones) {}

    public function obtenerDetalleProducto(int $productoId): ?array
    {
        return null;
    }

    public function obtenerVariacion(int $variacionId): ?array
    {
        $variacion = $this->variaciones[$variacionId] ?? null;

        if ($variacion === null) {
            return null;
        }

        return [
            'id_variacion' => $variacionId,
            'nombre' => 'Producto de prueba '.$variacionId,
            'sku' => 'SKU-PRUEBA-'.$variacionId,
            'imagen' => '',
            'precio' => (float) $variacion['precio'],
            'tarifa_impuesto' => (float) ($variacion['tarifa_impuesto'] ?? 13.00),
            'id_comercio' => (int) $variacion['id_comercio'],
        ];
    }
}
