<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\M7_Contabilidad\ComerciosM7GatewayInterface;
use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;
use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Events\M6_Pagos\FacturaGenerada;
use App\Events\M6_Pagos\ReembolsoProcesado;
use App\Listeners\M7_Contabilidad\ProcesarFacturaGeneradaListener;
use App\Listeners\M7_Contabilidad\ProcesarPedidoConfirmadoListener;
use App\Listeners\M7_Contabilidad\ProcesarReembolsoProcesadoListener;
use App\Listeners\M7_Contabilidad\ProcesarSubpedidoDespachadoListener;
use App\Services\M7_Contabilidad\Gateways\M2ComerciosGateway;
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

        $this->app->bind(
            ComerciosM7GatewayInterface::class,
            M2ComerciosGateway::class
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

        Event::listen(FacturaGenerada::class, ProcesarFacturaGeneradaListener::class);
        Event::listen(ReembolsoProcesado::class, ProcesarReembolsoProcesadoListener::class);
    }
}
