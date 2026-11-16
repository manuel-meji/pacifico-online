<?php

namespace App\Services\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Enums\M4_Inventario\EfectoStock;
use App\Enums\M4_Inventario\EstadoReservaCodigo;
use App\Enums\M4_Inventario\TipoMovimiento;
use App\Models\M4_Inventario\MovimientoInventario;
use App\Services\M4_Inventario\Dominio\EstadosReserva\TransicionNoPermitida;
use App\Services\M4_Inventario\DTO\DatosMovimiento;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\M4_Inventario\Repositorios\ExistenciaRepositoryInterface;
use App\Services\M4_Inventario\Repositorios\MovimientoRepositoryInterface;
use App\Services\M4_Inventario\Repositorios\ReservaRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Reingreso al kardex por una devolución aprobada en M5 (RF-021).
 *
 * ReservaService::registrarReingreso devuelve la reserva completa y una sola vez por reserva
 * (alcance del contrato M4: "retorno completo"). Una devolución puede ser parcial y una línea
 * puede devolverse en varias solicitudes, así que esta clase reingresa una cantidad concreta y
 * registra cada reingreso con una referencia propia "{id_reserva}:DEV{id_devolucion}".
 *
 * Reutiliza los mismos repositorios y reglas que ReservaService (bloqueo existencia → reserva,
 * costo de la salida original, kardex y publicación de disponibilidad). No modifica ReservaService.
 */
class ReingresoDevolucionService
{
    public function __construct(
        private readonly ExistenciaRepositoryInterface $existencias,
        private readonly ReservaRepositoryInterface $reservas,
        private readonly MovimientoRepositoryInterface $movimientos,
        private readonly KardexService $kardex,
        private readonly PublicadorDisponibilidad $publicador,
        private readonly NucleoDependencia $nucleo,
    ) {}

    /**
     * @return MovimientoInventario El movimiento de reingreso (o el ya registrado si se repite).
     */
    public function registrar(string $idReserva, int $idPedido, int $idSubpedido, int $idDevolucion, int $cantidad, string $justificacion): MovimientoInventario
    {
        if ($cantidad < 1) {
            throw ErrorInventarioException::datosInvalidos(['cantidad' => 'Debe ser un entero mayor o igual a 1.']);
        }

        $referencia = self::referencia($idReserva, $idDevolucion);

        $movimiento = DB::transaction(function () use ($idReserva, $idPedido, $idSubpedido, $idDevolucion, $cantidad, $justificacion, $referencia): MovimientoInventario {
            $vista = $this->reservas->buscar($idReserva) ?? throw ErrorInventarioException::reservaNoEncontrada();
            $existencia = $this->existencias->bloquearPorId($vista->id_existencia) ?? throw ErrorInventarioException::reservaNoEncontrada();
            $reserva = $this->reservas->bloquear($idReserva) ?? throw ErrorInventarioException::reservaNoEncontrada();

            // Solo una reserva ya consumida (con salida física) puede reingresar.
            if ($reserva->estadoCodigo() !== EstadoReservaCodigo::Consumida) {
                throw new TransicionNoPermitida($reserva->estadoCodigo()->codigo(), 'reingresar');
            }

            if (! $reserva->vinculoCoincide($idPedido, $idSubpedido)) {
                throw ErrorInventarioException::vinculoNoCorresponde();
            }

            // Idempotencia por devolución: el mismo reingreso no se aplica dos veces.
            if ($this->movimientos->porReferencia($reserva->id_comercio, TipoMovimiento::Reingreso->value, $referencia) !== null) {
                throw ErrorInventarioException::reingresoYaRegistrado();
            }

            // Nunca se reingresa más de lo que salió con esta reserva (suma de todos los reingresos).
            $yaReingresado = (int) MovimientoInventario::query()
                ->where('id_comercio', $reserva->id_comercio)
                ->where('tipo', TipoMovimiento::Reingreso->value)
                ->where(fn ($q) => $q->where('referencia', $reserva->id_reserva)
                    ->orWhere('referencia', 'like', $reserva->id_reserva.':DEV%'))
                ->sum('cantidad');

            if ($yaReingresado + $cantidad > $reserva->cantidad) {
                throw ErrorInventarioException::datosInvalidos([
                    'cantidad' => "Se intenta reingresar {$cantidad} unidades y ya hay {$yaReingresado} de {$reserva->cantidad} reingresadas.",
                ]);
            }

            $salida = $this->movimientos->porReferencia($reserva->id_comercio, TipoMovimiento::Salida->value, $reserva->id_reserva)
                ?? throw new TransicionNoPermitida('consumida', 'reingresar');

            $disponibleAntes = $existencia->disponible();
            $totalAntes = $this->existencias->disponibleTotal($existencia->id_comercio, $existencia->id_variacion);

            $existencia->aplicarEntrada($cantidad, $this->kardex->costoTrasEntrada($existencia, $cantidad, $salida->costo_unitario));
            $this->existencias->guardar($existencia);

            $movimiento = $this->kardex->registrar($existencia, new DatosMovimiento(
                TipoMovimiento::Reingreso,
                EfectoStock::Aumenta,
                $cantidad,
                $salida->costo_unitario,
                $this->nucleo->usuarioActual(),
                "Reingreso por devolución #{$idDevolucion} aprobada (subpedido {$idSubpedido}): {$justificacion}",
                $referencia,
            ));

            $this->publicador->notificarCambio($existencia, $disponibleAntes, $totalAntes);

            return $movimiento;
        });

        $this->nucleo->registrarAuditoria('reingreso_inventario', 'movimientos_inventario', $movimiento->id_movimiento, [
            'modulo' => 'M4', 'id_reserva' => $idReserva, 'id_devolucion' => $idDevolucion, 'cantidad' => $cantidad,
        ]);

        return $movimiento;
    }

    /** Referencia del movimiento (varchar(100): uuid de 36 + ":DEV" + id). */
    public static function referencia(string $idReserva, int $idDevolucion): string
    {
        return "{$idReserva}:DEV{$idDevolucion}";
    }
}
