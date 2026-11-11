<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Models\M6_Pagos\FacturaElectronica;
use App\Models\M6_Pagos\Pago;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * CU-008 Consultar historial de pagos y facturas (RF-008, RN-008).
 *
 * - RN-008 / RN-12: un cliente solo ve lo suyo y un comercio solo lo suyo
 *   (AmbitoAccesoM6::resolverFiltros). Fuera de su ámbito responde 404.
 * - Un pago es de un pedido multicomercio: un comercio lo ve si emitió una
 *   factura de ese pago. Se consulta solo con tablas de M6 (sin JOIN a otros módulos).
 */
class HistorialService
{
    public function __construct(private readonly AmbitoAccesoM6 $ambito) {}

    /**
     * @return array{pagos: Collection<int, Pago>, facturas: Collection<int, FacturaElectronica>}
     *
     * @throws ErrorContratoException SIN_RESULTADOS
     */
    public function consultar(?int $idCliente, ?int $idComercio): array
    {
        [$idCliente, $idComercio] = $this->ambito->resolverFiltros($idCliente, $idComercio);

        if ($idCliente === null && $idComercio === null) {
            throw ValidationException::withMessages(['id_comercio' => 'Indique id_comercio o id_cliente.']);
        }

        $facturas = FacturaElectronica::query()
            ->with(['estado', 'pago'])
            ->when($idCliente !== null, fn ($q) => $q->where('id_cliente', $idCliente))
            ->when($idComercio !== null, fn ($q) => $q->where('id_comercio', $idComercio))
            ->orderByDesc('id_factura_electronica')
            ->get();

        $pagos = Pago::query()
            ->with(['estado', 'medio'])
            ->when($idCliente !== null, fn ($q) => $q->where('id_cliente', $idCliente))
            ->when($idComercio !== null, fn ($q) => $q->whereIn(
                'id_pago',
                FacturaElectronica::query()->select('id_pago')->where('id_comercio', $idComercio)
            ))
            ->orderByDesc('id_pago')
            ->get();

        if ($pagos->isEmpty() && $facturas->isEmpty()) {
            throw ErrorContratoException::de(CodigoErrorM6::SinResultados);
        }

        return ['pagos' => $pagos, 'facturas' => $facturas];
    }
}
