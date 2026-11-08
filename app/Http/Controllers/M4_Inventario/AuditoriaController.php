<?php

namespace App\Http\Controllers\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\M4_Inventario\ConsultarAuditoriaRequest;
use Illuminate\Http\JsonResponse;

/**
 * GET /auditoria/inventario (consultarInventarioAuditoria, CU-M4-12, RN-M4-015):
 * solo lectura, rol de auditoría de la plataforma.
 */
class AuditoriaController extends Controller
{
    public function __construct(private readonly InventarioModuleInterface $inventario) {}

    public function consultar(ConsultarAuditoriaRequest $solicitud): JsonResponse
    {
        return response()->json($this->inventario->consultarInventarioAuditoria(
            $solicitud->idsComercio(),
            $solicitud->validated('fecha_inicio'),
            $solicitud->validated('fecha_fin'),
        ));
    }
}
