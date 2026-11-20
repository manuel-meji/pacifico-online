<?php

declare(strict_types=1);

namespace App\Http\Controllers\M2_Comercios;

use App\Enums\Nucleo\RolUsuario;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Http\Controllers\Controller;
use App\Models\M2_Comercios\SolicitudComercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    /**
     * Estado de la solicitud del propio comercio, para la persona encargada que ingresa antes de
     * que se apruebe. Solo el Encargado del comercio; los demás roles reciben 403 (ERR-002).
     */
    public function miSolicitud(Request $request): JsonResponse
    {
        $usuario = $this->encargadoConComercio($request);

        return response()->json($this->comercios->estadoSolicitudDelComercio((int) $usuario->id_comercio));
    }

    /**
     * CU-0013 con sesión (DEF-34): la persona encargada corrige su solicitud rechazada. El número y la
     * cédula solo se comparan con los registrados; el correo y los demás datos sí se pueden corregir,
     * y los documentos rechazados o vencidos se reemplazan por los que se adjunten.
     */
    public function corregirMia(Request $request): JsonResponse
    {
        $usuario = $this->encargadoConComercio($request);

        $datos = $request->validate([
            'id_solicitud' => ['sometimes', 'integer'],
            'cedula_juridica' => ['sometimes', 'string'],
            'correo_encargado' => ['sometimes', 'email', 'max:150'],
            'nombre_comercial' => ['sometimes', 'string'],
            'razon_social' => ['sometimes', 'string'],
            'direccion_fiscal' => ['sometimes', 'string'],
            'inscrito_como_contribuyente' => ['sometimes', 'boolean'],
            'nombre_encargado' => ['sometimes', 'string'],
            'telefono_encargado' => ['sometimes', 'nullable', 'string'],
            'documentos' => ['sometimes', 'array'],
            'documentos.*.archivo' => ['required', 'file', 'mimes:pdf,jpg,png', 'max:5120'],
            'documentos.*.tipo' => ['required', 'string'],
            'documentos.*.fecha_vencimiento' => ['required', 'date', 'after:today'],
        ]);

        $documentos = $datos['documentos'] ?? [];
        unset($datos['documentos']);

        $solicitud = $this->comercios->corregirSolicitudDelEncargado(
            (int) $usuario->id_comercio,
            (int) $usuario->getAuthIdentifier(),
            $datos,
            $documentos,
        );

        return response()->json($solicitud->only([
            'id_solicitud', 'id_comercio', 'nombre_encargado', 'correo_encargado', 'telefono_encargado',
            'estado', 'requiere_documentos', 'motivo_rechazo', 'fecha_reenvio', 'created_at', 'updated_at',
        ]));
    }

    /** Solo la persona encargada de un comercio consulta o corrige su propia solicitud. */
    private function encargadoConComercio(Request $request): Authenticatable
    {
        $usuario = $request->user();
        $rol = $usuario?->rol instanceof RolUsuario ? $usuario->rol : RolUsuario::tryFrom((string) $usuario?->rol);
        if ($rol !== RolUsuario::EncargadoComercio || $usuario->id_comercio === null) {
            throw ErrorNucleoException::rolSinPermiso();
        }

        return $usuario;
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

    /** CU-0022: abre el archivo original de un documento (solo el Encargado de la plataforma). */
    public function verArchivoDocumento(int $idSolicitud, int $idDocumento): Response
    {
        Gate::authorize('revisar', new SolicitudComercio);

        $archivo = $this->comercios->obtenerArchivoDocumento($idSolicitud, $idDocumento);

        return response($archivo['contenido'], 200, [
            'Content-Type' => $archivo['tipo'],
            'Content-Disposition' => 'inline; filename="documento-'.$idDocumento.'"',
            'Cache-Control' => 'no-store',
        ]);
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
        $datos = $request->validate([
            'nombre_comercial' => ['required', 'string'],
            'cedula_juridica' => [
                'required',
                'string',
                'regex:/^[0-9]-[0-9]{3}-[0-9]{6}$/',
            ],
            'razon_social' => ['required', 'string'],
            'direccion_fiscal' => ['required', 'string'],
            'inscrito_como_contribuyente' => ['required', 'boolean'],

            'nombre_encargado' => ['required', 'string'],
            'correo_encargado' => ['required', 'email'],
            'telefono_encargado' => ['nullable', 'string'],
            // Con contraseña, la cuenta de la persona encargada se crea con la solicitud. Es opcional en
            // la API para no romper a quien ya la usa; la pantalla de solicitud la pide siempre.
            'contrasena_encargado' => ['nullable', 'string', 'min:8', 'max:72', 'confirmed'],

            'documentos' => ['sometimes', 'array'],
            'documentos.*.archivo' => [
                'required',
                'file',
                'mimes:pdf,jpg,png',
                'max:5120',
            ],
            'documentos.*.tipo' => ['required', 'string'],
            'documentos.*.fecha_vencimiento' => [
                'required',
                'date',
                'after:today',
            ],
        ], [
            'contrasena_encargado.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'contrasena_encargado.max' => 'La contraseña no puede tener más de 72 caracteres.',
            'contrasena_encargado.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $documentos = $datos['documentos'] ?? [];
        unset($datos['documentos']);

        $solicitud = $this->comercios->registrarSolicitud(
            $datos,
            $documentos,
        );

        return response()->json($solicitud->only([
            'id_solicitud',
            'id_comercio',
            'nombre_encargado',
            'correo_encargado',
            'telefono_encargado',
            'estado',
            'requiere_documentos',
            'motivo_rechazo',
            'fecha_reenvio',
            'created_at',
            'updated_at',
        ]));
    }

    public function corregir(int $idSolicitud, Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cedula_juridica' => [
                'required',
                'string',
                'regex:/^[0-9]-[0-9]{3}-[0-9]{6}$/',
            ],
            'correo_encargado' => ['required', 'email'],

            'nombre_comercial' => ['sometimes', 'string'],
            'razon_social' => ['sometimes', 'string'],
            'direccion_fiscal' => ['sometimes', 'string'],
            'inscrito_como_contribuyente' => ['sometimes', 'boolean'],
            'nombre_encargado' => ['sometimes', 'string'],
            'telefono_encargado' => ['sometimes', 'nullable', 'string'],
        ]);

        $solicitud = $this->comercios->corregirSolicitud(
            $idSolicitud,
            $datos,
        );

        return response()->json($solicitud->only([
            'id_solicitud',
            'id_comercio',
            'nombre_encargado',
            'correo_encargado',
            'telefono_encargado',
            'estado',
            'requiere_documentos',
            'motivo_rechazo',
            'fecha_reenvio',
            'created_at',
            'updated_at',
        ]));
    }
}
