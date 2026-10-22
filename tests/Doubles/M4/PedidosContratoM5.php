<?php

namespace Tests\Doubles\M4;

use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use LogicException;

/**
 * Doble que imita a M5 (contrato openapi_M5 v1.1.0) en obtenerPedido y obtenerSubpedido, que M4
 * usará antes de registrar la salida por despacho (CU-M4-07): pedido pagado y subpedido del mismo
 * comercio con una línea que lleva el id_reserva. Las firmas siguen x-operacion-interfaz-php de M5.
 *
 * Errores: ERR-005 (id inválido), ERR-004 (no existe) y ERR-003 (subpedido de otro comercio).
 * La variante PedidosNoDisponible simula que M5 no responde (ERR-006).
 */
class PedidosContratoM5 implements PedidosServiceInterface
{
    /** @return array<string, mixed> PedidoMaestro con sus subpedidos. */
    public function obtenerPedido(int $idPedido): array
    {
        $this->validarId('id_pedido', $idPedido);
        $pedido = DatosPruebaM4::pedidos()[$idPedido] ?? throw ErrorProveedorException::noEncontrado();
        $subpedidos = array_values(array_filter(DatosPruebaM4::subpedidos(), fn (array $s): bool => $s['id_pedido'] === $idPedido));

        return $pedido + ['subpedidos' => $subpedidos];
    }

    /**
     * Si se indica el comercio de la sesión y el subpedido es de otro, responde ERR-003 (aislamiento).
     *
     * @return array<string, mixed> PedidoSubpedido con sus líneas.
     */
    public function obtenerSubpedido(int $idSubpedido, ?int $idComercioSesion = null): array
    {
        $this->validarId('id_subpedido', $idSubpedido);
        $subpedido = DatosPruebaM4::subpedidos()[$idSubpedido] ?? throw ErrorProveedorException::noEncontrado();

        if ($idComercioSesion !== null && $subpedido['id_comercio'] !== $idComercioSesion) {
            throw ErrorProveedorException::recursoNoAccesible();
        }

        return $subpedido;
    }

    public function crearPedido(array $datosCompra): array
    {
        throw new LogicException('Fuera del alcance del doble de M4.');
    }

    public function marcarComoPagado(int $pedidoId, string $comprobantePago): bool
    {
        throw new LogicException('Fuera del alcance del doble de M4.');
    }

    public function despacharSubpedido(int $subpedidoId, ?string $guiaTransporte = null): array
    {
        throw new LogicException('Fuera del alcance del doble de M4.');
    }

    private function validarId(string $campo, int $id): void
    {
        if ($id < 1) {
            throw ErrorProveedorException::datosInvalidos([$campo => 'Debe ser un entero mayor o igual a 1.']);
        }
    }
}
