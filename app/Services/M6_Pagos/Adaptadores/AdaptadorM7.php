<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos\Adaptadores;

use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use App\Services\M6_Pagos\Clients\ContabilidadClient;

final class AdaptadorM7 implements ContabilidadClient
{
    public function __construct(
        private readonly ContabilidadServiceInterface $contabilidad,
    ) {}

    /**
     * Sin acción (conexión M6–M7, acordado con Justin).
     *
     * M7 no expone una operación para recibir este aviso: según su contrato,
     * consume los eventos de Laravel PagoConfirmado, FacturaGenerada y
     * ReembolsoProcesado, que M6 ya publica. Antes lanzaba ERR-006 en cada pago
     * confirmado y solo dejaba una advertencia en el log (PublicadorResultadoPago
     * lo captura), sin afectar el aviso a M5.
     */
    public function enviarEventoFinanciero(int $idPago, string $tipo): void {}

    public function consultarRegistroContablePago(int $idPago): bool
    {
        $resultado = $this->contabilidad
            ->consultarRegistroContablePago($idPago);

        return (bool) ($resultado['registrado'] ?? false);
    }

    /**
     * Lee el campo opcional "subpedidos_registrados" de M7 (subpedidos del pago
     * con asiento COBRO registrado). Si M7 aún no lo envía, devuelve null y la
     * conciliación sigue usando solo "registrado", como antes.
     */
    public function subpedidosConCobro(int $idPago): ?array
    {
        $subpedidos = $this->contabilidad
            ->consultarRegistroContablePago($idPago)['subpedidos_registrados'] ?? null;

        if (! is_array($subpedidos)) {
            return null;
        }

        return array_values(array_unique(array_map('intval', $subpedidos)));
    }
}
