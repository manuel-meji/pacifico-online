<?php

namespace App\Services\M4_Inventario\Dependencias;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\Dependencias\CatalogoDependencia;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Throwable;

/**
 * M3 real, llamado dentro del mismo proceso por su interfaz PHP (sección 5.1 del
 * PC3). Toma id_comercio del producto embebido en la variación (VariacionDetalle).
 */
class CatalogoReal implements CatalogoDependencia
{
    public function __construct(private readonly CatalogoServiceInterface $catalogo) {}

    public function obtenerVariacion(int $idVariacion): ?array
    {
        try {
            $variacion = $this->catalogo->obtenerVariacion($idVariacion);
        } catch (Throwable) {
            throw ErrorInventarioException::dependenciaNoDisponible('M3');
        }

        if ($variacion === null) {
            return null;
        }

        $idComercio = $variacion['producto']['id_comercio'] ?? $variacion['id_comercio'] ?? null;
        $idProducto = $variacion['id_producto'] ?? $variacion['producto']['id_producto'] ?? null;

        if (! is_int($idComercio) || ! is_int($idProducto)) {
            throw ErrorInventarioException::dependenciaNoDisponible('M3', 'La variación de M3 no trae id_producto o id_comercio.');
        }

        return [
            'id_variacion' => $idVariacion,
            'id_producto' => $idProducto,
            'id_comercio' => $idComercio,
        ];
    }
}
