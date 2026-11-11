<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;
use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Listeners\M7_Contabilidad\ProcesarPedidoConfirmadoListener;
use App\Listeners\M7_Contabilidad\ProcesarSubpedidoDespachadoListener;
use App\Services\M7_Contabilidad\Gateways\M4CostosProductosVendidosGateway;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class M7ContabilidadServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CostosProductosVendidosGatewayInterface::class,
            M4CostosProductosVendidosGateway::class
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(
            base_path('routes/modules/m7_contabilidad_api.php')
        );

        Event::listen(
            PedidoConfirmado::class,
            ProcesarPedidoConfirmadoListener::class
        );

        Event::listen(
            SubpedidoDespachado::class,
            ProcesarSubpedidoDespachadoListener::class
        );
    }
}