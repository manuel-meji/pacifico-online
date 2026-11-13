<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Events\M6_Pagos\FacturaGenerada;
use App\Events\M6_Pagos\PagoConfirmado;
use InvalidArgumentException;
use LogicException;

final class CuadrePagoM6Service
{
    public function __construct(private readonly ValidadorPartidaDoble $validador) {}

    /**
     * Evalúa los eventos recibidos sin persistir ni generar asientos.
     * La coincidencia completa el cuadre por importes, no certifica la cantidad de subpedidos.
     *
     * @param  list<FacturaGenerada>  $facturas
     * @return array{estado: string, cobro_centimos: int, facturado_centimos: int, saldo_pendiente_centimos: int, exceso_centimos: int, requiere_revision: bool}
     */
    public function evaluar(PagoConfirmado $pago, array $facturas): array
    {
        if ($pago->idPago <= 0 || $pago->idPedido <= 0) {
            throw new InvalidArgumentException('El pago debe identificar el pago y el pedido.');
        }

        $cobro = $this->centimos($pago->monto);
        if ($cobro === 0) {
            throw new InvalidArgumentException('El cobro debe tener un monto positivo.');
        }

        $registradas = [];
        $subpedidos = [];
        $facturado = 0;
        foreach ($facturas as $factura) {
            if (! $factura instanceof FacturaGenerada
                || $factura->idPago !== $pago->idPago
                || $factura->idPedido !== $pago->idPedido
                || $factura->idFactura <= 0 || $factura->idSubpedido <= 0 || $factura->idComercio <= 0) {
                throw new InvalidArgumentException('Las facturas deben pertenecer al mismo pago y pedido e identificar factura, subpedido y comercio.');
            }
            if ($factura->subtotal === null || $factura->impuesto === null || $factura->envio === null) {
                throw new InvalidArgumentException('La factura no contiene el desglose completo.');
            }

            $subtotal = $this->centimos($factura->subtotal);
            $impuesto = $this->centimos($factura->impuesto);
            $envio = $this->centimos($factura->envio);
            $monto = $this->centimos($factura->monto);
            if ($monto === 0 || $subtotal + $impuesto + $envio !== $monto) {
                throw new InvalidArgumentException('El desglose de la factura no coincide con su monto positivo.');
            }

            $datos = [$factura->idSubpedido, $factura->idComercio, $subtotal, $impuesto, $envio];
            if (isset($registradas[$factura->idFactura])) {
                if ($registradas[$factura->idFactura] !== $datos) {
                    throw new LogicException('Una misma factura contiene datos financieros contradictorios.');
                }

                continue;
            }
            if (isset($subpedidos[$factura->idSubpedido])) {
                throw new LogicException('El subpedido contiene más de una factura.');
            }
            if ($monto > PHP_INT_MAX - $facturado) {
                throw new InvalidArgumentException('El total facturado excede el rango permitido.');
            }
            $registradas[$factura->idFactura] = $datos;
            $subpedidos[$factura->idSubpedido] = true;
            $facturado += $monto;
        }

        return [
            'estado' => $facturado > $cobro ? 'DIFERENCIA' : ($facturado < $cobro ? 'PENDIENTE_FACTURAS' : 'COMPLETO_POR_IMPORTES'),
            'cobro_centimos' => $cobro,
            'facturado_centimos' => $facturado,
            'saldo_pendiente_centimos' => max(0, $cobro - $facturado),
            'exceso_centimos' => max(0, $facturado - $cobro),
            'requiere_revision' => $facturado > $cobro,
        ];
    }

    private function centimos(float $monto): int
    {
        if (! is_finite($monto) || $monto < 0 || abs($monto - round($monto, 2)) > 0.000001) {
            throw new InvalidArgumentException('El importe financiero debe ser no negativo y tener como máximo dos decimales.');
        }

        return $this->validador->aCentimos(number_format($monto, 2, '.', ''));
    }
}
