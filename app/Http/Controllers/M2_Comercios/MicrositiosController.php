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
    /*
     * Medidas mínimas de las imágenes del micrositio, según cómo las muestra el portal:
     * el logotipo va en un círculo, el banner en una franja ancha de poca altura y la
     * galería en tarjetas. Solo se revisan las imágenes nuevas; las guardadas no cambian.
     */
    private const DIMENSIONES_LOGOTIPO = 'dimensions:min_width=200,min_height=200,min_ratio=3/4,max_ratio=4/3';

    private const DIMENSIONES_BANNER = 'dimensions:min_width=1200,min_ratio=2';

    private const DIMENSIONES_GALERIA = 'dimensions:min_width=600,min_height=400';

    public function __construct(
        private readonly ComerciosService $comercios
    ) {}

    /**
     * Mensajes en español para un campo de imagen y, en banners y galería,
     * para el texto alternativo de cada imagen.
     *
     * @return array<string, string>
     */
    private static function mensajesDeImagen(string $campo, string $mensajeDimensiones): array
    {
        $lista = str_ends_with($campo, '.*.imagen') ? substr($campo, 0, -strlen('.*.imagen')) : null;
        $deLista = $lista === null ? [] : [
            "{$campo}.required_without" => 'Elegí una imagen.',
            "{$lista}.*.texto_alternativo.required" => 'Escribí el texto alternativo: describí la imagen para quien no la puede ver.',
            "{$lista}.*.texto_alternativo.max" => 'El texto alternativo no puede pasar de 150 caracteres.',
        ];

        return $deLista + [
            "{$campo}.required" => 'Elegí una imagen.',
            "{$campo}.image" => 'El archivo debe ser una imagen.',
            "{$campo}.mimes" => 'La imagen debe ser JPG, PNG o WEBP.',
            "{$campo}.max" => 'La imagen no puede pesar más de 2 MB.',
            "{$campo}.dimensions" => $mensajeDimensiones,
        ];
    }

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
                self::DIMENSIONES_LOGOTIPO,
            ],
        ], self::mensajesDeImagen('logotipo', 'El logotipo debe ser cuadrado o casi cuadrado y medir al menos 200 × 200 px.'));

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
                'present',
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
                self::DIMENSIONES_GALERIA,
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
        ], self::mensajesDeImagen('galeria.*.imagen', 'Cada imagen de la galería debe medir al menos 600 × 400 px.'));

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
                'present',
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
                self::DIMENSIONES_BANNER,
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
        ], self::mensajesDeImagen('banners.*.imagen', 'El banner debe ser horizontal (el ancho al menos el doble del alto) y medir al menos 1200 px de ancho.'));

        return response()->json(
            $this->comercios->gestionarBanners(
                $idComercio,
                $datos['banners']
            )
        );
    }
}
