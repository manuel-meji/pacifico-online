<?php

declare(strict_types=1);

namespace App\Listeners\M7_Contabilidad;

use App\Events\M6_Pagos\FacturaGenerada;
use App\Services\M7_Contabilidad\BandejaEventosM6Service;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcesarFacturaGeneradaListener
{
    public function __construct(
        private readonly BandejaEventosM6Service $bandeja,
        private readonly ContextoComercio $contexto,
    ) {}

    public function handle(FacturaGenerada $evento): void
    {
        try {
            $this->contexto->ejecutarComoSistema(fn () => $this->bandeja->recibir($evento));
        } catch (Throwable $error) {
            Log::error('M7: no se pudo procesar la factura de M6.', [
                'id_evento' => $evento->idEvento,
                'id_factura' => $evento->idFactura,
                'exception' => $error,
            ]);
        }
    }
}
