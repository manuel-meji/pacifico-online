<?php

namespace App\Services\M1_Portal\Dobles;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use Illuminate\Support\Str;
use LogicException;

/**
 * Fake de M3 Catálogo de productos para las operaciones que consume M1:
 * listarCategorias, listarMarcas, buscarProductos, obtenerProducto y
 * listarRelacionados.
 *
 * Provisional: M3 no ha publicado su contrato en el repositorio. Las firmas y
 * estructuras se deducen de lo que el contrato de M1 (1.1.0) espera de M3
 * 1.2.0 (precio_desde, precio_hasta, filtros de búsqueda, lista plana de
 * categorías con id_categoria_padre). Se ajustan cuando M3 lo publique.
 *
 * Solo devuelve productos de comercios Activo, como declara M1 sobre M3.
 */
class CatalogoFake implements CatalogoServiceInterface
{
    private const MAXIMO_POR_PAGINA = 50;

    private const MAXIMO_RELACIONADOS = 20;

    private const ORDENES = ['precio_asc', 'precio_desc', 'novedad'];

    private const FILTROS = [
        'termino', 'id_categoria', 'id_comercio', 'id_marca', 'precio_minimo',
        'precio_maximo', 'orden', 'ids', 'pagina', 'por_pagina',
    ];

    /**
     * Lista plana de categorías; M1 arma el árbol con id_categoria_padre.
     *
     * @return list<array{id_categoria: int, nombre: string, id_categoria_padre: int|null, icono_url: string|null}>
     */
    public function listarCategorias(): array
    {
        return DatosPrueba::categorias();
    }

    /**
     * @return list<array{id_marca: int, nombre: string}>
     */
    public function listarMarcas(): array
    {
        return DatosPrueba::marcas();
    }

    /**
     * Búsqueda de productos publicados de comercios activos.
     *
     * Filtros admitidos (todos opcionales): termino, id_categoria (incluye sus
     * subcategorías), id_comercio, id_marca, precio_minimo y precio_maximo
     * (alguna variación dentro del rango), orden (precio_asc, precio_desc,
     * novedad; si se omite, por identificador), ids, pagina y por_pagina.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{pagina: int, por_pagina: int, total_resultados: int, productos: list<array<string, mixed>>}
     */
    public function buscarProductos(array $filtros = []): array
    {
        $this->validarFiltros($filtros);

        $pagina = $filtros['pagina'] ?? 1;
        $porPagina = $filtros['por_pagina'] ?? 20;

        $productos = array_filter(
            $this->productosVisibles(),
            fn (array $producto): bool => $this->cumpleFiltros($producto, $filtros),
        );

        $productos = $this->ordenar(array_values($productos), $filtros['orden'] ?? null);

        return [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_resultados' => count($productos),
            'productos' => array_map(
                fn (array $producto): array => $this->resumen($producto),
                array_slice($productos, ($pagina - 1) * $porPagina, $porPagina),
            ),
        ];
    }

    /**
     * Detalle del producto con sus variaciones. Lanza ERR-004 si no existe, no
     * está publicado o su comercio no está activo.
     *
     * @return array<string, mixed>
     */
    public function obtenerProducto(int $idProducto): array
    {
        $producto = $this->productoVisible($idProducto);

        unset($producto['relacionados'], $producto['publicado_en']);

        return [
            ...$producto,
            'precio_desde' => $this->precioDesde($producto),
            'precio_hasta' => $this->precioHasta($producto),
        ];
    }

    /**
     * Productos relacionados visibles, con la misma estructura que los
     * resultados de buscarProductos.
     *
     * @return list<array<string, mixed>>
     */
    public function listarRelacionados(int $idProducto, int $limite = 4): array
    {
        if ($limite < 1 || $limite > self::MAXIMO_RELACIONADOS) {
            throw ErrorProveedorException::datosInvalidos(['limite' => 'Debe estar entre 1 y '.self::MAXIMO_RELACIONADOS.'.']);
        }

        $producto = $this->productoVisible($idProducto);
        $visibles = $this->productosVisibles();
        $relacionados = [];

        foreach ($producto['relacionados'] as $idRelacionado) {
            if (isset($visibles[$idRelacionado])) {
                $relacionados[] = $this->resumen($visibles[$idRelacionado]);
            }
        }

        return array_slice($relacionados, 0, $limite);
    }

    public function obtenerDetalleProducto(int $productoId): ?array
    {
        throw new LogicException('Fuera del alcance del doble de M1.');
    }

    public function obtenerVariacion(int $variacionId): ?array
    {
        throw new LogicException('Fuera del alcance del doble de M1.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productosVisibles(): array
    {
        return array_filter(
            DatosPrueba::productos(),
            fn (array $producto): bool => DatosPrueba::comercioActivo($producto['id_comercio']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function productoVisible(int $idProducto): array
    {
        if ($idProducto < 1) {
            throw ErrorProveedorException::datosInvalidos(['id_producto' => 'Debe ser un entero mayor o igual a 1.']);
        }

        return $this->productosVisibles()[$idProducto]
            ?? throw ErrorProveedorException::noEncontrado('El producto no existe o no está publicado.');
    }

    /**
     * @param  array<string, mixed>  $producto
     * @param  array<string, mixed>  $filtros
     */
    private function cumpleFiltros(array $producto, array $filtros): bool
    {
        if (isset($filtros['termino']) && ! $this->coincideTermino($producto, $filtros['termino'])) {
            return false;
        }

        if (isset($filtros['id_categoria']) && ! in_array($producto['id_categoria'], $this->categoriaConSubcategorias($filtros['id_categoria']), true)) {
            return false;
        }

        if (isset($filtros['id_comercio']) && $producto['id_comercio'] !== $filtros['id_comercio']) {
            return false;
        }

        if (isset($filtros['id_marca']) && $producto['id_marca'] !== $filtros['id_marca']) {
            return false;
        }

        if (isset($filtros['ids']) && ! in_array($producto['id_producto'], $filtros['ids'], true)) {
            return false;
        }

        if (isset($filtros['precio_minimo']) || isset($filtros['precio_maximo'])) {
            $minimo = $filtros['precio_minimo'] ?? 0;
            $maximo = $filtros['precio_maximo'] ?? PHP_FLOAT_MAX;

            foreach ($producto['variaciones'] as $variacion) {
                if ($variacion['precio'] >= $minimo && $variacion['precio'] <= $maximo) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Coincidencia sin distinguir mayúsculas ni tildes, en nombre o descripción.
     *
     * @param  array<string, mixed>  $producto
     */
    private function coincideTermino(array $producto, string $termino): bool
    {
        $normalizar = fn (string $texto): string => Str::lower(Str::ascii($texto));

        return str_contains($normalizar($producto['nombre'].' '.$producto['descripcion']), $normalizar(trim($termino)));
    }

    /**
     * @return list<int>
     */
    private function categoriaConSubcategorias(int $idCategoria): array
    {
        $ids = [$idCategoria];

        foreach (DatosPrueba::categorias() as $categoria) {
            if ($categoria['id_categoria_padre'] === $idCategoria) {
                $ids[] = $categoria['id_categoria'];
            }
        }

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $productos
     * @return list<array<string, mixed>>
     */
    private function ordenar(array $productos, ?string $orden): array
    {
        usort($productos, fn (array $a, array $b): int => match ($orden) {
            'precio_asc' => $this->precioDesde($a) <=> $this->precioDesde($b),
            'precio_desc' => $this->precioDesde($b) <=> $this->precioDesde($a),
            'novedad' => strcmp($b['publicado_en'], $a['publicado_en']),
            default => $a['id_producto'] <=> $b['id_producto'],
        });

        return $productos;
    }

    /**
     * @param  array<string, mixed>  $producto
     * @return array{id_producto: int, id_comercio: int, id_categoria: int, id_marca: int|null, nombre: string, imagen_principal: string|null, precio_desde: float, precio_hasta: float, moneda: string}
     */
    private function resumen(array $producto): array
    {
        return [
            'id_producto' => $producto['id_producto'],
            'id_comercio' => $producto['id_comercio'],
            'id_categoria' => $producto['id_categoria'],
            'id_marca' => $producto['id_marca'],
            'nombre' => $producto['nombre'],
            'imagen_principal' => $producto['imagenes'][0] ?? null,
            'precio_desde' => $this->precioDesde($producto),
            'precio_hasta' => $this->precioHasta($producto),
            'moneda' => $producto['moneda'],
        ];
    }

    /**
     * @param  array<string, mixed>  $producto
     */
    private function precioDesde(array $producto): float
    {
        return min(array_column($producto['variaciones'], 'precio'));
    }

    /**
     * @param  array<string, mixed>  $producto
     */
    private function precioHasta(array $producto): float
    {
        return max(array_column($producto['variaciones'], 'precio'));
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function validarFiltros(array $filtros): void
    {
        $errores = [];

        foreach (array_diff(array_keys($filtros), self::FILTROS) as $desconocido) {
            $errores[$desconocido] = 'Filtro no admitido.';
        }

        if (isset($filtros['termino']) && (! is_string($filtros['termino']) || mb_strlen($filtros['termino']) > 100)) {
            $errores['termino'] = 'Debe ser texto de hasta 100 caracteres.';
        }

        foreach (['id_categoria', 'id_comercio', 'id_marca', 'pagina'] as $campo) {
            if (isset($filtros[$campo]) && (! is_int($filtros[$campo]) || $filtros[$campo] < 1)) {
                $errores[$campo] = 'Debe ser un entero mayor o igual a 1.';
            }
        }

        if (isset($filtros['por_pagina']) && (! is_int($filtros['por_pagina']) || $filtros['por_pagina'] < 1 || $filtros['por_pagina'] > self::MAXIMO_POR_PAGINA)) {
            $errores['por_pagina'] = 'Debe estar entre 1 y '.self::MAXIMO_POR_PAGINA.'.';
        }

        foreach (['precio_minimo', 'precio_maximo'] as $campo) {
            if (isset($filtros[$campo]) && (! is_numeric($filtros[$campo]) || $filtros[$campo] < 0)) {
                $errores[$campo] = 'Debe ser un número mayor o igual a 0.';
            }
        }

        if (isset($filtros['precio_minimo'], $filtros['precio_maximo']) && ! isset($errores['precio_minimo']) && ! isset($errores['precio_maximo'])
            && $filtros['precio_minimo'] > $filtros['precio_maximo']) {
            $errores['precio_minimo'] = 'No puede ser mayor que precio_maximo.';
        }

        if (isset($filtros['orden']) && ! in_array($filtros['orden'], self::ORDENES, true)) {
            $errores['orden'] = 'Debe ser uno de: '.implode(', ', self::ORDENES).'.';
        }

        if (isset($filtros['ids']) && (! is_array($filtros['ids']) || $filtros['ids'] === [] || array_filter($filtros['ids'], fn (mixed $id): bool => ! is_int($id)) !== [])) {
            $errores['ids'] = 'Debe ser una lista no vacía de enteros.';
        }

        if ($errores !== []) {
            throw ErrorProveedorException::datosInvalidos($errores);
        }
    }
}
