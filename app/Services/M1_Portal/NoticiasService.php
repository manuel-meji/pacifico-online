<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Contracts\M1_Portal\Exceptions\NoticiaNoModificableException;
use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Models\M1_Portal\NoticiaPortal;
use App\Models\M1_Portal\NoticiaPromocion;
use App\Models\M1_Portal\Promocion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Noticias de la plataforma (contrato M1 1.4.0, SC-08). M1 es el dueño de los
 * datos (tablas noticias_portal y noticia_promociones).
 *
 * - Contenido informativo distinto de las promociones: comercios nuevos,
 *   campañas de temporada, avisos de mantenimiento y cambios en políticas.
 * - Las publica únicamente la administración (rol encargado_plataforma); los
 *   comercios no publican noticias. El rol lo exige la ruta (RequiereRol).
 * - Cada noticia tiene título, resumen, contenido, imagen (URL) y fecha de
 *   publicación (fecha_inicio, que se fija al publicar). fecha_fin es hasta
 *   cuándo se muestra; si no se indica, un año.
 * - Puede enlazar promociones (noticia_promociones), pero es una entidad
 *   aparte: el portal muestra solo las promociones enlazadas que estén vigentes.
 * - Estados: borrador → publicada → archivada. Una archivada no se modifica
 *   (ERR-M1-006).
 */
class NoticiasService
{
    public const MAXIMO_POR_PAGINA = 50;

    public const POR_PAGINA_POR_DEFECTO = 10;

    private const LARGO_TITULO = 150;

    private const LARGO_SLUG = 170;

    private const LARGO_RESUMEN = 300;

    private const LARGO_CONTENIDO = 20000;

    private const LARGO_IMAGEN = 255;

    private const MAXIMO_PROMOCIONES = 10;

    private const MESES_VIGENCIA_POR_DEFECTO = 12;

    public function __construct(private PromocionesService $promociones) {}

    // ---------------------------------------------------------------
    // Portal
    // ---------------------------------------------------------------

    /**
     * Noticias publicadas y vigentes, de la más reciente a la más antigua.
     *
     * @return array{pagina: int, por_pagina: int, total: int, noticias: list<array<string, mixed>>}
     *
     * @throws DatosInvalidosException ERR-005 si pagina o por_pagina está fuera de rango.
     */
    public function listarPublicadas(int $pagina, int $porPagina): array
    {
        $errores = [];

        if ($pagina < 1) {
            $errores['pagina'] = 'Debe ser mayor o igual a 1.';
        }

        if ($porPagina < 1 || $porPagina > self::MAXIMO_POR_PAGINA) {
            $errores['por_pagina'] = 'Debe estar entre 1 y '.self::MAXIMO_POR_PAGINA.'.';
        }

        $this->exigirValidos($errores);

        $consulta = $this->publicadas(Carbon::now());
        $total = (clone $consulta)->count();

        $noticias = $consulta
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id_noticia_portal')
            ->forPage($pagina, $porPagina)
            ->get()
            ->map(fn (NoticiaPortal $noticia): array => $noticia->aResumen())
            ->values()
            ->all();

        return ['pagina' => $pagina, 'por_pagina' => $porPagina, 'total' => $total, 'noticias' => $noticias];
    }

    /**
     * Una noticia publicada y vigente, con sus promociones enlazadas que estén
     * vigentes. Si M3 no responde, la noticia se muestra igual, sin promociones.
     *
     * @return array<string, mixed> Esquema NoticiaDetalle.
     *
     * @throws RecursoNoEncontradoException ERR-004 si no existe, es un borrador, está archivada o fuera de su periodo.
     */
    public function obtenerPublicada(string $slug): array
    {
        $noticia = $this->publicadas(Carbon::now())
            ->with('enlacesPromociones')
            ->where('slug_noticia', $slug)
            ->first() ?? throw new RecursoNoEncontradoException('La noticia no existe o no está publicada.');

        try {
            $promociones = $this->promociones->listarVigentes($noticia->idsPromocion());
        } catch (DependenciaNoDisponibleException) {
            $promociones = [];
        }

        return [...$noticia->aResumen(), 'contenido' => $noticia->contenido_noticia, 'promociones' => $promociones];
    }

    // ---------------------------------------------------------------
    // Administración
    // ---------------------------------------------------------------

    /**
     * La administración crea una noticia en borrador.
     *
     * @param  array<string, mixed>  $datos  Esquema DatosNoticia.
     * @return array<string, mixed> Esquema Noticia.
     *
     * @throws DatosInvalidosException ERR-005
     */
    public function crear(array $datos, int $idUsuario): array
    {
        $ahora = Carbon::now();
        $campos = $this->validarDatos($datos, $ahora);

        return DB::transaction(function () use ($campos, $idUsuario, $ahora): array {
            $noticia = NoticiaPortal::query()->create([
                'id_usuario_autor' => $idUsuario,
                'titulo_noticia' => $campos['titulo'],
                'slug_noticia' => $this->slugDisponible($campos['titulo'], null),
                'resumen_noticia' => $campos['resumen'],
                'contenido_noticia' => $campos['contenido'],
                'imagen_url' => $campos['imagen_url'],
                'estado_noticia' => NoticiaPortal::ESTADO_BORRADOR,
                // NOT NULL en la tabla; la fecha de publicación real se fija al publicar.
                'fecha_inicio' => $ahora,
                'fecha_fin' => $campos['fecha_fin'],
            ]);

            $this->guardarPromociones($noticia, $campos['ids_promocion']);

            return $noticia->load('enlacesPromociones')->aRespuesta();
        });
    }

    /**
     * Reemplaza los datos de un borrador o de una publicada. El slug cambia
     * con el título solo mientras es borrador: publicada, su enlace se conserva.
     *
     * @param  array<string, mixed>  $datos  Esquema DatosNoticia.
     *
     * @throws RecursoNoEncontradoException ERR-004
     * @throws NoticiaNoModificableException ERR-M1-006 si está archivada.
     * @throws DatosInvalidosException ERR-005
     */
    public function actualizar(int $idNoticia, array $datos): array
    {
        return DB::transaction(function () use ($idNoticia, $datos): array {
            $noticia = $this->buscar($idNoticia, bloquear: true);

            if ($noticia->estado_noticia === NoticiaPortal::ESTADO_ARCHIVADA) {
                throw new NoticiaNoModificableException('La noticia está archivada y no se puede editar.');
            }

            $ahora = Carbon::now();
            $campos = $this->validarDatos($datos, $ahora);

            if ($noticia->estado_noticia === NoticiaPortal::ESTADO_PUBLICADA && $campos['fecha_fin']->lessThanOrEqualTo($noticia->fecha_inicio)) {
                $this->exigirValidos(['fecha_fin' => 'Debe ser posterior a la fecha de publicación.']);
            }

            $noticia->fill([
                'titulo_noticia' => $campos['titulo'],
                'resumen_noticia' => $campos['resumen'],
                'contenido_noticia' => $campos['contenido'],
                'imagen_url' => $campos['imagen_url'],
                'fecha_fin' => $campos['fecha_fin'],
            ]);

            if ($noticia->estado_noticia === NoticiaPortal::ESTADO_BORRADOR) {
                $noticia->slug_noticia = $this->slugDisponible($campos['titulo'], $noticia->id_noticia_portal);
            }

            $noticia->save();
            $noticia->enlacesPromociones()->delete();
            $this->guardarPromociones($noticia, $campos['ids_promocion']);

            return $noticia->load('enlacesPromociones')->aRespuesta();
        });
    }

    /**
     * Publica un borrador. La fecha de publicación es ahora, o la que se
     * indique para programarla; desde ese momento se muestra en el portal
     * hasta fecha_fin.
     *
     * @param  array<string, mixed>  $datos  Esquema PublicacionNoticia: {fecha_publicacion?}.
     *
     * @throws RecursoNoEncontradoException ERR-004
     * @throws NoticiaNoModificableException ERR-M1-006 si ya está publicada o archivada.
     * @throws DatosInvalidosException ERR-005
     */
    public function publicar(int $idNoticia, array $datos): array
    {
        return DB::transaction(function () use ($idNoticia, $datos): array {
            $noticia = $this->buscar($idNoticia, bloquear: true);

            if ($noticia->estado_noticia !== NoticiaPortal::ESTADO_BORRADOR) {
                throw new NoticiaNoModificableException($noticia->estado_noticia === NoticiaPortal::ESTADO_PUBLICADA
                    ? 'La noticia ya está publicada.'
                    : 'La noticia está archivada y no se puede publicar.');
            }

            $ahora = Carbon::now();
            $fecha = $ahora;

            if (($datos['fecha_publicacion'] ?? null) !== null) {
                $fecha = $this->fecha($datos['fecha_publicacion']);

                if ($fecha === null) {
                    $this->exigirValidos(['fecha_publicacion' => 'Debe ser una fecha y hora válida (ISO 8601).']);
                }
            }

            if ($noticia->fecha_fin->lessThanOrEqualTo($fecha) || $noticia->fecha_fin->lessThanOrEqualTo($ahora)) {
                $this->exigirValidos(['fecha_publicacion' => 'Debe ser anterior a fecha_fin, que también debe ser futura.']);
            }

            $noticia->forceFill(['estado_noticia' => NoticiaPortal::ESTADO_PUBLICADA, 'fecha_inicio' => $fecha])->save();

            return $noticia->load('enlacesPromociones')->aRespuesta();
        });
    }

    /**
     * Archiva un borrador o una publicada: deja de mostrarse y ya no se modifica.
     *
     * @throws RecursoNoEncontradoException ERR-004
     * @throws NoticiaNoModificableException ERR-M1-006 si ya estaba archivada.
     */
    public function archivar(int $idNoticia): array
    {
        return DB::transaction(function () use ($idNoticia): array {
            $noticia = $this->buscar($idNoticia, bloquear: true);

            if ($noticia->estado_noticia === NoticiaPortal::ESTADO_ARCHIVADA) {
                throw new NoticiaNoModificableException('La noticia ya está archivada.');
            }

            $noticia->forceFill(['estado_noticia' => NoticiaPortal::ESTADO_ARCHIVADA])->save();

            return $noticia->load('enlacesPromociones')->aRespuesta();
        });
    }

    /**
     * Todas las noticias, de la más reciente a la más antigua, con filtro
     * opcional por estado.
     *
     * @return list<array<string, mixed>>
     *
     * @throws DatosInvalidosException ERR-005 si el estado no es válido.
     */
    public function listarAdministracion(?string $estado): array
    {
        if ($estado !== null && ! in_array($estado, NoticiaPortal::ESTADOS, true)) {
            $this->exigirValidos(['estado' => 'Debe ser '.implode(', ', NoticiaPortal::ESTADOS).'.']);
        }

        return NoticiaPortal::query()
            ->with('enlacesPromociones')
            ->when($estado !== null, fn (Builder $consulta) => $consulta->where('estado_noticia', $estado))
            ->orderByDesc('updated_at')
            ->orderByDesc('id_noticia_portal')
            ->get()
            ->map(fn (NoticiaPortal $noticia): array => $noticia->aRespuesta())
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------
    // Ayudantes
    // ---------------------------------------------------------------

    /**
     * Publicadas y dentro de su periodo (fecha_inicio y fecha_fin incluidos,
     * igual que NoticiaPortal::estaPublicada()).
     *
     * @return Builder<NoticiaPortal>
     */
    private function publicadas(CarbonInterface $ahora): Builder
    {
        return NoticiaPortal::query()
            ->where('estado_noticia', NoticiaPortal::ESTADO_PUBLICADA)
            ->where('fecha_inicio', '<=', $ahora)
            ->where('fecha_fin', '>=', $ahora);
    }

    /**
     * @throws RecursoNoEncontradoException ERR-004
     */
    private function buscar(int $idNoticia, bool $bloquear = false): NoticiaPortal
    {
        return NoticiaPortal::query()
            ->with('enlacesPromociones')
            ->when($bloquear, fn (Builder $consulta) => $consulta->lockForUpdate())
            ->find($idNoticia) ?? throw new RecursoNoEncontradoException('La noticia no existe.');
    }

    /**
     * Valida el esquema DatosNoticia.
     *
     * @param  array<string, mixed>  $datos
     * @return array{titulo: string, resumen: string, contenido: string, imagen_url: ?string, fecha_fin: CarbonInterface, ids_promocion: list<int>}
     *
     * @throws DatosInvalidosException ERR-005 con el detalle por campo.
     */
    private function validarDatos(array $datos, CarbonInterface $ahora): array
    {
        $errores = [];
        $texto = function (string $campo, int $maximo) use ($datos, &$errores): string {
            $valor = is_string($datos[$campo] ?? null) ? trim($datos[$campo]) : '';

            if ($valor === '' || mb_strlen($valor) > $maximo) {
                $errores[$campo] = "Es obligatorio y debe tener entre 1 y {$maximo} caracteres.";
            }

            return $valor;
        };

        $titulo = $texto('titulo', self::LARGO_TITULO);
        $resumen = $texto('resumen', self::LARGO_RESUMEN);
        $contenido = $texto('contenido', self::LARGO_CONTENIDO);

        $imagen = $datos['imagen_url'] ?? null;
        if ($imagen !== null) {
            $imagen = is_string($imagen) ? trim($imagen) : $imagen;

            if (! is_string($imagen) || mb_strlen($imagen) > self::LARGO_IMAGEN
                || filter_var($imagen, FILTER_VALIDATE_URL) === false
                || ! in_array(strtolower((string) parse_url($imagen, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                $errores['imagen_url'] = 'Debe ser una URL http o https de hasta '.self::LARGO_IMAGEN.' caracteres.';
            }

            $imagen = $imagen === '' ? null : $imagen;
        }

        $fechaFin = $ahora->copy()->addMonths(self::MESES_VIGENCIA_POR_DEFECTO);
        if (($datos['fecha_fin'] ?? null) !== null) {
            $fechaFin = $this->fecha($datos['fecha_fin']);

            if ($fechaFin === null || $fechaFin->lessThanOrEqualTo($ahora)) {
                $errores['fecha_fin'] = 'Debe ser una fecha y hora futura (ISO 8601).';
            }
        }

        $idsPromocion = $datos['ids_promocion'] ?? [];
        if (! is_array($idsPromocion) || ! array_is_list($idsPromocion) || count($idsPromocion) > self::MAXIMO_PROMOCIONES
            || array_filter($idsPromocion, fn (mixed $id): bool => ! is_int($id) || $id < 1) !== []) {
            $errores['ids_promocion'] = 'Debe ser una lista de hasta '.self::MAXIMO_PROMOCIONES.' enteros mayores o iguales a 1.';
            $idsPromocion = [];
        } else {
            $idsPromocion = array_values(array_unique($idsPromocion));
            $existentes = Promocion::query()->withoutGlobalScopes()->whereIn('id_promocion', $idsPromocion)->pluck('id_promocion')->map(fn (mixed $id): int => (int) $id)->all();
            $faltantes = array_values(array_diff($idsPromocion, $existentes));

            if ($faltantes !== []) {
                $errores['ids_promocion'] = 'No existe la promoción '.implode(', ', $faltantes).'.';
            }
        }

        $this->exigirValidos($errores);

        return [
            'titulo' => $titulo,
            'resumen' => $resumen,
            'contenido' => $contenido,
            'imagen_url' => $imagen,
            'fecha_fin' => $fechaFin,
            'ids_promocion' => $idsPromocion,
        ];
    }

    /**
     * Slug a partir del título (formato de la tabla: ^[a-z0-9]+(-[a-z0-9]+)*$),
     * único: si ya existe se agrega -2, -3, etc.
     */
    private function slugDisponible(string $titulo, ?int $idNoticia): string
    {
        $base = Str::slug(Str::limit($titulo, 120, ''), '-', 'es');
        $base = trim(preg_replace('/[^a-z0-9-]+/', '', $base) ?? '', '-');
        $base = $base === '' ? 'noticia' : $base;
        $slug = $base;

        for ($numero = 2; $this->slugOcupado($slug, $idNoticia); $numero++) {
            $slug = Str::limit($base, self::LARGO_SLUG - strlen("-{$numero}"), '')."-{$numero}";
        }

        return $slug;
    }

    private function slugOcupado(string $slug, ?int $idNoticia): bool
    {
        return NoticiaPortal::query()
            ->where('slug_noticia', $slug)
            ->when($idNoticia !== null, fn (Builder $consulta) => $consulta->where('id_noticia_portal', '!=', $idNoticia))
            ->exists();
    }

    /**
     * @param  list<int>  $idsPromocion
     */
    private function guardarPromociones(NoticiaPortal $noticia, array $idsPromocion): void
    {
        foreach ($idsPromocion as $idPromocion) {
            NoticiaPromocion::query()->create(['id_noticia_portal' => $noticia->id_noticia_portal, 'id_promocion' => $idPromocion]);
        }
    }

    private function fecha(mixed $valor): ?CarbonInterface
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, string>  $errores
     *
     * @throws DatosInvalidosException
     */
    private function exigirValidos(array $errores): void
    {
        if ($errores !== []) {
            throw new DatosInvalidosException($errores);
        }
    }
}
