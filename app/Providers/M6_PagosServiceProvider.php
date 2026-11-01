<?php

namespace App\Providers;

use App\Services\M6_Pagos\Clients\{ClienteClient, ComercioClient, ContabilidadClient, PedidoClient};
use Illuminate\Support\ServiceProvider;
use Tests\Doubles\M1\FakeClienteClient;
use Tests\Doubles\M2\FakeComercioClient;
use Tests\Doubles\M5\FakePedidoClient;
use Tests\Doubles\M7\FakeContabilidadClient;

/**
 * Cambio doble -> adaptador real SIN tocar código de M6: config/m6.php  (M6_USAR_DOBLES=true|false).
 * Los adaptadores reales (AdaptadorM1/M2/M5/M7) llaman a las interfaces de app/Contracts/M*.
 */
class M6_PagosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (config('m6.usar_dobles', true)) {
            $this->app->bind(PedidoClient::class, FakePedidoClient::class);
            $this->app->bind(ClienteClient::class, FakeClienteClient::class);
            $this->app->bind(ComercioClient::class, FakeComercioClient::class);
            $this->app->bind(ContabilidadClient::class, FakeContabilidadClient::class);
            return;
        }
        $this->app->bind(PedidoClient::class, \App\Services\M6_Pagos\Adaptadores\AdaptadorM5::class);
        $this->app->bind(ClienteClient::class, \App\Services\M6_Pagos\Adaptadores\AdaptadorM1::class);
        $this->app->bind(ComercioClient::class, \App\Services\M6_Pagos\Adaptadores\AdaptadorM2::class);
        $this->app->bind(ContabilidadClient::class, \App\Services\M6_Pagos\Adaptadores\AdaptadorM7::class);
    }
}
