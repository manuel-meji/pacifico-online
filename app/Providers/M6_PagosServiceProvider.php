<?php

namespace App\Providers;

use App\Contracts\M6_Pagos\PasarelaPagoInterface;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM1;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM2;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM5;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM7;
use App\Services\M6_Pagos\Clients\ClienteClient;
use App\Services\M6_Pagos\Clients\ComercioClient;
use App\Services\M6_Pagos\Clients\ContabilidadClient;
use App\Services\M6_Pagos\Clients\PedidoClient;
use App\Services\M6_Pagos\Simuladores\PasarelaPagoSimulador;
use Illuminate\Support\ServiceProvider;
use Tests\Doubles\M1\FakeClienteClient;
use Tests\Doubles\M2\FakeComercioClient;
use Tests\Doubles\M5\FakePedidoClient;
use Tests\Doubles\M7\FakeContabilidadClient;

/**
 * Proveedor de M6.
 *  - register(): puertos hacia M1/M2/M5/M7 (doble o adaptador real) y la pasarela.
 *  - boot(): carga las rutas del contrato /api/m6/v1 con el grupo "api".
 *
 * Cambio doble -> adaptador real SIN tocar código de M6: config/m6.php (M6_USAR_DOBLES=true|false).
 * Los adaptadores reales (AdaptadorM1/M2/M5/M7) llaman a las interfaces de app/Contracts/M*.
 * El enlace PagosServiceInterface -> PagosService sigue en AppServiceProvider.
 */
class M6_PagosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Pasarela simulada (Tarjeta / SINPE / PayPal). Una sola instancia por proceso.
        $this->app->singleton(PasarelaPagoInterface::class, PasarelaPagoSimulador::class);

        if (config('m6.usar_dobles', true)) {
            // scoped: el mismo doble durante toda la petición (los fakes guardan lo que reciben).
            $this->app->scoped(PedidoClient::class, FakePedidoClient::class);
            $this->app->scoped(ClienteClient::class, FakeClienteClient::class);
            $this->app->scoped(ComercioClient::class, FakeComercioClient::class);
            $this->app->scoped(ContabilidadClient::class, FakeContabilidadClient::class);

            return;
        }

        $this->app->bind(PedidoClient::class, AdaptadorM5::class);
        $this->app->bind(ClienteClient::class, AdaptadorM1::class);
        $this->app->bind(ComercioClient::class, AdaptadorM2::class);
        $this->app->bind(ContabilidadClient::class, AdaptadorM7::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/modules/m6_pagos_api.php'));
    }
}
