<?php

namespace Tests\Doubles\M4;

use App\Contracts\M4_Inventario\Dependencias\CatalogoDependencia;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;

/**
 * Doble de prueba de M3 para M4 (contrato openapi_M3 v1.2.1: obtenerVariacion). Implementa
 * CatalogoDependencia y se conecta en config/integraciones.php (clave m4.m3.doble).
 *
 * Toma las variaciones de DatosPruebaM4 (VariacionDetalle con el producto embebido) y entrega
 * a M4 id_variacion, id_producto e id_comercio. Variación inexistente: null (M4 responde
 * ERR-M4-013); identificador inválido: ERR-005. La variante CatalogoNoDisponible simula que M3
 * no responde (ERR-006).
 */
class CatalogoDobleM4 implements CatalogoDependencia
{
    public function obtenerVariacion(int $idVariacion): ?array
    {
        if ($idVariacion < 1) {
            throw ErrorInventarioException::datosInvalidos(['id_variacion' => 'Debe ser un entero mayor o igual a 1.']);
        }

        $variacion = DatosPruebaM4::variaciones()[$idVariacion] ?? null;

        if ($variacion === null) {
            return null;
        }

        return [
            'id_variacion' => $variacion['id_variacion'],
            'id_producto' => $variacion['id_producto'],
            'id_comercio' => $variacion['producto']['id_comercio'],
        ];
    }
}
