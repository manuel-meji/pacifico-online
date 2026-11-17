<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Listeners\M2_Comercios\RegistrarComision;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\SolicitudComercio;
use App\Policies\M2_Comercios\ComercioPolicy;
use App\Policies\M2_Comercios\SolicitudComercioPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Proveedor de M2: carga las rutas del contrato (/api/m2/v1) con el grupo "api".
 * El enlace ComerciosServiceInterface → ComerciosService sigue en AppServiceProvider.
 */
class M2ComerciosServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Comercio::class, ComercioPolicy::class);

        Gate::policy(SolicitudComercio::class, SolicitudComercioPolicy::class);

        Event::listen(SubpedidoDespachado::class, RegistrarComision::class);

        $this->loadRoutesFrom(base_path('routes/modules/m2_comercios_api.php'));
    }
}
