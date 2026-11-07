<?php

declare(strict_types=1);

namespace App\Services\M2_Comercios;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Events\M2_Comercios\ComercioAprobado;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\ComisionComercio;
use App\Models\M2_Comercios\ComisionSubpedido;
use App\Models\M2_Comercios\ContactoComercio;
use App\Models\M2_Comercios\DocumentoComercio;
use App\Models\M2_Comercios\PublicidadComercio;
use App\Models\M2_Comercios\BannerComercio;
use App\Models\M2_Comercios\ImagenGaleriaComercio;
use Illuminate\Support\Facades\Log;
use App\Models\M2_Comercios\HorarioComercio;
use App\Models\M2_Comercios\Micrositio;
use App\Models\M2_Comercios\PoliticaComercio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\M2_Comercios\SolicitudComercio;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use App\Events\M2_Comercios\ComercioReactivado;
use App\Events\M2_Comercios\ComercioSuspendido;
use App\Models\M2_Comercios\SedeComercio;
use App\Models\M2_Comercios\SuspensionComercio;
use App\Services\Nucleo\ContextoComercio;
use Closure;

/**
 * Fachada de M2 (patrón Facade, sección 7 del PC3): implementa las 8 operaciones
 * de consulta del contrato OpenAPI v1.2.1 (contracts/m2-comercios/v1/openapi.yaml).
 *
 * Los demás módulos llaman a M2 solo por ComerciosServiceInterface, en el mismo
 * proceso; nunca usan sus modelos ni sus tablas. Por esa vía el contrato no
 * responde 401 ni 403: la autorización la aplica cada consumidor en su ruta, y
 * ConsultasComerciosController la aplica en las rutas HTTP de M2.
 *
 * Errores: ErrorNucleoException con los códigos del núcleo (ERR-004 y ERR-005),
 * que ManejadorErrores convierte en ErrorEstandar y que los adaptadores de los
 * consumidores leen en la propiedad codigo.
 */
class ComerciosService implements ComerciosServiceInterface
{
    public const MODULO = 'M2_Comercios';

    public const MAXIMO_POR_PAGINA = 50;

    public const MAXIMO_LOTE = 50;

    public function __construct(
        private readonly ServicioIdentidadInterface $identidad,
        private readonly NucleoServiceInterface $nucleo,
    ) {}

    /**
     * Servicio de M5 que usa el cálculo de la comisión (Ds-03). Se pide recién
     * cuando se usa, no en el constructor: PedidosService de M5 depende a su vez
     * de ComerciosServiceInterface, y pedirlo al construir crea un ciclo infinito.
     */
    /**
     * Las consultas públicas y entre módulos leen el micrositio de cualquier comercio
     * habilitado (datos públicos), así que corren sin el filtro de PerteneceAComercio.
     * La autorización de cada vía la aplican el controlador y los consumidores.
     */
    private function comoSistema(Closure $consulta): mixed
    {
        return app(ContextoComercio::class)->ejecutarComoSistema($consulta);
    }

    private function subpedidos(): SubpedidosServiceInterface
    {
        return app(SubpedidosServiceInterface::class);
    }

    public function listarComerciosHabilitados(int $pagina = 1, int $porPagina = 20, bool $destacado = false): array
    {
        return $this->comoSistema(function () use ($pagina, $porPagina, $destacado): array {
            $errores = [];
            if ($pagina < 1) {
                $errores['pagina'] = ['Debe ser un entero mayor o igual a 1.'];
            }
            if ($porPagina < 1 || $porPagina > self::MAXIMO_POR_PAGINA) {
                $errores['por_pagina'] = ['Debe estar entre 1 y '.self::MAXIMO_POR_PAGINA.'.'];
            }
            $this->exigirValidos($errores);

            $consulta = Comercio::query()->habilitados()->with(['micrositio', 'publicidades']);

            if ($destacado) {
                // Contrato: los destacados se ordenan por el inicio de la publicidad vigente, la más reciente primero.
                $consulta->destacados()->orderByDesc(
                    PublicidadComercio::query()
                        ->selectRaw('MAX(inicio)')
                        ->whereColumn('publicidad_comercio.id_comercio', 'comercios.id_comercio')
                        ->vigentes()
                );
            }
            $consulta->orderBy('nombre_comercial');

            $total = (clone $consulta)->count();
            $comercios = $consulta->forPage($pagina, $porPagina)->get();

            return [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $total,
                'comercios' => $comercios->map(fn (Comercio $c): array => $this->resumen($c))->values()->all(),
            ];
        });
    }

    public function obtenerResumenComerciosLote(array $idsComercio): array
    {
        return $this->comoSistema(function () use ($idsComercio): array {
            $ids = array_values($idsComercio);
            $errores = [];
            if ($ids === [] || count($ids) > self::MAXIMO_LOTE) {
                $errores['ids_comercio'] = ['Debe tener entre 1 y '.self::MAXIMO_LOTE.' identificadores.'];
            } elseif (array_filter($ids, fn ($id): bool => ! is_int($id)) !== []) {
                $errores['ids_comercio'] = ['Todos los identificadores deben ser enteros.'];
            } elseif (count(array_unique($ids)) !== count($ids)) {
                $errores['ids_comercio'] = ['Los identificadores no se pueden repetir.'];
            }
            $this->exigirValidos($errores);

            $encontrados = Comercio::query()->habilitados()
                ->with(['micrositio', 'publicidades'])
                ->whereIn('id_comercio', $ids)
                ->get()
                ->keyBy('id_comercio');

            // Se omiten los inexistentes o no habilitados y se conserva el orden de la solicitud.
            $comercios = [];
            foreach ($ids as $id) {
                if ($encontrados->has($id)) {
                    $comercios[] = $this->resumen($encontrados->get($id));
                }
            }

            return ['comercios' => $comercios];
        });
    }

    public function obtenerDatosComercioPublico(int $idComercio): array
    {
        return $this->comoSistema(function () use ($idComercio): array {
            $c = Comercio::query()->habilitados()
                ->with(['micrositio', 'banners', 'galeria', 'horarios', 'contactos', 'politicas'])
                ->find($idComercio);

            if ($c === null) {
                throw $this->noEncontrado('El comercio no existe o no está habilitado.');
            }

            $contacto = ['telefono' => null, 'correo' => null, 'direccion' => null];
            $redes = [];
            foreach ($c->contactos as $medio) {
                if (in_array($medio->tipo, ContactoComercio::TIPOS_CONTACTO, true)) {
                    $contacto[$medio->tipo] ??= $medio->valor;
                } elseif (in_array($medio->tipo, ContactoComercio::TIPOS_RED_SOCIAL, true)) {
                    $redes[] = ['plataforma' => $medio->tipo, 'url' => $medio->valor];
                }
            }

            return [
                'id_comercio' => $c->id_comercio,
                'nombre_comercial' => $c->nombre_comercial,
                'descripcion' => $c->micrositio?->descripcion,
                'logo_url' => $this->url($c->micrositio?->logotipo),
                'banners' => $c->banners->where('activo', true)->sortBy('orden')->map(fn ($b): array => [
                    'id_banner' => $b->id_banner,
                    'imagen_url' => $this->url($b->ruta),
                    'texto_alternativo' => $b->texto_alternativo,
                ])->values()->all(),
                'galeria' => $c->galeria->sortBy('orden')->map(fn ($i): array => [
                    'id_imagen' => $i->id_imagen,
                    'imagen_url' => $this->url($i->ruta),
                    'texto_alternativo' => $i->texto_alternativo,
                ])->values()->all(),
                'horarios' => $c->horarios->sortBy('dia_semana')->map(fn ($h): array => [
                    'id_horario' => $h->id_horario,
                    'dia_semana' => $h->dia_semana,
                    'hora_apertura' => $h->hora_apertura ? substr((string) $h->hora_apertura, 0, 5) : null,
                    'hora_cierre' => $h->hora_cierre ? substr((string) $h->hora_cierre, 0, 5) : null,
                    'cerrado' => $h->cerrado,
                ])->values()->all(),
                'contacto' => $contacto,
                'redes_sociales' => $redes,
                'politicas' => $c->politicas->map(fn ($p): array => [
                    'tipo' => $p->tipo,
                    'descripcion' => $p->contenido,
                ])->values()->all(),
                'estado' => $c->estado,
            ];
        });
    }

    public function obtenerComercioActivo(int $idComercio): array
    {
        $c = $this->buscar($idComercio);

        return [
            'id_comercio' => $c->id_comercio,
            'estado' => $c->estado,
            'activo' => $c->estaActivo(),
            'motivo_estado' => $c->motivoEstado(),
        ];
    }

    public function obtenerSedesComercio(int $idComercio): array
    {
        $c = $this->buscar($idComercio);

        // id_sede = id_ubicacion de M4 (confirmado por M4 el 6/11). Se devuelven todas las
        // sedes con su indicador habilitada, para que M4 distinga una sede ajena de una
        // deshabilitada.
        return [
            'id_comercio' => $c->id_comercio,
            'sedes' => $c->sedes()->orderBy('id_sede')->get()->map(fn (SedeComercio $sede): array => [
                'id_sede' => $sede->id_sede,
                'nombre' => $sede->nombre,
                'direccion' => $sede->direccion,
                'habilitada' => $sede->habilitada,
            ])->values()->all(),
        ];
    }

    public function obtenerPoliticasEnvio(int $idComercio): array
    {
        return $this->comoSistema(function () use ($idComercio): array {
            $c = $this->buscar($idComercio);
            $politicas = $c->politicas()->get();

            $tarifas = $politicas->where('tipo', 'envio')
                ->flatMap(fn ($p): array => $p->configuracion_envio ?? [])
                ->map(fn (array $t): array => [
                    'zona' => (string) ($t['zona'] ?? ''),
                    'costo' => (float) ($t['costo'] ?? 0),
                    'moneda' => (string) ($t['moneda'] ?? 'CRC'),
                ])->values()->all();

            return [
                'id_comercio' => $c->id_comercio,
                'politicas' => $politicas->map(fn ($p): array => [
                    'tipo' => $p->tipo,
                    'descripcion' => $p->contenido,
                ])->values()->all(),
                'tarifas' => $tarifas,
            ];
        });
    }

    public function obtenerDatosFiscalesComercio(int $idComercio): array
    {
        $c = $this->buscar($idComercio);
        $comision = ComisionComercio::vigentePara($c->id_comercio);

        return [
            'id_comercio' => $c->id_comercio,
            'cedula_juridica' => $c->cedula_juridica,
            'razon_social' => $c->razon_social,
            'direccion_fiscal' => $c->direccion_fiscal,
            'inscrito_como_contribuyente' => $c->inscrito_como_contribuyente,
            'comision' => $comision === null ? null : [
                'tipo' => $comision->tipo,
                'valor' => (float) $comision->valor,
                'base_calculo' => 'subtotal del subpedido',
                'moneda' => $comision->tipo === ComisionComercio::MONTO_FIJO ? $comision->moneda : null,
            ],
        ];
    }

    public function obtenerComisionSubpedido(int $idSubpedido): array
    {
        $comision = ComisionSubpedido::query()->where('id_subpedido', $idSubpedido)->first();

        if ($comision === null) {
            throw $this->noEncontrado('La comisión del subpedido aún no se ha calculado.');
        }

        return [
            'id_subpedido' => $comision->id_subpedido,
            'id_comercio' => $comision->id_comercio,
            'monto_comision' => (float) $comision->monto,
            'base_utilizada' => (float) $comision->subtotal_base,
            'moneda' => $comision->moneda,
        ];
    }

    // ------------------------------------------------------------------
    // Métodos heredados del borrador del Comité. Se eliminan cuando la
    // interfaz pase a las 8 operaciones del contrato (segundo PR de M2).
    // ------------------------------------------------------------------

    /**
     * @deprecated Usar obtenerDatosComercioPublico (micrositio) u obtenerComercioActivo (estado).
     */
    public function obtenerMicrositio(int $comercioId): array
    {
        return $this->comoSistema(function () use ($comercioId): array {
            $c = $this->buscar($comercioId);

            return [
                'id_comercio' => $c->id_comercio,
                'nombre_comercial' => $c->nombre_comercial,
                'estado' => $c->estado,
                'activo' => $c->estaActivo(),
                'logo_url' => $this->url($c->micrositio?->logotipo),
            ];
        });
    }

    /**
     * @deprecated La comisión no se registra por llamada síncrona: M2 la calcula al
     *             consumir el evento SubpedidoDespachado de M5 (contrato v1.2.1, RN-010)
     *             y M7 la consulta con obtenerComisionSubpedido. No registra nada.
     */
    public function registrarComision(int $pedidoId, float $subtotalPedido): bool
    {
        Log::warning('M2: registrarComision está en desuso; la comisión se registra con el evento SubpedidoDespachado.', [
            'id_pedido' => $pedidoId,
        ]);

        return false;
    }

    // --- CU-0001 a CU-0007: edición del micrositio
public function consultar(int $idComercio): array
{
    $comercio = Comercio::query()
        ->with([
            'micrositio',
            'banners',
            'galeria',
            'horarios',
            'contactos',
            'politicas',
        ])
        ->find($idComercio);

    if ($comercio === null) {
        throw $this->noEncontrado('El comercio no existe.');
    }

    return [
        'id_comercio' => $comercio->id_comercio,
        'nombre_comercial' => $comercio->nombre_comercial,
        'descripcion' => $comercio->micrositio?->descripcion,
        'logotipo' => $comercio->micrositio?->logotipo,

        'banners' => $comercio->banners
            ->sortBy('orden')
            ->values()
            ->map(fn ($banner): array => [
                'id_banner' => $banner->id_banner,
                'ruta' => $banner->ruta,
                'texto_alternativo' => $banner->texto_alternativo,
                'orden' => $banner->orden,
                'activo' => $banner->activo,
            ])
            ->all(),

        'galeria' => $comercio->galeria
            ->sortBy('orden')
            ->values()
            ->map(fn ($imagen): array => [
                'id_imagen' => $imagen->id_imagen,
                'ruta' => $imagen->ruta,
                'texto_alternativo' => $imagen->texto_alternativo,
                'orden' => $imagen->orden,
            ])
            ->all(),

        'horarios' => $comercio->horarios
            ->sortBy('dia_semana')
            ->values()
            ->map(fn ($horario): array => [
                'id_horario' => $horario->id_horario,
                'dia_semana' => $horario->dia_semana,
                'hora_apertura' => $horario->hora_apertura
                    ? substr((string) $horario->hora_apertura, 0, 5)
                    : null,
                'hora_cierre' => $horario->hora_cierre
                    ? substr((string) $horario->hora_cierre, 0, 5)
                    : null,
                'cerrado' => $horario->cerrado,
            ])
            ->all(),

        'contactos' => $comercio->contactos
            ->map(fn ($contacto): array => [
                'id_contacto' => $contacto->id_contacto,
                'tipo' => $contacto->tipo,
                'valor' => $contacto->valor,
            ])
            ->values()
            ->all(),

        'politicas' => $comercio->politicas
            ->map(fn ($politica): array => [
                'id_politica' => $politica->id_politica,
                'tipo' => $politica->tipo,
                'contenido' => $politica->contenido,
                'configuracion_envio' => $politica->configuracion_envio,
            ])
            ->values()
            ->all(),
    ];
}

public function actualizarInformacion(
    int $idComercio,
    ?string $descripcion
): Micrositio {
    $this->buscar($idComercio);

    $micrositio = Micrositio::query()->firstOrCreate([
        'id_comercio' => $idComercio,
    ]);

    $micrositio->descripcion = $descripcion;
    $micrositio->save();

    return $micrositio;
}

public function guardarLogotipo(
    int $idComercio,
    UploadedFile $archivo
): Micrositio {
    $this->buscar($idComercio);

    $micrositio = Micrositio::query()->firstOrCreate([
        'id_comercio' => $idComercio,
    ]);

    $ruta = Storage::disk('public')->putFile(
        "m2/comercios/{$idComercio}/logotipo",
        $archivo
    );

    $micrositio->cambiarLogotipo(
        Storage::disk('public')->url($ruta)
    );

    return $micrositio;
}

public function guardarHorarios(int $idComercio, array $horarios): array
{
    $this->buscar($idComercio);

    $errores = [];
    $dias = [];

    if (count($horarios) !== 7) {
        $errores['horarios'] = [
            'Se deben indicar los horarios de los 7 días.',
        ];
    }

    foreach ($horarios as $indice => $horario) {
        $dia = (int) ($horario['dia_semana'] ?? 0);
        $cerrado = (bool) ($horario['cerrado'] ?? false);

        if ($dia < 1 || $dia > 7) {
            $errores["horarios.{$indice}.dia_semana"] = [
                'El día de la semana debe estar entre 1 y 7.',
            ];
        }

        if (in_array($dia, $dias, true)) {
            $errores["horarios.{$indice}.dia_semana"] = [
                'No se pueden repetir días de la semana.',
            ];
        }

        $dias[] = $dia;

        if (! $cerrado) {
            $apertura = $horario['hora_apertura'] ?? null;
            $cierre = $horario['hora_cierre'] ?? null;

            if ($apertura === null || $cierre === null) {
                $errores["horarios.{$indice}"] = [
                    'Un día abierto debe indicar hora de apertura y cierre.',
                ];
            } elseif ($apertura >= $cierre) {
                $errores["horarios.{$indice}.hora_apertura"] = [
                    'La hora de apertura debe ser anterior a la hora de cierre.',
                ];
            }
        }
    }

    $this->exigirValidos($errores);

    return DB::transaction(function () use ($idComercio, $horarios): array {
        HorarioComercio::query()
            ->where('id_comercio', $idComercio)
            ->delete();

        $resultado = [];

        foreach ($horarios as $datos) {
            if ((bool) $datos['cerrado']) {
                $datos['hora_apertura'] = null;
                $datos['hora_cierre'] = null;
            }

            $horario = new HorarioComercio();

            $horario->actualizar([
                ...$datos,
                'id_comercio' => $idComercio,
            ]);

            $resultado[] = $horario->toArray();
        }

        return $resultado;
    });
}

public function guardarContactos(int $idComercio, array $contactos): array
{
    $this->buscar($idComercio);

    return DB::transaction(function () use ($idComercio, $contactos): array {
        ContactoComercio::query()
            ->where('id_comercio', $idComercio)
            ->delete();

        $resultado = [];

        foreach ($contactos as $datos) {
            $contacto = new ContactoComercio();

            $contacto->actualizar([
                ...$datos,
                'id_comercio' => $idComercio,
            ]);

            $resultado[] = $contacto->toArray();
        }

        return $resultado;
    });
}

public function guardarPoliticas(int $idComercio, array $politicas): array
{
    $this->buscar($idComercio);

    return DB::transaction(function () use ($idComercio, $politicas): array {
        PoliticaComercio::query()
            ->where('id_comercio', $idComercio)
            ->delete();

        $resultado = [];

        foreach ($politicas as $datos) {
            if (($datos['tipo'] ?? null) !== 'envio') {
                $datos['configuracion_envio'] = null;
            }

            $politica = new PoliticaComercio();

            $politica->actualizar([
                ...$datos,
                'id_comercio' => $idComercio,
            ]);

            $resultado[] = $politica->toArray();
        }

        return $resultado;
    });
}

public function gestionarBanners(int $idComercio, array $banners): array
{
    $this->buscar($idComercio);

    $existentes = BannerComercio::query()
        ->where('id_comercio', $idComercio)
        ->get()
        ->keyBy('id_banner');

    $rutasEliminar = [];

    $resultado = DB::transaction(function () use (
        $idComercio,
        $banners,
        $existentes,
        &$rutasEliminar
    ): array {
        $idsConservados = [];
        $resultado = [];

        foreach ($banners as $datos) {
            $idBanner = $datos['id_banner'] ?? null;

            if ($idBanner !== null) {
                $banner = $existentes->get($idBanner);

                if ($banner === null) {
                    throw $this->noEncontrado(
                        'El banner no existe o no pertenece al comercio.'
                    );
                }

                $idsConservados[] = (int) $idBanner;
                $ruta = $banner->ruta;
            } else {
                $banner = new BannerComercio();
                $ruta = null;
            }

            if (
                isset($datos['imagen'])
                && $datos['imagen'] instanceof UploadedFile
            ) {
                $rutaNueva = Storage::disk('public')->putFile(
                    "m2/comercios/{$idComercio}/banners",
                    $datos['imagen']
                );

                if ($ruta !== null) {
                    $rutasEliminar[] = $ruta;
                }

                $ruta = Storage::disk('public')->url($rutaNueva);
            }

            if ($ruta === null) {
                throw ErrorNucleoException::paraModulo(
                    self::MODULO,
                    'ERR-005',
                    422,
                    'El banner nuevo debe incluir una imagen.',
                    [
                        'imagen' => [
                            'La imagen es obligatoria para un banner nuevo.',
                        ],
                    ]
                );
            }

            $banner->actualizar([
                'id_comercio' => $idComercio,
                'ruta' => $ruta,
                'texto_alternativo' => $datos['texto_alternativo'],
                'orden' => $datos['orden'],
                'activo' => $datos['activo'],
            ]);

            $resultado[] = $banner->toArray();
        }

        foreach ($existentes as $idBanner => $bannerExistente) {
            if (! in_array((int) $idBanner, $idsConservados, true)) {
                $rutasEliminar[] = $bannerExistente->ruta;
                $bannerExistente->delete();
            }
        }

        return $resultado;
    });

    foreach (array_unique($rutasEliminar) as $ruta) {
        $this->eliminarArchivoPublico($ruta);
    }

    return $resultado;
}

public function gestionarGaleria(int $idComercio, array $galeria): array
{
    $this->buscar($idComercio);

    $existentes = ImagenGaleriaComercio::query()
        ->where('id_comercio', $idComercio)
        ->get()
        ->keyBy('id_imagen');

    $rutasEliminar = [];

    $resultado = DB::transaction(function () use (
        $idComercio,
        $galeria,
        $existentes,
        &$rutasEliminar
    ): array {
        $idsConservados = [];
        $resultado = [];

        foreach ($galeria as $datos) {
            $idImagen = $datos['id_imagen'] ?? null;

            if ($idImagen !== null) {
                $imagen = $existentes->get($idImagen);

                if ($imagen === null) {
                    throw $this->noEncontrado(
                        'La imagen no existe o no pertenece al comercio.'
                    );
                }

                $idsConservados[] = (int) $idImagen;
                $ruta = $imagen->ruta;
            } else {
                $imagen = new ImagenGaleriaComercio();
                $ruta = null;
            }

            if (
                isset($datos['imagen'])
                && $datos['imagen'] instanceof UploadedFile
            ) {
                $rutaNueva = Storage::disk('public')->putFile(
                    "m2/comercios/{$idComercio}/galeria",
                    $datos['imagen']
                );

                if ($ruta !== null) {
                    $rutasEliminar[] = $ruta;
                }

                $ruta = Storage::disk('public')->url($rutaNueva);
            }

            if ($ruta === null) {
                throw ErrorNucleoException::paraModulo(
                    self::MODULO,
                    'ERR-005',
                    422,
                    'La imagen nueva de la galería debe incluir un archivo.',
                    [
                        'imagen' => [
                            'La imagen es obligatoria para un elemento nuevo.',
                        ],
                    ]
                );
            }

            $imagen->actualizar([
                'id_comercio' => $idComercio,
                'ruta' => $ruta,
                'texto_alternativo' => $datos['texto_alternativo'],
                'orden' => $datos['orden'],
            ]);

            $resultado[] = $imagen->toArray();
        }

        foreach ($existentes as $idImagen => $imagenExistente) {
            if (! in_array((int) $idImagen, $idsConservados, true)) {
                $rutasEliminar[] = $imagenExistente->ruta;
                $imagenExistente->delete();
            }
        }

        return $resultado;
    });

    foreach (array_unique($rutasEliminar) as $ruta) {
        $this->eliminarArchivoPublico($ruta);
    }

    return $resultado;
}
    private function buscar(int $idComercio): Comercio
    {
        return Comercio::query()->find($idComercio)
            ?? throw $this->noEncontrado('El comercio no existe.');
    }

    // --- CU-0014: revisar solicitudes (Jorge) ---

    /** @return array{pagina: int, por_pagina: int, total: int, solicitudes: list<array<string, mixed>>} */
    public function listarSolicitudes(?string $estado, int $pagina, int $porPagina): array
    {
        $errores = [];
        if ($estado !== null && ! in_array($estado, [SolicitudComercio::PENDIENTE, SolicitudComercio::APROBADA, SolicitudComercio::RECHAZADA], true)) {
            $errores['estado'] = ['El estado de la solicitud no es válido.'];
        }
        if ($pagina < 1) {
            $errores['pagina'] = ['Debe ser un entero mayor o igual a 1.'];
        }
        if ($porPagina < 1 || $porPagina > self::MAXIMO_POR_PAGINA) {
            $errores['por_pagina'] = ['Debe estar entre 1 y '.self::MAXIMO_POR_PAGINA.'.'];
        }
        $this->exigirValidos($errores);

        $consulta = SolicitudComercio::query()->with(['comercio', 'documentos']);
        if ($estado !== null) {
            $consulta->where('estado', $estado);
        }

        $total = (clone $consulta)->count();
        $solicitudes = $consulta->orderByDesc('id_solicitud')->forPage($pagina, $porPagina)->get();

        return [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => $total,
            'solicitudes' => $solicitudes->map(fn (SolicitudComercio $solicitud): array => $this->datosSolicitud($solicitud))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function consultarSolicitud(int $idSolicitud): array
    {
        $solicitud = SolicitudComercio::query()->with(['comercio', 'documentos'])->find($idSolicitud)
            ?? throw $this->noEncontrado('La solicitud no existe.');

        return $this->datosSolicitud($solicitud);
    }

    /** @return array<string, mixed> */
    private function datosSolicitud(SolicitudComercio $solicitud): array
    {
        return $solicitud->only([
            'id_solicitud', 'id_comercio', 'nombre_encargado', 'correo_encargado',
            'telefono_encargado', 'estado', 'requiere_documentos', 'motivo_rechazo',
            'fecha_reenvio', 'created_at', 'updated_at',
        ]) + [
            'comercio' => $solicitud->comercio->only([
                'id_comercio', 'cedula_juridica', 'nombre_comercial', 'razon_social',
                'direccion_fiscal', 'inscrito_como_contribuyente', 'estado', 'created_at', 'updated_at',
            ]),
            'documentos' => $solicitud->documentos->map(fn (DocumentoComercio $documento): array => $documento->only([
                'id_documento_comercio', 'id_comercio', 'tipo', 'fecha_vencimiento',
                'resultado', 'observacion', 'created_at', 'updated_at',
            ]))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function resumen(Comercio $c): array
    {
        return [
            'id_comercio' => $c->id_comercio,
            'nombre_comercial' => $c->nombre_comercial,
            'logo_url' => $this->url($c->micrositio?->logotipo),
            'estado' => $c->estado,
            'destacado' => $c->publicidades->contains(fn (PublicidadComercio $p): bool => $p->estaVigente()),
        ];
    }

    private function url(?string $ruta): ?string
    {
        if ($ruta === null || $ruta === '') {
            return null;
        }

        return str_starts_with($ruta, 'http') ? $ruta : asset($ruta);
    }

    // --- CU-0022: verificar documentos comerciales (Jorge) ---

    public function evaluarDocumento(int $idSolicitud, int $idDocumento, string $resultado, ?string $observacion): DocumentoComercio
    {
        $this->exigirValidos(in_array($resultado, ['valido', 'invalido'], true)
            ? []
            : ['resultado' => ['El resultado debe ser valido o invalido.']]);

        $solicitud = SolicitudComercio::query()->find($idSolicitud)
            ?? throw $this->noEncontrado('La solicitud no existe.');
        $documento = $solicitud->documentos()->find($idDocumento)
            ?? throw $this->noEncontrado('El documento no existe en el comercio de la solicitud.');

        $documento->evaluar($resultado, $observacion);

        return $documento;
    }

    // --- CU-0016: rechazar solicitud comercial (Jorge) ---

    public function rechazarSolicitud(int $idSolicitud, string $motivo): SolicitudComercio
    {
        return DB::transaction(function () use ($idSolicitud, $motivo): SolicitudComercio {
            $motivo = trim($motivo);
            if ($motivo === '') {
                $this->exigirValidos(['motivo' => ['El motivo de rechazo es obligatorio.']]);
            }

            $solicitud = SolicitudComercio::query()->lockForUpdate()->find($idSolicitud)
                ?? throw $this->noEncontrado('La solicitud no existe.');

            if (! $solicitud->estaPendiente()) {
                $this->exigirValidos(['estado' => ['La solicitud ya fue revisada.']]);
            }

            $comercio = $solicitud->comercio()->lockForUpdate()->first()
                ?? throw $this->noEncontrado('El comercio no existe.');

            $solicitud->rechazar($motivo);
            $comercio->estado = Comercio::RECHAZADO;
            $comercio->save();

            $this->nucleo->registrarAuditoria('rechazar_solicitud_comercio', 'solicitud_comercio', $solicitud->id_solicitud, [
                'modulo' => 'M2',
                'id_comercio' => $comercio->id_comercio,
                'motivo' => $motivo,
            ]);

            return $solicitud;
        });
    }

    // --- CU-0015: aprobar solicitud comercial (Jorge) ---

    public function aprobarSolicitud(int $idSolicitud): Comercio
    {
        return DB::transaction(function () use ($idSolicitud): Comercio {
            $solicitud = SolicitudComercio::query()->lockForUpdate()->find($idSolicitud)
                ?? throw $this->noEncontrado('La solicitud no existe.');

            if (! $solicitud->estaPendiente()) {
                $this->exigirValidos(['estado' => ['La solicitud ya fue revisada.']]);
            }

            $comercio = $solicitud->comercio()->lockForUpdate()->first()
                ?? throw $this->noEncontrado('El comercio no existe.');

            $errores = [];
            if (! $comercio->inscrito_como_contribuyente) {
                $errores['inscrito_como_contribuyente'] = ['El comercio debe estar inscrito como contribuyente.'];
            }
            foreach (['cedula_juridica', 'razon_social', 'direccion_fiscal'] as $campo) {
                if (trim((string) $comercio->{$campo}) === '') {
                    $errores[$campo] = ['La información empresarial está incompleta.'];
                }
            }

            if ($solicitud->requiere_documentos) {
                $documentos = $solicitud->documentos()->lockForUpdate()->get();
                $fecha = now();
                if ($documentos->isEmpty() || $documentos->contains(fn (DocumentoComercio $documento): bool => ! $documento->esValido($fecha))) {
                    $errores['documentos'] = ['Los documentos requeridos deben existir, estar validados y vigentes.'];
                }
            }
            $this->exigirValidos($errores);

            $solicitud->aprobar();
            $comercio->activar();

            $this->identidad->crearCuenta([
                'nombre' => $solicitud->nombre_encargado,
                'correo' => $solicitud->correo_encargado,
            ], RolUsuario::EncargadoComercio, $comercio->id_comercio);

            $this->nucleo->registrarAuditoria('aprobar_solicitud_comercio', 'solicitud_comercio', $solicitud->id_solicitud, [
                'modulo' => 'M2', 'id_comercio' => $comercio->id_comercio,
            ]);

            ComercioAprobado::dispatch((string) Str::uuid(), now(), $comercio->id_comercio, $comercio->estado, $comercio->nombre_comercial);

            return $comercio;
        });
    }

    // --- CU-0012 y CU-0013: solicitud de incorporación
public function registrarSolicitud(array $datos, array $documentos): SolicitudComercio
{
    return DB::transaction(function () use ($datos, $documentos): SolicitudComercio {
        if (Comercio::query()
            ->where('cedula_juridica', $datos['cedula_juridica'])
            ->exists()) {
            $this->exigirValidos([
                'cedula_juridica' => ['Ya existe un comercio con esta cédula jurídica.'],
            ]);
        }

        $comercio = Comercio::create([
            'nombre_comercial' => $datos['nombre_comercial'],
            'cedula_juridica' => $datos['cedula_juridica'],
            'razon_social' => $datos['razon_social'],
            'direccion_fiscal' => $datos['direccion_fiscal'],
            'inscrito_como_contribuyente' => $datos['inscrito_como_contribuyente'],
            'estado' => Comercio::PENDIENTE,
        ]);

        $solicitud = SolicitudComercio::create([
            'id_comercio' => $comercio->id_comercio,
            'nombre_encargado' => $datos['nombre_encargado'],
            'correo_encargado' => $datos['correo_encargado'],
            'telefono_encargado' => $datos['telefono_encargado'] ?? null,
            'estado' => SolicitudComercio::PENDIENTE,
            'requiere_documentos' => $documentos !== [],
            'motivo_rechazo' => null,
            'fecha_reenvio' => null,
        ]);

        foreach ($documentos as $documento) {
            /** @var UploadedFile $archivo */
            $archivo = $documento['archivo'];

            $cifrado = Crypt::encryptString($archivo->getContent());

            $ruta = 'm2/documentos/'
                .$comercio->id_comercio.'/'
                .Str::uuid().'.enc';

            Storage::disk('local')->put($ruta, $cifrado);

            DocumentoComercio::create([
                'id_comercio' => $comercio->id_comercio,
                'tipo' => $documento['tipo'],
                'ruta_cifrada' => $ruta,
                'fecha_vencimiento' => $documento['fecha_vencimiento'],
                'resultado' => 'pendiente',
                'observacion' => null,
            ]);
        }

        return $solicitud;
    });
}

public function corregirSolicitud(int $idSolicitud, array $datos): SolicitudComercio
{
    return DB::transaction(function () use ($idSolicitud, $datos): SolicitudComercio {
        $solicitud = SolicitudComercio::query()
            ->lockForUpdate()
            ->find($idSolicitud)
            ?? throw $this->noEncontrado('La solicitud no existe.');

        $comercio = $solicitud->comercio()
            ->lockForUpdate()
            ->first()
            ?? throw $this->noEncontrado('La solicitud no existe.');

        if (
            $comercio->cedula_juridica !== $datos['cedula_juridica']
            || mb_strtolower($solicitud->correo_encargado)
                !== mb_strtolower($datos['correo_encargado'])
        ) {
            throw $this->noEncontrado('La solicitud no existe.');
        }

        if ($solicitud->estado !== SolicitudComercio::RECHAZADA) {
            $this->exigirValidos([
                'estado' => ['Solo se puede corregir una solicitud rechazada.'],
            ]);
        }

        foreach (
            [
                'nombre_comercial',
                'razon_social',
                'direccion_fiscal',
                'inscrito_como_contribuyente',
            ] as $campo
        ) {
            if (array_key_exists($campo, $datos)) {
                $comercio->{$campo} = $datos[$campo];
            }
        }

        $comercio->estado = Comercio::PENDIENTE;
        $comercio->save();

        if (array_key_exists('nombre_encargado', $datos)) {
            $solicitud->nombre_encargado = $datos['nombre_encargado'];
        }

        if (array_key_exists('telefono_encargado', $datos)) {
            $solicitud->telefono_encargado = $datos['telefono_encargado'];
        }

        $solicitud->estado = SolicitudComercio::PENDIENTE;
        $solicitud->motivo_rechazo = null;
        $solicitud->fecha_reenvio = now();
        $solicitud->save();

        return $solicitud;
    });
}
    // --- CU-0017 y CU-0018: suspender y reactivar comercios (Diego) ---

    /** CU-0017 (diseño, Ds-02). RN-004: solo un comercio Activo. RN-005: motivo obligatorio. */
    public function suspenderComercio(int $idComercio, string $motivo): SuspensionComercio
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            $this->exigirValidos(['motivo' => ['El motivo de la suspensión es obligatorio.']]);
        }
        $idUsuario = auth()->id() ?? throw ErrorNucleoException::sesionInvalida();

        return DB::transaction(function () use ($idComercio, $motivo, $idUsuario): SuspensionComercio {
            $comercio = Comercio::query()->lockForUpdate()->find($idComercio)
                ?? throw $this->noEncontrado('El comercio no existe.');

            if (! $comercio->estaActivo()) {
                $this->exigirValidos(['estado' => ['Solo se puede suspender un comercio Activo.']]);
            }

            $comercio->suspender();
            $suspension = $comercio->suspensiones()->create([
                'id_usuario' => $idUsuario,
                'motivo' => $motivo,
                'fecha_suspension' => now(),
            ]);

            $this->nucleo->registrarAuditoria('suspender_comercio', 'comercio', $comercio->id_comercio, [
                'modulo' => 'M2', 'motivo' => $motivo,
            ]);

            ComercioSuspendido::dispatch((string) Str::uuid(), now(), $comercio->id_comercio, Comercio::SUSPENDIDO, $motivo);

            return $suspension;
        });
    }

    /** CU-0018. Solo un comercio Suspendido; cierra la suspensión abierta. RN-017: comisión y membresía se mantienen. */
    public function reactivarComercio(int $idComercio): Comercio
    {
        return DB::transaction(function () use ($idComercio): Comercio {
            $comercio = Comercio::query()->lockForUpdate()->find($idComercio)
                ?? throw $this->noEncontrado('El comercio no existe.');

            if (! $comercio->estaSuspendido()) {
                $this->exigirValidos(['estado' => ['Solo se puede reactivar un comercio Suspendido.']]);
            }

            $comercio->suspensiones()->whereNull('fecha_reactivacion')->get()
                ->each(fn (SuspensionComercio $suspension) => $suspension->cerrar());
            $comercio->activar();

            $this->nucleo->registrarAuditoria('reactivar_comercio', 'comercio', $comercio->id_comercio, ['modulo' => 'M2']);

            ComercioReactivado::dispatch((string) Str::uuid(), now(), $comercio->id_comercio, Comercio::ACTIVO, $comercio->nombre_comercial);

            return $comercio;
        });
    }

    /** Panel de la plataforma: todos los comercios, en cualquier estado. */
    public function listarComercios(?string $estado, int $pagina, int $porPagina): array
    {
        $errores = [];
        if ($estado !== null && ! in_array($estado, Comercio::ESTADOS, true)) {
            $errores['estado'] = ['El estado no es válido.'];
        }
        if ($pagina < 1) {
            $errores['pagina'] = ['Debe ser un entero mayor o igual a 1.'];
        }
        if ($porPagina < 1 || $porPagina > self::MAXIMO_POR_PAGINA) {
            $errores['por_pagina'] = ['Debe estar entre 1 y '.self::MAXIMO_POR_PAGINA.'.'];
        }
        $this->exigirValidos($errores);

        $consulta = Comercio::query()->when($estado !== null, fn ($q) => $q->where('estado', $estado));
        $total = (clone $consulta)->count();
        $comercios = $consulta->orderBy('nombre_comercial')->forPage($pagina, $porPagina)->get();

        return [
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => $total,
            'comercios' => $comercios->map(fn (Comercio $c): array => $this->fichaComercio($c))->values()->all(),
        ];
    }

    /** Ficha administrativa de un comercio (diseño: ComerciosController::consultar). */
    public function consultarComercio(int $idComercio): array
    {
        return $this->fichaComercio($this->buscar($idComercio));
    }

    /** @return array<string, mixed> */
    private function fichaComercio(Comercio $c): array
    {
        return [
            'id_comercio' => $c->id_comercio,
            'nombre_comercial' => $c->nombre_comercial,
            'cedula_juridica' => $c->cedula_juridica,
            'razon_social' => $c->razon_social,
            'estado' => $c->estado,
            'motivo_estado' => $c->motivoEstado(),
        ];
    }

    private function noEncontrado(string $mensaje): ErrorNucleoException
    {
        return ErrorNucleoException::paraModulo(self::MODULO, 'ERR-004', 404, $mensaje);
    }

    /** @param  array<string, list<string>>  $errores */
    private function exigirValidos(array $errores): void
    {
        if ($errores !== []) {
            throw ErrorNucleoException::paraModulo(
                self::MODULO,
                'ERR-005',
                422,
                'Revisa los datos marcados (detalle por campo en detalles).',
                $errores,
            );
        }
    }

    private function eliminarArchivoPublico(?string $ruta): void
{
    if ($ruta === null || $ruta === '') {
        return;
    }

    $path = parse_url($ruta, PHP_URL_PATH);

    if (! is_string($path) || $path === '') {
        return;
    }

    $path = ltrim($path, '/');

    if (str_starts_with($path, 'storage/')) {
        $path = substr($path, strlen('storage/'));
    }

    Storage::disk('public')->delete($path);
}
}
