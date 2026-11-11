<?php

declare(strict_types=1);

namespace App\Http\Controllers\M6_Pagos;

use App\Http\Controllers\Controller;
use App\Http\Requests\M6_Pagos\ListarFacturasRequest;
use App\Http\Resources\M6_Pagos\FacturaResource;
use App\Services\M6_Pagos\FacturacionService;
use Illuminate\Http\JsonResponse;

/** Consulta de facturas electrónicas (CU-004). Controlador delgado. */
class FacturasController extends Controller
{
    public function __construct(private readonly FacturacionService $facturacion) {}

    /** GET /api/m6/v1/facturas?id_pedido= — ListarFacturasPorPedido. Lista vacía si no hay facturas. */
    public function index(ListarFacturasRequest $request): JsonResponse
    {
        $facturas = $this->facturacion->listarPorPedido((int) $request->validated('id_pedido'));

        return response()->json(FacturaResource::collection($facturas)->resolve($request));
    }

    /** GET /api/m6/v1/facturas/{id_factura} — ObtenerFacturaElectronica. */
    public function show(int $idFactura): FacturaResource
    {
        return new FacturaResource($this->facturacion->obtener($idFactura));
    }
}
