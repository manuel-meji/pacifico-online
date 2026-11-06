<?php

namespace App\Providers;

use App\Contracts\M6_Pagos\PasarelaPagoInterface;
use App\Services\M6_Pagos\Simuladores\PasarelaPagoSimulador;
use Illuminate\Support\ServiceProvider;

class M6_PagosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Una sola instancia por proceso.
        $this->app->singleton(PasarelaPagoInterface::class, PasarelaPagoSimulador::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/modules/m6_pagos_api.php'));
    }
}
