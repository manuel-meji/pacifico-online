<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\M2_Comercios\Comercio;
use App\Policies\M2_Comercios\ComercioPolicy;
use Illuminate\Support\Facades\Gate;

/**
 * Proveedor de M2: carga las rutas del contrato (/api/m2/v1) con el grupo "api".
 * El enlace ComerciosServiceInterface → ComerciosService sigue en AppServiceProvider.
 */
class M2ComerciosServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Comercio::class, ComercioPolicy::class);
        
        $this->loadRoutesFrom(base_path('routes/modules/m2_comercios_api.php'));
    }
}
