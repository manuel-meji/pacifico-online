<?php

namespace App\Services\M3_Catalogo;

use App\Contracts\M3_Catalogo\CatalogoConsultasInterface;
use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Models\M3_Catalogo\Categoria;
use App\Models\M3_Catalogo\EstadoComercioCatalogo;
use App\Models\M3_Catalogo\Marca;
use App\Models\M3_Catalogo\Producto;
use App\Models\M3_Catalogo\ProductoRelacionado;
use App\Models\M3_Catalogo\ProductoVariacion;
use App\Services\M3_Catalogo\Excepciones\DatosInvalidosCatalogoException;
use Illuminate\Database\Eloquent\Builder;

/**
 * M3 Catalogo: lectura real de la base de datos (contrato OpenAPI M3 v1.3.0).
 *
 * Visibilidad publica: producto en estado 'publicado' y comercio con activo = true en
 * estado_comercios_catalogo (tabla propia de M3, mantenida con eventos de M2; no se
 * consulta a M2). Los nombres de campo son los del contrato.
 */
class CatalogoService implements CatalogoConsultasInterface, CatalogoServiceInterface
{
    private const POR_PAGINA_DEFECTO = 20;

    private const POR_PAGINA_MAXIMO = 100;

    private const MAXIMO_IDS = 50;

    private const ORDENES = ['precio_asc', 'precio_desc', 'novedad'];

    public function obtenerDetalleProducto(int $productoId): ?array
    {
        $producto = $this->productosVisibles()
            ->with(['variaciones.atributos', 'imagenes'])
            ->where('id_producto', $productoId)
            ->first();

        if ($producto === null) {
            return null;
        }

        return $this->productoBase($producto) + [
            'variaciones' => $producto->variaciones
                ->sortBy('id_variacion')
                ->map(fn (ProductoVariacion $v): array => $this->variacion($v))
                ->values()
                ->all(),
            'imagenes' => $producto->imagenes
                ->sortBy('orden')
                ->map(fn ($i): array => [
                    'id_imagen' => (int) $i->id_imagen,
                    'id_producto' => (int) $i->id_producto,
                    'id_variacion' => $i->id_variacion === null ? null : (int) $i->id_variacion,
                    'imagen_url' => $i->imagen_url,
                    'orden' => (int) $i->orden,
                ])
                ->values()
                ->all(),
        ];
    }

    public function obtenerVariacion(int $variacionId): ?array
    {
        $variacion = ProductoVariacion::query()
            ->with(['atributos', 'producto'])
            ->where('id_variacion', $variacionId)
            ->first();

        if ($variacion === null || $variacion->producto === null) {
            return null;
        }

        return $this->variacion($variacion) + [
            'producto' => $this->productoBase($variacion->producto),
        ];
    }

    public function buscarProductos(array $filtros = []): array
    {
        $f = $this->validarFiltros($filtros);

        // Con ids se ignoran los demas filtros y no hay paginacion.
        if ($f['ids'] !== null) {
            $productos = $this->consultaResumen($this->productosVisibles())
                ->whereIn('id_producto', $f['ids'])
                ->get()
                ->keyBy('id_producto');

            $ordenados = [];
            foreach ($f['ids'] as $id) {
                if ($productos->has($id)) {
                    $ordenados[] = $this->resumen($productos->get($id));
                }
            }

            return [
                'pagina' => 1,
                'por_pagina' => count($ordenados),
                'total_resultados' => count($ordenados),
                'productos' => $ordenados,
            ];
        }

        $consulta = $this->consultaResumen($this->productosVisibles());

        if ($f['id_comercio'] !== null) {
            $consulta->where('id_comercio', $f['id_comercio']);
        }
        if ($f['id_categoria'] !== null) {
            $consulta->whereIn('id_categoria', $this->categoriaConSubcategorias($f['id_categoria']));
        }
        if ($f['id_marca'] !== null) {
            $consulta->where('id_marca', $f['id_marca']);
        }
        if ($f['destacado'] !== null) {
            $consulta->where('destacado', $f['destacado']);
        }
        if ($f['q'] !== null) {
            $patron = '%'.addcslashes(mb_strtolower($f['q']), '%_\\').'%';
            $consulta->where(function (Builder $q) use ($patron): void {
                $q->whereRaw("LOWER(nombre) LIKE ? ESCAPE '\\'", [$patron])
                    ->orWhereRaw("LOWER(COALESCE(descripcion, '')) LIKE ? ESCAPE '\\'", [$patron]);
            });
        }
        if ($f['precio_minimo'] !== null || $f['precio_maximo'] !== null) {
            $consulta->whereHas('variaciones', function (Builder $q) use ($f): void {
                if ($f['precio_minimo'] !== null) {
                    $q->where('precio', '>=', $f['precio_minimo']);
                }
                if ($f['precio_maximo'] !== null) {
                    $q->where('precio', '<=', $f['precio_maximo']);
                }
            });
        }

        match ($f['orden']) {
            'precio_asc' => $consulta->orderBy('precio_min'),
            'precio_desc' => $consulta->orderByDesc('precio_max'),
            'novedad' => $consulta->orderByDesc('created_at'),
            default => null,
        };
        $consulta->orderBy('id_producto');

        $pagina = $consulta->paginate($f['por_pagina'], ['*'], 'pagina', $f['pagina']);

        return [
            'pagina' => $f['pagina'],
            'por_pagina' => $f['por_pagina'],
            'total_resultados' => $pagina->total(),
            'productos' => $pagina->getCollection()
                ->map(fn (Producto $p): array => $this->resumen($p))
                ->values()
                ->all(),
        ];
    }

    public function listarCategorias(): array
    {
        return Categoria::query()
            ->orderBy('nombre')->orderBy('id_categoria')
            ->get()
            ->map(function (Categoria $c): array {
                $fila = [
                    'id_categoria' => (int) $c->id_categoria,
                    'nombre' => $c->nombre,
                ];
                if ($c->descripcion !== null) {
                    $fila['descripcion'] = $c->descripcion;
                }
                $fila['id_categoria_padre'] = $c->id_categoria_padre === null ? null : (int) $c->id_categoria_padre;

                return $fila;
            })
            ->all();
    }

    public function listarMarcas(?bool $destacada = null): array
    {
        $consulta = Marca::query()->orderBy('nombre')->orderBy('id_marca');

        if ($destacada !== null) {
            $consulta->where('destacada', $destacada);
        }

        return $consulta->get()
            ->map(function (Marca $m): array {
                $fila = ['id_marca' => (int) $m->id_marca, 'nombre' => $m->nombre];
                if ($m->logotipo_url !== null) {
                    $fila['logotipo_url'] = $m->logotipo_url;
                }
                $fila['destacada'] = (bool) $m->destacada;

                return $fila;
            })
            ->all();
    }

    public function listarRelacionados(int $productoId, int $limite = 4): ?array
    {
        $existe = $this->productosVisibles()->where('id_producto', $productoId)->exists();

        if (! $existe) {
            return null;
        }

        $idsRelacionados = ProductoRelacionado::query()
            ->where('id_producto', $productoId)
            ->orderBy('id_producto_relacionado')
            ->pluck('id_producto_relacionado')
            ->all();

        if ($idsRelacionados === []) {
            return [];
        }

        $productos = $this->consultaResumen($this->productosVisibles())
            ->whereIn('id_producto', $idsRelacionados)
            ->orderBy('id_producto')
            ->limit(max(1, $limite))
            ->get();

        return $productos->map(fn (Producto $p): array => $this->resumen($p))->values()->all();
    }

    // ------------------------------------------------------------------ privados

    /** Solo productos publicados de comercios activos (contrato v1.3.0). */
    private function productosVisibles(): Builder
    {
        return Producto::query()
            ->where('estado_producto', 'publicado')
            ->whereIn(
                'id_comercio',
                EstadoComercioCatalogo::query()->where('activo', true)->select('id_comercio')
            );
    }

    /** Carga imagenes y variaciones de una sola vez (sin N+1) y el rango de precios para ordenar. */
    private function consultaResumen(Builder $consulta): Builder
    {
        return $consulta
            ->with([
                'imagenes' => fn ($q) => $q->orderBy('orden'),
                'variaciones' => fn ($q) => $q->where('disponible', true),
            ])
            ->withMin(['variaciones as precio_min' => fn ($q) => $q->where('disponible', true)], 'precio')
            ->withMax(['variaciones as precio_max' => fn ($q) => $q->where('disponible', true)], 'precio');
    }

    /** @return list<int> la categoria y todas sus descendientes */
    private function categoriaConSubcategorias(int $idCategoria): array
    {
        $hijosPorPadre = Categoria::query()
            ->whereNotNull('id_categoria_padre')
            ->get(['id_categoria', 'id_categoria_padre'])
            ->groupBy('id_categoria_padre');

        $ids = [$idCategoria];
        $pendientes = [$idCategoria];

        while ($pendientes !== []) {
            $actual = array_pop($pendientes);

            foreach ($hijosPorPadre->get($actual, []) as $hijo) {
                $id = (int) $hijo->id_categoria;
                if (! in_array($id, $ids, true)) {
                    $ids[] = $id;
                    $pendientes[] = $id;
                }
            }
        }

        return $ids;
    }

    /** @return array<string, mixed> campos de Producto del contrato */
    private function productoBase(Producto $p): array
    {
        $fila = [
            'id_producto' => (int) $p->id_producto,
            'created_at' => $p->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'estado_producto' => $p->estado_producto,
            'id_comercio' => (int) $p->id_comercio,
            'id_categoria' => (int) $p->id_categoria,
            'id_marca' => $p->id_marca === null ? null : (int) $p->id_marca,
            'nombre' => $p->nombre,
        ];
        if ($p->descripcion !== null) {
            $fila['descripcion'] = $p->descripcion;
        }
        $fila['video_url'] = $p->video_url;
        $fila['destacado'] = (bool) $p->destacado;

        return $fila;
    }

    /** @return array<string, mixed> ProductoVariacion del contrato */
    private function variacion(ProductoVariacion $v): array
    {
        $fila = [
            'id_variacion' => (int) $v->id_variacion,
            'id_producto' => (int) $v->id_producto,
            'sku' => $v->sku,
            'precio' => (float) $v->precio,
            'tarifa_impuesto' => (float) $v->tarifa_impuesto,
        ];
        if ($v->codigo_barras !== null) {
            $fila['codigo_barras'] = $v->codigo_barras;
        }
        if ($v->codigo_fabricante !== null) {
            $fila['codigo_fabricante'] = $v->codigo_fabricante;
        }
        $fila['disponible'] = (bool) $v->disponible;
        $fila['atributos'] = $v->atributos
            ->sortBy('id_atributo')
            ->map(fn ($a): array => ['tipo_atributo' => $a->tipo_atributo, 'valor' => $a->valor])
            ->values()
            ->all();

        return $fila;
    }

    /** @return array<string, mixed> ProductoResumenBusqueda del contrato */
    private function resumen(Producto $p): array
    {
        $precios = $p->variaciones->pluck('precio')->map(fn ($x) => (float) $x);
        $primeraImagen = $p->imagenes->sortBy('orden')->first();

        return $this->productoBase($p) + [
            'imagen_principal_url' => $primeraImagen?->imagen_url,
            'precio_desde' => $precios->isEmpty() ? null : $precios->min(),
            'precio_hasta' => $precios->isEmpty() ? null : $precios->max(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function validarFiltros(array $filtros): array
    {
        $errores = [];

        $entero = function (string $campo, int $minimo = 1) use ($filtros, &$errores): ?int {
            if (! array_key_exists($campo, $filtros) || $filtros[$campo] === null || $filtros[$campo] === '') {
                return null;
            }
            $valor = filter_var($filtros[$campo], FILTER_VALIDATE_INT);
            if ($valor === false || $valor < $minimo) {
                $errores[$campo] = "Debe ser un entero mayor o igual a {$minimo}.";

                return null;
            }

            return $valor;
        };

        $decimal = function (string $campo) use ($filtros, &$errores): ?float {
            if (! array_key_exists($campo, $filtros) || $filtros[$campo] === null || $filtros[$campo] === '') {
                return null;
            }
            if (! is_numeric($filtros[$campo]) || (float) $filtros[$campo] < 0) {
                $errores[$campo] = 'Debe ser un numero mayor o igual a 0.';

                return null;
            }

            return (float) $filtros[$campo];
        };

        $booleano = function (string $campo) use ($filtros, &$errores): ?bool {
            if (! array_key_exists($campo, $filtros) || $filtros[$campo] === null || $filtros[$campo] === '') {
                return null;
            }
            $valor = filter_var($filtros[$campo], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($valor === null) {
                $errores[$campo] = 'Debe ser verdadero o falso.';
            }

            return $valor;
        };

        $ids = null;
        if (array_key_exists('ids', $filtros) && $filtros['ids'] !== null && $filtros['ids'] !== '') {
            $crudos = is_array($filtros['ids']) ? $filtros['ids'] : explode(',', (string) $filtros['ids']);
            $ids = [];
            foreach ($crudos as $crudo) {
                $id = filter_var(is_string($crudo) ? trim($crudo) : $crudo, FILTER_VALIDATE_INT);
                if ($id === false || $id < 1) {
                    $errores['ids'] = 'Debe ser una lista de enteros mayores o iguales a 1.';
                    $ids = null;
                    break;
                }
                $ids[] = $id;
            }
            if ($ids !== null && count($ids) > self::MAXIMO_IDS) {
                $errores['ids'] = 'Maximo '.self::MAXIMO_IDS.' identificadores.';
                $ids = null;
            }
            $ids = $ids === null ? null : array_values(array_unique($ids));
        }

        $orden = $filtros['orden'] ?? null;
        if ($orden !== null && $orden !== '' && ! in_array($orden, self::ORDENES, true)) {
            $errores['orden'] = 'Debe ser precio_asc, precio_desc o novedad.';
            $orden = null;
        }

        $porPagina = $entero('por_pagina');
        if ($porPagina !== null && $porPagina > self::POR_PAGINA_MAXIMO) {
            $errores['por_pagina'] = 'El maximo es '.self::POR_PAGINA_MAXIMO.'.';
        }

        $q = $filtros['q'] ?? $filtros['termino'] ?? null; // 'termino' es lo que envia M1
        $q = is_string($q) && trim($q) !== '' ? trim($q) : null;

        $resultado = [
            'id_comercio' => $entero('id_comercio'),
            'id_categoria' => $entero('id_categoria'),
            'id_marca' => $entero('id_marca'),
            'destacado' => $booleano('destacado'),
            'q' => $q,
            'ids' => $ids,
            'precio_minimo' => $decimal('precio_minimo'),
            'precio_maximo' => $decimal('precio_maximo'),
            'orden' => $orden === '' ? null : $orden,
            'pagina' => $entero('pagina') ?? 1,
            'por_pagina' => $porPagina ?? self::POR_PAGINA_DEFECTO,
        ];

        if ($errores !== []) {
            throw new DatosInvalidosCatalogoException($errores);
        }

        return $resultado;
    }
}
