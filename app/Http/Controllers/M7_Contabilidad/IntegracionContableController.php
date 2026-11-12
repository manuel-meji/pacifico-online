<?php

declare(strict_types=1);

namespace App\Http\Controllers\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class IntegracionContableController extends Controller
{
    public function __construct(
        private readonly ContabilidadServiceInterface $contabilidad,
    ) {}

    public function consultarRegistroContablePago(int $idPago): JsonResponse
    {
        return response()->json(
            $this->contabilidad->consultarRegistroContablePago($idPago)
        );
    }
}
