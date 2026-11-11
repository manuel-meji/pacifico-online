<?php

namespace Tests\Doubles\M1;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Services\M1_Portal\Dobles\CatalogoFake;
use App\Services\M1_Portal\Dobles\ErrorProveedorException;

/**
 * Doble de M3 con la forma del contrato M3 1.3.0 (rama feature/M3-consultas-reales),
 * sobre los mismos datos de prueba de CatalogoFake. Sirve para comprobar que el
 * adaptador CatalogoClient de M1 funciona con ese contrato antes de que M3 lo integre:
 * - No existe obtenerProducto: el detalle es obtenerDetalleProducto, que devuelve null
 *   si el producto no existe o no está visible (en lugar de ERR-004).
 * - ProductoResumenBusqueda trae imagen_principal_url y no trae moneda.
 * - Producto no trae moneda y sus imágenes son objetos (id_imagen, imagen_url, orden,
 *   id_variacion); las variaciones no traen imágenes propias.
 * - listarRelacionados devuelve null si el producto origen no está visible.
 */
class CatalogoContratoM3v130 implements CatalogoServiceInterface
{
    private CatalogoFake $datos;

    public function __construct()
    {
        $this->datos = new CatalogoFake;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function buscarProductos(array $filtros = []): array
    {
        $respuesta = $this->datos->buscarProductos($filtros);
        $respuesta['productos'] = array_map(fn (array $producto): array => $this->resumen($producto), $respuesta['productos']);

        return $respuesta;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarCategorias(): array
    {
        return $this->datos->listarCategorias();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarMarcas(?bool $destacada = null): array
    {
        return $this->datos->listarMarcas();
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function listarRelacionados(int $productoId, int $limite = 4): ?array
    {
        try {
            return array_map(fn (array $producto): array => $this->resumen($producto), $this->datos->listarRelacionados($productoId, $limite));
        } catch (ErrorProveedorException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function obtenerDetalleProducto(int $productoId): ?array
    {
        try {
            $producto = $this->datos->obtenerProducto($productoId);
        } catch (ErrorProveedorException) {
            return null;
        }

        unset($producto['moneda'], $producto['precio_desde'], $producto['precio_hasta']);

        $producto['imagenes'] = array_map(fn (string $url, int $i): array => [
            'id_imagen' => $productoId * 10 + $i,
            'id_producto' => $productoId,
            // La segunda imagen es de la primera variación, como permite el contrato 1.3.0.
            'id_variacion' => $i === 1 ? ($producto['variaciones'][0]['id_variacion'] ?? null) : null,
            'imagen_url' => $url,
            'orden' => $i + 1,
        ], $producto['imagenes'], array_keys($producto['imagenes']));

        $producto['variaciones'] = array_map(function (array $variacion): array {
            unset($variacion['imagenes']);

            return $variacion;
        }, $producto['variaciones']);

        return $producto;
    }

    public function obtenerVariacion(int $variacionId): ?array
    {
        return $this->datos->obtenerVariacion($variacionId);
    }

    /**
     * @param  array<string, mixed>  $producto  ProductoResumen 1.2.x de CatalogoFake.
     * @return array<string, mixed> ProductoResumenBusqueda 1.3.0.
     */
    private function resumen(array $producto): array
    {
        $producto['imagen_principal_url'] = $producto['imagen_principal'];
        unset($producto['imagen_principal'], $producto['moneda']);

        return $producto;
    }
}
