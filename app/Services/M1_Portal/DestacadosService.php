<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\ConflictoDestacadoException;
use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\LimiteDestacadosException;
use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M1_Portal\CargoDestacado;
use App\Models\M1_Portal\ProductoDestacado;
use App\Models\M1_Portal\SolicitudDestacado;
use App\Models\M1_Portal\TarifaDestacado;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use App\Services\Nucleo\ContextoComercio;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Solicitud, aprobación y cobro de productos destacados (contrato M1 1.4.0,
 * SC-08):
 * - El comercio solicita destacar uno de sus productos por 7, 15 o 30 días.
 *   Se guarda la tarifa vigente al solicitar, que es la que se cobra.
 * - La administración aprueba (el periodo empieza al aprobar y se genera el
 *   cargo) o rechaza con un motivo. También destaca productos por su cuenta,
 *   sin costo, con fechas libres.
 * - Cada comercio tiene como máximo 3 destacados activos al mismo tiempo,
 *   contando los pagados y los de la administración.
 * - Un mismo producto no puede estar destacado dos veces en el mismo periodo.
 *
 * El aislamiento por comercio (RN-12) lo aplican los modelos con
 * PerteneceAComercio; el comercio de la sesión se toma de ContextoComercio,
 * nunca del cuerpo de la petición.
 */
class DestacadosService
{
    private const LARGO_MAXIMO_MOTIVO = 500;

    private const MONTO_MAXIMO = 9_999_999_999.99;

    public function __construct(
        private CatalogoClient $catalogo,
        private ContextoComercio $contexto,
    ) {}

    // ---------------------------------------------------------------
    // Tarifas
    // ---------------------------------------------------------------

    /**
     * Tarifas definidas, por periodo.
     *
     * @return list<array<string, mixed>>
     */
    public function listarTarifas(): array
    {
        return TarifaDestacado::query()
            ->orderBy('periodo_dias')
            ->get()
            ->map(fn (TarifaDestacado $tarifa): array => $tarifa->aRespuesta())
            ->values()
            ->all();
    }

    /**
     * Crea o reemplaza la tarifa de un periodo. No cambia las solicitudes ya
     * hechas, que conservan la tarifa con la que se solicitaron.
     *
     * @param  array<string, mixed>  $datos  {monto}
     *
     * @throws DatosInvalidosException ERR-005
     */
    public function definirTarifa(int $periodoDias, array $datos, int $idUsuario): array
    {
        $errores = [];

        if (! in_array($periodoDias, TarifaDestacado::PERIODOS, true)) {
            $errores['periodo_dias'] = 'Debe ser 7, 15 o 30.';
        }

        $monto = $datos['monto'] ?? null;

        if (! is_int($monto) && ! is_float($monto)) {
            $errores['monto'] = 'Es obligatorio y debe ser un número.';
        } elseif ($monto < 0 || $monto > self::MONTO_MAXIMO || round($monto, 2) != $monto) {
            $errores['monto'] = 'Debe ser mayor o igual a 0 y tener como máximo 2 decimales.';
        }

        $this->exigirValidos($errores);

        $tarifa = TarifaDestacado::query()->updateOrCreate(
            ['periodo_dias' => $periodoDias],
            ['monto' => $monto, 'id_usuario_actualiza' => $idUsuario],
        );

        return $tarifa->aRespuesta();
    }

    // ---------------------------------------------------------------
    // Comercio: solicitudes
    // ---------------------------------------------------------------

    /**
     * El comercio de la sesión solicita destacar uno de sus productos.
     *
     * @param  array<string, mixed>  $datos  {id_producto, periodo_dias}
     *
     * @throws DatosInvalidosException ERR-005 datos inválidos o periodo sin tarifa.
     * @throws RecursoNoEncontradoException ERR-004 si el producto no existe, no está publicado o es de otro comercio.
     * @throws ConflictoDestacadoException ERR-M1-003 si ya tiene una solicitud pendiente o está destacado.
     */
    public function solicitar(array $datos, int $idUsuario): array
    {
        $idComercio = $this->comercioDeSesion();
        $errores = [];

        $idProducto = $datos['id_producto'] ?? null;
        if (! is_int($idProducto) || $idProducto < 1) {
            $errores['id_producto'] = 'Es obligatorio y debe ser un entero mayor o igual a 1.';
        }

        $periodo = $datos['periodo_dias'] ?? null;
        if (! in_array($periodo, TarifaDestacado::PERIODOS, true)) {
            $errores['periodo_dias'] = 'Debe ser 7, 15 o 30.';
        }

        $this->exigirValidos($errores);

        $tarifa = TarifaDestacado::query()->where('periodo_dias', $periodo)->first();
        if ($tarifa === null) {
            $this->exigirValidos(['periodo_dias' => 'La administración todavía no definió la tarifa de este periodo.']);
        }

        // M3 dice de qué comercio es el producto. Uno ajeno responde igual que
        // uno inexistente, para no revelar productos de otros comercios.
        if ($this->catalogo->obtenerProducto($idProducto)->comercio->idComercio !== $idComercio) {
            throw new RecursoNoEncontradoException('El producto no existe o no pertenece al comercio.');
        }

        $ahora = Carbon::now();

        if ($this->productoDestacadoEntre($idProducto, $ahora, $ahora)) {
            throw new ConflictoDestacadoException('El producto ya está destacado.');
        }

        try {
            $solicitud = SolicitudDestacado::query()->create([
                'id_comercio' => $idComercio,
                'id_producto' => $idProducto,
                'periodo_dias' => $periodo,
                'monto' => $tarifa->monto,
                'moneda' => $tarifa->moneda,
                'id_usuario_solicita' => $idUsuario,
            ]);
        } catch (UniqueConstraintViolationException $error) {
            throw new ConflictoDestacadoException('El producto ya tiene una solicitud pendiente.', $error);
        }

        // Respaldo para bases sin el índice único parcial (pruebas en SQLite).
        $pendientes = SolicitudDestacado::query()
            ->where('id_producto', $idProducto)
            ->where('estado', SolicitudDestacado::ESTADO_PENDIENTE)
            ->count();

        if ($pendientes > 1) {
            $solicitud->delete();

            throw new ConflictoDestacadoException('El producto ya tiene una solicitud pendiente.');
        }

        return $solicitud->refresh()->aRespuesta();
    }

    /**
     * Solicitudes del comercio de la sesión, de la más reciente a la más antigua.
     *
     * @return list<array<string, mixed>>
     *
     * @throws DatosInvalidosException ERR-005 si el estado no es válido.
     */
    public function listarSolicitudesDelComercio(?string $estado): array
    {
        $this->comercioDeSesion();

        return $this->listar($estado, null);
    }

    // ---------------------------------------------------------------
    // Administración
    // ---------------------------------------------------------------

    /**
     * Todas las solicitudes, opcionalmente por estado y comercio.
     *
     * @return list<array<string, mixed>>
     *
     * @throws DatosInvalidosException ERR-005
     */
    public function listarSolicitudes(?string $estado, ?int $idComercio): array
    {
        return $this->listar($estado, $idComercio);
    }

    /**
     * Aprueba una solicitud pendiente: crea el destacado desde ahora por el
     * periodo solicitado y el cargo con la tarifa de la solicitud.
     *
     * @throws RecursoNoEncontradoException ERR-004 si la solicitud no existe.
     * @throws ConflictoDestacadoException ERR-M1-003 si ya fue resuelta o el producto ya está destacado.
     * @throws LimiteDestacadosException ERR-M1-002 si el comercio ya tiene 3 destacados activos en ese periodo.
     */
    public function aprobar(int $idSolicitud, int $idUsuario): array
    {
        return DB::transaction(function () use ($idSolicitud, $idUsuario): array {
            $solicitud = $this->solicitudPendiente($idSolicitud);
            $this->bloquearComercio($solicitud->id_comercio);

            $inicio = Carbon::now();
            $fin = $inicio->copy()->addDays($solicitud->periodo_dias);

            $this->exigirEspacio($solicitud->id_comercio, $solicitud->id_producto, $inicio, $fin);

            $destacado = ProductoDestacado::query()->create([
                'id_producto' => $solicitud->id_producto,
                'id_comercio' => $solicitud->id_comercio,
                'origen' => ProductoDestacado::ORIGEN_SOLICITUD,
                'id_solicitud_destacado' => $solicitud->id_solicitud_destacado,
                'posicion' => $this->siguientePosicion(),
                'fecha_inicio' => $inicio,
                'fecha_fin' => $fin,
                'activo' => true,
            ]);

            if ((float) $solicitud->monto > 0) {
                CargoDestacado::query()->create([
                    'id_comercio' => $solicitud->id_comercio,
                    'id_solicitud_destacado' => $solicitud->id_solicitud_destacado,
                    'id_producto_destacado' => $destacado->id_producto_destacado,
                    'id_producto' => $solicitud->id_producto,
                    'periodo_dias' => $solicitud->periodo_dias,
                    'monto' => $solicitud->monto,
                    'moneda' => $solicitud->moneda,
                    'fecha_cargo' => $inicio,
                    'fecha_inicio' => $inicio,
                    'fecha_fin' => $fin,
                ]);
            }

            $solicitud->forceFill([
                'estado' => SolicitudDestacado::ESTADO_APROBADA,
                'id_usuario_resuelve' => $idUsuario,
                'fecha_resolucion' => $inicio,
            ])->save();

            return $solicitud->aRespuesta($destacado);
        });
    }

    /**
     * Rechaza una solicitud pendiente con un motivo para el comercio.
     *
     * @param  array<string, mixed>  $datos  {motivo}
     *
     * @throws DatosInvalidosException ERR-005 si falta el motivo.
     * @throws RecursoNoEncontradoException ERR-004
     * @throws ConflictoDestacadoException ERR-M1-003 si ya fue resuelta.
     */
    public function rechazar(int $idSolicitud, array $datos, int $idUsuario): array
    {
        $motivo = is_string($datos['motivo'] ?? null) ? trim($datos['motivo']) : '';

        if ($motivo === '' || mb_strlen($motivo) > self::LARGO_MAXIMO_MOTIVO) {
            $this->exigirValidos(['motivo' => 'Es obligatorio y debe tener entre 1 y '.self::LARGO_MAXIMO_MOTIVO.' caracteres.']);
        }

        return DB::transaction(function () use ($idSolicitud, $motivo, $idUsuario): array {
            $solicitud = $this->solicitudPendiente($idSolicitud);

            $solicitud->forceFill([
                'estado' => SolicitudDestacado::ESTADO_RECHAZADA,
                'motivo_rechazo' => $motivo,
                'id_usuario_resuelve' => $idUsuario,
                'fecha_resolucion' => Carbon::now(),
            ])->save();

            return $solicitud->aRespuesta();
        });
    }

    /**
     * La administración destaca un producto por su cuenta (por ejemplo, para
     * una campaña), sin costo y con fechas libres.
     *
     * @param  array<string, mixed>  $datos  {id_producto, fecha_inicio, fecha_fin}
     *
     * @throws DatosInvalidosException ERR-005
     * @throws RecursoNoEncontradoException ERR-004 si el producto no existe o no está publicado.
     * @throws ConflictoDestacadoException ERR-M1-003 si el producto ya está destacado en esas fechas.
     * @throws LimiteDestacadosException ERR-M1-002 si su comercio ya tiene 3 destacados activos en esas fechas.
     */
    public function destacarPorPlataforma(array $datos): array
    {
        $errores = [];

        $idProducto = $datos['id_producto'] ?? null;
        if (! is_int($idProducto) || $idProducto < 1) {
            $errores['id_producto'] = 'Es obligatorio y debe ser un entero mayor o igual a 1.';
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
        } elseif ($fin->lessThanOrEqualTo(Carbon::now())) {
            $errores['fecha_fin'] = 'Debe ser posterior al momento actual.';
        }

        $this->exigirValidos($errores);

        $idComercio = $this->catalogo->obtenerProducto($idProducto)->comercio->idComercio;

        return DB::transaction(function () use ($idProducto, $idComercio, $inicio, $fin): array {
            $this->bloquearComercio($idComercio);
            $this->exigirEspacio($idComercio, $idProducto, $inicio, $fin);

            $destacado = ProductoDestacado::query()->create([
                'id_producto' => $idProducto,
                'id_comercio' => $idComercio,
                'origen' => ProductoDestacado::ORIGEN_PLATAFORMA,
                'posicion' => $this->siguientePosicion(),
                'fecha_inicio' => $inicio,
                'fecha_fin' => $fin,
                'activo' => true,
            ]);

            return $this->destacadoARespuesta($destacado);
        });
    }

    // ---------------------------------------------------------------
    // Cargos (ComercialPortalContract)
    // ---------------------------------------------------------------

    /**
     * Cargos de un comercio con fecha de cargo entre $desde y $hasta (días
     * completos, ambos incluidos). Lo consulta el backend de otro módulo, sin
     * comercio en la sesión, así que se lee en modo sistema con el filtro
     * explícito por $idComercio.
     *
     * @return list<array<string, mixed>>
     *
     * @throws DatosInvalidosException ERR-005
     */
    public function listarCargos(int $idComercio, string $desde, string $hasta): array
    {
        $errores = [];

        if ($idComercio < 1) {
            $errores['id_comercio'] = 'Debe ser un entero mayor o igual a 1.';
        }

        $inicio = $this->fecha($desde);
        $fin = $this->fecha($hasta);

        if ($inicio === null) {
            $errores['desde'] = 'Debe tener el formato AAAA-MM-DD.';
        }

        if ($fin === null) {
            $errores['hasta'] = 'Debe tener el formato AAAA-MM-DD.';
        } elseif ($inicio !== null && $fin->lessThan($inicio)) {
            $errores['hasta'] = 'No puede ser anterior a desde.';
        }

        $this->exigirValidos($errores);

        return $this->contexto->ejecutarComoSistema(fn (): array => CargoDestacado::query()
            ->where('id_comercio', $idComercio)
            ->whereBetween('fecha_cargo', [$inicio->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->orderBy('fecha_cargo')
            ->orderBy('id_cargo_destacado')
            ->get()
            ->map(fn (CargoDestacado $cargo): array => $cargo->aRespuesta())
            ->values()
            ->all());
    }

    // ---------------------------------------------------------------
    // Ayudantes
    // ---------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function listar(?string $estado, ?int $idComercio): array
    {
        if ($estado !== null && ! in_array($estado, SolicitudDestacado::ESTADOS, true)) {
            $this->exigirValidos(['estado' => 'Debe ser pendiente, aprobada o rechazada.']);
        }

        $solicitudes = SolicitudDestacado::query()
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado))
            ->when($idComercio !== null, fn ($consulta) => $consulta->where('id_comercio', $idComercio))
            ->orderByDesc('created_at')
            ->orderByDesc('id_solicitud_destacado')
            ->get();

        $destacados = ProductoDestacado::query()
            ->whereIn('id_solicitud_destacado', $solicitudes->pluck('id_solicitud_destacado'))
            ->get()
            ->keyBy('id_solicitud_destacado');

        return $solicitudes
            ->map(fn (SolicitudDestacado $solicitud): array => $solicitud->aRespuesta($destacados->get($solicitud->id_solicitud_destacado)))
            ->values()
            ->all();
    }

    /**
     * Comercio de la sesión (EstablecerComercio). Sin comercio, ERR-003.
     */
    private function comercioDeSesion(): int
    {
        return $this->contexto->idActual() ?? throw ErrorNucleoException::recursoNoAccesible();
    }

    /**
     * Solicitud bloqueada para resolverla. Debe estar pendiente.
     */
    private function solicitudPendiente(int $idSolicitud): SolicitudDestacado
    {
        $solicitud = SolicitudDestacado::query()->lockForUpdate()->find($idSolicitud)
            ?? throw new RecursoNoEncontradoException('La solicitud no existe.');

        if ($solicitud->estado !== SolicitudDestacado::ESTADO_PENDIENTE) {
            throw new ConflictoDestacadoException('La solicitud ya fue resuelta.');
        }

        return $solicitud;
    }

    /**
     * Serializa las aprobaciones y destacados de un mismo comercio dentro de
     * la transacción, para que dos operaciones simultáneas no pasen juntas el
     * máximo de 3 (bloqueo consultivo de PostgreSQL; en otras bases las
     * pruebas corren en serie).
     */
    private function bloquearComercio(int $idComercio): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [1001, $idComercio]);
        }
    }

    /**
     * El producto no puede estar destacado en las mismas fechas y el
     * comercio no puede pasar de 3 destacados activos en ese periodo.
     */
    private function exigirEspacio(int $idComercio, int $idProducto, CarbonInterface $inicio, CarbonInterface $fin): void
    {
        if ($this->productoDestacadoEntre($idProducto, $inicio, $fin)) {
            throw new ConflictoDestacadoException('El producto ya está destacado en ese periodo.');
        }

        $activos = $this->entre(ProductoDestacado::query()->where('id_comercio', $idComercio), $inicio, $fin)->count();

        if ($activos >= ReglasDestacados::MAXIMO_POR_COMERCIO) {
            throw new LimiteDestacadosException;
        }
    }

    private function productoDestacadoEntre(int $idProducto, CarbonInterface $inicio, CarbonInterface $fin): bool
    {
        return $this->entre(ProductoDestacado::query()->where('id_producto', $idProducto), $inicio, $fin)->exists();
    }

    /**
     * Destacados activos cuyo periodo se cruza con [$inicio, $fin].
     *
     * @param  Builder<ProductoDestacado>  $consulta
     * @return Builder<ProductoDestacado>
     */
    private function entre(Builder $consulta, CarbonInterface $inicio, CarbonInterface $fin): Builder
    {
        return $consulta
            ->where('activo', true)
            ->where('fecha_inicio', '<=', $fin)
            ->where('fecha_fin', '>=', $inicio);
    }

    private function siguientePosicion(): int
    {
        return min(32767, ((int) ProductoDestacado::query()->max('posicion')) + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function destacadoARespuesta(ProductoDestacado $destacado): array
    {
        return [
            'id_producto_destacado' => $destacado->id_producto_destacado,
            'id_producto' => $destacado->id_producto,
            'id_comercio' => $destacado->id_comercio,
            'origen' => $destacado->origen,
            'fecha_inicio' => $destacado->fecha_inicio->toIso8601String(),
            'fecha_fin' => $destacado->fecha_fin->toIso8601String(),
        ];
    }

    private function fechaHora(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $valor)) {
            return null;
        }

        // Se pasa a la zona de la aplicación (UTC) antes de guardar: las columnas timestamp no guardan
        // el desfase, así que un "2026-11-20T08:00:00-06:00" quedaría como 08:00 UTC, seis horas antes.
        try {
            return Carbon::parse($valor)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    private function fecha(string $valor): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        try {
            $fecha = Carbon::createFromFormat('!Y-m-d', $valor);
        } catch (Throwable) {
            return null;
        }

        return $fecha instanceof Carbon && $fecha->format('Y-m-d') === $valor ? $fecha : null;
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
