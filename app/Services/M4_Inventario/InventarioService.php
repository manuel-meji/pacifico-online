<?php

namespace App\Services\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\CatalogoDependencia;
use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Enums\M4_Inventario\MotivoCierre;
use App\Enums\M4_Inventario\TipoMovimiento;
use App\Models\M4_Inventario\AjusteInventario;
use App\Models\M4_Inventario\Existencia;
use App\Models\M4_Inventario\Reserva;
use App\Services\M4_Inventario\DTO\AjusteDTO;
use App\Services\M4_Inventario\DTO\FiltroKardex;
use App\Services\M4_Inventario\DTO\RegistroMovimientoDTO;
use App\Services\M4_Inventario\DTO\ReservaSolicitudDTO;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\M4_Inventario\Repositorios\ExistenciaRepositoryInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Fachada del módulo: implementación de InventarioModuleInterface (contrato openapi
 * v1.3.0, 20 operaciones). Valida la forma de cada solicitud, abre el ámbito del
 * núcleo y delega en los servicios de aplicación del diseño del PC3 (figura 4.2a/b):
 *  - ReservaService: ciclo de vida de la reserva y vencimiento (ADR-002).
 *  - RegistroMovimientoService y AjusteService: movimientos manuales y conteos.
 *  - KardexService: kardex, kardex valorizado, costo de productos vendidos y auditoría.
 * Responde siempre con los cuerpos de los schemas del contrato.
 *
 * RN-12: cada operación corre dentro de NucleoDependencia::ejecutarEnAmbito y las
 * consultas usan los modelos con el Global Scope del núcleo (PerteneceAComercio).
 * El filtro por comercio lo aplica el núcleo, no una condición opcional de M4.
 * Las consultas de disponibilidad son públicas (M1) y corren en modo sistema.
 *
 * Las dependencias de M2, M3, M5 y el núcleo llegan por interfaz y pueden ser el doble
 * o el servicio real según config/integraciones.php (ver M4IntegracionesServiceProvider).
 */
class InventarioService implements InventarioModuleInterface
{
    private const MAXIMO_LOTE = 100;

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
        private readonly ReservaService $reservas,
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

    // ------------------------------------------------------------------ reservas (ReservaService)

    public function reservarExistencias(
        int $idComercio,
        int $idVariacion,
        int $cantidad,
        ?int $idUbicacion = null,
        ?string $claveIdempotencia = null,
        ?int $expiracionMinutos = null,
    ): array {
        $this->exigirPositivos(['id_comercio' => $idComercio, 'id_variacion' => $idVariacion, 'cantidad' => $cantidad]
            + ($idUbicacion !== null ? ['id_ubicacion' => $idUbicacion] : [])
            + ($expiracionMinutos !== null ? ['expiracion_minutos' => $expiracionMinutos] : []));

        if ($claveIdempotencia !== null && ($claveIdempotencia === '' || strlen($claveIdempotencia) > 100)) {
            throw ErrorInventarioException::datosInvalidos(['Idempotency-Key' => 'Debe tener entre 1 y 100 caracteres.']);
        }

        $dto = new ReservaSolicitudDTO(
            $idComercio, $idVariacion, $cantidad, $idUbicacion,
            $expiracionMinutos ?? (int) config('integraciones.m4.reserva_minutos', 15),
        );

        return $this->nucleo->ejecutarEnAmbito(
            fn (): array => $this->reservas->reservar($dto, $claveIdempotencia)->reserva->aContrato()
        );
    }

    public function obtenerReserva(string $idReserva): array
    {
        return $this->nucleo->ejecutarEnAmbito(fn (): array => $this->reservas->mostrar($idReserva)->aContrato());
    }

    public function confirmarReserva(string $idReserva, int $idPedido, int $idSubpedido): array
    {
        $this->exigirPositivos(['id_pedido' => $idPedido, 'id_subpedido' => $idSubpedido]);

        return $this->nucleo->ejecutarEnAmbito(
            fn (): array => $this->reservas->confirmar($idReserva, $idPedido, $idSubpedido)->aContrato()
        );
    }

    /**
     * El motivo del contrato es pago_rechazado, pedido_cancelado o solicitud_m5. Si un
     * consumidor manda otro texto se registra como solicitud_m5 para no dejar la
     * reserva comprometida.
     */
    public function liberarReserva(string $idReserva, ?string $motivo = null): array
    {
        $cierre = MotivoCierre::tryFrom((string) $motivo) ?? MotivoCierre::SolicitudM5;

        return $this->nucleo->ejecutarEnAmbito(fn (): array => $this->reservas->liberar($idReserva, $cierre)->aContrato());
    }

    public function confirmarSalidaPorReserva(string $idReserva, int $idPedido, int $idSubpedido): array
    {
        $this->exigirPositivos(['id_pedido' => $idPedido, 'id_subpedido' => $idSubpedido]);

        return $this->nucleo->ejecutarEnAmbito(
            fn (): array => $this->reservas->registrarSalida($idReserva, $idPedido, $idSubpedido)->aContrato()
        );
    }

    public function registrarReingresoPorReserva(string $idReserva, array $solicitud): array
    {
        $errores = [];

        foreach (['id_pedido', 'id_subpedido'] as $campo) {
            if (! is_int($solicitud[$campo] ?? null) || $solicitud[$campo] < 1) {
                $errores[$campo] = 'Debe ser un entero mayor o igual a 1.';
            }
        }

        $justificacion = trim((string) ($solicitud['justificacion'] ?? ''));

        if ($justificacion === '' || mb_strlen($justificacion) > 255) {
            $errores['justificacion'] = 'Es obligatoria y admite hasta 255 caracteres.';
        }

        if ($errores !== []) {
            throw ErrorInventarioException::datosInvalidos($errores);
        }

        return $this->nucleo->ejecutarEnAmbito(function () use ($idReserva, $solicitud, $justificacion): array {
            $movimiento = $this->reservas->registrarReingreso(
                $idReserva, $solicitud['id_pedido'], $solicitud['id_subpedido'], (string) ($solicitud['id_recepcion'] ?? ''), $justificacion,
            );

            return $movimiento->aContrato($this->reservas->mostrar($idReserva)->id_existencia);
        });
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
        $variacion = $this->catalogo->obtenerVariacion($variacionId) ?? throw ErrorInventarioException::variacionNoEncontrada();
        $reserva = $this->reservarExistencias($variacion['id_comercio'], $variacionId, $cantidad);

        $this->nucleo->ejecutarEnAmbito(fn () => Reserva::query()->whereKey($reserva['id_reserva'])->update(['id_pedido' => $pedidoId]));

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
