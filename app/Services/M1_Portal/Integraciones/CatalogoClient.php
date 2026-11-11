<?php

namespace App\Services\M1_Portal\Integraciones;

use App\Contracts\M1_Portal\Exceptions\CriterioInvalidoException;
use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Services\M1_Portal\DTO\CategoriaDTO;
use App\Services\M1_Portal\DTO\ComercioDTO;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\M1_Portal\DTO\Disponibilidad;
use App\Services\M1_Portal\DTO\EstadoComercio;
use App\Services\M1_Portal\DTO\FichaProductoDTO;
use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use App\Services\M1_Portal\DTO\ResultadoBusquedaDTO;
use App\Services\M1_Portal\DTO\VariacionDTO;
use Throwable;

/**
 * Adaptador del contrato de M3 Catálogo (buscarProductos, listarCategorias,
 * listarMarcas, obtenerProducto y listarRelacionados): traduce las respuestas
 * a DTO de M1, guarda las marcas y las categorías en la caché y corta la
 * espera a los 3 s (RNF-M1-04).
 *
 * M3 solo entrega datos del catálogo. Lo que viene de otros módulos queda con
 * un valor provisional que el servicio de M1 completa: nombreComercio vacío
 * (M2), disponibilidad Agotado y comprable falso (M4), y en la ficha un
 * ComercioDTO con solo el id_comercio (M2).
 *
 * Mientras M3 no publique estas operaciones en CatalogoServiceInterface, se
 * llaman con las firmas que el contrato de M1 espera de M3 1.2.0 (las mismas
 * de CatalogoFake); si el servicio real no las tiene, se informa ERR-006.
 */
class CatalogoClient
{
    use LlamaProveedor;

    private const MAXIMO_POR_PAGINA_M3 = 50;

    private const RELACIONADOS = 4;

    public function __construct(
        private CatalogoServiceInterface $contrato,
        private CachePortal $cache,
    ) {
        $this->cargarTiempoMaximo();
    }

    /**
     * @throws CriterioInvalidoException ERR-005 si M3 rechaza el criterio.
     */
    public function buscarProductos(CriterioBusquedaDTO $criterio): ResultadoBusquedaDTO
    {
        $filtros = array_filter([
            'termino' => $criterio->termino,
            'id_categoria' => $criterio->idCategoria,
            'id_comercio' => $criterio->idComercio,
            'id_marca' => $criterio->idMarca,
            'precio_minimo' => $criterio->precioMinimo,
            'precio_maximo' => $criterio->precioMaximo,
            'orden' => $criterio->orden,
            'pagina' => $criterio->pagina,
            'por_pagina' => $criterio->porPagina,
        ], fn (mixed $valor): bool => $valor !== null);

        $respuesta = $this->llamar(
            'M3',
            fn (): array => $this->contrato->buscarProductos($filtros),
            fn (?string $codigo, Throwable $error): ?CriterioInvalidoException => $codigo === 'ERR-005'
                ? new CriterioInvalidoException($error->getMessage(), $error)
                : null,
        );

        return new ResultadoBusquedaDTO(
            productos: array_map(fn (array $producto): ProductoResumenDTO => $this->resumen($producto), $respuesta['productos']),
            totalResultados: $respuesta['total_resultados'],
            pagina: $respuesta['pagina'],
            porPagina: $respuesta['por_pagina'],
        );
    }

    /**
     * Productos por identificador (destacados), en el mismo orden de $ids.
     * Los que no existan o no estén visibles se omiten.
     *
     * @param  list<int>  $ids
     * @return list<ProductoResumenDTO>
     */
    public function buscarPorIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        $encontrados = [];

        foreach (array_chunk($ids, self::MAXIMO_POR_PAGINA_M3) as $lote) {
            $respuesta = $this->llamar('M3', fn (): array => $this->contrato->buscarProductos([
                'ids' => $lote,
                'por_pagina' => count($lote),
            ]));

            foreach ($respuesta['productos'] as $producto) {
                $encontrados[$producto['id_producto']] = $this->resumen($producto);
            }
        }

        return array_values(array_filter(array_map(fn (int $id): ?ProductoResumenDTO => $encontrados[$id] ?? null, $ids)));
    }

    /**
     * Precio normal, producto y comercio de varias variaciones, con
     * CatalogoServiceInterface::obtenerVariacion de M3 (una llamada por
     * variación). Las que M3 no encuentra se omiten.
     *
     * @param  list<int>  $idsVariacion
     * @return array<int, array{id_variacion: int, id_producto: int, id_comercio: int, precio: float}> Por id_variacion.
     *
     * @throws DependenciaNoDisponibleException si M3 no responde.
     */
    public function obtenerVariaciones(array $idsVariacion): array
    {
        $resultado = [];

        foreach (array_values(array_unique($idsVariacion)) as $idVariacion) {
            $variacion = $this->llamar('M3', fn (): ?array => $this->contrato->obtenerVariacion($idVariacion));

            if (! is_array($variacion) || ! isset($variacion['precio'], $variacion['id_producto'])) {
                continue;
            }

            $resultado[$idVariacion] = [
                'id_variacion' => $idVariacion,
                'id_producto' => (int) $variacion['id_producto'],
                'id_comercio' => (int) ($variacion['producto']['id_comercio'] ?? 0),
                'precio' => (float) $variacion['precio'],
            ];
        }

        return $resultado;
    }

    /**
     * Lista plana de categorías de M3, con id_categoria_padre. El orden y la
     * visibilidad del menú los agrega el servicio con CategoriaPortal.
     *
     * @return list<CategoriaDTO>
     */
    public function listarCategorias(): array
    {
        $categorias = $this->cache->obtener(CachePortal::CLAVE_CATEGORIAS);

        if ($categorias === null) {
            $categorias = $this->llamar('M3', fn (): array => $this->contrato->listarCategorias());
            $this->cache->guardar(CachePortal::CLAVE_CATEGORIAS, $categorias);
        }

        return array_map(fn (array $categoria): CategoriaDTO => new CategoriaDTO(
            idCategoria: $categoria['id_categoria'],
            nombre: $categoria['nombre'],
            idCategoriaPadre: $categoria['id_categoria_padre'] ?? null,
            iconoUrl: $categoria['icono_url'] ?? null,
        ), $categorias);
    }

    /**
     * @throws RecursoNoEncontradoException ERR-004 si el producto no existe, no está publicado o su comercio no está activo.
     */
    public function obtenerProducto(int $idProducto): FichaProductoDTO
    {
        $producto = $this->llamar(
            'M3',
            fn (): array => $this->contrato->obtenerProducto($idProducto),
            $this->traducirNoEncontrado(...),
        );

        return new FichaProductoDTO(
            idProducto: $producto['id_producto'],
            nombre: $producto['nombre'],
            descripcion: $producto['descripcion'] ?? null,
            marca: $this->nombreMarca($producto['id_marca'] ?? null),
            videoUrl: $producto['video_url'] ?? null,
            moneda: $producto['moneda'],
            imagenes: $producto['imagenes'] ?? [],
            variaciones: array_map(fn (array $variacion): VariacionDTO => new VariacionDTO(
                idVariacion: $variacion['id_variacion'],
                sku: $variacion['sku'],
                atributos: $variacion['atributos'] ?? [],
                precio: (float) $variacion['precio'],
                imagenes: $variacion['imagenes'] ?? [],
                ventaHabilitada: (bool) $variacion['disponible'],
                disponibilidad: Disponibilidad::Agotado,
                comprable: false,
            ), $producto['variaciones']),
            comercio: new ComercioDTO(
                idComercio: $producto['id_comercio'],
                nombreComercial: '',
                logotipo: null,
                descripcion: null,
                imagenes: [],
                horarios: [],
                contacto: [],
                redesSociales: [],
                politicas: [],
                estado: EstadoComercio::Activo,
            ),
            relacionados: [],
            compraHabilitada: false,
        );
    }

    /**
     * @return list<ProductoResumenDTO>
     *
     * @throws RecursoNoEncontradoException ERR-004 si el producto no existe o no está visible.
     */
    public function listarRelacionados(int $idProducto): array
    {
        $relacionados = $this->llamar(
            'M3',
            fn (): array => $this->contrato->listarRelacionados($idProducto, self::RELACIONADOS),
            $this->traducirNoEncontrado(...),
        );

        return array_map(fn (array $producto): ProductoResumenDTO => $this->resumen($producto), $relacionados);
    }

    /**
     * Nombre de la marca a partir de la lista de marcas de M3, que se guarda
     * en la caché porque se usa en cada ficha.
     */
    private function nombreMarca(?int $idMarca): ?string
    {
        if ($idMarca === null) {
            return null;
        }

        $marcas = $this->cache->obtener(CachePortal::CLAVE_MARCAS);

        if ($marcas === null) {
            $marcas = $this->llamar('M3', fn (): array => $this->contrato->listarMarcas());
            $this->cache->guardar(CachePortal::CLAVE_MARCAS, $marcas);
        }

        foreach ($marcas as $marca) {
            if ($marca['id_marca'] === $idMarca) {
                return $marca['nombre'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $producto  Resultado de búsqueda de M3.
     */
    private function resumen(array $producto): ProductoResumenDTO
    {
        $precio = (float) $producto['precio_desde'];
        // Contrato M1 1.1.0 (ProductoResumen.precio_hasta): igual a precio
        // cuando todas las variaciones cuestan lo mismo.
        $precioHasta = max($precio, (float) ($producto['precio_hasta'] ?? $precio));

        return new ProductoResumenDTO(
            idProducto: $producto['id_producto'],
            idComercio: $producto['id_comercio'],
            nombre: $producto['nombre'],
            imagenPrincipal: $producto['imagen_principal'] ?? null,
            precio: $precio,
            precioHasta: $precioHasta,
            moneda: $producto['moneda'],
            nombreComercio: '',
            disponibilidad: Disponibilidad::Agotado,
        );
    }

    private function traducirNoEncontrado(?string $codigo, Throwable $error): ?RecursoNoEncontradoException
    {
        return $codigo === 'ERR-004' ? new RecursoNoEncontradoException($error->getMessage(), $error) : null;
    }
}
