<?php

namespace Tests\Doubles\M3_Catalogo;

use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Services\M1_Portal\Dobles\ErrorProveedorException;

class InventarioStub implements InventarioServiceInterface
{
    private array $existencias = [
        15201 => 5,
        15202 => 0,
        15203 => 2,
        15211 => 0,
        15221 => 40,
        15222 => 30,
        15231 => 8,
        15241 => 3,
        15242 => 6,
        15251 => 0,
        15261 => 10,
    ];

    private array $reservas = [];

    private int $siguienteReservaId = 1;

    public function consultarExistencias(int $variacionId): int
    {
        if ($variacionId < 1) {
            throw ErrorProveedorException::datosInvalidos([
                'id_variacion' => 'Debe ser un identificador positivo.',
            ]);
        }

        return $this->existencias[$variacionId] ?? 0;
    }

    public function solicitarReserva(
        int $variacionId,
        int $cantidad,
        int $pedidoId
    ): array {
        if ($variacionId < 1 || $cantidad < 1 || $pedidoId < 1) {
            throw ErrorProveedorException::datosInvalidos([
                'reserva' => 'Los identificadores y la cantidad deben ser positivos.',
            ]);
        }

        if (!array_key_exists($variacionId, $this->existencias)) {
            throw ErrorProveedorException::noEncontrado(
                'La variación no existe en los datos de prueba.'
            );
        }

        if ($this->existencias[$variacionId] < $cantidad) {
            throw ErrorProveedorException::datosInvalidos([
                'cantidad' => 'No hay existencias suficientes para reservar.',
            ]);
        }

        $reservaId = $this->siguienteReservaId++;

        $this->existencias[$variacionId] -= $cantidad;

        $this->reservas[$reservaId] = [
            'reserva_id' => $reservaId,
            'variacion_id' => $variacionId,
            'cantidad' => $cantidad,
            'pedido_id' => $pedidoId,
            'estado' => 'RESERVADO',
            'expira_en' => now()->addMinutes(15)->toIso8601String(),
        ];

        return $this->reservas[$reservaId];
    }

    public function confirmarSalidaPorDespacho(int $reservaId): bool
    {
        if (!isset($this->reservas[$reservaId])) {
            return false;
        }

        if ($this->reservas[$reservaId]['estado'] !== 'RESERVADO') {
            return false;
        }

        $this->reservas[$reservaId]['estado'] = 'DESPACHADO';

        return true;
    }
}
