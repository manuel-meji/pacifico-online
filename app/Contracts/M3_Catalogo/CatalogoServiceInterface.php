<?php

namespace App\Contracts\M3_Catalogo;

interface CatalogoServiceInterface
{
    /**
     * Consultar detalle de producto y sus variaciones.
     * Invocado por M1 y M5 en el recorrido transversal.
     */
    public function obtenerDetalleProducto(int $productoId): ?array;

    /**
     * Consultar información de una variación específica (SKU, precio, atributos).
     */
    public function obtenerVariacion(int $variacionId): ?array;
}
