<?php

namespace Tests\Doubles\M4;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use LogicException;

/**
 * Doble que imita a M3 tal como lo define su contrato (openapi_M3 v1.2.1), para probar el
 * adaptador real de M4 (CatalogoReal) sin esperar a M3. Implementa CatalogoServiceInterface:
 * obtenerVariacion devuelve VariacionDetalle con el producto embebido, de donde M4 toma
 * id_producto e id_comercio; null si la variación no existe; ERR-005 si el id es inválido.
 */
class CatalogoContratoM3 implements CatalogoServiceInterface
{
    /** @return array<string, mixed>|null */
    public function obtenerVariacion(int $variacionId): ?array
    {
        if ($variacionId < 1) {
            throw ErrorProveedorException::datosInvalidos(['id_variacion' => 'Debe ser un entero mayor o igual a 1.']);
        }

        return DatosPruebaM4::variaciones()[$variacionId] ?? null;
    }

    public function obtenerDetalleProducto(int $productoId): ?array
    {
        throw new LogicException('Fuera del alcance del doble de M4.');
    }
}
