<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\M2_Comercios\SolicitudComercio;
use App\Policies\M2_Comercios\SolicitudComercioPolicy;
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
        Gate::policy(SolicitudComercio::class, SolicitudComercioPolicy::class);

        $this->loadRoutesFrom(base_path('routes/modules/m2_comercios_api.php'));
    }
}
