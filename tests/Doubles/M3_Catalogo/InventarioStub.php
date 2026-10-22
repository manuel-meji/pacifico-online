<?php

namespace Tests\Doubles\M3_Catalogo;

use App\Contracts\M4_Inventario\InventarioServiceInterface;
use DateTimeImmutable;

class InventarioStub implements InventarioServiceInterface
{
    private array $existencias = [
        101 => 5,
        102 => 0,
        103 => 2,
        104 => 0,
        105 => 40,
        106 => 8,
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

        $expiraEn = (new DateTimeImmutable())
            ->modify('+15 minutes')
            ->format(DATE_ATOM);

        $this->reservas[$reservaId] = [
            'reserva_id' => $reservaId,
            'variacion_id' => $variacionId,
            'cantidad' => $cantidad,
            'pedido_id' => $pedidoId,
            'estado' => 'RESERVADO',
            'expira_en' => $expiraEn,
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
