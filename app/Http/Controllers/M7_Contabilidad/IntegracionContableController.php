<?php

declare(strict_types=1);

namespace App\Http\Controllers\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ContabilidadComercioServiceInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IntegracionContableController extends Controller
{
    public function __construct(
        private readonly ContabilidadComercioServiceInterface $contabilidad,
    ) {}

    public function consultarRegistroContablePago(Request $request, int $idPago): JsonResponse
    {
        $usuario = $request->user();

        //La plataforma consulta sin filtro; cualquier otro rol queda limitado a su comercio.
        if ($usuario->rol === RolUsuario::EncargadoPlataforma) {
            return response()->json($this->contabilidad->consultarRegistroContablePago($idPago));
        }

        return response()->json(
            $this->contabilidad->consultarRegistroContablePagoDeComercio($idPago, (int) $usuario->id_comercio)
        );
    }
}
