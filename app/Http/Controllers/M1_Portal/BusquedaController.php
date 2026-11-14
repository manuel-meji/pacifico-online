<?php

namespace App\Http\Controllers\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Búsqueda y menú de categorías del portal. Capa delgada: valida la entrada
 * y llama al contrato de M1. Los errores los convierte el manejador del núcleo.
 */
class BusquedaController extends Controller
{
    private const REGLAS = [
        'q' => 'nullable|string|max:100',
        'id_categoria' => 'nullable|integer|min:1',
        'id_comercio' => 'nullable|integer|min:1',
        'id_marca' => 'nullable|integer|min:1',
        'precio_minimo' => 'nullable|numeric|min:0',
        'precio_maximo' => 'nullable|numeric|min:0',
        'orden' => 'nullable|in:precio_asc,precio_desc,novedad',
        'pagina' => 'nullable|integer|min:1',
        'por_pagina' => 'nullable|integer|between:1,50',
    ];

    private const MENSAJES = [
        'string' => 'Debe ser texto.',
        'integer' => 'Debe ser un número entero.',
        'numeric' => 'Debe ser un número.',
        'min' => 'Debe ser mayor o igual a :min.',
        'q.max' => 'Debe tener como máximo :max caracteres.',
        'between' => 'Debe estar entre :min y :max.',
        'in' => 'Debe ser precio_asc, precio_desc o novedad.',
    ];

    public function __construct(private PortalPublicoContract $portal) {}

    /**
     * GET /api/m1/v1/productos/busqueda (CU-002 Buscar productos).
     *
     * @throws DatosInvalidosException ERR-005 con el detalle por campo.
     */
    public function buscar(Request $request): JsonResponse
    {
        $datos = $this->validar($request->query());

        return response()->json($this->portal->buscarProductos(
            $datos['q'] ?? null,
            $this->entero($datos, 'id_categoria'),
            $this->entero($datos, 'id_comercio'),
            $this->entero($datos, 'pagina') ?? 1,
            $this->entero($datos, 'por_pagina') ?? 20,
            $this->entero($datos, 'id_marca'),
            $this->decimal($datos, 'precio_minimo'),
            $this->decimal($datos, 'precio_maximo'),
            $datos['orden'] ?? null,
        ));
    }

    /**
     * GET /api/m1/v1/categorias (menú de categorías, RN-M1-09).
     */
    public function listarCategorias(): JsonResponse
    {
        return response()->json($this->portal->listarCategoriasPortal());
    }

    /**
     * Reglas de formato del contrato. La búsqueda vacía o de un solo carácter
     * ya es válida: lista todos los productos o los que coincidan (sin RN-M1-08).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws DatosInvalidosException
     */
    private function validar(array $query): array
    {
        $validador = Validator::make($query, self::REGLAS, self::MENSAJES);

        if ($validador->fails()) {
            throw new DatosInvalidosException(
                array_map(fn (array $mensajes): string => $mensajes[0], $validador->errors()->messages())
            );
        }

        return $validador->validated();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function entero(array $datos, string $campo): ?int
    {
        return isset($datos[$campo]) ? (int) $datos[$campo] : null;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function decimal(array $datos, string $campo): ?float
    {
        return isset($datos[$campo]) ? (float) $datos[$campo] : null;
    }
}
