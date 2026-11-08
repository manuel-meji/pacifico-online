<?php

namespace App\Services\M4_Inventario;

use App\Enums\M4_Inventario\EfectoStock;
use App\Enums\M4_Inventario\TipoMovimiento;
use App\Models\M4_Inventario\MovimientoInventario;
use App\Services\M4_Inventario\DTO\DatosMovimiento;
use App\Services\M4_Inventario\DTO\RegistroMovimientoDTO;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\M4_Inventario\Repositorios\ExistenciaRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * CU-M4-01 «Registrar movimiento de inventario» (DS-01 del PC3): entrada con costo
 * o salida manual.
 *
 * Antes de tocar datos valida, por los contratos de M2 y M3, el comercio activo
 * (RN-M4-009), la variación del comercio y la sede habilitada. Después, en una
 * transacción con la existencia bloqueada (ADR-002), actualiza el físico, recalcula
 * el costo promedio en las entradas y asienta movimiento y kardex.
 */
class RegistroMovimientoService
{
    public function __construct(
        private readonly ValidadorPertenencia $validador,
        private readonly ExistenciaRepositoryInterface $existencias,
        private readonly KardexService $kardex,
        private readonly PublicadorDisponibilidad $publicador,
    ) {}

    /**
     * @return array{0: MovimientoInventario, 1: int} El movimiento y el id_existencia.
     */
    public function registrarMovimientoManual(RegistroMovimientoDTO $dto, int $idUsuario): array
    {
        $this->validador->validarComercioActivo($dto->idComercio);
        $variacion = $this->validador->validarVariacion($dto->idComercio, $dto->idVariacion);
        $this->validador->validarUbicacion($dto->idComercio, $dto->idUbicacion);

        return DB::transaction(function () use ($dto, $idUsuario, $variacion): array {
            $existencia = $dto->tipo === TipoMovimiento::Entrada
                ? $this->existencias->bloquearOCrear($dto->idComercio, $variacion['id_producto'], $dto->idVariacion, $dto->idUbicacion)
                : ($this->existencias->bloquear($dto->idComercio, $dto->idVariacion, $dto->idUbicacion)
                    ?? throw ErrorInventarioException::existenciaInsuficiente());

            $disponibleAntes = $existencia->disponible();
            $totalAntes = $this->existencias->disponibleTotal($dto->idComercio, $dto->idVariacion);

            if ($dto->tipo === TipoMovimiento::Entrada) {
                $costo = (float) $dto->costoUnitario;
                $existencia->aplicarEntrada($dto->cantidad, $this->kardex->costoTrasEntrada($existencia, $dto->cantidad, $costo));
                $efecto = EfectoStock::Aumenta;
            } else {
                $existencia->disminuirFisica($dto->cantidad);
                $costo = $this->kardex->costoSalida($existencia, $dto->cantidad);
                $efecto = EfectoStock::Disminuye;
            }

            $this->existencias->guardar($existencia);

            $movimiento = $this->kardex->registrar($existencia, new DatosMovimiento(
                $dto->tipo, $efecto, $dto->cantidad, $costo, $idUsuario, $dto->justificacion,
            ));

            $this->publicador->notificarCambio($existencia, $disponibleAntes, $totalAntes);

            return [$movimiento, $existencia->id_existencia];
        });
    }
}
