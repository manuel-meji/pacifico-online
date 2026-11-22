<?php

declare(strict_types=1);

namespace App\Http\Controllers\M2_Comercios;

use App\Http\Controllers\Controller;
use App\Http\Controllers\M2_Comercios\Concerns\ValidaEnEspanol;
use App\Models\M2_Comercios\Comercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MicrositiosController extends Controller
{
    use ValidaEnEspanol;

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
            "{$lista}.*.texto_alternativo.required" => 'Describí qué se ve en la imagen: lo leen los lectores de pantalla y aparece si la imagen no carga.',
            "{$lista}.*.texto_alternativo.min" => 'Describí la imagen con al menos 5 caracteres, por ejemplo «Fachada de la tienda».',
            "{$lista}.*.texto_alternativo.regex" => 'La descripción de la imagen debe tener palabras, no solo números o símbolos.',
            "{$lista}.*.texto_alternativo.max" => 'La descripción de la imagen no puede pasar de 150 caracteres.',
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

        $datos = $this->validarM2($request, [
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

    /** Hallazgo 21: el logotipo también se puede quitar; el portal muestra el ícono de tienda. */
    public function quitarLogotipo(int $idComercio): JsonResponse
    {
        $comercio = Comercio::findOrFail($idComercio);

        Gate::authorize('actualizar', $comercio);

        return response()->json(
            $this->comercios->quitarLogotipo($idComercio)
        );
    }

    public function actualizarInformacion(int $idComercio, Request $request): JsonResponse
    {
        $comercio = Comercio::findOrFail($idComercio);

        Gate::authorize('actualizar', $comercio);

        $datos = $this->validarM2($request, [
            // Opcional (vacía la quita), pero si se escribe tiene que decir algo (hallazgo 22).
            'descripcion' => [
                'present',
                'nullable',
                'string',
                'min:10',
                'max:2000',
                'regex:'.self::CON_LETRAS,
            ],
        ], [
            'descripcion.min' => 'Escribí una descripción de al menos 10 caracteres, o dejala vacía para no mostrar ninguna.',
            'descripcion.regex' => 'La descripción debe tener palabras, no solo números o símbolos.',
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

        $datos = $this->validarM2($request, [
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
        ], [
            'horarios.size' => 'Indicá el horario de los 7 días.',
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

        $datos = $this->validarM2($request, [
            // present: una lista vacía quita todos los contactos.
            'contactos' => [
                'present',
                'array',
                'max:8',
            ],
            // Uno por tipo (hallazgo 33): el portal (contrato 1.2.2) muestra un solo teléfono, correo y
            // dirección, y una red de cada tipo. El contenido se valida y se normaliza en el servicio.
            'contactos.*.tipo' => [
                'required',
                'string',
                'in:telefono,correo,direccion,facebook,instagram,whatsapp,tiktok,sitio_web',
                'distinct',
            ],
            'contactos.*.valor' => [
                'required',
                'string',
                'max:255',
            ],
        ], [
            'contactos.*.tipo.distinct' => 'Ya agregaste ese tipo de contacto: va uno de cada tipo. Para un segundo número usá WhatsApp.',
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

        $datos = $this->validarM2($request, [
            'politicas' => [
                'present',
                'array',
                'max:5',
            ],
            // Una política por tipo (hallazgo 35).
            'politicas.*.tipo' => [
                'required',
                'string',
                'in:envio,devolucion,garantia,privacidad,otra',
                'distinct',
            ],
            'politicas.*.contenido' => [
                'required',
                'string',
                'min:10',
                'max:2000',
                'regex:'.self::CON_LETRAS,
            ],
            'politicas.*.configuracion_envio' => [
                'nullable',
                'array',
                'max:2',
            ],
            // M5 elige la tarifa por estas dos zonas (seleccionarTarifaPorZona); con otro texto
            // cobraría la primera tarifa (hallazgo 34).
            'politicas.*.configuracion_envio.*.zona' => [
                'required_with:politicas.*.configuracion_envio',
                'string',
                'in:GAM,Resto del país',
                'distinct',
            ],
            'politicas.*.configuracion_envio.*.costo' => [
                'required_with:politicas.*.configuracion_envio',
                'numeric',
                'min:0',
                'max:100000',
            ],
            'politicas.*.configuracion_envio.*.moneda' => [
                'required_with:politicas.*.configuracion_envio',
                'in:CRC',
            ],
        ], [
            'politicas.*.tipo.distinct' => 'Ya agregaste una política de ese tipo: va una de cada tipo.',
            'politicas.*.contenido.min' => 'Explicá la política con al menos 10 caracteres.',
            'politicas.*.contenido.regex' => 'La política debe tener palabras, no solo números o símbolos.',
            'politicas.*.configuracion_envio.*.zona.in' => 'Elegí la zona: GAM o Resto del país.',
            'politicas.*.configuracion_envio.*.zona.distinct' => 'Ya agregaste una tarifa para esa zona.',
            'politicas.*.configuracion_envio.*.costo.max' => 'El costo de envío no puede pasar de ₡100 000.',
            'politicas.*.configuracion_envio.*.costo.min' => 'El costo de envío no puede ser negativo.',
            'politicas.*.configuracion_envio.max' => 'Podés indicar una tarifa para GAM y otra para Resto del país.',
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

        $datos = $this->validarM2($request, [
            'galeria' => [
                'present',
                'array',
                'max:12',
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
                'min:5',
                'max:150',
                'regex:'.self::CON_LETRAS,
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

        $datos = $this->validarM2($request, [
            'banners' => [
                'present',
                'array',
                'max:5',
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
                'min:5',
                'max:150',
                'regex:'.self::CON_LETRAS,
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
