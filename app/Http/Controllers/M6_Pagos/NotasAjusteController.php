<?php

declare(strict_types=1);

namespace App\Http\Controllers\M6_Pagos;

use App\Http\Controllers\Controller;
use App\Http\Requests\M6_Pagos\EmitirNotaAjusteRequest;
use App\Http\Resources\M6_Pagos\NotaCreditoDebitoResource;
use App\Services\M6_Pagos\DocumentoAjusteService;
use Illuminate\Http\JsonResponse;

/** Notas de crédito (CU-005) y de débito (CU-006) sobre una factura. Controlador delgado. */
class NotasAjusteController extends Controller
{
    public function __construct(private readonly DocumentoAjusteService $ajustes) {}

    /** POST /api/m6/v1/facturas/{id_factura}/notas-credito — GenerarNotaCredito. */
    public function credito(EmitirNotaAjusteRequest $request, int $idFactura): JsonResponse
    {
        $nota = $this->ajustes->emitirNotaCredito($idFactura, (float) $request->validated('monto'), trim($request->validated('motivo')));

        return (new NotaCreditoDebitoResource($nota))->response()->setStatusCode(201);
    }

    /** POST /api/m6/v1/facturas/{id_factura}/notas-debito — GenerarNotaDebito. */
    public function debito(EmitirNotaAjusteRequest $request, int $idFactura): JsonResponse
    {
        $nota = $this->ajustes->emitirNotaDebito($idFactura, (float) $request->validated('monto'), trim($request->validated('motivo')));

        return (new NotaCreditoDebitoResource($nota))->response()->setStatusCode(201);
    }
}
