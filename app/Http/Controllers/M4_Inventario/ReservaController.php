<?php

namespace App\Http\Controllers\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\M4_Inventario\LiberarReservaRequest;
use App\Http\Requests\M4_Inventario\ReingresoRequest;
use App\Http\Requests\M4_Inventario\ReservarExistenciasRequest;
use App\Http\Requests\M4_Inventario\VincularPedidoRequest;
use App\Http\Resources\M4_Inventario\MovimientoResource;
use App\Http\Resources\M4_Inventario\ReservaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * API de reservas (figura 4.1c del PC3, CU-M4-06 y CU-M4-07). M5 usa la interfaz PHP
 * dentro del mismo proceso; estas rutas exponen las mismas operaciones por HTTP.
 */
class ReservaController extends Controller
{
    public function __construct(private readonly InventarioModuleInterface $inventario) {}

    /** POST /reservas: 201, o 200 si es un reintento con la misma Idempotency-Key. */
    public function reservar(ReservarExistenciasRequest $solicitud): JsonResponse
    {
        $inicio = Carbon::now()->startOfSecond();
        $reserva = $this->inventario->reservarExistencias(
            (int) $solicitud->validated('id_comercio'),
            (int) $solicitud->validated('id_variacion'),
            (int) $solicitud->validated('cantidad'),
            $solicitud->validated('id_ubicacion') !== null ? (int) $solicitud->validated('id_ubicacion') : null,
            $solicitud->claveIdempotencia(),
            $solicitud->validated('expiracion_minutos') !== null ? (int) $solicitud->validated('expiracion_minutos') : null,
        );
        $esReintento = Carbon::parse($reserva['fecha_creacion'])->lt($inicio);

        return (new ReservaResource($reserva))->response()->setStatusCode($esReintento ? 200 : 201);
    }

    /** GET /reservas/{id_reserva} */
    public function mostrar(string $idReserva): JsonResponse
    {
        return (new ReservaResource($this->inventario->obtenerReserva($idReserva)))->response();
    }

    /** POST /reservas/{id_reserva}/confirmar */
    public function confirmar(string $idReserva, VincularPedidoRequest $solicitud): JsonResponse
    {
        return (new ReservaResource($this->inventario->confirmarReserva($idReserva, $solicitud->idPedido(), $solicitud->idSubpedido())))->response();
    }

    /** POST /reservas/{id_reserva}/liberar */
    public function liberar(string $idReserva, LiberarReservaRequest $solicitud): JsonResponse
    {
        return (new ReservaResource($this->inventario->liberarReserva($idReserva, $solicitud->motivo()->value)))->response();
    }

    /** POST /reservas/{id_reserva}/salida: 201, o 200 si la salida ya existía. */
    public function registrarSalida(string $idReserva, VincularPedidoRequest $solicitud): JsonResponse
    {
        $yaConsumida = $this->inventario->obtenerReserva($idReserva)['estado_reserva'] === 'consumida';
        $salida = $this->inventario->confirmarSalidaPorReserva($idReserva, $solicitud->idPedido(), $solicitud->idSubpedido());

        return (new ReservaResource($salida))->response()->setStatusCode($yaConsumida ? 200 : 201);
    }

    /** POST /reservas/{id_reserva}/reingreso: 201 Movimiento. */
    public function registrarReingreso(string $idReserva, ReingresoRequest $solicitud): JsonResponse
    {
        $movimiento = $this->inventario->registrarReingresoPorReserva($idReserva, $solicitud->aSolicitud());

        return (new MovimientoResource($movimiento))->response()->setStatusCode(201);
    }
}
