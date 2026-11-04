<?php

declare(strict_types=1);

namespace App\Http\Controllers\M2_Comercios;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Http\Controllers\Controller;
use App\Policies\M2_Comercios\ComercioPolicy;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador delgado de las 8 operaciones del contrato v1.2.1 (/api/m2/v1).
 *
 * Valida la forma de la entrada, autoriza (ComercioPolicy) y delega en la fachada.
 * Los errores (ValidationException, ErrorNucleoException) los convierte en
 * ErrorEstandar el ManejadorErrores del núcleo.
 *
 * Mientras la interfaz es el borrador del Comité, el controlador depende de
 * ComerciosService; en el PR 2 de M2 pasa a ComerciosServiceInterface.
 */
class ConsultasComerciosController extends Controller
{
    public function __construct(
        private readonly ComerciosService $comercios,
        private readonly ComercioPolicy $politica,
    ) {}

    public function listar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:'.ComerciosService::MAXIMO_POR_PAGINA],
            'destacado' => ['sometimes', 'in:true,false,1,0'],
        ]);

        return response()->json($this->comercios->listarComerciosHabilitados(
            (int) ($datos['pagina'] ?? 1),
            (int) ($datos['por_pagina'] ?? 20),
            filter_var($datos['destacado'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ));
    }

    public function resumenLote(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'ids_comercio' => ['required', 'array', 'min:1', 'max:'.ComerciosService::MAXIMO_LOTE],
            'ids_comercio.*' => ['integer', 'distinct'],
        ]);

        $ids = array_map('intval', $datos['ids_comercio']);

        return response()->json($this->comercios->obtenerResumenComerciosLote($ids));
    }

    public function publico(int $idComercio): JsonResponse
    {
        return response()->json($this->comercios->obtenerDatosComercioPublico($idComercio));
    }

    public function estado(Request $request, int $idComercio): JsonResponse
    {
        $this->politica->consultarComercio($this->usuario($request), $idComercio);

        return response()->json($this->comercios->obtenerComercioActivo($idComercio));
    }

    public function sedes(Request $request, int $idComercio): JsonResponse
    {
        $this->politica->consultarComercio($this->usuario($request), $idComercio);

        return response()->json($this->comercios->obtenerSedesComercio($idComercio));
    }

    public function politicasEnvio(Request $request, int $idComercio): JsonResponse
    {
        $usuario = $this->usuario($request);
        $this->politica->consultarPoliticasEnvio($usuario, $idComercio);

        // El cliente solo ve comercios habilitados (x-autorizacion).
        if ($this->politica->esCliente($usuario) && ! $this->comercios->obtenerComercioActivo($idComercio)['activo']) {
            throw ErrorNucleoException::recursoNoEncontrado();
        }

        return response()->json($this->comercios->obtenerPoliticasEnvio($idComercio));
    }

    public function datosFiscales(Request $request, int $idComercio): JsonResponse
    {
        $this->politica->consultarComercio($this->usuario($request), $idComercio);

        return response()->json($this->comercios->obtenerDatosFiscalesComercio($idComercio));
    }

    public function comisionSubpedido(Request $request, int $idSubpedido): JsonResponse
    {
        $usuario = $this->usuario($request);
        if ($this->politica->esCliente($usuario)) {
            throw ErrorNucleoException::rolSinPermiso();
        }
        $comision = $this->comercios->obtenerComisionSubpedido($idSubpedido);
        $this->politica->consultarComision($usuario, (int) $comision['id_comercio']);

        return response()->json($comision);
    }

    private function usuario(Request $request): Authenticatable
    {
        $usuario = $request->user();

        if (! $usuario instanceof Authenticatable) {
            throw ErrorNucleoException::sesionInvalida();
        }

        return $usuario;
    }
}
