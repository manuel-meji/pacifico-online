<?php

namespace App\Services\M4_Inventario;

use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Contracts\M4_Inventario\Dependencias\VerificadorAutorizacionesInterface;
use App\Enums\M4_Inventario\EfectoStock;
use App\Enums\M4_Inventario\TipoMovimiento;
use App\Models\M4_Inventario\Existencia;
use App\Models\M4_Inventario\MovimientoInventario;
use App\Services\M4_Inventario\DTO\DatosMovimiento;
use App\Services\M4_Inventario\DTO\TransferenciaDTO;
use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use App\Services\M4_Inventario\Repositorios\ExistenciaRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CU-M4-05 «Transferir existencias entre ubicaciones» (RF-M4-005, RN-M4-012).
 *
 * Antes de tocar datos valida, por los contratos de M2 y M3, el comercio activo
 * (ERR-M4-015), la variación del comercio y las dos sedes habilitadas. Después, en
 * una sola transacción, bloquea las dos existencias siempre en el mismo orden (por
 * id_existencia, para no provocar interbloqueos entre transferencias cruzadas),
 * verifica la autorización previa del Administrador (antes de revelar si alcanza el
 * stock), descuenta en el origen sin tocar
 * unidades reservadas (ERR-M4-001), suma en el destino con el costo de salida del
 * origen y asienta dos movimientos y dos líneas de kardex con la misma referencia.
 * Cualquier error deshace todo.
 */
class TransferenciaService
{
    public function __construct(
        private readonly ValidadorPertenencia $validador,
        private readonly ExistenciaRepositoryInterface $existencias,
        private readonly KardexService $kardex,
        private readonly VerificadorAutorizacionesInterface $autorizaciones,
        private readonly PublicadorDisponibilidad $publicador,
        private readonly NucleoDependencia $nucleo,
    ) {}

    /**
     * @return array{id_transferencia: string, movimientos: list<array<string, mixed>>} TransferenciaRespuesta.
     */
    public function registrarTransferencia(TransferenciaDTO $dto, int $idUsuario): array
    {
        $this->validador->validarComercioActivo($dto->idComercio);
        $variacion = $this->validador->validarVariacion($dto->idComercio, $dto->idVariacion);
        $this->validador->validarUbicacion($dto->idComercio, $dto->idUbicacionOrigen);
        $this->validador->validarUbicacion($dto->idComercio, $dto->idUbicacionDestino);

        $idTransferencia = (string) Str::uuid();

        $respuesta = DB::transaction(function () use ($dto, $idUsuario, $variacion, $idTransferencia): array {
            [$origen, $destino] = $this->bloquearEnOrden($dto, $variacion['id_producto']);

            $autorizacion = $this->autorizaciones->verificar(
                $dto->idAutorizacion,
                VerificadorAutorizacionesInterface::OPERACION_TRANSFERENCIA,
                $dto->idComercio,
                $dto->idVariacion,
                [$dto->idUbicacionOrigen, $dto->idUbicacionDestino],
                $dto->cantidad,
            );

            if ($origen->disponible() < $dto->cantidad) {
                throw ErrorInventarioException::existenciaInsuficiente();
            }

            $disponibleOrigenAntes = $origen->disponible();
            $disponibleDestinoAntes = $destino->disponible();
            $totalAntes = $this->existencias->disponibleTotal($dto->idComercio, $dto->idVariacion);

            $costo = $this->kardex->costoSalida($origen, $dto->cantidad);
            $origen->disminuirFisica($dto->cantidad);
            $destino->aplicarEntrada($dto->cantidad, $this->kardex->costoTrasEntrada($destino, $dto->cantidad, $costo));

            $this->existencias->guardar($origen);
            $this->existencias->guardar($destino);

            $fechaAutorizacion = Carbon::parse($autorizacion['fecha_autorizacion']);
            $salida = $this->asentar($origen, TipoMovimiento::TransferenciaSalida, EfectoStock::Disminuye, $dto, $costo, $idUsuario, $idTransferencia, $autorizacion['id_autorizador'], $fechaAutorizacion);
            $entrada = $this->asentar($destino, TipoMovimiento::TransferenciaEntrada, EfectoStock::Aumenta, $dto, $costo, $idUsuario, $idTransferencia, $autorizacion['id_autorizador'], $fechaAutorizacion);

            $this->autorizaciones->marcarUsada($dto->idAutorizacion);
            $this->publicador->notificarCambio($origen, $disponibleOrigenAntes, $totalAntes);
            $this->publicador->notificarCambio($destino, $disponibleDestinoAntes, $totalAntes);

            return [
                'id_transferencia' => $idTransferencia,
                'movimientos' => [
                    $salida->aContrato($origen->id_existencia),
                    $entrada->aContrato($destino->id_existencia),
                ],
            ];
        });

        $this->nucleo->registrarAuditoria('transferencia_inventario', 'movimientos_inventario', $respuesta['movimientos'][0]['id_movimiento'], [
            'modulo' => 'M4',
            'id_comercio' => $dto->idComercio,
            'id_transferencia' => $idTransferencia,
            'id_variacion' => $dto->idVariacion,
            'id_ubicacion_origen' => $dto->idUbicacionOrigen,
            'id_ubicacion_destino' => $dto->idUbicacionDestino,
            'cantidad' => $dto->cantidad,
        ]);

        return $respuesta;
    }

    /**
     * Bloquea origen y destino por id_existencia ascendente. Si el destino no tiene
     * existencia todavía, se crea dentro de la transacción (se deshace si algo falla).
     *
     * @return array{0: Existencia, 1: Existencia}
     */
    private function bloquearEnOrden(TransferenciaDTO $dto, int $idProducto): array
    {
        $ids = Existencia::query()
            ->where('id_comercio', $dto->idComercio)
            ->where('id_variacion', $dto->idVariacion)
            ->whereIn('id_ubicacion', [$dto->idUbicacionOrigen, $dto->idUbicacionDestino])
            ->pluck('id_existencia', 'id_ubicacion');

        $idOrigen = $ids[$dto->idUbicacionOrigen] ?? throw ErrorInventarioException::existenciaInsuficiente();
        $idDestino = $ids[$dto->idUbicacionDestino] ?? Existencia::query()->create([
            'id_comercio' => $dto->idComercio,
            'id_producto' => $idProducto,
            'id_variacion' => $dto->idVariacion,
            'id_ubicacion' => $dto->idUbicacionDestino,
        ])->id_existencia;

        $bloqueadas = [];

        foreach (collect([$idOrigen, $idDestino])->sort()->values() as $id) {
            $bloqueadas[$id] = $this->existencias->bloquearPorId($id)
                ?? throw ErrorInventarioException::existenciaNoEncontrada();
        }

        return [$bloqueadas[$idOrigen], $bloqueadas[$idDestino]];
    }

    private function asentar(
        Existencia $existencia,
        TipoMovimiento $tipo,
        EfectoStock $efecto,
        TransferenciaDTO $dto,
        float $costo,
        int $idUsuario,
        string $idTransferencia,
        int $idAutorizador,
        Carbon $fechaAutorizacion,
    ): MovimientoInventario {
        return $this->kardex->registrar($existencia, new DatosMovimiento(
            $tipo, $efecto, $dto->cantidad, $costo, $idUsuario, $dto->justificacion,
            $idTransferencia, $idAutorizador, $fechaAutorizacion,
        ));
    }
}
