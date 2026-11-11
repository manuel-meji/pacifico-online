<?php

namespace App\Contracts\M3_Catalogo;

use App\Services\M3_Catalogo\Excepciones\DatosInvalidosCatalogoException;

/**
 * Contrato de lectura del catalogo (M3 v1.3.0): busqueda, categorias, marcas y relacionados.
 *
 * Se mantiene separada de CatalogoServiceInterface para no romper a los otros modulos
 * que implementan esa interfaz (dobles de M1, M4 y M5).
 */
interface CatalogoConsultasInterface
{
    /**
     * Operacion buscarProductos del contrato M3 v1.3.0.
     * Filtros: id_comercio, id_categoria, id_marca, destacado, q, ids, precio_minimo,
     * precio_maximo, orden, pagina, por_pagina.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{pagina: int, por_pagina: int, total_resultados: int, productos: list<array<string, mixed>>}
     *
     * @throws DatosInvalidosCatalogoException ERR-005
     */
    public function buscarProductos(array $filtros = []): array;

    /**
     * Operacion listarCategorias: lista plana (no arbol) con id_categoria_padre.
     *
     * @return list<array<string, mixed>>
     */
    public function listarCategorias(): array;

    /**
     * Operacion listarMarcas, con filtro opcional de destacadas.
     *
     * @return list<array<string, mixed>>
     */
    public function listarMarcas(?bool $destacada = null): array;

    /**
     * Operacion listarRelacionados: resumen de los productos relacionados visibles.
     * Devuelve null si el producto origen no existe o no esta visible (ERR-004).
     *
     * @return list<array<string, mixed>>|null
     */
    public function listarRelacionados(int $productoId, int $limite = 4): ?array;
}
