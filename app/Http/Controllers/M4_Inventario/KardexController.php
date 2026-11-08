<?php

namespace App\Http\Controllers\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\M4_Inventario\ConsultarKardexRequest;
use App\Http\Requests\M4_Inventario\KardexValorizadoRequest;
use Illuminate\Http\JsonResponse;

/**
 * Kardex (CU-M4-08 y CU-M4-10) y costo de productos vendidos para M7.
 */
class KardexController extends Controller
{
    public function __construct(private readonly InventarioModuleInterface $inventario) {}

    /** GET /kardex (consultarKardex). */
    public function consultar(ConsultarKardexRequest $solicitud): JsonResponse
    {
        return response()->json($this->inventario->consultarKardex(
            (int) $solicitud->validated('id_comercio'),
            (int) $solicitud->validated('id_variacion'),
            $solicitud->filtros(),
        ));
    }

    /** GET /kardex/valorizado (obtenerKardexValorizado). */
    public function valorizado(KardexValorizadoRequest $solicitud): JsonResponse
    {
        return response()->json($this->inventario->obtenerKardexValorizado(
            (int) $solicitud->validated('id_comercio'),
            (string) $solicitud->validated('fecha_inicio'),
            (string) $solicitud->validated('fecha_fin'),
            $solicitud->filtros(),
        ));
    }

    /** GET /subpedidos/{id_subpedido}/costos-productos-vendidos (ObtenerCostoProductosVendidos). */
    public function costosProductosVendidos(int $idSubpedido): JsonResponse
    {
        return response()->json($this->inventario->obtenerCostoProductosVendidos($idSubpedido));
    }
}
