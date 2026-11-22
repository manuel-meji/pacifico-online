<?php

declare(strict_types=1);

namespace App\Http\Controllers\M2_Comercios;

use App\Enums\Nucleo\RolUsuario;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\M2_Comercios\Concerns\ValidaEnEspanol;
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
    use ValidaEnEspanol;

    public function __construct(private readonly ComerciosService $comercios) {}

    public function listar(Request $request): JsonResponse
    {
        Gate::authorize('revisar', new SolicitudComercio);

        $datos = $this->validarM2($request, [
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

        // El número y la cédula solo se comparan con los registrados (no se pueden cambiar); los demás
        // datos cumplen las mismas reglas que en la solicitud (hallazgos 6 y 17).
        $datos = $this->validarM2($request, [
            'id_solicitud' => ['sometimes', 'integer'],
            'cedula_juridica' => ['sometimes', 'string'],
        ] + self::reglasDatosSolicitud(parcial: true));

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

        return response()->json($this->comercios->aprobarSolicitud($idSolicitud, $request->user())->only([
            'id_comercio', 'cedula_juridica', 'nombre_comercial', 'razon_social',
            'direccion_fiscal', 'inscrito_como_contribuyente', 'estado', 'created_at', 'updated_at',
        ]));
    }

    public function rechazar(int $idSolicitud, Request $request): JsonResponse
    {
        Gate::authorize('rechazar', new SolicitudComercio);

        $datos = $this->validarM2($request, ['motivo' => ['required', 'string', 'min:10', 'max:500']], [
            'motivo.required' => 'Escribí el motivo del rechazo: la persona encargada lo va a ver para corregir su solicitud.',
            'motivo.min' => 'Explicá el motivo con un poco más de detalle (al menos 10 caracteres).',
        ]);
        $solicitud = $this->comercios->rechazarSolicitud($idSolicitud, $datos['motivo'], $request->user());

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

        $datos = $this->validarM2($request, [
            'resultado' => ['required', 'string', Rule::in(['valido', 'invalido'])],
            'observacion' => ['nullable', 'string', 'max:500'],
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
        $datos = $this->validarM2($request, [
            'cedula_juridica' => ['required', 'string', 'regex:'.self::CEDULA_JURIDICA],
            // Con contraseña, la cuenta de la persona encargada queda lista para ingresar; sin ella, el
            // núcleo le envía una invitación para definirla. En los dos casos la cuenta se crea con la
            // solicitud (hallazgo 23). La pantalla de solicitud la pide siempre.
            'contrasena_encargado' => ['nullable', 'string', 'min:8', 'max:72', 'confirmed'],
        ] + self::reglasDatosSolicitud());

        $documentos = $datos['documentos'] ?? [];
        unset($datos['documentos']);

        // La ruta es pública; si la usa la plataforma con su sesión (pestaña «Registrar comercio»),
        // queda registrado quién lo hizo (hallazgo 11).
        $registrador = auth('sanctum')->user();
        $rol = $registrador?->rol instanceof RolUsuario ? $registrador->rol : RolUsuario::tryFrom((string) $registrador?->rol);

        $solicitud = $this->comercios->registrarSolicitud(
            $datos,
            $documentos,
            $rol === RolUsuario::EncargadoPlataforma ? $registrador : null,
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
        // Ruta pública anterior al DEF-34 (deuda técnica): número, cédula y correo identifican la solicitud.
        $reglas = self::reglasDatosSolicitud(parcial: true);
        unset($reglas['documentos'], $reglas['documentos.*.archivo'], $reglas['documentos.*.tipo'], $reglas['documentos.*.fecha_vencimiento']);
        $datos = $this->validarM2($request, [
            'cedula_juridica' => ['required', 'string', 'regex:'.self::CEDULA_JURIDICA],
            'correo_encargado' => ['required', 'email', 'max:150'],
        ] + $reglas);

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
