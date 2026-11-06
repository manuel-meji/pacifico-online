<?php

declare(strict_types=1);

namespace App\Http\Controllers\M2_Comercios;

use App\Http\Controllers\Controller;
use App\Models\M2_Comercios\SolicitudComercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SolicitudesController extends Controller
{
    public function __construct(private readonly ComerciosService $comercios) {}

    public function listar(Request $request): JsonResponse
    {
        Gate::authorize('revisar', new SolicitudComercio);

        $datos = $request->validate([
            'estado' => ['nullable', Rule::in([SolicitudComercio::PENDIENTE, SolicitudComercio::APROBADA, SolicitudComercio::RECHAZADA])],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:'.ComerciosService::MAXIMO_POR_PAGINA],
        ]);

        return response()->json($this->comercios->listarSolicitudes(
            $datos['estado'] ?? null,
            (int) ($datos['pagina'] ?? 1),
            (int) ($datos['por_pagina'] ?? 20),
        ));
    }

    public function consultar(int $idSolicitud): JsonResponse
    {
        Gate::authorize('revisar', new SolicitudComercio);

        return response()->json($this->comercios->consultarSolicitud($idSolicitud));
    }

    public function aprobar(int $idSolicitud, Request $request): JsonResponse
    {
        Gate::authorize('aprobar', new SolicitudComercio);

        return response()->json($this->comercios->aprobarSolicitud($idSolicitud)->only([
            'id_comercio', 'cedula_juridica', 'nombre_comercial', 'razon_social',
            'direccion_fiscal', 'inscrito_como_contribuyente', 'estado', 'created_at', 'updated_at',
        ]));
    }

    public function rechazar(int $idSolicitud, Request $request): JsonResponse
    {
        Gate::authorize('rechazar', new SolicitudComercio);

        $datos = $request->validate(['motivo' => ['required', 'string']]);
        $solicitud = $this->comercios->rechazarSolicitud($idSolicitud, $datos['motivo']);

        return response()->json($solicitud->only([
            'id_solicitud', 'id_comercio', 'nombre_encargado', 'correo_encargado',
            'telefono_encargado', 'estado', 'requiere_documentos', 'motivo_rechazo',
            'fecha_reenvio', 'created_at', 'updated_at',
        ]));
    }

    public function evaluarDocumento(int $idSolicitud, int $idDocumento, Request $request): JsonResponse
    {
        Gate::authorize('evaluarDocumento', new SolicitudComercio);

        $datos = $request->validate([
            'resultado' => ['required', 'string', Rule::in(['valido', 'invalido'])],
            'observacion' => ['nullable', 'string'],
        ]);

        $documento = $this->comercios->evaluarDocumento(
            $idSolicitud,
            $idDocumento,
            $datos['resultado'],
            $datos['observacion'] ?? null,
        );

        return response()->json($documento->only([
            'id_documento_comercio', 'id_comercio', 'tipo', 'fecha_vencimiento',
            'resultado', 'observacion', 'created_at', 'updated_at',
        ]));
    }

    public function registrar(Request $request): JsonResponse
    {
        return response()->json(['mensaje' => 'Pendiente de implementar'], 501);
    }

    public function corregir(int $idSolicitud, Request $request): JsonResponse
    {
        return response()->json(['mensaje' => 'Pendiente de implementar'], 501);
    }
}
