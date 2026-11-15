<?php

declare(strict_types=1);

namespace App\Listeners\M7_Contabilidad;

use App\Events\M2_Comercios\ComercioAprobado;
use App\Services\M7_Contabilidad\PlanCuentasBaseService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prepara la contabilidad de un comercio recién aprobado por M2. Los errores se
 * registran sin propagarse para no afectar la aprobación ya confirmada; el comando
 * m7:crear-plan-cuentas permite reintentar.
 */
final class CrearPlanCuentasComercioListener
{
    public function __construct(
        private readonly PlanCuentasBaseService $planBase,
    ) {}

    public function handle(ComercioAprobado $evento): void
    {
        try {
            $resultado = $this->planBase->crearParaComercio($evento->id_comercio, 'ComercioAprobado');

            Log::info('M7: plan de cuentas base preparado para el comercio aprobado.', [
                'id_evento' => $evento->id_evento,
                'id_comercio' => $evento->id_comercio,
                ...$resultado,
            ]);
        } catch (Throwable $error) {
            Log::error('M7: no se pudo crear el plan de cuentas del comercio aprobado.', [
                'id_evento' => $evento->id_evento,
                'id_comercio' => $evento->id_comercio,
                'exception' => $error,
            ]);
        }
    }
}
