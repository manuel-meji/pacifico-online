<?php

declare(strict_types=1);

namespace App\Http\Controllers\M2_Comercios;

use App\Http\Controllers\Controller;
use App\Models\M2_Comercios\Comercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MicrositiosController extends Controller
{
    public function __construct(
        private readonly ComerciosService $comercios
    ) {}

    // --- CU-0001 a CU-0007: edición del micrositio

    public function consultar(int $idComercio): JsonResponse
    {
        $comercio = Comercio::findOrFail($idComercio);

        Gate::authorize('actualizar', $comercio);

        return response()->json(
            $this->comercios->consultar($idComercio)
        );
    }

    public function guardarLogotipo(int $idComercio, Request $request): JsonResponse
    {
        $comercio = Comercio::findOrFail($idComercio);

        Gate::authorize('actualizar', $comercio);

        $datos = $request->validate([
            'logotipo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        return response()->json(
            $this->comercios->guardarLogotipo(
                $idComercio,
                $datos['logotipo']
            )
        );
    }

    public function actualizarInformacion(int $idComercio, Request $request): JsonResponse
    {
        $comercio = Comercio::findOrFail($idComercio);

        Gate::authorize('actualizar', $comercio);

        $datos = $request->validate([
            'descripcion' => [
                'present',
                'nullable',
                'string',
            ],
        ]);

        return response()->json(
            $this->comercios->actualizarInformacion(
                $idComercio,
                $datos['descripcion']
            )
        );
    }

    public function guardarHorarios(int $idComercio, Request $request): JsonResponse
    {
        $comercio = Comercio::findOrFail($idComercio);

        Gate::authorize('actualizar', $comercio);

        $datos = $request->validate([
            'horarios' => [
                'required',
                'array',
                'size:7',
            ],
            'horarios.*.dia_semana' => [
                'required',
                'integer',
                'between:1,7',
                'distinct',
            ],
            'horarios.*.cerrado' => [
                'required',
                'boolean',
            ],
            'horarios.*.hora_apertura' => [
                'nullable',
                'date_format:H:i',
            ],
            'horarios.*.hora_cierre' => [
                'nullable',
                'date_format:H:i',
            ],
        ]);

        return response()->json(
            $this->comercios->guardarHorarios(
                $idComercio,
                $datos['horarios']
            )
        );
    }

    public function guardarContactos(int $idComercio, Request $request): JsonResponse
    {
        $comercio = Comercio::findOrFail($idComercio);

        Gate::authorize('actualizar', $comercio);

        $datos = $request->validate([
            'contactos' => [
                'required',
                'array',
            ],
            'contactos.*.tipo' => [
                'required',
                'string',
                'in:telefono,correo,direccion,facebook,instagram,whatsapp,tiktok,sitio_web',
            ],
            'contactos.*.valor' => [
                'required',
                'string',
            ],
        ]);

        return response()->json(
            $this->comercios->guardarContactos(
                $idComercio,
                $datos['contactos']
            )
        );
    }

    public function guardarPoliticas(int $idComercio, Request $request): JsonResponse
    {
        $comercio = Comercio::findOrFail($idComercio);

        Gate::authorize('actualizar', $comercio);

        $datos = $request->validate([
            'politicas' => [
                'required',
                'array',
            ],
            'politicas.*.tipo' => [
                'required',
                'string',
                'in:envio,devolucion,garantia,privacidad,otra',
            ],
            'politicas.*.contenido' => [
                'required',
                'string',
            ],
            'politicas.*.configuracion_envio' => [
                'nullable',
                'array',
            ],
            'politicas.*.configuracion_envio.*.zona' => [
                'required_with:politicas.*.configuracion_envio',
                'string',
            ],
            'politicas.*.configuracion_envio.*.costo' => [
                'required_with:politicas.*.configuracion_envio',
                'numeric',
                'min:0',
            ],
            'politicas.*.configuracion_envio.*.moneda' => [
                'required_with:politicas.*.configuracion_envio',
                'in:CRC',
            ],
        ]);

        return response()->json(
            $this->comercios->guardarPoliticas(
                $idComercio,
                $datos['politicas']
            )
        );
    }

     public function gestionarGaleria(int $idComercio, Request $request): JsonResponse
{
    $comercio = Comercio::findOrFail($idComercio);

    Gate::authorize('actualizar', $comercio);

    $datos = $request->validate([
        'galeria' => [
            'required',
            'array',
        ],
        'galeria.*.id_imagen' => [
            'nullable',
            'integer',
        ],
        'galeria.*.imagen' => [
            'required_without:galeria.*.id_imagen',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:2048',
        ],
        'galeria.*.orden' => [
            'required',
            'integer',
            'min:1',
        ],
        'galeria.*.texto_alternativo' => [
            'required',
            'string',
            'max:150',
        ],
    ]);

    return response()->json(
        $this->comercios->gestionarGaleria(
            $idComercio,
            $datos['galeria']
        )
    );
}

    public function gestionarBanners(int $idComercio, Request $request): JsonResponse
{
    $comercio = Comercio::findOrFail($idComercio);

    Gate::authorize('actualizar', $comercio);

    $datos = $request->validate([
        'banners' => [
            'required',
            'array',
        ],
        'banners.*.id_banner' => [
            'nullable',
            'integer',
        ],
        'banners.*.imagen' => [
            'required_without:banners.*.id_banner',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:2048',
        ],
        'banners.*.orden' => [
            'required',
            'integer',
            'min:1',
        ],
        'banners.*.texto_alternativo' => [
            'required',
            'string',
            'max:150',
        ],
        'banners.*.activo' => [
            'required',
            'boolean',
        ],
    ]);

    return response()->json(
        $this->comercios->gestionarBanners(
            $idComercio,
            $datos['banners']
        )
    );
}
}