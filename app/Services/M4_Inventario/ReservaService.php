<?php

namespace App\Services\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Contracts\M4_Inventario\Dependencias\PedidosDependencia;
use App\Enums\M4_Inventario\EfectoStock;
use App\Enums\M4_Inventario\EstadoReservaCodigo;
use App\Enums\M4_Inventario\MotivoCierre;
use App\Enums\M4_Inventario\TipoMovimiento;
use App\Events\M4_Inventario\ExistenciaReservada;
use App\Events\M4_Inventario\ReservaVencida;
use App\Events\M4_Inventario\SalidaInventarioConfirmada;
use App\Models\M4_Inventario\Existencia;
use App\Models\M4_Inventario\MovimientoInventario;
use App\Models\M4_Inventario\Reserva;
use App\Services\M4_Inventario\DTO\DatosMovimiento;
use App\Services\M4_Inventario\DTO\ReservaSolicitudDTO;
use App\Services\M4_Inventario\DTO\ResultadoReserva;
use App\Services\M4_Inventario\DTO\ResultadoSalida;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\M4_Inventario\Repositorios\ExistenciaRepositoryInterface;
use App\Services\M4_Inventario\Repositorios\MovimientoRepositoryInterface;
use App\Services\M4_Inventario\Repositorios\ReservaRepositoryInterface;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ciclo de vida de la reserva (CU-M4-06 y CU-M4-07, DS-03 y DS-04 del PC3):
 * reservar, consultar, confirmar, liberar, salida por despacho, reingreso y
 * vencimiento automático.
 *
 * Control de concurrencia (ADR-002, RN-04): cada operación bloquea con
 * SELECT … FOR UPDATE primero la existencia y después la reserva, siempre en
 * ese orden, así dos operaciones sobre la misma existencia se ordenan y no se
 * cruzan en un interbloqueo. Los cambios de estado los decide el patrón State
 * (Reserva::estado()); cada uno deja su línea en el kardex sin cambiar el físico,
 * salvo la salida (disminuye) y el reingreso (aumenta).
 */
class ReservaService
{
    public const LOTE_VENCIMIENTO = 100;

    public function __construct(
        private readonly ValidadorPertenencia $validador,
        private readonly ExistenciaRepositoryInterface $existencias,
        private readonly ReservaRepositoryInterface $reservas,
        private readonly MovimientoRepositoryInterface $movimientos,
        private readonly KardexService $kardex,
        private readonly PedidosDependencia $pedidos,
        private readonly PublicadorDisponibilidad $publicador,
        private readonly NucleoDependencia $nucleo,
    ) {}

    /**
     * Reserva atómica sin compromiso parcial: una sola ubicación con toda la cantidad.
     * La misma clave de idempotencia con los mismos datos devuelve la reserva original.
     */
    public function reservar(ReservaSolicitudDTO $dto, ?string $claveIdempotencia): ResultadoReserva
    {
        if ($claveIdempotencia !== null && ($previa = $this->reservas->buscarPorClave($dto->idComercio, $claveIdempotencia)) !== null) {
            return $this->repetir($previa, $dto);
        }

        $this->validador->validarComercioActivo($dto->idComercio);
        $this->validador->validarVariacion($dto->idComercio, $dto->idVariacion);
        $habilitadas = $this->validador->ubicacionesHabilitadas($dto->idComercio);

        if ($dto->idUbicacion !== null && ! in_array($dto->idUbicacion, $habilitadas, true)) {
            throw ErrorInventarioException::ubicacionNoEncontrada();
        }

        $clave = $claveIdempotencia ?? (string) Str::uuid();

        try {
            $reserva = DB::transaction(fn (): Reserva => $this->crearReserva($dto, $habilitadas, $clave));
        } catch (UniqueConstraintViolationException $e) {
            // Otra solicitud con la misma clave ganó la carrera: se devuelve la suya.
            return $this->repetir($this->reservas->buscarPorClave($dto->idComercio, $clave) ?? throw $e, $dto);
        }

        return new ResultadoReserva($reserva, false);
    }

    public function mostrar(string $idReserva): Reserva
    {
        return $this->reservas->buscar($idReserva) ?? throw $this->noEncontrada($idReserva);
    }

    /**
     * ACTIVA → CONFIRMADA con el vínculo al pedido y subpedido de M5. Repetirla con el
     * mismo vínculo no cambia nada; con otro vínculo, ERR-M4-007. Si el plazo ya venció,
     * se liberan las unidades una sola vez y se rechaza con ERR-M4-005.
     */
    public function confirmar(string $idReserva, int $idPedido, int $idSubpedido): Reserva
    {
        $vencio = false;

        $reserva = DB::transaction(function () use ($idReserva, $idPedido, $idSubpedido, &$vencio): Reserva {
            [$reserva, $existencia] = $this->bloquear($idReserva);

            if ($reserva->estadoCodigo() === EstadoReservaCodigo::Confirmada) {
                return $reserva->vinculoCoincide($idPedido, $idSubpedido) ? $reserva : throw ErrorInventarioException::vinculoNoCorresponde();
            }

            if ($reserva->estaVencida(Carbon::now())) {
                $this->vencerReserva($reserva, $existencia);
                $vencio = true;

                return $reserva;
            }

            $reserva->confirmar($idPedido, $idSubpedido);
            $this->reservas->guardar($reserva);
            $this->asentar($existencia, TipoMovimiento::ConfirmacionReserva, $reserva,
                "M5 confirmó la reserva: pedido {$idPedido}, subpedido {$idSubpedido}");

            return $reserva;
        });

        if ($vencio) {
            throw ErrorInventarioException::reservaVencida();
        }

        return $reserva;
    }

    /**
     * ACTIVA o CONFIRMADA → LIBERADA: devuelve las unidades una sola vez. Liberar de nuevo
     * una reserva cerrada no tiene efecto; una reserva ya despachada responde ERR-M4-008.
     */
    public function liberar(string $idReserva, MotivoCierre $motivo): Reserva
    {
        return DB::transaction(function () use ($idReserva, $motivo): Reserva {
            [$reserva, $existencia] = $this->bloquear($idReserva);
            $estado = $reserva->estadoCodigo();

            if ($estado === EstadoReservaCodigo::Consumida) {
                throw ErrorInventarioException::reservaYaConsumida();
            }

            if ($estado->esCerrada()) {
                return $reserva;
            }

            $disponibleAntes = $existencia->disponible();
            $totalAntes = $this->existencias->disponibleTotal($existencia->id_comercio, $existencia->id_variacion);

            $reserva->liberar($motivo);
            $existencia->liberarCompromiso($reserva->cantidad);
            $this->reservas->guardar($reserva);
            $this->existencias->guardar($existencia);
            $this->asentar($existencia, TipoMovimiento::LiberacionReserva, $reserva, 'Reserva liberada ('.str_replace('_', ' ', $motivo->value).')');
            $this->publicador->notificarCambio($existencia, $disponibleAntes, $totalAntes);

            return $reserva;
        });
    }

    /**
     * Salida por despacho (DS-04): solo una reserva CONFIRMADA del mismo pedido y
     * subpedido, que M5 confirma pagado. Descuenta físico y reservado, asienta la
     * salida valorizada y publica SalidaInventarioConfirmada. Repetirla devuelve la
     * salida original sin duplicar movimiento ni evento.
     */
    public function registrarSalida(string $idReserva, int $idPedido, int $idSubpedido): ResultadoSalida
    {
        return DB::transaction(function () use ($idReserva, $idPedido, $idSubpedido): ResultadoSalida {
            [$reserva, $existencia] = $this->bloquear($idReserva);

            if ($reserva->estadoCodigo() === EstadoReservaCodigo::Consumida) {
                if (! $reserva->vinculoCoincide($idPedido, $idSubpedido)) {
                    throw ErrorInventarioException::vinculoNoCorresponde();
                }

                $original = $this->movimientos->porReferencia($reserva->id_comercio, TipoMovimiento::Salida->value, $reserva->id_reserva);

                return new ResultadoSalida($original, $reserva, true);
            }

            if ($reserva->estadoCodigo() === EstadoReservaCodigo::Vencida) {
                throw ErrorInventarioException::reservaVencida();
            }

            if ($reserva->estadoCodigo() !== EstadoReservaCodigo::Confirmada) {
                $reserva->consumir(); // El estado lanza TransicionNoPermitida (ERR-M4-006).
            }

            if (! $reserva->vinculoCoincide($idPedido, $idSubpedido)) {
                throw ErrorInventarioException::vinculoNoCorresponde();
            }

            $this->validarPagoYVinculo($reserva, $idPedido, $idSubpedido);

            $costo = $this->kardex->costoSalida($existencia, $reserva->cantidad);
            $existencia->consumirCompromiso($reserva->cantidad);
            $reserva->consumir();
            $this->existencias->guardar($existencia);
            $this->reservas->guardar($reserva);

            $salida = $this->kardex->registrar($existencia, new DatosMovimiento(
                TipoMovimiento::Salida,
                EfectoStock::Disminuye,
                $reserva->cantidad,
                $costo,
                $this->nucleo->usuarioActual(),
                "Despacho del subpedido {$idSubpedido} (pedido {$idPedido})",
                $reserva->id_reserva,
            ));

            SalidaInventarioConfirmada::dispatch($salida, $reserva);

            return new ResultadoSalida($salida, $reserva, false);
        });
    }

    /**
     * Reingreso por cancelación de un pedido ya despachado (alcance del contrato:
     * retorno completo). Exige la recepción física registrada, entra al costo de la
     * salida original y no se registra dos veces.
     */
    public function registrarReingreso(string $idReserva, int $idPedido, int $idSubpedido, string $idRecepcion, string $justificacion): MovimientoInventario
    {
        $movimiento = DB::transaction(function () use ($idReserva, $idPedido, $idSubpedido, $idRecepcion, $justificacion): MovimientoInventario {
            [$reserva, $existencia] = $this->bloquear($idReserva);

            if ($reserva->estadoCodigo() !== EstadoReservaCodigo::Consumida) {
                throw new Dominio\EstadosReserva\TransicionNoPermitida($reserva->estadoCodigo()->codigo(), 'reingresar');
            }

            if (! $reserva->vinculoCoincide($idPedido, $idSubpedido)) {
                throw ErrorInventarioException::vinculoNoCorresponde();
            }

            if (trim($idRecepcion) === '') {
                throw ErrorInventarioException::recepcionNoVerificada();
            }

            if ($this->movimientos->porReferencia($reserva->id_comercio, TipoMovimiento::Reingreso->value, $reserva->id_reserva) !== null) {
                throw ErrorInventarioException::reingresoYaRegistrado();
            }

            $salida = $this->movimientos->porReferencia($reserva->id_comercio, TipoMovimiento::Salida->value, $reserva->id_reserva)
                ?? throw new Dominio\EstadosReserva\TransicionNoPermitida('consumida', 'reingresar');

            $disponibleAntes = $existencia->disponible();
            $totalAntes = $this->existencias->disponibleTotal($existencia->id_comercio, $existencia->id_variacion);
            $existencia->aplicarEntrada($reserva->cantidad, $this->kardex->costoTrasEntrada($existencia, $reserva->cantidad, $salida->costo_unitario));
            $this->existencias->guardar($existencia);

            $movimiento = $this->kardex->registrar($existencia, new DatosMovimiento(
                TipoMovimiento::Reingreso,
                EfectoStock::Aumenta,
                $reserva->cantidad,
                $salida->costo_unitario,
                $this->nucleo->usuarioActual(),
                "Reingreso por cancelación (recepción {$idRecepcion}): {$justificacion}",
                $reserva->id_reserva,
            ));

            $this->publicador->notificarCambio($existencia, $disponibleAntes, $totalAntes);

            return $movimiento;
        });

        $this->nucleo->registrarAuditoria('reingreso_inventario', 'movimientos_inventario', $movimiento->id_movimiento, [
            'modulo' => 'M4', 'id_reserva' => $idReserva, 'id_recepcion' => $idRecepcion,
        ]);

        return $movimiento;
    }

    /**
     * Vence las reservas ACTIVA cuyo plazo se cumplió (tarea programada cada 60 s,
     * RNF-M4-007). Cada una en su transacción y con la existencia bloqueada primero.
     *
     * @return int Reservas vencidas.
     */
    public function vencerReservasExpiradas(DateTimeInterface $ahora, int $limite = self::LOTE_VENCIMIENTO): int
    {
        $vencidas = 0;

        foreach (Reserva::query()
            ->where('id_estado_reserva', EstadoReservaCodigo::Activa->value)
            ->where('fecha_vencimiento', '<=', $ahora)
            ->orderBy('fecha_vencimiento')
            ->limit($limite)
            ->pluck('id_reserva') as $idReserva) {
            $vencidas += DB::transaction(function () use ($idReserva, $ahora): int {
                [$reserva, $existencia] = $this->bloquear($idReserva);

                if (! $reserva->estaVencida($ahora)) {
                    return 0; // Otra operación la confirmó o liberó mientras tanto.
                }

                $this->vencerReserva($reserva, $existencia);

                return 1;
            });
        }

        return $vencidas;
    }

    /**
     * Si M5 reenvía el mismo id de pedido para reservas que ya no son activas, se omiten.
     *
     * @return list<string>
     */
    public function reservasDelPedido(int $idPedido): array
    {
        return Reserva::query()->where('id_pedido', $idPedido)->pluck('id_reserva')->all();
    }

    // ------------------------------------------------------------------ internos

    /**
     * @param  list<int>  $habilitadas
     */
    private function crearReserva(ReservaSolicitudDTO $dto, array $habilitadas, string $clave): Reserva
    {
        $filas = $this->existencias->candidatasParaReserva($dto->idComercio, $dto->idVariacion)->keyBy('id_existencia');

        if ($filas->isEmpty()) {
            throw ErrorInventarioException::existenciaNoEncontrada();
        }

        $totalAntes = $this->existencias->disponibleTotal($dto->idComercio, $dto->idVariacion);

        // Las reservas ACTIVA vencidas de estas existencias se liberan exactamente una vez.
        foreach ($this->reservas->activasVencidas(Carbon::now(), self::LOTE_VENCIMIENTO, $filas->keys()->all()) as $vencida) {
            $this->vencerReserva($vencida, $filas[$vencida->id_existencia]);
        }

        $candidatas = $filas->filter(fn (Existencia $e): bool => $dto->idUbicacion !== null
            ? $e->id_ubicacion === $dto->idUbicacion
            : in_array($e->id_ubicacion, $habilitadas, true));

        if ($candidatas->isEmpty()) {
            throw ErrorInventarioException::existenciaNoEncontrada();
        }

        $elegida = $candidatas->first(fn (Existencia $e): bool => $e->disponible() >= $dto->cantidad)
            ?? throw ErrorInventarioException::existenciaInsuficiente();

        $disponibleAntes = $elegida->disponible();
        $elegida->comprometer($dto->cantidad);
        $this->existencias->guardar($elegida);

        $reserva = Reserva::crearActiva($elegida, $dto->cantidad, $dto->minutosExpiracion, $clave);
        $this->reservas->guardar($reserva);
        $this->asentar($elegida, TipoMovimiento::Reserva, $reserva, 'Reserva de existencias para un pedido');

        ExistenciaReservada::dispatch($reserva->aContrato());
        $this->publicador->notificarCambio($elegida, $disponibleAntes, $totalAntes);

        return $reserva;
    }

    private function vencerReserva(Reserva $reserva, Existencia $existencia): void
    {
        $disponibleAntes = $existencia->disponible();
        $totalAntes = $this->existencias->disponibleTotal($existencia->id_comercio, $existencia->id_variacion);

        $reserva->vencer();
        $existencia->liberarCompromiso($reserva->cantidad);
        $this->reservas->guardar($reserva);
        $this->existencias->guardar($existencia);
        $this->asentar($existencia, TipoMovimiento::VencimientoReserva, $reserva, 'Venció el plazo de la reserva sin confirmación');

        ReservaVencida::dispatch($reserva);
        $this->publicador->notificarCambio($existencia, $disponibleAntes, $totalAntes);
    }

    /**
     * Bloquea la existencia y después la reserva (orden fijo, ver la descripción de la clase).
     *
     * @return array{0: Reserva, 1: Existencia}
     */
    private function bloquear(string $idReserva): array
    {
        $vista = $this->reservas->buscar($idReserva) ?? throw $this->noEncontrada($idReserva);
        $existencia = $this->existencias->bloquearPorId($vista->id_existencia) ?? throw $this->noEncontrada($idReserva);
        $reserva = $this->reservas->bloquear($idReserva) ?? throw $this->noEncontrada($idReserva);
        $reserva->setRelation('existencia', $existencia);

        return [$reserva, $existencia];
    }

    /** Línea de kardex de un movimiento de reserva (sin cambio físico ni costo). */
    private function asentar(Existencia $existencia, TipoMovimiento $tipo, Reserva $reserva, string $motivo): void
    {
        $this->kardex->registrar($existencia, new DatosMovimiento(
            $tipo, EfectoStock::Ninguno, $reserva->cantidad, 0.0, $this->nucleo->usuarioActual(), $motivo, $reserva->id_reserva,
        ));
    }

    /**
     * Por el contrato de M5: el subpedido existe, es del pedido y del comercio de la
     * reserva (ERR-M4-007) y está pagado (ERR-M4-009). Si M5 no responde, ERR-006.
     */
    private function validarPagoYVinculo(Reserva $reserva, int $idPedido, int $idSubpedido): void
    {
        $subpedido = $this->pedidos->obtenerSubpedido($idSubpedido);

        if ($subpedido === null || $subpedido['id_pedido'] !== $idPedido || $subpedido['id_comercio'] !== $reserva->id_comercio) {
            throw ErrorInventarioException::vinculoNoCorresponde();
        }

        if (! in_array($subpedido['estado_subpedido'], PedidosDependencia::ESTADOS_PAGADOS, true)) {
            throw ErrorInventarioException::pedidoNoPagado();
        }
    }

    private function repetir(Reserva $previa, ReservaSolicitudDTO $dto): ResultadoReserva
    {
        $previa->loadMissing('existencia');

        if ($previa->existencia->id_variacion !== $dto->idVariacion || $previa->cantidad !== $dto->cantidad) {
            throw ErrorInventarioException::claveIdempotenciaReutilizada();
        }

        return new ResultadoReserva($previa, true);
    }

    /**
     * La reserva no está en el ámbito de la sesión. Si existe en otro comercio se
     * responde ERR-003 (RN-12); si no existe, ERR-M4-004. Ambos son 404 y ninguno
     * entrega datos de la reserva.
     */
    private function noEncontrada(string $idReserva): ErrorInventarioException
    {
        $existeEnOtroComercio = Str::isUuid($idReserva) && $this->nucleo->ejecutarEnAmbito(
            fn (): bool => Reserva::query()->whereKey($idReserva)->exists(),
            publica: true,
        );

        return $existeEnOtroComercio
            ? ErrorInventarioException::recursoNoAccesible()
            : ErrorInventarioException::reservaNoEncontrada();
    }
}
