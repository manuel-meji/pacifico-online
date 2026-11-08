<?php

namespace App\Services\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\CatalogoDependencia;
use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Enums\M4_Inventario\TipoMovimiento;
use App\Events\M4_Inventario\ExistenciaAgotada;
use App\Events\M4_Inventario\ExistenciaRepuesta;
use App\Events\M4_Inventario\ExistenciaReservada;
use App\Models\M4_Inventario\AjusteInventario;
use App\Models\M4_Inventario\Existencia;
use App\Services\M4_Inventario\DTO\AjusteDTO;
use App\Services\M4_Inventario\DTO\FiltroKardex;
use App\Services\M4_Inventario\DTO\RegistroMovimientoDTO;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\M4_Inventario\Repositorios\ExistenciaRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Implementación de InventarioModuleInterface (contrato openapi v1.3.0).
 * Despliegue mínimo del PC3: verificación de estado, consultas de disponibilidad,
 * reserva atómica bajo bloqueo pesimista SELECT … FOR UPDATE (ADR-002) y
 * liberación. Las demás operaciones del contrato se implementan en el PC4;
 * mientras tanto lanzan LogicException para que ningún consumidor reciba una
 * respuesta inventada.
 *
 * Usa las tablas de las migraciones 2026_10_21_00000x_m4_* (sección 6.5):
 *  - existencias: disponible = cantidad_fisica − cantidad_reservada (no se maneja stock defectuoso).
 *  - reservas: estado en id_estado_reserva (1 activa, 2 confirmada, 3 consumida,
 *    4 liberada, 5 vencida); el trigger tr_reservas_transicion solo permite
 *    1→2, 1→4, 1→5, 2→3 y 2→4. Cada cambio de estado se agrega a historial.
 *
 * Las dependencias de M2 y M3 llegan por interfaz y pueden ser el doble o el
 * servicio real según config/integraciones.php (ver M4IntegracionesServiceProvider).
 *
 * RN-12 (PC4): cada operación corre dentro de NucleoDependencia::ejecutarEnAmbito y
 * lee las existencias por ExistenciaRepositoryInterface, que usa los modelos con el
 * Global Scope del núcleo (PerteneceAComercio). El filtro por comercio lo aplica el
 * núcleo, no una condición opcional de M4.
 */
class InventarioService implements InventarioModuleInterface
{
    private const MAXIMO_LOTE = 100;

    public const ESTADOS = [1 => 'activa', 2 => 'confirmada', 3 => 'consumida', 4 => 'liberada', 5 => 'vencida'];

    private const ACTIVA = 1;

    private const CONSUMIDA = 3;

    private const LIBERADA = 4;

    private const VENCIDA = 5;

    private const DISPONIBLE_SQL = 'cantidad_fisica - cantidad_reservada';

    /** @var array<int, string|null> SKU por variación, consultado a M3 una vez por petición. */
    private array $skus = [];

    public function __construct(
        private readonly ComerciosDependencia $comercios,
        private readonly CatalogoDependencia $catalogo,
        private readonly NucleoDependencia $nucleo,
        private readonly ExistenciaRepositoryInterface $existencias,
        private readonly KardexService $kardex,
        private readonly RegistroMovimientoService $movimientos,
        private readonly AjusteService $ajustes,
        private readonly PublicadorDisponibilidad $publicador,
    ) {}

    // ------------------------------------------------------------------ estado

    public function verificarEstado(): array
    {
        return ['status' => 'ok', 'module' => 'm4-inventario'];
    }

    /**
     * RN-12: con sesión de comercio, un recurso de otro comercio responde ERR-003 (404).
     * Sin sesión de comercio (M1, M5, procesos del sistema) no se filtra.
     */
    private function exigirComercioDeSesion(int $idComercioRecurso): void
    {
        $comercioSesion = $this->nucleo->comercioActual();

        if ($comercioSesion !== null && $comercioSesion !== $idComercioRecurso) {
            throw ErrorInventarioException::recursoNoAccesible();
        }
    }

    // ------------------------------------------------------------------ disponibilidad

    public function consultarStockDisponible(int $idComercio, int $idVariacion, ?int $idUbicacion = null): array
    {
        $this->exigirPositivos(['id_comercio' => $idComercio, 'id_variacion' => $idVariacion]
            + ($idUbicacion !== null ? ['id_ubicacion' => $idUbicacion] : []));

        $resumen = $this->nucleo->ejecutarEnAmbito(
            fn (): array => $this->existencias->resumenVariacion($idComercio, $idVariacion, $idUbicacion),
            publica: true,
        );

        return [
            'id_comercio' => $idComercio,
            'id_variacion' => $idVariacion,
            'id_ubicacion' => $idUbicacion,
            'cantidad_disponible' => $resumen['disponible'],
            'estado_disponibilidad' => $resumen['disponible'] > 0 ? 'disponible' : 'agotado',
            'bajo_stock' => $resumen['filas'] > 0 && $resumen['disponible'] <= $resumen['minimo'],
        ];
    }

    public function consultarDisponibilidadLote(array $items): array
    {
        $this->validarLote($items, 'id_variacion', permiteUbicacion: true);

        return array_map(fn (array $item): array => $this->consultarStockDisponible(
            $item['id_comercio'],
            $item['id_variacion'],
            $item['id_ubicacion'] ?? null,
        ), array_values($items));
    }

    public function consultarDisponibilidadProducto(int $idComercio, int $idProducto): array
    {
        $this->exigirPositivos(['id_comercio' => $idComercio, 'id_producto' => $idProducto]);

        $disponible = $this->nucleo->ejecutarEnAmbito(
            fn (): int => $this->existencias->disponibleProducto($idComercio, $idProducto),
            publica: true,
        );

        return [
            'id_comercio' => $idComercio,
            'id_producto' => $idProducto,
            'estado_disponibilidad' => $disponible > 0 ? 'disponible' : 'agotado',
        ];
    }

    public function consultarDisponibilidadProductoLote(array $items): array
    {
        $this->validarLote($items, 'id_producto', permiteUbicacion: false);

        return array_map(fn (array $item): array => $this->consultarDisponibilidadProducto(
            $item['id_comercio'],
            $item['id_producto'],
        ), array_values($items));
    }

    // ------------------------------------------------------------------ reservas

    public function reservarExistencias(
        int $idComercio,
        int $idVariacion,
        int $cantidad,
        ?int $idUbicacion = null,
        ?string $claveIdempotencia = null,
        ?int $expiracionMinutos = null,
    ): array {
        return $this->nucleo->ejecutarEnAmbito(fn (): array => $this->reservarEnAmbito(
            $idComercio, $idVariacion, $cantidad, $idUbicacion, $claveIdempotencia, $expiracionMinutos,
        ));
    }

    private function reservarEnAmbito(
        int $idComercio,
        int $idVariacion,
        int $cantidad,
        ?int $idUbicacion,
        ?string $claveIdempotencia,
        ?int $expiracionMinutos,
    ): array {
        $this->exigirPositivos(['id_comercio' => $idComercio, 'id_variacion' => $idVariacion, 'cantidad' => $cantidad]
            + ($idUbicacion !== null ? ['id_ubicacion' => $idUbicacion] : [])
            + ($expiracionMinutos !== null ? ['expiracion_minutos' => $expiracionMinutos] : []));

        if ($claveIdempotencia !== null && ($claveIdempotencia === '' || strlen($claveIdempotencia) > 100)) {
            throw ErrorInventarioException::datosInvalidos(['Idempotency-Key' => 'Debe tener entre 1 y 100 caracteres.']);
        }

        if ($claveIdempotencia !== null && ($previa = $this->reservaPorClave($idComercio, $claveIdempotencia)) !== null) {
            return $this->repetirReserva($previa, $idVariacion, $cantidad);
        }

        if (! ($this->comercios->obtenerComercioActivo($idComercio)['activo'] ?? false)) {
            throw ErrorInventarioException::comercioNoHabilitado();
        }

        $variacion = $this->catalogo->obtenerVariacion($idVariacion);

        if ($variacion === null) {
            throw ErrorInventarioException::variacionNoEncontrada();
        }

        if ($variacion['id_comercio'] !== $idComercio) {
            throw ErrorInventarioException::recursoNoAccesible();
        }

        $habilitadas = $this->ubicacionesHabilitadas($idComercio);

        if ($idUbicacion !== null && ! in_array($idUbicacion, $habilitadas, true)) {
            throw ErrorInventarioException::ubicacionNoEncontrada();
        }

        $minutos = $expiracionMinutos ?? (int) config('integraciones.m4.reserva_minutos', 15);
        $clave = $claveIdempotencia ?? (string) Str::uuid();

        try {
            [$idReserva, $antes, $despues] = DB::transaction(fn (): array => $this->crearReserva(
                $idComercio, $idVariacion, $cantidad, $idUbicacion, $habilitadas, $clave, $minutos,
            ));
        } catch (UniqueConstraintViolationException $e) {
            // Otra solicitud con la misma clave ganó la carrera: se devuelve su reserva.
            $previa = $this->reservaPorClave($idComercio, $clave) ?? throw $e;

            return $this->repetirReserva($previa, $idVariacion, $cantidad);
        }

        $reserva = $this->aReserva($this->filaReserva($idReserva));

        // Los eventos se publican después de confirmar la transacción.
        ExistenciaReservada::dispatch($reserva);
        $this->publicarCambioDisponibilidad($idComercio, $variacion['id_producto'], $idVariacion, $antes, $despues);

        return $reserva;
    }

    public function liberarReserva(string $idReserva, ?string $motivo = null): array
    {
        return $this->nucleo->ejecutarEnAmbito(fn (): array => $this->liberarEnAmbito($idReserva, $motivo));
    }

    private function liberarEnAmbito(string $idReserva, ?string $motivo): array
    {
        if (! Str::isUuid($idReserva)) {
            throw ErrorInventarioException::reservaNoEncontrada();
        }

        $resultado = DB::transaction(function () use ($idReserva, $motivo): ?array {
            $reserva = DB::table('reservas')->where('id_reserva', $idReserva)->lockForUpdate()->first();

            if ($reserva === null) {
                throw ErrorInventarioException::reservaNoEncontrada();
            }

            $this->exigirComercioDeSesion(
                (int) DB::table('existencias')->where('id_existencia', $reserva->id_existencia)->value('id_comercio')
            );

            $estado = (int) $reserva->id_estado_reserva;

            if ($estado === self::CONSUMIDA) {
                throw ErrorInventarioException::reservaYaConsumida();
            }

            // Liberar de nuevo una reserva cerrada no vuelve a descontar.
            if ($estado === self::LIBERADA || $estado === self::VENCIDA) {
                return null;
            }

            $existencia = DB::table('existencias')
                ->where('id_existencia', $reserva->id_existencia)
                ->lockForUpdate()
                ->first();

            $antes = $this->disponibleTotal((int) $existencia->id_comercio, (int) $existencia->id_variacion);

            $this->cerrarReserva($reserva, self::LIBERADA, $motivo ?? 'liberada');

            return [
                (int) $existencia->id_comercio,
                (int) $existencia->id_producto,
                (int) $existencia->id_variacion,
                $antes,
                $this->disponibleTotal((int) $existencia->id_comercio, (int) $existencia->id_variacion),
            ];
        });

        if ($resultado !== null) {
            $this->publicarCambioDisponibilidad(...$resultado);
        }

        return $this->aReserva($this->filaReserva($idReserva));
    }

    // ------------------------------------------------------------------ operaciones del PC4

    public function obtenerReserva(string $idReserva): array
    {
        $this->pendiente('obtenerReserva');
    }

    public function confirmarReserva(string $idReserva, int $idPedido, int $idSubpedido): array
    {
        $this->pendiente('confirmarReserva');
    }

    public function confirmarSalidaPorReserva(string $idReserva, int $idPedido, int $idSubpedido): array
    {
        $this->pendiente('confirmarSalidaPorReserva');
    }

    public function registrarReingresoPorReserva(string $idReserva, array $solicitud): array
    {
        $this->pendiente('registrarReingresoPorReserva');
    }

    // ------------------------------------------------------------------ existencias y movimientos

    public function listarExistencias(int $idComercio, array $filtros = []): array
    {
        $this->exigirPositivos(['id_comercio' => $idComercio]
            + array_map('intval', array_intersect_key($filtros, ['id_ubicacion' => 0, 'id_variacion' => 0])));
        [$pagina, $porPagina] = FiltroKardex::paginacion($filtros);

        return $this->nucleo->ejecutarEnAmbito(function () use ($idComercio, $filtros, $pagina, $porPagina): array {
            $this->exigirComercioDeSesion($idComercio);

            $resultado = Existencia::query()
                ->where('id_comercio', $idComercio)
                ->when(isset($filtros['id_ubicacion']), fn ($q) => $q->where('id_ubicacion', (int) $filtros['id_ubicacion']))
                ->when(isset($filtros['id_variacion']), fn ($q) => $q->where('id_variacion', (int) $filtros['id_variacion']))
                ->when(filter_var($filtros['bajo_minimo'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $q
                    ->where('stock_minimo', '>', 0)
                    ->whereRaw(self::DISPONIBLE_SQL.' <= stock_minimo'))
                ->orderBy('id_variacion')
                ->orderBy('id_ubicacion')
                ->paginate($porPagina, ['*'], 'pagina', $pagina);

            return [
                'datos' => array_map(fn (Existencia $e): array => $e->aContrato($this->sku($e->id_variacion)), $resultado->items()),
                'paginacion' => [
                    'pagina' => $resultado->currentPage(),
                    'por_pagina' => $resultado->perPage(),
                    'total' => $resultado->total(),
                    'total_paginas' => max(1, $resultado->lastPage()),
                ],
            ];
        });
    }

    public function fijarStockMinimo(int $idExistencia, int $stockMinimo): array
    {
        $this->exigirPositivos(['id_existencia' => $idExistencia]);

        if ($stockMinimo < 0) {
            throw ErrorInventarioException::datosInvalidos(['stock_minimo' => 'Debe ser un entero mayor o igual a 0.']);
        }

        return $this->nucleo->ejecutarEnAmbito(function () use ($idExistencia, $stockMinimo): array {
            $existencia = DB::transaction(function () use ($idExistencia, $stockMinimo): Existencia {
                $existencia = $this->existencias->bloquearPorId($idExistencia) ?? throw $this->noEncontrada(
                    ErrorInventarioException::existenciaNoEncontrada()
                );
                $anterior = $existencia->stock_minimo;
                $existencia->stock_minimo = $stockMinimo;
                $this->existencias->guardar($existencia);
                $this->publicador->alertarSiBajoMinimo($existencia);

                $this->nucleo->registrarAuditoria('fijar_stock_minimo', 'existencias', $existencia->id_existencia, [
                    'modulo' => 'M4', 'id_comercio' => $existencia->id_comercio, 'anterior' => $anterior, 'nuevo' => $stockMinimo,
                ]);

                return $existencia;
            });

            return $existencia->aContrato($this->sku($existencia->id_variacion));
        });
    }

    public function registrarMovimientoManual(array $solicitud): array
    {
        $dto = RegistroMovimientoDTO::desdeSolicitud($solicitud);

        return $this->nucleo->ejecutarEnAmbito(function () use ($dto): array {
            $this->exigirComercioDeSesion($dto->idComercio);
            [$movimiento, $idExistencia] = $this->movimientos->registrarMovimientoManual($dto, $this->nucleo->usuarioActual());

            return $movimiento->aContrato($idExistencia);
        });
    }

    /**
     * Alcance deseable (transferencias entre ubicaciones): no implementado en v0.9.0.
     */
    public function registrarTransferencia(array $solicitud): array
    {
        throw ErrorInventarioException::operacionNoImplementada('registrarTransferencia');
    }

    public function registrarAjusteInventario(array $solicitud): array
    {
        $dto = AjusteDTO::desdeSolicitud($solicitud);

        return $this->nucleo->ejecutarEnAmbito(function () use ($dto): array {
            $this->exigirComercioDeSesion($dto->idComercio);

            return $this->aAjuste($this->ajustes->registrarAjuste($dto, $this->nucleo->usuarioActual()));
        });
    }

    // ------------------------------------------------------------------ kardex

    public function consultarKardex(int $idComercio, int $idVariacion, array $filtros = []): array
    {
        $this->exigirPositivos(['id_comercio' => $idComercio, 'id_variacion' => $idVariacion]
            + (isset($filtros['id_ubicacion']) ? ['id_ubicacion' => (int) $filtros['id_ubicacion']] : []));
        [$pagina, $porPagina] = FiltroKardex::paginacion($filtros);
        [$desde, $hasta] = FiltroKardex::periodo($filtros['fecha_inicio'] ?? null, $filtros['fecha_fin'] ?? null);

        return $this->nucleo->ejecutarEnAmbito(function () use ($idComercio, $idVariacion, $filtros, $pagina, $porPagina, $desde, $hasta): array {
            $this->exigirComercioDeSesion($idComercio);

            return $this->kardex->consultar(new FiltroKardex(
                $idComercio,
                $idVariacion,
                isset($filtros['id_ubicacion']) ? (int) $filtros['id_ubicacion'] : null,
                $desde,
                $hasta,
                null,
                $pagina,
                $porPagina,
            ));
        });
    }

    public function obtenerKardexValorizado(int $idComercio, string $fechaInicio, string $fechaFin, array $filtros = []): array
    {
        $this->exigirPositivos(['id_comercio' => $idComercio]);
        [$desde, $hasta] = FiltroKardex::periodo($fechaInicio, $fechaFin, obligatorio: true);
        [$pagina, $porPagina] = FiltroKardex::paginacion($filtros);
        $tipo = $filtros['tipo_movimiento'] ?? null;

        if ($tipo !== null && TipoMovimiento::tryFrom((string) $tipo) === null) {
            throw ErrorInventarioException::datosInvalidos(['tipo_movimiento' => 'No es un tipo de movimiento del contrato.']);
        }

        return $this->nucleo->ejecutarEnAmbito(function () use ($idComercio, $desde, $hasta, $tipo, $pagina, $porPagina): array {
            $this->exigirComercioDeSesion($idComercio);

            return $this->kardex->valorizado(new FiltroKardex($idComercio, null, null, $desde, $hasta, $tipo, $pagina, $porPagina));
        });
    }

    public function consultarInventarioAuditoria(array $idsComercio, ?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $this->pendiente('consultarInventarioAuditoria');
    }

    public function obtenerCostoProductosVendidos(int $idSubpedido): array
    {
        $this->exigirPositivos(['id_subpedido' => $idSubpedido]);

        return $this->nucleo->ejecutarEnAmbito(fn (): array => $this->kardex->costosProductosVendidos(
            $idSubpedido,
            $this->nucleo->comercioActual() !== null,
        ));
    }

    private function pendiente(string $operacion): never
    {
        throw new LogicException("Operación {$operacion} del contrato M4 v1.3.0: se implementa en el PC4.");
    }

    // ------------------------------------------------------------------ interfaz anterior

    public function consultarExistencias(int $variacionId): int
    {
        $disponible = $this->nucleo->ejecutarEnAmbito(
            fn (): int => (int) Existencia::query()
                ->where('id_variacion', $variacionId)
                ->toBase()
                ->selectRaw('COALESCE(SUM('.self::DISPONIBLE_SQL.'), 0) AS disponible')
                ->value('disponible'),
            publica: true,
        );

        return max(0, $disponible);
    }

    public function solicitarReserva(int $variacionId, int $cantidad, int $pedidoId): array
    {
        $variacion = $this->catalogo->obtenerVariacion($variacionId);

        if ($variacion === null) {
            throw ErrorInventarioException::variacionNoEncontrada();
        }

        $reserva = $this->reservarExistencias($variacion['id_comercio'], $variacionId, $cantidad);

        DB::table('reservas')->where('id_reserva', $reserva['id_reserva'])->update(['id_pedido' => $pedidoId]);

        return [
            'reserva_id' => $reserva['id_reserva'],
            'variacion_id' => $variacionId,
            'cantidad' => $cantidad,
            'pedido_id' => $pedidoId,
            'estado' => 'RESERVADO',
            'expira_en' => $reserva['fecha_expiracion'],
        ];
    }

    public function confirmarSalidaPorDespacho(int $reservaId): bool
    {
        throw new LogicException(
            'Fuera del despliegue mínimo: la salida por despacho usa confirmarSalidaPorReserva (id_reserva UUID) y registra kardex.'
        );
    }

    // ------------------------------------------------------------------ reserva atómica

    /**
     * Selección, comprobación de stock y reserva en una sola transacción bajo
     * SELECT … FOR UPDATE sobre las filas de existencias de la variación (ADR-002).
     *
     * @param  list<int>  $habilitadas
     * @return array{0: string, 1: int, 2: int} id_reserva y disponible total antes y después.
     */
    private function crearReserva(
        int $idComercio,
        int $idVariacion,
        int $cantidad,
        ?int $idUbicacion,
        array $habilitadas,
        string $clave,
        int $minutos,
    ): array {
        $filas = DB::table('existencias')
            ->where('id_comercio', $idComercio)
            ->where('id_variacion', $idVariacion)
            ->orderBy('id_existencia')
            ->lockForUpdate()
            ->get();

        if ($filas->isEmpty()) {
            throw ErrorInventarioException::existenciaNoEncontrada();
        }

        $antes = $this->disponibleTotal($idComercio, $idVariacion);

        // Se liberan exactamente una vez las reservas activas que ya vencieron.
        $this->vencerReservas($filas->pluck('id_existencia')->all());

        $candidatas = DB::table('existencias')
            ->whereIn('id_existencia', $filas->pluck('id_existencia'))
            ->when($idUbicacion !== null, fn ($q) => $q->where('id_ubicacion', $idUbicacion))
            ->when($idUbicacion === null, fn ($q) => $q->whereIn('id_ubicacion', $habilitadas))
            ->orderBy('id_existencia')
            ->get();

        if ($candidatas->isEmpty()) {
            throw ErrorInventarioException::existenciaNoEncontrada();
        }

        $elegida = $candidatas->first(fn (object $e): bool => $this->disponible($e) >= $cantidad)
            ?? throw ErrorInventarioException::existenciaInsuficiente();

        DB::table('existencias')
            ->where('id_existencia', $elegida->id_existencia)
            ->update([
                'cantidad_reservada' => DB::raw('cantidad_reservada + '.$cantidad),
                'updated_at' => Carbon::now(),
            ]);

        $ahora = Carbon::now();
        $idReserva = (string) Str::uuid();

        DB::table('reservas')->insert([
            'id_reserva' => $idReserva,
            'id_comercio' => $idComercio,
            'id_existencia' => $elegida->id_existencia,
            'id_estado_reserva' => self::ACTIVA,
            'cantidad' => $cantidad,
            'fecha_creacion' => $ahora,
            'fecha_vencimiento' => $ahora->copy()->addMinutes($minutos),
            'clave_idempotencia' => $clave,
            'historial' => json_encode([['estado' => 'activa', 'fecha' => $ahora->toIso8601String(), 'motivo' => 'creada']]),
        ]);

        return [$idReserva, $antes, $this->disponibleTotal($idComercio, $idVariacion)];
    }

    /**
     * @param  list<int|string>  $idsExistencia
     */
    private function vencerReservas(array $idsExistencia): void
    {
        $vencidas = DB::table('reservas')
            ->whereIn('id_existencia', $idsExistencia)
            ->where('id_estado_reserva', self::ACTIVA)
            ->where('fecha_vencimiento', '<=', Carbon::now())
            ->lockForUpdate()
            ->get();

        foreach ($vencidas as $reserva) {
            $this->cerrarReserva($reserva, self::VENCIDA, 'vencida');
        }
    }

    /**
     * Cierra la reserva (liberada o vencida) y devuelve su cantidad a la existencia.
     */
    private function cerrarReserva(object $reserva, int $estado, string $motivo): void
    {
        DB::table('existencias')
            ->where('id_existencia', $reserva->id_existencia)
            ->update([
                'cantidad_reservada' => DB::raw('cantidad_reservada - '.(float) $reserva->cantidad),
                'updated_at' => Carbon::now(),
            ]);

        $historial = json_decode($reserva->historial ?: '[]', true) ?: [];
        $historial[] = ['estado' => self::ESTADOS[$estado], 'fecha' => Carbon::now()->toIso8601String(), 'motivo' => $motivo];

        DB::table('reservas')
            ->where('id_reserva', $reserva->id_reserva)
            ->update(['id_estado_reserva' => $estado, 'historial' => json_encode($historial)]);
    }

    // ------------------------------------------------------------------ auxiliares

    /**
     * Recurso que no se encontró dentro del ámbito: con sesión de comercio puede ser
     * de otro comercio y se responde ERR-003 (RN-12); sin sesión, el código propio.
     */
    private function noEncontrada(ErrorInventarioException $propio): ErrorInventarioException
    {
        return $this->nucleo->comercioActual() !== null ? ErrorInventarioException::recursoNoAccesible() : $propio;
    }

    /** SKU de la variación según M3; si M3 no responde, la existencia se lista sin SKU. */
    private function sku(int $idVariacion): ?string
    {
        if (! array_key_exists($idVariacion, $this->skus)) {
            try {
                $this->skus[$idVariacion] = $this->catalogo->obtenerVariacion($idVariacion)['sku'] ?? null;
            } catch (ErrorInventarioException) {
                $this->skus[$idVariacion] = null;
            }
        }

        return $this->skus[$idVariacion];
    }

    /**
     * Esquema Ajuste del contrato.
     *
     * @return array<string, mixed>
     */
    private function aAjuste(AjusteInventario $ajuste): array
    {
        $ajuste->loadMissing(['motivo', 'movimiento']);

        return [
            'id_ajuste' => $ajuste->id_ajuste,
            'id_comercio' => $ajuste->id_comercio,
            'id_existencia' => $ajuste->id_existencia,
            'id_movimiento' => $ajuste->id_movimiento,
            'motivo_ajuste' => $ajuste->motivo?->codigo,
            'cantidad_sistema' => $ajuste->cantidad_sistema,
            'cantidad_contada' => $ajuste->cantidad_contada,
            'diferencia' => $ajuste->diferencia($ajuste->cantidad_sistema),
            'id_usuario' => $ajuste->id_usuario,
            'justificacion' => $ajuste->justificacion,
            'fecha_ajuste' => $ajuste->created_at->toIso8601String(),
            'id_autorizador' => $ajuste->movimiento?->id_autorizador,
            'fecha_autorizacion' => $ajuste->movimiento?->fecha_autorizacion?->toIso8601String(),
        ];
    }

    private function reservaPorClave(int $idComercio, string $clave): ?object
    {
        return $this->consultaReservas()
            ->where('r.id_comercio', $idComercio)
            ->where('r.clave_idempotencia', $clave)
            ->first();
    }

    /**
     * Misma clave con los mismos datos: la misma reserva. Con otros datos: ERR-M4-003.
     *
     * @return array<string, mixed>
     */
    private function repetirReserva(object $previa, int $idVariacion, int $cantidad): array
    {
        if ((int) $previa->id_variacion !== $idVariacion || (int) $previa->cantidad !== $cantidad) {
            throw ErrorInventarioException::claveIdempotenciaReutilizada();
        }

        return $this->aReserva($previa);
    }

    private function filaReserva(string $idReserva): object
    {
        return $this->consultaReservas()->where('r.id_reserva', $idReserva)->first();
    }

    private function consultaReservas(): Builder
    {
        return DB::table('reservas as r')
            ->join('existencias as e', 'e.id_existencia', '=', 'r.id_existencia')
            ->select('r.*', 'e.id_variacion', 'e.id_ubicacion');
    }

    /**
     * @return list<int>
     */
    private function ubicacionesHabilitadas(int $idComercio): array
    {
        $sedes = $this->comercios->obtenerSedesComercio($idComercio)['sedes'] ?? [];

        return array_values(array_map(
            fn (array $sede): int => (int) $sede['id_sede'],
            array_filter($sedes, fn (array $sede): bool => (bool) ($sede['habilitada'] ?? false)),
        ));
    }

    private function disponible(object $existencia): int
    {
        return (int) ((float) $existencia->cantidad_fisica - (float) $existencia->cantidad_reservada);
    }

    private function disponibleTotal(int $idComercio, int $idVariacion): int
    {
        return $this->existencias->disponibleTotal($idComercio, $idVariacion);
    }

    private function publicarCambioDisponibilidad(int $idComercio, int $idProducto, int $idVariacion, int $antes, int $despues): void
    {
        if ($antes > 0 && $despues <= 0) {
            ExistenciaAgotada::dispatch($idComercio, $idProducto, $idVariacion);
        } elseif ($antes <= 0 && $despues > 0) {
            ExistenciaRepuesta::dispatch($idComercio, $idProducto, $idVariacion, $despues);
        }
    }

    /**
     * Reserva en la forma del schema Reserva del contrato.
     *
     * @return array<string, mixed>
     */
    private function aReserva(object $fila): array
    {
        $estado = self::ESTADOS[(int) $fila->id_estado_reserva];
        $historial = json_decode($fila->historial ?: '[]', true) ?: [];
        $cierre = in_array($estado, ['consumida', 'liberada', 'vencida'], true) ? end($historial) : false;

        return [
            'id_reserva' => $fila->id_reserva,
            'id_comercio' => (int) $fila->id_comercio,
            'id_existencia' => (int) $fila->id_existencia,
            'id_variacion' => (int) $fila->id_variacion,
            'id_ubicacion' => (int) $fila->id_ubicacion,
            'cantidad' => (int) $fila->cantidad,
            'estado_reserva' => $estado,
            'id_pedido' => $fila->id_pedido !== null ? (int) $fila->id_pedido : null,
            'id_subpedido' => $fila->id_subpedido !== null ? (int) $fila->id_subpedido : null,
            'fecha_creacion' => Carbon::parse($fila->fecha_creacion)->toIso8601String(),
            'fecha_expiracion' => Carbon::parse($fila->fecha_vencimiento)->toIso8601String(),
            'fecha_resolucion' => $cierre ? $cierre['fecha'] : null,
            'motivo_cierre' => $cierre ? $cierre['motivo'] : null,
        ];
    }

    /**
     * @param  array<string, int>  $campos
     */
    private function exigirPositivos(array $campos): void
    {
        $errores = [];

        foreach ($campos as $campo => $valor) {
            if ($valor < 1) {
                $errores[$campo] = 'Debe ser un entero mayor o igual a 1.';
            }
        }

        if ($errores !== []) {
            throw ErrorInventarioException::datosInvalidos($errores);
        }
    }

    /**
     * @param  array<mixed>  $items
     */
    private function validarLote(array $items, string $campoId, bool $permiteUbicacion): void
    {
        if ($items === [] || count($items) > self::MAXIMO_LOTE) {
            throw ErrorInventarioException::datosInvalidos(['items' => 'Debe tener entre 1 y '.self::MAXIMO_LOTE.' elementos.']);
        }

        foreach (array_values($items) as $indice => $item) {
            $campos = ['id_comercio', $campoId];

            if ($permiteUbicacion && is_array($item) && array_key_exists('id_ubicacion', $item)) {
                $campos[] = 'id_ubicacion';
            }

            foreach ($campos as $campo) {
                if (! is_array($item) || ! is_int($item[$campo] ?? null) || $item[$campo] < 1) {
                    throw ErrorInventarioException::datosInvalidos(["items.{$indice}.{$campo}" => 'Debe ser un entero mayor o igual a 1.']);
                }
            }
        }
    }
}
