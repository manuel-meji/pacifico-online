<?php

namespace App\Providers;

use App\Contracts\M6_Pagos\ConsultaEventosPagosInterface;
use App\Contracts\M6_Pagos\HaciendaInterface;
use App\Contracts\M6_Pagos\PasarelaPagoInterface;
use App\Services\M6_Pagos\ConsultaEventosPagosService;
use App\Services\M6_Pagos\Simuladores\HaciendaSimulador;
use App\Services\M6_Pagos\Simuladores\PasarelaPagoSimulador;
use Illuminate\Support\ServiceProvider;

class M6_PagosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Una sola instancia por proceso.
        $this->app->singleton(PasarelaPagoInterface::class, PasarelaPagoSimulador::class);
        $this->app->singleton(HaciendaInterface::class, HaciendaSimulador::class);

        // Contrato M6 v1.4.0 (conexión M6–M7): consulta de eventos financieros por período.
        $this->app->bind(ConsultaEventosPagosInterface::class, ConsultaEventosPagosService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/modules/m6_pagos_api.php'));
    }
}
