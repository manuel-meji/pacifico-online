<?php

declare(strict_types=1);

namespace App\Listeners\M7_Contabilidad;

use App\Events\M6_Pagos\ReembolsoProcesado;
use App\Services\M7_Contabilidad\RecuperarEventosM6Service;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcesarReembolsoProcesadoListener
{
    public function __construct(
        private readonly RecuperarEventosM6Service $recuperacion,
        private readonly ContextoComercio $contexto,
    ) {}

    public function handle(ReembolsoProcesado $evento): void
    {
        try {
            $this->contexto->ejecutarComoSistema(fn () => $this->recuperacion->procesarReembolso($evento));
        } catch (Throwable $error) {
            Log::error('M7: no se pudo procesar el reembolso de M6.', [
                'id_evento' => $evento->idEvento,
                'id_reembolso' => $evento->idReembolso,
                'exception' => $error,
            ]);
        }
    }
}
