<?php

declare(strict_types=1);

namespace App\Services\M2_Comercios;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Contracts\Nucleo\ServicioIdentidadInterface;
use App\Enums\Nucleo\RolUsuario;
use App\Events\M2_Comercios\ComercioAprobado;
use App\Events\M2_Comercios\ComercioReactivado;
use App\Events\M2_Comercios\ComercioSuspendido;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M2_Comercios\BannerComercio;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\ComisionComercio;
use App\Models\M2_Comercios\ComisionSubpedido;
use App\Models\M2_Comercios\ContactoComercio;
use App\Models\M2_Comercios\DocumentoComercio;
use App\Models\M2_Comercios\HorarioComercio;
use App\Models\M2_Comercios\ImagenGaleriaComercio;
use App\Models\M2_Comercios\MembresiaComercio;
use App\Models\M2_Comercios\Micrositio;
use App\Models\M2_Comercios\PoliticaComercio;
use App\Models\M2_Comercios\PublicidadComercio;
use App\Models\M2_Comercios\SedeComercio;
use App\Models\M2_Comercios\SolicitudComercio;
use App\Models\M2_Comercios\SuspensionComercio;
use App\Services\M2_Comercios\Comision\CalculoMontoFijo;
use App\Services\M2_Comercios\Comision\CalculoPorcentaje;
use App\Services\Nucleo\ContextoComercio;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fachada de M2 (patrón Facade, sección 7 del PC3): implementa las 8 operaciones
 * de consulta del contrato OpenAPI v1.2.2 (contracts/m2-comercios/v1/openapi.yaml).
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

    private const CORREO_EN_USO = 'Ya existe una cuenta con este correo. Usá otro correo para la persona encargada.';

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

        $anterior = $micrositio->logotipo;
        $micrositio->cambiarLogotipo(
            $this->guardarImagen("m2/comercios/{$idComercio}/logotipo", $archivo)
        );
        $this->eliminarArchivoPublico($anterior);

        return $micrositio;
    }

    /** Hallazgo 21: quita el logotipo; el archivo se borra después de guardar el cambio. */
    public function quitarLogotipo(int $idComercio): Micrositio
    {
        $this->buscar($idComercio);

        $micrositio = Micrositio::query()->firstOrCreate([
            'id_comercio' => $idComercio,
        ]);

        $anterior = $micrositio->logotipo;
        $micrositio->cambiarLogotipo(null);
        $this->eliminarArchivoPublico($anterior);

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

                // Hallazgo 31: el error va en el campo que falta, para que la pantalla lo marque.
                if ($apertura === null || $cierre === null) {
                    $mensaje = ['Indicá la hora de apertura y de cierre, o marcá el día como cerrado.'];
                    if ($apertura === null) {
                        $errores["horarios.{$indice}.hora_apertura"] = $mensaje;
                    }
                    if ($cierre === null) {
                        $errores["horarios.{$indice}.hora_cierre"] = $mensaje;
                    }
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

                $horario = new HorarioComercio;

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

        // Hallazgo 32: cada valor se valida según su tipo y se guarda normalizado (un teléfono como
        // 8888-8888, una red como enlace https de esa red), así el portal nunca muestra un enlace roto.
        $errores = [];
        foreach ($contactos as $indice => $contacto) {
            [$valor, $error] = ContactoComercio::normalizar((string) $contacto['tipo'], (string) $contacto['valor']);
            if ($error !== null) {
                $errores["contactos.{$indice}.valor"] = [$error];
            } else {
                $contactos[$indice]['valor'] = $valor;
            }
        }
        $this->exigirValidos($errores);

        return DB::transaction(function () use ($idComercio, $contactos): array {
            ContactoComercio::query()
                ->where('id_comercio', $idComercio)
                ->delete();

            $resultado = [];

            foreach ($contactos as $datos) {
                $contacto = new ContactoComercio;

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

                $politica = new PoliticaComercio;

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
                    $banner = new BannerComercio;
                    $ruta = null;
                }

                if (
                    isset($datos['imagen'])
                    && $datos['imagen'] instanceof UploadedFile
                ) {
                    $urlNueva = $this->guardarImagen(
                        "m2/comercios/{$idComercio}/banners",
                        $datos['imagen']
                    );

                    if ($ruta !== null) {
                        $rutasEliminar[] = $ruta;
                    }

                    $ruta = $urlNueva;
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
                    $imagen = new ImagenGaleriaComercio;
                    $ruta = null;
                }

                if (
                    isset($datos['imagen'])
                    && $datos['imagen'] instanceof UploadedFile
                ) {
                    $urlNueva = $this->guardarImagen(
                        "m2/comercios/{$idComercio}/galeria",
                        $datos['imagen']
                    );

                    if ($ruta !== null) {
                        $rutasEliminar[] = $ruta;
                    }

                    $ruta = $urlNueva;
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

    /**
     * Estado de la revisión para la persona encargada que creó su cuenta con la solicitud: el
     * panel de M2 le muestra solo esto mientras el comercio no esté aprobado.
     *
     * @return array<string, mixed>
     */
    public function estadoSolicitudDelComercio(int $idComercio): array
    {
        $comercio = Comercio::query()->find($idComercio)
            ?? throw $this->noEncontrado('El comercio no existe.');
        $solicitud = SolicitudComercio::query()->where('id_comercio', $idComercio)
            ->orderByDesc('id_solicitud')->first();

        // Lleva los datos que el encargado ve ya llenos al corregir su solicitud (DEF-34) y el
        // resultado de cada documento, para que sepa cuál debe reemplazar. Solo es de su comercio.
        return [
            'id_comercio' => $comercio->id_comercio,
            'nombre_comercial' => $comercio->nombre_comercial,
            'estado_comercio' => $comercio->estado,
            'comercio' => $comercio->only([
                'cedula_juridica', 'nombre_comercial', 'razon_social', 'direccion_fiscal', 'inscrito_como_contribuyente',
            ]),
            'solicitud' => $solicitud?->only([
                'id_solicitud', 'estado', 'motivo_rechazo', 'created_at', 'fecha_reenvio', 'fecha_revision',
                'nombre_encargado', 'correo_encargado', 'telefono_encargado',
            ]),
            'documentos' => $solicitud === null ? [] : $solicitud->documentos()->orderBy('id_documento_comercio')->get()
                ->map(fn (DocumentoComercio $documento): array => $documento->only([
                    'id_documento_comercio', 'tipo', 'fecha_vencimiento', 'resultado', 'observacion',
                ]) + ['debe_reemplazarse' => $this->debeReemplazarse($documento)])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function datosSolicitud(SolicitudComercio $solicitud): array
    {
        return $solicitud->only([
            'id_solicitud', 'id_comercio', 'nombre_encargado', 'correo_encargado',
            'telefono_encargado', 'estado', 'requiere_documentos', 'motivo_rechazo',
            'fecha_reenvio', 'cuenta_creada', 'fecha_revision', 'nombre_revisor', 'ultimo_motivo_rechazo',
            'nombre_registrador', 'created_at', 'updated_at',
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

    /**
     * CU-0022: contenido original de un documento de la solicitud, para que el
     * Encargado de la plataforma lo revise. Los archivos se guardan cifrados (RN-002)
     * y sin su tipo, así que el tipo se detecta del contenido descifrado.
     *
     * @return array{contenido: string, tipo: string}
     */
    public function obtenerArchivoDocumento(int $idSolicitud, int $idDocumento): array
    {
        $solicitud = SolicitudComercio::query()->find($idSolicitud)
            ?? throw $this->noEncontrado('La solicitud no existe.');
        $documento = $solicitud->documentos()->find($idDocumento)
            ?? throw $this->noEncontrado('El documento no existe en el comercio de la solicitud.');

        $cifrado = Storage::disk('local')->get($documento->ruta_cifrada)
            ?? throw $this->noEncontrado('El archivo del documento no está disponible.');
        $contenido = Crypt::decryptString($cifrado);

        $this->nucleo->registrarAuditoria('consultar_documento_comercio', 'documento_comercio', $documento->id_documento_comercio, [
            'modulo' => 'M2', 'id_solicitud' => $solicitud->id_solicitud,
        ]);

        return [
            'contenido' => $contenido,
            'tipo' => (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido) ?: 'application/octet-stream',
        ];
    }

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

    public function rechazarSolicitud(int $idSolicitud, string $motivo, ?Authenticatable $revisor = null): SolicitudComercio
    {
        return DB::transaction(function () use ($idSolicitud, $motivo, $revisor): SolicitudComercio {
            $motivo = trim($motivo);
            if ($motivo === '') {
                $this->exigirValidos(['motivo' => ['El motivo de rechazo es obligatorio.']]);
            }

            $solicitud = SolicitudComercio::query()->lockForUpdate()->find($idSolicitud)
                ?? throw $this->noEncontrado('La solicitud no existe.');

            $this->exigirPendiente($solicitud);

            $comercio = $solicitud->comercio()->lockForUpdate()->first()
                ?? throw $this->noEncontrado('El comercio no existe.');

            $solicitud->rechazar($motivo, ...$this->datosRevisor($revisor));
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

    public function aprobarSolicitud(int $idSolicitud, ?Authenticatable $revisor = null): Comercio
    {
        return DB::transaction(function () use ($idSolicitud, $revisor): Comercio {
            $solicitud = SolicitudComercio::query()->lockForUpdate()->find($idSolicitud)
                ?? throw $this->noEncontrado('La solicitud no existe.');

            $this->exigirPendiente($solicitud);

            $comercio = $solicitud->comercio()->lockForUpdate()->first()
                ?? throw $this->noEncontrado('El comercio no existe.');

            $errores = [];
            if (! $comercio->inscrito_como_contribuyente) {
                $errores['inscrito_como_contribuyente'] = ['El comercio debe estar inscrito como contribuyente ante Hacienda (RN-001).'];
            }
            foreach (['cedula_juridica', 'razon_social', 'direccion_fiscal'] as $campo) {
                if (trim((string) $comercio->{$campo}) === '') {
                    $errores[$campo] = ['La información empresarial está incompleta.'];
                }
            }

            if ($solicitud->requiere_documentos) {
                $documentos = $solicitud->documentos()->lockForUpdate()->get();
                $problema = $this->problemaDeDocumentos($documentos);
                if ($problema !== null) {
                    $errores['documentos'] = [$problema];
                }
            }
            $this->exigirValidos($errores);
            // M4 toma las ubicaciones de inventario de las sedes del comercio (id_sede = id_ubicacion):
            // un comercio recién aprobado necesita al menos una para registrar existencias.
            if (! $comercio->sedes()->exists()) {
                $comercio->sedes()->create([
                    'nombre' => 'Sede principal',
                    'direccion' => $comercio->direccion_fiscal,
                    'habilitada' => true,
                ]);
            }

            $solicitud->aprobar(...$this->datosRevisor($revisor));
            $comercio->activar();

            // Desde la revisión del 22/11 (hallazgo 23) la cuenta se crea siempre con la solicitud, así que
            // aprobar solo activa el comercio. Este paso queda para las solicitudes anteriores, que se
            // registraron sin contraseña y todavía no tienen cuenta: se crea aquí y el núcleo invita.
            try {
                if (! $solicitud->cuenta_creada) {
                    $this->identidad->crearCuenta([
                        'nombre' => $solicitud->nombre_encargado,
                        'correo' => $solicitud->correo_encargado,
                    ], RolUsuario::EncargadoComercio, $comercio->id_comercio);
                }
            } catch (ErrorNucleoException $e) {
                // El núcleo rechaza el correo del encargado (ya tiene cuenta): se relanza dentro de la
                // transacción, así que la aprobación y la activación del comercio se revierten.
                if (array_key_exists('correo', $e->getDetalles() ?? [])) {
                    $this->exigirValidos(['correo_encargado' => ['Ya existe una cuenta con el correo del encargado, así que no se puede crear la cuenta del comercio. Rechazá la solicitud y pedile que la corrija con otro correo.']]);
                }

                throw $e;
            }

            $this->nucleo->registrarAuditoria('aprobar_solicitud_comercio', 'solicitud_comercio', $solicitud->id_solicitud, [
                'modulo' => 'M2', 'id_comercio' => $comercio->id_comercio,
            ]);

            ComercioAprobado::dispatch((string) Str::uuid(), now(), $comercio->id_comercio, $comercio->estado, $comercio->nombre_comercial);

            return $comercio;
        });
    }

    // --- CU-0012 y CU-0013: solicitud de incorporación
    public function registrarSolicitud(array $datos, array $documentos, ?Authenticatable $plataforma = null): SolicitudComercio
    {
        return DB::transaction(function () use ($datos, $documentos, $plataforma): SolicitudComercio {
            // Cédula y correo se validan juntos, antes de crear nada, para que el formulario muestre ambos errores.
            $errores = [];
            if (Comercio::query()
                ->where('cedula_juridica', $datos['cedula_juridica'])
                ->exists()) {
                $errores['cedula_juridica'] = ['Ya existe un comercio con esta cédula jurídica.'];
            }
            if ($this->identidad->existeCorreo($datos['correo_encargado'])) {
                $errores['correo_encargado'] = [self::CORREO_EN_USO];
            }
            $this->exigirValidos($errores);

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
                'cuenta_creada' => true,
                'estado' => SolicitudComercio::PENDIENTE,
                'requiere_documentos' => $documentos !== [],
                'motivo_rechazo' => null,
                'fecha_reenvio' => null,
            ]);

            foreach ($documentos as $documento) {
                $this->guardarDocumento($comercio, $documento);
            }

            // Hallazgo 11: si la registra la plataforma en nombre del comercio, queda dicho quién y en la bitácora.
            if ($plataforma !== null) {
                [$idUsuario, $nombre] = $this->datosRevisor($plataforma);
                $solicitud->forceFill(['registrada_por' => $idUsuario, 'nombre_registrador' => $nombre])->save();
                $this->nucleo->registrarAuditoria('registrar_solicitud_comercio', 'solicitud_comercio', $solicitud->id_solicitud, [
                    'modulo' => 'M2', 'id_comercio' => $comercio->id_comercio, 'por_plataforma' => true,
                ]);
            }

            // Hallazgo 23: la cuenta de la persona encargada se crea siempre con la solicitud, con el
            // comercio Pendiente. Así el correo queda reservado desde el inicio, la persona puede ver el
            // estado de la revisión y corregir si la rechazan, y aprobar ya no depende de crear la cuenta.
            // Con contraseña, M2 solo le pasa al núcleo el cifrado (la contraseña en claro no se guarda);
            // sin ella, el núcleo le envía una invitación para definirla. Va al final de la transacción,
            // para que un error al guardar los documentos no deje una cuenta creada.
            $cuenta = ['nombre' => $datos['nombre_encargado'], 'correo' => $datos['correo_encargado']];
            if (isset($datos['contrasena_encargado'])) {
                $cuenta['contrasena_cifrada'] = Hash::make($datos['contrasena_encargado']);
            }
            try {
                $this->identidad->crearCuenta($cuenta, RolUsuario::EncargadoComercio, $comercio->id_comercio);
            } catch (ErrorNucleoException $e) {
                if (array_key_exists('correo', $e->getDetalles() ?? [])) {
                    $this->exigirValidos(['correo_encargado' => [self::CORREO_EN_USO]]);
                }

                throw $e;
            }

            return $solicitud;
        });
    }

    /** RN-002: el documento se cifra al guardarlo y queda pendiente de evaluación (CU-0022). */
    private function guardarDocumento(Comercio $comercio, array $documento): DocumentoComercio
    {
        /** @var UploadedFile $archivo */
        $archivo = $documento['archivo'];

        $ruta = 'm2/documentos/'.$comercio->id_comercio.'/'.Str::uuid().'.enc';
        Storage::disk('local')->put($ruta, Crypt::encryptString($archivo->getContent()));

        return DocumentoComercio::create([
            'id_comercio' => $comercio->id_comercio,
            'tipo' => $documento['tipo'],
            'ruta_cifrada' => $ruta,
            'fecha_vencimiento' => $documento['fecha_vencimiento'],
            'resultado' => 'pendiente',
            'observacion' => null,
        ]);
    }

    /** Un documento marcado como inválido o ya vencido impide aprobar la solicitud: hay que reemplazarlo. */
    private function debeReemplazarse(DocumentoComercio $documento): bool
    {
        return $documento->resultado === 'invalido'
            || ($documento->fecha_vencimiento !== null && $documento->fecha_vencimiento->toDateString() < now()->toDateString());
    }

    /**
     * CU-0013 con sesión (DEF-34): la persona encargada corrige su propia solicitud rechazada.
     *
     * La solicitud se identifica por la sesión, no por los datos que se escriben. El número de solicitud
     * y la cédula jurídica no se pueden cambiar: si llegan distintos, el error dice cuál no coincide.
     * El correo sí se puede corregir; se actualiza también en la cuenta del núcleo para que el ingreso
     * use el mismo correo. Los documentos inválidos o vencidos se reemplazan por los nuevos.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<array{archivo: UploadedFile, tipo: string, fecha_vencimiento: string}>  $documentos
     */
    public function corregirSolicitudDelEncargado(int $idComercio, int $idUsuario, array $datos, array $documentos): SolicitudComercio
    {
        $rutasPorBorrar = [];

        $solicitud = DB::transaction(function () use ($idComercio, $idUsuario, $datos, $documentos, &$rutasPorBorrar): SolicitudComercio {
            $comercio = Comercio::query()->lockForUpdate()->find($idComercio)
                ?? throw $this->noEncontrado('El comercio no existe.');
            $solicitud = SolicitudComercio::query()->where('id_comercio', $idComercio)
                ->orderByDesc('id_solicitud')->lockForUpdate()->first()
                ?? throw $this->noEncontrado('Tu comercio no tiene una solicitud registrada.');

            $errores = [];
            if (array_key_exists('id_solicitud', $datos) && (int) $datos['id_solicitud'] !== $solicitud->id_solicitud) {
                $errores['id_solicitud'] = ["El número de solicitud no corresponde a tu solicitud (#{$solicitud->id_solicitud})."];
            }
            if (array_key_exists('cedula_juridica', $datos) && $datos['cedula_juridica'] !== $comercio->cedula_juridica) {
                $errores['cedula_juridica'] = ['La cédula jurídica no coincide con la registrada en tu solicitud; no se puede cambiar al corregirla.'];
            }
            $this->exigirValidos($errores);

            if ($solicitud->estado !== SolicitudComercio::RECHAZADA) {
                $this->exigirValidos(['estado' => ['Solo se puede corregir una solicitud rechazada.']]);
            }

            // Se reemplazan los inválidos o vencidos y, además, los del mismo tipo que un documento nuevo:
            // cada tipo queda una sola vez en la solicitud (hallazgo 25).
            $tiposNuevos = array_map(fn (array $documento): string => (string) $documento['tipo'], $documentos);
            $actuales = $solicitud->documentos()->lockForUpdate()->get();
            $pendientesDeReemplazo = $actuales->filter(fn (DocumentoComercio $documento): bool => $this->debeReemplazarse($documento));
            $porReemplazar = $actuales->filter(fn (DocumentoComercio $documento): bool => $this->debeReemplazarse($documento)
                || in_array($documento->tipo, $tiposNuevos, true));
            if ($pendientesDeReemplazo->isNotEmpty() && $documentos === []) {
                $this->exigirValidos(['documentos' => [
                    'Tenés documentos rechazados o vencidos: subí uno nuevo para reemplazarlos antes de enviar la corrección.',
                ]]);
            }

            $correoNuevo = isset($datos['correo_encargado'])
                && mb_strtolower(trim((string) $datos['correo_encargado'])) !== mb_strtolower((string) $solicitud->correo_encargado)
                ? trim((string) $datos['correo_encargado'])
                : null;
            if ($correoNuevo !== null) {
                if ($this->identidad->existeCorreo($correoNuevo)) {
                    $this->exigirValidos(['correo_encargado' => [self::CORREO_EN_USO]]);
                }
                try {
                    $this->identidad->actualizarCorreo($idUsuario, $correoNuevo);
                } catch (ErrorNucleoException $e) {
                    if (array_key_exists('correo', $e->getDetalles() ?? [])) {
                        $this->exigirValidos(['correo_encargado' => [self::CORREO_EN_USO]]);
                    }

                    throw $e;
                }
                $solicitud->correo_encargado = $correoNuevo;
            }

            foreach (['nombre_comercial', 'razon_social', 'direccion_fiscal', 'inscrito_como_contribuyente'] as $campo) {
                if (array_key_exists($campo, $datos)) {
                    $comercio->{$campo} = $datos[$campo];
                }
            }
            $comercio->estado = Comercio::PENDIENTE;
            $comercio->save();

            foreach ($porReemplazar as $documento) {
                $rutasPorBorrar[] = $documento->ruta_cifrada;
                $documento->delete();
            }
            foreach ($documentos as $documento) {
                $this->guardarDocumento($comercio, $documento);
            }

            foreach (['nombre_encargado', 'telefono_encargado'] as $campo) {
                if (array_key_exists($campo, $datos)) {
                    $solicitud->{$campo} = $datos[$campo];
                }
            }
            $solicitud->requiere_documentos = $solicitud->requiere_documentos || $documentos !== [];
            $solicitud->estado = SolicitudComercio::PENDIENTE;
            $solicitud->motivo_rechazo = null;
            $solicitud->fecha_reenvio = now();
            $solicitud->save();

            return $solicitud;
        });

        // Los archivos reemplazados se borran solo si la corrección se guardó.
        foreach ($rutasPorBorrar as $ruta) {
            Storage::disk('local')->delete($ruta);
        }

        return $solicitud;
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

    // --- Destacar comercios: publicidad del comercio (Diego) ---

    /**
     * Destacar un comercio por 7, 15 o 30 días. Si la solicita el encargado del comercio
     * queda Pendiente hasta que la plataforma la apruebe; si la crea la plataforma queda
     * Aprobada de inmediato (destacado por cuenta de la plataforma, sin costo).
     */
    public function solicitarPublicidad(int $idComercio, int $dias, bool $porPlataforma): PublicidadComercio
    {
        $this->exigirDiasDePublicidad($dias);

        return DB::transaction(function () use ($idComercio, $dias, $porPlataforma): PublicidadComercio {
            $comercio = Comercio::query()->lockForUpdate()->find($idComercio)
                ?? throw $this->noEncontrado('El comercio no existe.');

            if (! $comercio->estaActivo()) {
                $this->exigirValidos(['estado' => ['Solo se puede destacar un comercio Activo.']]);
            }
            if (! $porPlataforma && $comercio->publicidades()->where('estado', PublicidadComercio::PENDIENTE)->exists()) {
                $this->exigirValidos(['dias' => ['Ya tenés una solicitud de destacado pendiente de revisión.']]);
            }

            // Hallazgo 38: si ya hay un destacado aprobado que no terminó, el nuevo lo extiende (empieza
            // al día siguiente) en vez de superponerse y perder días.
            $inicio = $this->inicioDelSiguienteDestacado($comercio->id_comercio);
            $publicidad = $comercio->publicidades()->create([
                'ubicacion' => 'portada',
                'inicio' => $inicio->toDateString(),
                'fin' => $inicio->copy()->addDays($dias - 1)->toDateString(),
                'activa' => true,
                'estado' => $porPlataforma ? PublicidadComercio::APROBADA : PublicidadComercio::PENDIENTE,
                'dias_solicitados' => $dias,
            ]);

            if ($porPlataforma) {
                $this->nucleo->registrarAuditoria('destacar_comercio', 'publicidad_comercio', $publicidad->id_publicidad_comercio, [
                    'modulo' => 'M2', 'id_comercio' => $comercio->id_comercio, 'dias' => $dias,
                ]);
            }

            return $publicidad;
        });
    }

    /**
     * Diseño (tabla 4.1): la plataforma aprueba o rechaza una solicitud de destacado.
     * Al aprobar, el periodo empieza el día de la aprobación.
     */
    public function gestionarPublicidad(int $idPublicidad, string $accion, ?string $motivo = null): PublicidadComercio
    {
        if (! in_array($accion, ['aprobar', 'rechazar', 'retirar'], true)) {
            $this->exigirValidos(['accion' => ['La acción debe ser aprobar, rechazar o retirar.']]);
        }
        $motivo = $motivo === null ? null : trim($motivo);
        // Hallazgo 37: rechazar o retirar un destacado lleva motivo, como el rechazo de una solicitud (RN-016).
        if ($accion !== 'aprobar' && ($motivo === null || $motivo === '')) {
            $this->exigirValidos(['motivo' => ['Escribí el motivo: el comercio lo va a ver en su historial.']]);
        }

        return DB::transaction(function () use ($idPublicidad, $accion, $motivo): PublicidadComercio {
            $publicidad = PublicidadComercio::query()->lockForUpdate()->find($idPublicidad)
                ?? throw $this->noEncontrado('La solicitud de destacado no existe.');

            if ($accion === 'retirar') {
                // Hallazgo 38: la plataforma puede retirar un destacado aprobado que todavía no terminó.
                if ($publicidad->estado !== PublicidadComercio::APROBADA || ! $publicidad->activa
                    || $publicidad->fin->toDateString() < now()->toDateString()) {
                    $this->exigirValidos(['estado' => ['Solo se puede retirar un destacado vigente o programado.']]);
                }
                $publicidad->fill(['activa' => false, 'motivo' => $motivo]);
            } else {
                if (! $publicidad->estaPendiente()) {
                    $this->exigirValidos(['estado' => ['La solicitud de destacado ya fue revisada en otra sesión.']]);
                }

                if ($accion === 'aprobar') {
                    $dias = $publicidad->dias_solicitados ?? 7;
                    $inicio = $this->inicioDelSiguienteDestacado($publicidad->id_comercio, $publicidad->id_publicidad_comercio);
                    $publicidad->fill([
                        'estado' => PublicidadComercio::APROBADA,
                        'inicio' => $inicio->toDateString(),
                        'fin' => $inicio->copy()->addDays($dias - 1)->toDateString(),
                    ]);
                } else {
                    $publicidad->fill(['estado' => PublicidadComercio::RECHAZADA, 'activa' => false, 'motivo' => $motivo]);
                }
            }
            $publicidad->save();

            $this->nucleo->registrarAuditoria($accion.'_destacado_comercio', 'publicidad_comercio', $publicidad->id_publicidad_comercio, [
                'modulo' => 'M2', 'id_comercio' => $publicidad->id_comercio, 'motivo' => $motivo,
            ]);

            return $publicidad;
        });
    }

    /**
     * Día en que empieza un destacado nuevo: hoy, o el día siguiente al fin del destacado aprobado que
     * todavía no terminó (vigente o ya programado), para que se encadenen sin superponerse.
     */
    private function inicioDelSiguienteDestacado(int $idComercio, ?int $excepto = null): Carbon
    {
        $hoy = now()->startOfDay();
        $ultimoFin = PublicidadComercio::query()
            ->where('id_comercio', $idComercio)
            ->where('estado', PublicidadComercio::APROBADA)
            ->where('activa', true)
            ->whereDate('fin', '>=', $hoy->toDateString())
            ->when($excepto !== null, fn ($q) => $q->where('id_publicidad_comercio', '!=', $excepto))
            ->max('fin');

        return $ultimoFin === null ? $hoy : Carbon::parse($ultimoFin)->startOfDay()->addDay();
    }

    /** Hallazgo 37: destacados aprobados que no terminaron, para que la plataforma los vea y los pueda retirar. */
    public function listarPublicidadVigente(): array
    {
        $hoy = now()->toDateString();

        return [
            'publicidades' => PublicidadComercio::query()->with('comercio')
                ->where('estado', PublicidadComercio::APROBADA)
                ->where('activa', true)
                ->whereDate('fin', '>=', $hoy)
                ->orderBy('inicio')->orderBy('id_publicidad_comercio')->get()
                ->map(fn (PublicidadComercio $p): array => $this->datosPublicidad($p) + [
                    'nombre_comercial' => $p->comercio?->nombre_comercial,
                    'dias_restantes' => (int) now()->startOfDay()->diffInDays($p->fin->copy()->startOfDay()) + 1,
                ])->values()->all(),
        ];
    }

    /** Publicidades de un comercio, la más reciente primero. */
    public function listarPublicidadComercio(int $idComercio): array
    {
        $comercio = $this->buscar($idComercio);

        return [
            'id_comercio' => $comercio->id_comercio,
            'destacado' => $comercio->publicidades()->vigentes()->exists(),
            'publicidades' => $comercio->publicidades()->orderByDesc('id_publicidad_comercio')->get()
                ->map(fn (PublicidadComercio $p): array => $this->datosPublicidad($p))->values()->all(),
        ];
    }

    /** Solicitudes de destacado que esperan revisión de la plataforma. */
    public function listarPublicidadPendiente(): array
    {
        return [
            'publicidades' => PublicidadComercio::query()->with('comercio')
                ->where('estado', PublicidadComercio::PENDIENTE)
                ->orderBy('id_publicidad_comercio')->get()
                ->map(fn (PublicidadComercio $p): array => $this->datosPublicidad($p) + [
                    'nombre_comercial' => $p->comercio?->nombre_comercial,
                ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function datosPublicidad(PublicidadComercio $p): array
    {
        return [
            'id_publicidad_comercio' => $p->id_publicidad_comercio,
            'id_comercio' => $p->id_comercio,
            'estado' => $p->estado ?? PublicidadComercio::APROBADA,
            'dias_solicitados' => $p->dias_solicitados,
            'inicio' => $p->inicio?->toDateString(),
            'fin' => $p->fin?->toDateString(),
            'vigente' => $p->estaVigente(),
            // Un destacado aprobado que la plataforma retiró antes de terminar.
            'retirada' => ($p->estado ?? PublicidadComercio::APROBADA) === PublicidadComercio::APROBADA && ! $p->activa,
            'motivo' => $p->getAttribute('motivo'),
            'solicitado_el' => $p->created_at?->toDateString(),
            'revisado_el' => ($p->estado ?? PublicidadComercio::APROBADA) === PublicidadComercio::PENDIENTE ? null : $p->updated_at?->toDateString(),
        ];
    }

    private function exigirDiasDePublicidad(int $dias): void
    {
        if (! in_array($dias, PublicidadComercio::DIAS_PERMITIDOS, true)) {
            $this->exigirValidos(['dias' => ['El periodo debe ser de 7, 15 o 30 días.']]);
        }
    }

    /**
     * RN-011 y hallazgo 24: si otra persona ya revisó la solicitud, el mensaje dice cómo quedó, para que
     * la pantalla se actualice y no parezca que sigue pendiente.
     */
    private function exigirPendiente(SolicitudComercio $solicitud): void
    {
        if (! $solicitud->estaPendiente()) {
            $estado = $solicitud->estado === SolicitudComercio::APROBADA ? 'aprobada' : 'rechazada';
            $this->exigirValidos(['estado' => ["Esta solicitud ya fue {$estado} en otra sesión. Se actualizó la pantalla con su estado."]]);
        }
    }

    /** @return array{0: ?int, 1: ?string} id y nombre de quien revisa, si hay sesión. */
    private function datosRevisor(?Authenticatable $revisor): array
    {
        $revisor ??= auth()->user();
        if ($revisor === null) {
            return [null, null];
        }

        $nombre = $revisor->getAttribute('nombre');

        return [(int) $revisor->getAuthIdentifier(), is_string($nombre) ? $nombre : null];
    }

    /**
     * Qué impide aprobar por los documentos (RN-002), dicho de forma que se sepa qué hacer.
     *
     * @param  Collection<int, DocumentoComercio>  $documentos
     */
    private function problemaDeDocumentos($documentos): ?string
    {
        if ($documentos->isEmpty()) {
            return 'La solicitud requiere documentos y no tiene ninguno.';
        }

        $hoy = now()->toDateString();
        $sinRevisar = $documentos->where('resultado', 'pendiente')->count();
        $invalidos = $documentos->where('resultado', 'invalido')->count();
        $vencidos = $documentos->filter(fn (DocumentoComercio $d): bool => $d->fecha_vencimiento !== null
            && $d->fecha_vencimiento->toDateString() < $hoy)->count();

        $partes = [];
        if ($sinRevisar > 0) {
            $partes[] = $sinRevisar === 1 ? 'revisar 1 documento' : "revisar {$sinRevisar} documentos";
        }
        if ($invalidos > 0) {
            $partes[] = $invalidos === 1 ? '1 documento está inválido' : "{$invalidos} documentos están inválidos";
        }
        if ($vencidos > 0) {
            $partes[] = $vencidos === 1 ? '1 documento está vencido' : "{$vencidos} documentos están vencidos";
        }

        if ($partes === [] && $documentos->contains(fn (DocumentoComercio $d): bool => ! $d->esValido(now()))) {
            // Cualquier otro caso que RN-002 no acepte.
            $partes[] = 'que todos los documentos estén validados y vigentes';
        }

        return $partes === [] ? null : 'Para aprobar falta: '.implode('; ', $partes).'.';
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
                'Revisá los datos marcados (detalle por campo en detalles).',
                $errores,
            );
        }
    }

    /** Disco de logotipos, banners y galería (config/m2.php): local o Supabase Storage. */
    private function discoImagenes(): FilesystemAdapter
    {
        return Storage::disk(config('m2.disco_imagenes', 'public'));
    }

    /**
     * Sube la imagen al disco de M2 y devuelve su URL pública.
     * Si el disco no la guarda (putFile devuelve false), lanza ERR-006 en vez de guardar una URL rota.
     */
    private function guardarImagen(string $carpeta, UploadedFile $archivo): string
    {
        $ruta = $this->discoImagenes()->putFile($carpeta, $archivo);

        if ($ruta === false) {
            throw ErrorNucleoException::paraModulo(
                self::MODULO,
                'ERR-006',
                503,
                'No se pudo guardar la imagen. Intentá de nuevo en unos minutos.'
            );
        }

        return $this->discoImagenes()->url($ruta);
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

        // Sirve para el disco local (/storage/m2/...) y para Supabase Storage
        // (/storage/v1/object/public/<bucket>/m2/...): todo lo de M2 cuelga de m2/.
        $inicio = strpos($path, 'm2/');
        if ($inicio === false) {
            return;
        }

        $this->discoImagenes()->delete(substr($path, $inicio));
    }

    // --- Comisión del subpedido, Ds-03 (Josefh) ---

    public function registrarComisionDespacho(int $idSubpedido, string $idEvento): void
    {
        // 1. Idempotencia
        if (ComisionSubpedido::existePorSubpedido($idSubpedido)) {
            return;
        }

        // 2. Obtener los datos reales del subpedido desde M5
        $subpedido = $this->subpedidos()->obtenerSubpedido($idSubpedido);

        // 3. Verificar que M5 devolvió el subpedido correcto y que fue despachado
        if (
            $subpedido['id_subpedido'] !== $idSubpedido
            || $subpedido['estado_subpedido'] !== 'despachado'
        ) {
            Log::warning('M2: no se registra comisión porque el subpedido no es válido o no está despachado.', [
                'id_subpedido' => $idSubpedido,
            ]);

            return;
        }

        // 4. Obtener la configuración de comisión vigente
        $config = ComisionComercio::vigentePara($subpedido['id_comercio']);

        if ($config === null) {
            Log::warning('M2: no existe configuración de comisión vigente para el comercio.', [
                'id_comercio' => $subpedido['id_comercio'],
                'id_subpedido' => $idSubpedido,
            ]);

            return;
        }

        // 5. Buscar una membresía vigente con comisión preferencial
        $membresia = MembresiaComercio::query()
            ->where('id_comercio', $subpedido['id_comercio'])
            ->get()
            ->first(
                fn (MembresiaComercio $membresia): bool => $membresia->estaVigente(now())
                    && $membresia->comision_preferencial !== null
            );

        if ($membresia !== null) {
            $estrategia = new CalculoPorcentaje;
            $valor = (string) $membresia->comision_preferencial;
        } else {
            // 6. Seleccionar la estrategia según la configuración normal
            $estrategia = match ($config->tipo) {
                ComisionComercio::PORCENTAJE => new CalculoPorcentaje,
                ComisionComercio::MONTO_FIJO => new CalculoMontoFijo,
            };

            $valor = (string) $config->valor;
        }

        $monto = $estrategia->calcular(
            (string) $subpedido['subtotal'],
            $valor
        );

        // 7. Guardar una sola vez, incluso si dos eventos llegan al mismo tiempo
        try {
            ComisionSubpedido::create([
                'id_subpedido' => $idSubpedido,
                'id_comercio' => $subpedido['id_comercio'],
                'subtotal_base' => $subpedido['subtotal'],
                'monto' => $monto,
                'moneda' => 'CRC',
                'id_evento' => $idEvento,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Otro evento ya registró esta misma comisión.
        }
    }
}
