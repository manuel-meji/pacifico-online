<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\PromocionNoModificableException;
use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Contracts\M1_Portal\Exceptions\TraslapePromocionException;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M1_Portal\Promocion;
use App\Models\M1_Portal\PromocionVariacion;
use App\Services\M1_Portal\DTO\FichaProductoDTO;
use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use App\Services\M1_Portal\DTO\VariacionDTO;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use App\Services\Nucleo\ContextoComercio;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Promociones de los comercios (contrato M1 1.4.0, SC-08). M1 es el dueño de
 * los datos.
 *
 * - Solo dos tipos: porcentaje de descuento o precio fijo rebajado.
 * - Fecha y hora de inicio y de fin obligatorias: se programa por adelantado
 *   y se activa y desactiva sola.
 * - Al producto completo o a variaciones específicas. Un mismo producto o
 *   variación no puede tener dos promociones activas que se traslapen.
 * - El comercio crea y gestiona las suyas. Una programada se edita completa;
 *   una iniciada solo puede adelantar su fin o desactivarse.
 * - La plataforma decide el orden en el portal y puede ocultar una promoción
 *   que incumpla las políticas; oculta, no rebaja el precio.
 * - El portal y M5 (obtenerPreciosVigentes) ven solo las vigentes. El precio
 *   con descuento va a 2 decimales y lo asume el comercio; la comisión se
 *   calcula sobre el precio pagado (M5 → M2).
 */
class PromocionesService
{
    private const LARGO_TITULO = 120;

    private const LARGO_TEXTO = 500;

    private const MAXIMO_VARIACIONES_PRECIOS = 100;

    public function __construct(
        private CatalogoClient $catalogo,
        private CompletadorProductos $completador,
        private ContextoComercio $contexto,
    ) {}

    // ---------------------------------------------------------------
    // Comercio
    // ---------------------------------------------------------------

    /**
     * El comercio de la sesión crea una promoción para uno de sus productos.
     *
     * @param  array<string, mixed>  $datos  Esquema DatosPromocion.
     *
     * @throws DatosInvalidosException ERR-005
     * @throws RecursoNoEncontradoException ERR-004 si el producto no existe, no está publicado o es de otro comercio.
     * @throws TraslapePromocionException ERR-M1-004
     */
    public function crear(array $datos, int $idUsuario): array
    {
        $idComercio = $this->comercioDeSesion();
        $idProducto = $datos['id_producto'] ?? null;

        if (! is_int($idProducto) || $idProducto < 1) {
            $this->exigirValidos(['id_producto' => 'Es obligatorio y debe ser un entero mayor o igual a 1.']);
        }

        $producto = $this->productoDelComercio($idProducto, $idComercio);
        $campos = $this->validarDatos($datos, $producto, exigirFinFuturo: true);

        return DB::transaction(function () use ($campos, $idComercio, $idProducto, $idUsuario): array {
            $this->bloquearProducto($idProducto);
            $this->exigirSinTraslape($idProducto, $campos['alcance'], $campos['variaciones'], $campos['fecha_inicio'], $campos['fecha_fin'], null);

            $promocion = Promocion::query()->create([
                'id_comercio' => $idComercio,
                'id_producto' => $idProducto,
                'titulo' => $campos['titulo'],
                'descripcion' => $campos['descripcion'],
                'tipo' => $campos['tipo'],
                'valor' => $campos['valor'],
                'alcance' => $campos['alcance'],
                'fecha_inicio' => $campos['fecha_inicio'],
                'fecha_fin' => $campos['fecha_fin'],
                'id_usuario_crea' => $idUsuario,
            ]);

            $this->guardarVariaciones($promocion, $campos['variaciones']);

            return $promocion->load('variaciones')->aRespuesta(Carbon::now());
        });
    }

    /**
     * Promociones del comercio de la sesión, de la más reciente a la más antigua.
     *
     * @return list<array<string, mixed>>
     *
     * @throws DatosInvalidosException ERR-005 si el estado no es válido.
     */
    public function listarDelComercio(?string $estado): array
    {
        $this->comercioDeSesion();

        return $this->listar($estado, null);
    }

    /**
     * Una programada se reemplaza completa (sin cambiar el producto). Una
     * iniciada solo acepta fecha_fin, para adelantar el fin.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws RecursoNoEncontradoException ERR-004 si no existe o es de otro comercio.
     * @throws PromocionNoModificableException ERR-M1-005 si terminó o está desactivada.
     * @throws DatosInvalidosException ERR-005
     * @throws TraslapePromocionException ERR-M1-004
     */
    public function actualizar(int $idPromocion, array $datos): array
    {
        $comercio = $this->comercioDeSesion();

        return DB::transaction(function () use ($idPromocion, $datos, $comercio): array {
            $promocion = $this->buscar($idPromocion, bloquear: true);
            $ahora = Carbon::now();
            $estado = $promocion->estado($ahora);

            if (in_array($estado, [Promocion::ESTADO_FINALIZADA, Promocion::ESTADO_DESACTIVADA], true)) {
                throw new PromocionNoModificableException;
            }

            if ($estado !== Promocion::ESTADO_PROGRAMADA) {
                $this->adelantarFin($promocion, $datos, $ahora);

                return $promocion->load('variaciones')->aRespuesta($ahora);
            }

            if (array_key_exists('id_producto', $datos) && $datos['id_producto'] !== $promocion->id_producto) {
                $this->exigirValidos(['id_producto' => 'No se puede cambiar el producto de una promoción.']);
            }

            $producto = $this->productoDelComercio($promocion->id_producto, $comercio);
            $campos = $this->validarDatos($datos, $producto, exigirFinFuturo: true);

            $this->bloquearProducto($promocion->id_producto);
            $this->exigirSinTraslape($promocion->id_producto, $campos['alcance'], $campos['variaciones'], $campos['fecha_inicio'], $campos['fecha_fin'], $promocion->id_promocion);

            $promocion->fill([
                'titulo' => $campos['titulo'],
                'descripcion' => $campos['descripcion'],
                'tipo' => $campos['tipo'],
                'valor' => $campos['valor'],
                'alcance' => $campos['alcance'],
                'fecha_inicio' => $campos['fecha_inicio'],
                'fecha_fin' => $campos['fecha_fin'],
            ])->save();

            $promocion->variaciones()->delete();
            $this->guardarVariaciones($promocion, $campos['variaciones']);

            return $promocion->load('variaciones')->aRespuesta($ahora);
        });
    }

    /**
     * El comercio desactiva una promoción que no ha terminado. Deja de
     * rebajar el precio de inmediato y no se puede reactivar.
     *
     * @throws RecursoNoEncontradoException ERR-004
     * @throws PromocionNoModificableException ERR-M1-005 si ya terminó o ya estaba desactivada.
     */
    public function desactivar(int $idPromocion): array
    {
        $this->comercioDeSesion();

        return DB::transaction(function () use ($idPromocion): array {
            $promocion = $this->buscar($idPromocion, bloquear: true);
            $ahora = Carbon::now();

            if (in_array($promocion->estado($ahora), [Promocion::ESTADO_FINALIZADA, Promocion::ESTADO_DESACTIVADA], true)) {
                throw new PromocionNoModificableException;
            }

            $promocion->forceFill(['activa' => false])->save();

            return $promocion->load('variaciones')->aRespuesta($ahora);
        });
    }

    // ---------------------------------------------------------------
    // Administración
    // ---------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     *
     * @throws DatosInvalidosException ERR-005
     */
    public function listarAdministracion(?string $estado, ?int $idComercio): array
    {
        return $this->listar($estado, $idComercio);
    }

    /**
     * Posición de la promoción en la sección del portal (1 primero). null la
     * manda al final, después de las que tienen posición.
     *
     * @param  array<string, mixed>  $datos  {orden}
     */
    public function ordenar(int $idPromocion, array $datos): array
    {
        $orden = $datos['orden'] ?? null;

        if (! array_key_exists('orden', $datos) || ($orden !== null && (! is_int($orden) || $orden < 1 || $orden > 32767))) {
            $this->exigirValidos(['orden' => 'Es obligatorio: un entero de 1 a 32767, o null para quitar la posición.']);
        }

        $promocion = $this->buscar($idPromocion);
        $promocion->forceFill(['orden' => $orden])->save();

        return $promocion->load('variaciones')->aRespuesta(Carbon::now());
    }

    /**
     * La plataforma oculta una promoción que incumple las políticas: deja de
     * mostrarse y de rebajar el precio.
     *
     * @param  array<string, mixed>  $datos  {motivo}
     */
    public function ocultar(int $idPromocion, array $datos): array
    {
        $motivo = is_string($datos['motivo'] ?? null) ? trim($datos['motivo']) : '';

        if ($motivo === '' || mb_strlen($motivo) > self::LARGO_TEXTO) {
            $this->exigirValidos(['motivo' => 'Es obligatorio y debe tener entre 1 y '.self::LARGO_TEXTO.' caracteres.']);
        }

        $promocion = $this->buscar($idPromocion);
        $promocion->forceFill(['oculta' => true, 'motivo_ocultamiento' => $motivo])->save();

        return $promocion->load('variaciones')->aRespuesta(Carbon::now());
    }

    /**
     * Vuelve a mostrar una promoción oculta; rebaja el precio si sigue vigente.
     */
    public function mostrar(int $idPromocion): array
    {
        $promocion = $this->buscar($idPromocion);
        $promocion->forceFill(['oculta' => false, 'motivo_ocultamiento' => null])->save();

        return $promocion->load('variaciones')->aRespuesta(Carbon::now());
    }

    // ---------------------------------------------------------------
    // Portal y M5
    // ---------------------------------------------------------------

    /**
     * Promociones vigentes y visibles para la sección del portal, en el orden
     * de la plataforma, con el producto (solo publicados y de comercios
     * activos) y el precio desde con la promoción. Con $soloIds, solo esas
     * (las promociones que enlaza una noticia).
     *
     * @param  list<int>|null  $soloIds
     * @return list<array<string, mixed>>
     */
    public function listarVigentes(?array $soloIds = null): array
    {
        if ($soloIds === []) {
            return [];
        }

        $ahora = Carbon::now();
        $promociones = $this->vigentes($ahora)
            ->when($soloIds !== null, fn (Builder $consulta) => $consulta->whereIn('id_promocion', $soloIds))
            ->orderByRaw('orden IS NULL')
            ->orderBy('orden')
            ->orderBy('fecha_inicio')
            ->orderBy('id_promocion')
            ->get();

        if ($promociones->isEmpty()) {
            return [];
        }

        $productos = [];
        foreach ($this->completador->completar($this->catalogo->buscarPorIds($promociones->pluck('id_producto')->unique()->values()->all())) as $producto) {
            $productos[$producto->idProducto] = $producto;
        }

        $resultado = [];

        foreach ($promociones as $promocion) {
            $producto = $productos[$promocion->id_producto] ?? null;

            if ($producto !== null) {
                $resultado[] = [
                    ...$promocion->aRespuesta($ahora),
                    'producto' => $this->resumenConPromocion($producto, $promocion),
                ];
            }
        }

        return $resultado;
    }

    /**
     * Promoción vigente de cada producto (a lo sumo una con alcance producto;
     * las de variaciones también cuentan). Para la búsqueda, los destacados,
     * el micrositio y la ficha.
     *
     * @param  list<int>  $idsProducto
     * @return array<int, list<Promocion>> Por id_producto.
     */
    public function vigentesPorProducto(array $idsProducto): array
    {
        if ($idsProducto === []) {
            return [];
        }

        $porProducto = [];

        foreach ($this->vigentes(Carbon::now())->whereIn('id_producto', array_values(array_unique($idsProducto)))->get() as $promocion) {
            $porProducto[$promocion->id_producto][] = $promocion;
        }

        return $porProducto;
    }

    /**
     * Precio con la promoción vigente de cada variación (contrato 1.4.0,
     * PortalPublicoContract::obtenerPreciosVigentes, lo usa M5). Las que M3
     * no encuentra se omiten.
     *
     * @param  list<int>  $idsVariacion
     * @return array<int, array{id_variacion: int, precio_base: float, precio_final: float, id_promocion: int|null, fin_promocion: string|null}>
     *
     * @throws DatosInvalidosException ERR-005
     */
    public function preciosVigentes(array $idsVariacion): array
    {
        if ($idsVariacion === []) {
            return [];
        }

        if (count($idsVariacion) > self::MAXIMO_VARIACIONES_PRECIOS
            || array_filter($idsVariacion, fn (mixed $id): bool => ! is_int($id) || $id < 1) !== []) {
            $this->exigirValidos(['ids_variacion' => 'Debe tener entre 1 y '.self::MAXIMO_VARIACIONES_PRECIOS.' enteros mayores o iguales a 1.']);
        }

        $variaciones = $this->catalogo->obtenerVariaciones($idsVariacion);
        $promociones = $this->vigentesPorProducto(array_column($variaciones, 'id_producto'));
        $resultado = [];

        foreach ($variaciones as $idVariacion => $variacion) {
            $mejor = $this->mejorPromocion($promociones[$variacion['id_producto']] ?? [], $idVariacion, $variacion['precio']);

            $resultado[$idVariacion] = [
                'id_variacion' => $idVariacion,
                'precio_base' => round($variacion['precio'], 2),
                'precio_final' => $mejor?->precioCon($variacion['precio']) ?? round($variacion['precio'], 2),
                'id_promocion' => $mejor?->id_promocion,
                'fin_promocion' => $mejor?->fecha_fin->toIso8601String(),
            ];
        }

        return $resultado;
    }

    /**
     * Campo promocion de ProductoResumen, o null si no tiene una vigente.
     * precio_promocional es el precio desde con la promoción cuando aplica a
     * todo el producto; con variaciones específicas no se conoce aquí y va null.
     *
     * @param  list<Promocion>  $promociones
     * @return array<string, mixed>|null
     */
    public function promocionDeResumen(ProductoResumenDTO $producto, array $promociones): ?array
    {
        $promocion = $promociones[0] ?? null;

        foreach ($promociones as $candidata) {
            if ($candidata->alcance === Promocion::ALCANCE_PRODUCTO) {
                $promocion = $candidata;
            }
        }

        return $promocion === null ? null : $this->datosPromocion($promocion, $promocion->alcance === Promocion::ALCANCE_PRODUCTO ? $promocion->precioCon($producto->precio) : null);
    }

    /**
     * Campos de promoción de una variación de la ficha, o [] si no tiene.
     *
     * @param  list<Promocion>  $promociones
     * @return array{precio_promocional?: float, id_promocion?: int, fin_promocion?: string}
     */
    public function promocionDeVariacion(VariacionDTO $variacion, array $promociones): array
    {
        $mejor = $this->mejorPromocion($promociones, $variacion->idVariacion, $variacion->precio);

        return $mejor === null ? [] : [
            'precio_promocional' => $mejor->precioCon($variacion->precio),
            'id_promocion' => $mejor->id_promocion,
            'fin_promocion' => $mejor->fecha_fin->toIso8601String(),
        ];
    }

    // ---------------------------------------------------------------
    // Ayudantes
    // ---------------------------------------------------------------

    /**
     * Promociones que rebajan el precio ahora: activas, visibles y dentro de
     * su periodo. Se leen en modo sistema porque el portal y M5 no tienen
     * comercio en la sesión.
     *
     * @return Builder<Promocion>
     */
    private function vigentes(CarbonInterface $ahora): Builder
    {
        return Promocion::query()
            ->withoutGlobalScopes()
            ->with('variaciones')
            ->where('activa', true)
            ->where('oculta', false)
            ->where('fecha_inicio', '<=', $ahora)
            ->where('fecha_fin', '>=', $ahora);
    }

    /**
     * La de menor precio entre las que aplican a la variación (no debería
     * haber más de una, porque no se permiten traslapes).
     *
     * @param  list<Promocion>  $promociones
     */
    private function mejorPromocion(array $promociones, int $idVariacion, float $precio): ?Promocion
    {
        $mejor = null;

        foreach ($promociones as $promocion) {
            if ($promocion->aplicaA($idVariacion) && ($mejor === null || $promocion->precioCon($precio) < $mejor->precioCon($precio))) {
                $mejor = $promocion;
            }
        }

        return $mejor;
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenConPromocion(ProductoResumenDTO $producto, Promocion $promocion): array
    {
        return [
            'id_producto' => $producto->idProducto,
            'nombre' => $producto->nombre,
            'imagen_principal' => $producto->imagenPrincipal,
            'precio' => $producto->precio,
            'precio_hasta' => $producto->precioHasta,
            'moneda' => $producto->moneda,
            'disponibilidad' => $producto->disponibilidad->value,
            'comercio' => [
                'id_comercio' => $producto->idComercio,
                'nombre_comercial' => $producto->nombreComercio,
            ],
            'promocion' => $this->datosPromocion($promocion, $promocion->alcance === Promocion::ALCANCE_PRODUCTO ? $promocion->precioCon($producto->precio) : null),
        ];
    }

    /**
     * Esquema PromocionResumen.
     *
     * @return array<string, mixed>
     */
    private function datosPromocion(Promocion $promocion, ?float $precioPromocional): array
    {
        return [
            'id_promocion' => $promocion->id_promocion,
            'titulo' => $promocion->titulo,
            'tipo' => $promocion->tipo,
            'valor' => (float) $promocion->valor,
            'alcance' => $promocion->alcance,
            'precio_promocional' => $precioPromocional,
            'fecha_fin' => $promocion->fecha_fin->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listar(?string $estado, ?int $idComercio): array
    {
        if ($estado !== null && ! in_array($estado, Promocion::ESTADOS, true)) {
            $this->exigirValidos(['estado' => 'Debe ser '.implode(', ', Promocion::ESTADOS).'.']);
        }

        $ahora = Carbon::now();

        return Promocion::query()
            ->with('variaciones')
            ->when($idComercio !== null, fn (Builder $consulta) => $consulta->where('id_comercio', $idComercio))
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id_promocion')
            ->get()
            ->filter(fn (Promocion $promocion): bool => $estado === null || $promocion->estado($ahora) === $estado)
            ->map(fn (Promocion $promocion): array => $promocion->aRespuesta($ahora))
            ->values()
            ->all();
    }

    /**
     * Una iniciada solo acepta fecha_fin, que debe adelantar el fin sin
     * quedar en el pasado.
     *
     * @param  array<string, mixed>  $datos
     */
    private function adelantarFin(Promocion $promocion, array $datos, CarbonInterface $ahora): void
    {
        $otros = array_diff(array_keys($datos), ['fecha_fin']);

        if ($otros !== []) {
            $this->exigirValidos(array_fill_keys(array_values($otros), 'Una promoción iniciada solo permite adelantar fecha_fin o desactivarse.'));
        }

        $fin = $this->fechaHora($datos['fecha_fin'] ?? null);

        if ($fin === null) {
            $this->exigirValidos(['fecha_fin' => 'Es obligatoria, con fecha y hora (ISO 8601).']);
        } elseif ($fin->greaterThan($promocion->fecha_fin) || $fin->lessThan($ahora)) {
            $this->exigirValidos(['fecha_fin' => 'Solo se puede adelantar: entre el momento actual y el fin vigente.']);
        }

        $promocion->forceFill(['fecha_fin' => $fin])->save();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{titulo: string, descripcion: ?string, tipo: string, valor: float, alcance: string, variaciones: list<int>, fecha_inicio: Carbon, fecha_fin: Carbon}
     */
    private function validarDatos(array $datos, FichaProductoDTO $producto, bool $exigirFinFuturo): array
    {
        $errores = [];

        $titulo = is_string($datos['titulo'] ?? null) ? trim($datos['titulo']) : '';
        if ($titulo === '' || mb_strlen($titulo) > self::LARGO_TITULO) {
            $errores['titulo'] = 'Es obligatorio y debe tener entre 1 y '.self::LARGO_TITULO.' caracteres.';
        }

        $descripcion = $datos['descripcion'] ?? null;
        if ($descripcion !== null && (! is_string($descripcion) || mb_strlen($descripcion) > self::LARGO_TEXTO)) {
            $errores['descripcion'] = 'Debe ser texto de hasta '.self::LARGO_TEXTO.' caracteres.';
        }

        $tipo = $datos['tipo'] ?? null;
        if (! in_array($tipo, [Promocion::TIPO_PORCENTAJE, Promocion::TIPO_PRECIO_FIJO], true)) {
            $errores['tipo'] = 'Debe ser porcentaje o precio_fijo.';
        }

        $alcance = $datos['alcance'] ?? null;
        if (! in_array($alcance, [Promocion::ALCANCE_PRODUCTO, Promocion::ALCANCE_VARIACIONES], true)) {
            $errores['alcance'] = 'Debe ser producto o variaciones.';
        }

        $preciosDelProducto = [];
        foreach ($producto->variaciones as $variacion) {
            $preciosDelProducto[$variacion->idVariacion] = $variacion->precio;
        }

        $variaciones = [];
        if ($alcance === Promocion::ALCANCE_VARIACIONES) {
            $lista = $datos['variaciones'] ?? null;

            if (! is_array($lista) || $lista === [] || ! array_is_list($lista)
                || array_filter($lista, fn (mixed $id): bool => ! is_int($id)) !== []
                || count(array_unique($lista)) !== count($lista)) {
                $errores['variaciones'] = 'Con alcance variaciones, es una lista sin repetidos de id_variacion del producto.';
            } elseif (array_diff($lista, array_keys($preciosDelProducto)) !== []) {
                $errores['variaciones'] = 'Todas deben ser variaciones del producto.';
            } else {
                $variaciones = array_values($lista);
            }
        } elseif (! empty($datos['variaciones'])) {
            $errores['variaciones'] = 'Solo se indican con alcance variaciones.';
        }

        $valor = $datos['valor'] ?? null;
        if (! is_int($valor) && ! is_float($valor)) {
            $errores['valor'] = 'Es obligatorio y debe ser un número.';
        } elseif (round($valor, 2) != $valor || $valor <= 0) {
            $errores['valor'] = 'Debe ser mayor que 0 y tener como máximo 2 decimales.';
        } elseif ($tipo === Promocion::TIPO_PORCENTAJE && $valor >= 100) {
            $errores['valor'] = 'Un porcentaje debe ser menor que 100.';
        } elseif ($tipo === Promocion::TIPO_PRECIO_FIJO && ! isset($errores['variaciones'])) {
            $afectadas = $alcance === Promocion::ALCANCE_VARIACIONES ? array_intersect_key($preciosDelProducto, array_flip($variaciones)) : $preciosDelProducto;

            if ($afectadas !== [] && $valor >= min($afectadas)) {
                $errores['valor'] = 'El precio fijo debe ser menor que el precio normal de cada variación afectada ('.number_format(min($afectadas), 2, '.', '').').';
            }
        }

        $inicio = $this->fechaHora($datos['fecha_inicio'] ?? null);
        $fin = $this->fechaHora($datos['fecha_fin'] ?? null);

        if ($inicio === null) {
            $errores['fecha_inicio'] = 'Es obligatoria, con fecha y hora (ISO 8601).';
        }

        if ($fin === null) {
            $errores['fecha_fin'] = 'Es obligatoria, con fecha y hora (ISO 8601).';
        } elseif ($inicio !== null && $fin->lessThanOrEqualTo($inicio)) {
            $errores['fecha_fin'] = 'Debe ser posterior a fecha_inicio.';
        } elseif ($exigirFinFuturo && $fin->lessThanOrEqualTo(Carbon::now())) {
            $errores['fecha_fin'] = 'Debe ser posterior al momento actual.';
        }

        $this->exigirValidos($errores);

        return [
            'titulo' => $titulo,
            'descripcion' => is_string($descripcion) && trim($descripcion) !== '' ? trim($descripcion) : null,
            'tipo' => $tipo,
            'valor' => (float) $valor,
            'alcance' => $alcance,
            'variaciones' => $variaciones,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
        ];
    }

    /**
     * Un mismo producto o variación no puede tener dos promociones activas
     * que se traslapen: choca con cualquier activa del producto en esas
     * fechas si alguna de las dos es del producto completo o comparten una
     * variación. Una oculta sigue contando, porque puede volver a mostrarse.
     *
     * @param  list<int>  $variaciones
     */
    private function exigirSinTraslape(int $idProducto, string $alcance, array $variaciones, CarbonInterface $inicio, CarbonInterface $fin, ?int $excepto): void
    {
        /** @var Collection<int, Promocion> $existentes */
        $existentes = Promocion::query()
            ->withoutGlobalScopes()
            ->with('variaciones')
            ->where('id_producto', $idProducto)
            ->where('activa', true)
            ->where('fecha_inicio', '<=', $fin)
            ->where('fecha_fin', '>=', $inicio)
            ->when($excepto !== null, fn (Builder $consulta) => $consulta->where('id_promocion', '<>', $excepto))
            ->get();

        foreach ($existentes as $existente) {
            if ($alcance === Promocion::ALCANCE_PRODUCTO
                || $existente->alcance === Promocion::ALCANCE_PRODUCTO
                || array_intersect($variaciones, $existente->idsVariacion()) !== []) {
                throw new TraslapePromocionException;
            }
        }
    }

    /**
     * @param  list<int>  $variaciones
     */
    private function guardarVariaciones(Promocion $promocion, array $variaciones): void
    {
        foreach ($variaciones as $idVariacion) {
            PromocionVariacion::query()->create([
                'id_promocion' => $promocion->id_promocion,
                'id_variacion' => $idVariacion,
            ]);
        }
    }

    /**
     * Producto del comercio, desde M3. Uno ajeno responde igual que uno
     * inexistente, para no revelar productos de otros comercios.
     */
    private function productoDelComercio(int $idProducto, int $idComercio): FichaProductoDTO
    {
        $producto = $this->catalogo->obtenerProducto($idProducto);

        if ($producto->comercio->idComercio !== $idComercio) {
            throw new RecursoNoEncontradoException('El producto no existe o no pertenece al comercio.');
        }

        return $producto;
    }

    /**
     * Promoción del ámbito actual (la del comercio de la sesión, o cualquiera
     * para la administración con acceso auditado).
     */
    private function buscar(int $idPromocion, bool $bloquear = false): Promocion
    {
        return Promocion::query()
            ->when($bloquear, fn (Builder $consulta) => $consulta->lockForUpdate())
            ->find($idPromocion)
            ?? throw new RecursoNoEncontradoException('La promoción no existe.');
    }

    /**
     * Serializa las promociones de un mismo producto dentro de la transacción
     * para que dos simultáneas no se traslapen (bloqueo consultivo de
     * PostgreSQL; en otras bases las pruebas corren en serie).
     */
    private function bloquearProducto(int $idProducto): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [1002, $idProducto]);
        }
    }

    private function comercioDeSesion(): int
    {
        return $this->contexto->idActual() ?? throw ErrorNucleoException::recursoNoAccesible();
    }

    private function fechaHora(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $valor)) {
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
     */
    private function exigirValidos(array $errores): void
    {
        if ($errores !== []) {
            throw new DatosInvalidosException($errores);
        }
    }
}
