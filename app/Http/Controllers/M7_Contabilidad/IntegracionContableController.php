<?php

declare(strict_types=1);

namespace App\Http\Controllers\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ContabilidadComercioServiceInterface;
use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IntegracionContableController extends Controller
{
    public function __construct(
        private readonly ContabilidadComercioServiceInterface $contabilidad,
        private readonly NucleoServiceInterface $nucleo,
    ) {}

    public function consultarRegistroContablePago(Request $request, int $idPago): JsonResponse
    {
        $usuario = $request->user();

        // La plataforma consulta sin filtro; cualquier otro rol queda limitado a su comercio.
        if ($usuario->rol === RolUsuario::EncargadoPlataforma) {
            $this->nucleo->registrarAuditoria(
                'consulta_registro_contable_pago',
                'pago',
                $idPago,
                ['modulo' => 'M7', 'motivo' => sprintf('%s %s', $request->method(), $request->path())]
            );

            return response()->json($this->contabilidad->consultarRegistroContablePago($idPago));
        }

        return response()->json(
            $this->contabilidad->consultarRegistroContablePagoDeComercio($idPago, (int) $usuario->id_comercio)
        );
    }
}
