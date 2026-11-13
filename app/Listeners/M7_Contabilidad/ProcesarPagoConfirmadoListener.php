<?php

declare(strict_types=1);

namespace App\Listeners\M7_Contabilidad;

use App\Events\M6_Pagos\PagoConfirmado;
use App\Services\M7_Contabilidad\BandejaEventosM6Service;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcesarPagoConfirmadoListener
{
    public function __construct(private readonly BandejaEventosM6Service $bandeja) {}

    public function handle(PagoConfirmado $evento): void
    {
        try {
            $this->bandeja->recibir($evento);
        } catch (Throwable $error) {
            Log::error('M7: no se pudo recibir el pago confirmado de M6.', ['id_pago' => $evento->idPago, 'exception' => $error]);
        }
    }
}
